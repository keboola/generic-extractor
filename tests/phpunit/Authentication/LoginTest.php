<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Login;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;

class LoginTest extends ExtractorTestCase
{
    public function testAuthenticateClient(): void
    {
        $expiresInSeconds = 5000;
        $expires = time() + $expiresInSeconds;

        // Create Login auth
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'headerToken']],
                'query' => ['qToken' => ['response' => 'queryToken']],
            ],
            'expires' => ['response' => 'expiresIn', 'relative' => true],
        ];
        $auth = new Login($attrs, $api);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200((string) json_encode((object) [
                'headerToken' => 1234,
                'queryToken' => 4321,
                'expiresIn' => $expiresInSeconds,
            ]))
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        self::assertEquals(
            (object) ['data' => [1, 2, 3]],
            $restClient->download($restClient->createRequest(['endpoint' => '/api/get']))
        );
        self::assertEquals(
            (object) ['data' => [4, 5, 6]],
            $restClient->download($restClient->createRequest([
                'endpoint' => '/api/get',
                'params' => ['foo1' => 'bar1'],
                'headers' => ['X-Request-Param' => 'bar2'],
            ]))
        );

        // Assert login call, "first" attribute is in body, "second" int the header
        $loginCall = $history->shift();
        self::assertEquals(
            (string) json_encode(['par' => $attrs['first']]),
            (string) $loginCall->getRequest()->getBody()
        );
        self::assertEquals($attrs['second'], $loginCall->getRequest()->getHeaderLine('X-Header'));

        // Assert API calls, must contain signature
        $apiCall1 = $history->shift();
        self::assertEquals(1234, $apiCall1->getRequest()->getHeaderLine('X-Test-Auth'));
        self::assertEquals('qToken=4321', $apiCall1->getRequest()->getUri()->getQuery());
        $apiCall2 = $history->shift();
        self::assertEquals(1234, $apiCall2->getRequest()->getHeaderLine('X-Test-Auth'));
        self::assertEquals('bar2', $apiCall2->getRequest()->getHeaderLine('X-Request-Param'));
        self::assertEquals('foo1=bar1&qToken=4321', $apiCall2->getRequest()->getUri()->getQuery());

        // No more history items
        self::assertTrue($history->isEmpty());

        // Check that the expiration meets expectations
        $expiration = $auth->getExpiration();
        self::assertTrue(is_int($expiration));
        self::assertLessThan($expires + 2, $expiration);
    }

    public function testAuthenticateClientExpired(): void
    {
        $expiresInSecondsFirst = 1;
        $expiresInSecondsSecond = 5000;

        // Create Login auth
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'headerToken']],
                'query' => ['qToken' => ['response' => 'queryToken']],
            ],
            'expires' => ['response' => 'expiresIn', 'relative' => true],
        ];
        $auth = new Login($attrs, $api);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200((string) json_encode((object) [
                'headerToken' => 1234,
                'queryToken' => 4321,
                'expiresIn' => $expiresInSecondsFirst,
            ]))
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            // Auth response -> new login
            ->addResponse200((string) json_encode((object) [
                'headerToken' => 9876,
                'queryToken' => 5432,
                'expiresIn' => $expiresInSecondsSecond,
            ]))
            // Third API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [7, 8, 9],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [1, 2, 3]], $restClient->download($request));
        self::assertEquals((object) ['data' => [4, 5, 6]], $restClient->download($request));

        // Login call + 2 API calls -> same as in previous testAuthenticateClient test
        self::assertSame(3, $history->count());
        $history->clear();

        // Let's wait for the login expiration
        sleep($expiresInSecondsFirst + 1);
        $expiresSecond = time() + $expiresInSecondsSecond;

        // Run -> expected new login
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [7, 8, 9]], $restClient->download($request));

        // Assert login call
        $loginCall = $history->shift();
        self::assertEquals(
            (string) json_encode(['par' => $attrs['first']]),
            (string) $loginCall->getRequest()->getBody()
        );
        self::assertEquals($attrs['second'], $loginCall->getRequest()->getHeaderLine('X-Header'));

        // Assert API call
        $apiCall1 = $history->shift();
        self::assertEquals(9876, $apiCall1->getRequest()->getHeaderLine('X-Test-Auth'));
        self::assertEquals('qToken=5432', $apiCall1->getRequest()->getUri()->getQuery());

        // No more history items
        self::assertTrue($history->isEmpty());

        // Check that the expiration meets expectations
        $expiration = $auth->getExpiration();
        self::assertTrue(is_int($expiration));
        self::assertLessThan($expiresSecond + 2, $expiration);
    }

    public function testAuthenticateClientScalar(): void
    {
        // Create Login auth
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'format' => 'json',
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'data']],
            ],
        ];
        $auth = new Login($attrs, $api);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200((string) json_encode('someToken')) // <<<< scalar JSON value
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [1, 2, 3]], $restClient->download($request));
        self::assertEquals((object) ['data' => [4, 5, 6]], $restClient->download($request));

        // Assert login call, "first" attribute is in body, "second" int the header
        $loginCall = $history->shift();
        self::assertEquals(
            (string) json_encode(['par' => $attrs['first']]),
            (string) $loginCall->getRequest()->getBody()
        );
        self::assertEquals($attrs['second'], $loginCall->getRequest()->getHeaderLine('X-Header'));

        // Assert API calls, must contain signature
        $apiCall1 = $history->shift();
        self::assertEquals('someToken', $apiCall1->getRequest()->getHeaderLine('X-Test-Auth'));
        $apiCall2 = $history->shift();
        self::assertEquals('someToken', $apiCall2->getRequest()->getHeaderLine('X-Test-Auth'));

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testAuthenticateClientText(): void
    {
        // Create Login auth
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'format' => 'text',
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'data']],
            ],
        ];
        $auth = new Login($attrs, $api);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200('someToken') // <<<< text body
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [1, 2, 3]], $restClient->download($request));
        self::assertEquals((object) ['data' => [4, 5, 6]], $restClient->download($request));

        // Assert login call, "first" attribute is in body, "second" int the header
        $loginCall = $history->shift();
        self::assertEquals(
            (string) json_encode(['par' => $attrs['first']]),
            (string) $loginCall->getRequest()->getBody()
        );
        self::assertEquals($attrs['second'], $loginCall->getRequest()->getHeaderLine('X-Header'));

        // Assert API calls, must contain signature
        $apiCall1 = $history->shift();
        self::assertEquals('someToken', $apiCall1->getRequest()->getHeaderLine('X-Test-Auth'));
        $apiCall2 = $history->shift();
        self::assertEquals('someToken', $apiCall2->getRequest()->getHeaderLine('X-Test-Auth'));

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testAuthenticateClientWithFunctionInApiRequestHeaders(): void
    {
        // Create Login auth
        $api = [
            'loginRequest' => [
                'endpoint' => 'login',
                'headers' => ['X-Header' => 'fooBar'],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'headers' => [
                    // backward compatible
                    'Authorization1' => 'tokens.header',
                    // function
                    'Authorization2' => [
                        'function' => 'concat',
                        'args' => [
                            'Bearer',
                            ' ',
                            ['response' => 'tokens.header'],
                        ],
                    ],
                    // direct reference
                    'Authorization3' => [
                        'response' => 'tokens.header',
                    ],
                ],
                'query' => [
                    // backward compatible
                    'qToken1' => 'tokens.query',
                    // function
                    'qToken2' => [
                        'function' => 'concat',
                        'args' => [
                            'qt',
                            ['response' => 'tokens.query'],
                        ],
                    ],
                    // direct reference
                    'qToken3' => [
                        'response' => 'tokens.query',
                    ],
                ],
            ],
        ];
        $auth = new Login(['a1' => ['b1' => 'c1'], 'a2' => ['b2' => 'c2']], $api);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200((string) json_encode((object) [
                'tokens' => [
                    'header' => 1234,
                    'query' => 4321,
                ],
            ]))
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [1, 2, 3]], $restClient->download($request));
        self::assertEquals((object) ['data' => [4, 5, 6]], $restClient->download($request));

        // Assert login call
        $loginCall = $history->shift();
        self::assertEquals('fooBar', $loginCall->getRequest()->getHeaderLine('X-Header'));

        // Assert API calls, must contain signature
        $apiCall1 = $history->shift();
        self::assertEquals('1234', $apiCall1->getRequest()->getHeaderLine('Authorization1'));
        self::assertEquals('Bearer 1234', $apiCall1->getRequest()->getHeaderLine('Authorization2'));
        self::assertEquals('1234', $apiCall1->getRequest()->getHeaderLine('Authorization3'));
        self::assertEquals(
            'qToken1=4321&qToken2=qt4321&qToken3=4321',
            $apiCall1->getRequest()->getUri()->getQuery()
        );
        $apiCall2 = $history->shift();
        self::assertEquals('1234', $apiCall2->getRequest()->getHeaderLine('Authorization1'));
        self::assertEquals('Bearer 1234', $apiCall2->getRequest()->getHeaderLine('Authorization2'));
        self::assertEquals('1234', $apiCall2->getRequest()->getHeaderLine('Authorization3'));
        self::assertEquals(
            'qToken1=4321&qToken2=qt4321&qToken3=4321',
            $apiCall2->getRequest()->getUri()->getQuery()
        );

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testInvalid1(): void
    {
        $api = [
            'format' => 'js',
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("'format' must be either 'json' or 'text'");
        new Login([], $api);
    }

    public function testInvalid2(): void
    {
        $api = [
            'format' => 'json',
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("loginRequest' is not configured for Login authentication");
        new Login([], $api);
    }

    public function testInvalid3(): void
    {
        $api = [
            'loginRequest' => [
                'method' => 'POST',
            ],
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Request endpoint must be set for the Login authentication method.');
        new Login([], $api);
    }

    public function testInvalid4(): void
    {
        $api = [
            'loginRequest' => [
                'method' => 'POST',
                'endpoint' => 'dummy',
            ],
            'expires' => 'never',
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            "The 'expires' attribute must be either an integer " .
            "or an array with 'response' key containing a path in the response"
        );
        new Login([], $api);
    }
}
