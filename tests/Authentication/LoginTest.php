<?php

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Login;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class LoginTest extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $expiresIn = 5000;
        $expires = time() + $expiresIn;
        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode((object) [ // auth
                'headerToken' => 1234,
                'queryToken' => 4321,
                'expiresIn' => $expiresIn,
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ])))
        ]);
        $history = new History();
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com/api'], [], []);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST'
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'headerToken']],
                'query' => ['qToken' => ['response' => 'queryToken']]
            ],
            'expires' => ['response' => 'expiresIn', 'relative' => true]
        ];

        $auth = new Login($attrs, $api);
        $auth->authenticateClient($restClient);

        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);
        $restClient->download($request);

        // test creation of the login request
        self::assertEquals($attrs['second'], $history->getIterator()[0]['request']->getHeader('X-Header'));
        self::assertEquals(
            json_encode(['par' => $attrs['first']]),
            (string) $history->getIterator()[0]['request']->getBody()
        );

        // test signature of the api request
        self::assertEquals(1234, $history->getIterator()[1]['request']->getHeader('X-Test-Auth'));
        self::assertEquals('qToken=4321', (string) $history->getIterator()[1]['request']->getQuery());
        self::assertEquals(1234, $history->getIterator()[2]['request']->getHeader('X-Test-Auth'));
        self::assertEquals('qToken=4321', (string) $history->getIterator()[2]['request']->getQuery());

        $expiry = self::getProperty(
            $restClient->getClient()->getEmitter()->listeners('before')[0][0],
            'expires'
        );
        self::assertGreaterThan($expires - 2, $expiry);
        self::assertLessThan($expires + 2, $expiry);
    }

    public function testAuthenticateClientScalar()
    {
        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode('someToken'))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ])))
        ]);
        $history = new History();
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com/api'], [], []);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'format' => 'json',
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST'
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'data']],
            ],
        ];

        $auth = new Login($attrs, $api);
        $auth->authenticateClient($restClient);

        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);
        $restClient->download($request);

        // test creation of the login request
        self::assertEquals($attrs['second'], $history->getIterator()[0]['request']->getHeader('X-Header'));
        self::assertEquals(
            json_encode(['par' => $attrs['first']]),
            (string) $history->getIterator()[0]['request']->getBody()
        );

        // test signature of the api request
        self::assertEquals('someToken', $history->getIterator()[1]['request']->getHeader('X-Test-Auth'));
        self::assertEquals('someToken', $history->getIterator()[2]['request']->getHeader('X-Test-Auth'));
    }

    public function testAuthenticateClientText()
    {
        $mock = new Mock([
            new Response(200, [], Stream::factory('someToken')),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ])))
        ]);
        $history = new History();
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com/api'], [], []);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);
        $attrs = ['first' => 1, 'second' => 'two'];
        $api = [
            'format' => 'text',
            'loginRequest' => [
                'endpoint' => 'login',
                'params' => ['par' => ['attr' => 'first']],
                'headers' => ['X-Header' => ['attr' => 'second']],
                'method' => 'POST'
            ],
            'apiRequest' => [
                'headers' => ['X-Test-Auth' => ['response' => 'data']],
            ],
        ];

        $auth = new Login($attrs, $api);
        $auth->authenticateClient($restClient);

        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);
        $restClient->download($request);

        // test creation of the login request
        self::assertEquals($attrs['second'], $history->getIterator()[0]['request']->getHeader('X-Header'));
        self::assertEquals(
            json_encode(['par' => $attrs['first']]),
            (string) $history->getIterator()[0]['request']->getBody()
        );

        // test signature of the api request
        self::assertEquals('someToken', $history->getIterator()[1]['request']->getHeader('X-Test-Auth'));
        self::assertEquals('someToken', $history->getIterator()[2]['request']->getHeader('X-Test-Auth'));
    }

    public function testAuthenticateClientWithFunctionInApiRequestHeaders()
    {
        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode((object) [ // auth
                'tokens' => [
                    'header' => 1234,
                    'query' => 4321
                ]
            ]))),
            new Response(200, [], Stream::factory(json_encode((object) [ // api call
                'data' => [1,2,3]
            ])))
        ]);

        $history = new History();
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com/api'], [], []);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);
        $api = [
            'loginRequest' => [
                'endpoint' => 'login',
                'headers' => ['X-Header' => 'fooBar'],
                'method' => 'POST'
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
                            ['response' => 'tokens.header']
                        ]
                    ],
                    // direct reference
                    'Authorization3' => [
                        'response' => 'tokens.header'
                    ]
                ],
                'query' => [
                    // backward compatible
                    'qToken1' => 'tokens.query',
                    // function
                    'qToken2' => [
                        'function' => 'concat',
                        'args' => [
                            'qt',
                            ['response' => 'tokens.query']
                        ]
                    ],
                    // direct reference
                    'qToken3' => [
                        'response' => 'tokens.query'
                    ]
                ]
            ]
        ];

        $auth = new Login(['a1' => ['b1' => 'c1'], 'a2' => ['b2' => 'c2']], $api);
        $auth->authenticateClient($restClient);
        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);

        // test creation of the login request
        self::assertEquals('fooBar', $history->getIterator()[0]['request']->getHeader('X-Header'));

        // test signature of the api request
        self::assertEquals('1234', $history->getIterator()[1]['request']->getHeader('Authorization1'));
        self::assertEquals('Bearer 1234', $history->getIterator()[1]['request']->getHeader('Authorization2'));
        self::assertEquals('1234', $history->getIterator()[1]['request']->getHeader('Authorization3'));
        self::assertEquals(
            'qToken1=4321&qToken2=qt4321&qToken3=4321',
            (string) $history->getIterator()[1]['request']->getQuery()
        );
        self::assertEquals(
            json_encode(['data' => [1,2,3]]),
            (string) $history->getIterator()[1]['response']->getBody()
        );
    }

    public function testInvalid1()
    {
        $api = [
            'format' => 'js',
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("'format' must be either 'json' or 'text'");
        new Login([], $api);
    }

    public function testInvalid2()
    {
        $api = [
            'format' => 'json',
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("loginRequest' is not configured for Login authentication");
        new Login([], $api);
    }

    public function testInvalid3()
    {
        $api = [
            'loginRequest' => [
                'method' => 'POST'
            ],
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("Request endpoint must be set for the Login authentication method.");
        new Login([], $api);
    }

    public function testInvalid4()
    {
        $api = [
            'loginRequest' => [
                'method' => 'POST',
                'endpoint' => 'dummy'
            ],
            'expires' => 'never'
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage("The 'expires' attribute must be either an integer or an array with 'response' key containing a path in the response");
        new Login([], $api);
    }
}
