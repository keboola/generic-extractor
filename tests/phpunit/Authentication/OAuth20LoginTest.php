<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\OAuth20Login;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class OAuth20LoginTest extends ExtractorTestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('TODO fix test');
        parent::setUp();
    }

    public function testAuthenticateClient(): void
    {
        $mock = new Mock(
            [
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [ // auth
                        'access_token' => 1234,
                        'expires_in' => 3,
                        ]
                    )
                )
            ),
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [ // api call
                        'data' => [1,2,3],
                        ]
                    )
                )
            ),
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [ // api call
                        'data' => [1,2,3],
                        ]
                    )
                )
            ),
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [ // auth
                        'access_token' => 4321,
                        'expires_in' => 3,
                        ]
                    )
                )
            ),
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [ // api call
                        'data' => [1,2,3],
                        ]
                    )
                )
            ),
            ]
        );
        $history = new History();

        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com/api'], [], []);
        $restClient->getClient()->getEmitter()->attach($mock);
        $restClient->getClient()->getEmitter()->attach($history);

        $oauthCredentials = [
            'appKey' => 1,
            '#appSecret' => 'two',
            '#data' => (string) json_encode(
                [
                'access_token' => '1234',
                'refresh_token' => 'asdf',
                'expires_in' => 3600,
                ]
            ),
        ];

        $authentication = [
            'loginRequest' => [
                'endpoint' => 'auth/refresh',
                'params' => ['refresh_token' => ['user' => 'refresh_token']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'query' => ['access_token' => 'access_token'],
            ],
            'expires' => ['response' => 'expires_in', 'relative' => true],
        ];

        $auth = new OAuth20Login([], ['oauth_api' => ['credentials' => $oauthCredentials]], $authentication);
        $auth->authenticateClient($restClient);

        $request = $restClient->createRequest(['endpoint' => '/']);
        $restClient->download($request);
        $restClient->download($request);
        sleep(5);
        $restClient->download($request);

        // test signature of the api request
        self::assertEquals('access_token=1234', (string) $history->getIterator()[1]['request']->getQuery());
        self::assertEquals('access_token=1234', (string) $history->getIterator()[2]['request']->getQuery());
        self::assertEquals('access_token=4321', (string) $history->getIterator()[4]['request']->getQuery());

        $expiry = self::getProperty(
            $restClient->getClient()->getEmitter()->listeners('before')[0][0],
            'expires'
        );
        self::assertEquals(time() + 3, $expiry);
    }
}
