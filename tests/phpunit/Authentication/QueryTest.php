<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Query;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class QueryTest extends ExtractorTestCase
{
    public function testAuthenticateClient(): void
    {
        $authentication = [
            'query' => [
                'paramOne' => (object) ['attr' => 'first'],
                'paramTwo' => (object) [
                    'function' => 'md5',
                    'args' => [(object) ['attr' => 'second']],
                ],
                'paramThree' => 'string',
            ],
        ];
        $configAttributes = ['first' => 1, 'second' => 'two'];

        $auth = new Query($configAttributes, $authentication);
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com'], [], []);
        $auth->attachToClient($restClient);

        $request = $restClient->getClient()->createRequest('GET', '/');
        $restClient->getClient()->send($request);

        self::assertEquals(
            'paramOne=1&paramTwo=' . md5($configAttributes['second']) . '&paramThree=string',
            (string) $request->getQuery()
        );
    }

    public function testAuthenticateClientQuery(): void
    {
        $auth = new Query([], ['query' => ['authParam' => 'secretCodeWow']]);
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com'], [], []);
        $auth->attachToClient($restClient);

        $mock = new Mock(
            [
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [
                        'data' => [1,2,3],
                        ]
                    )
                )
            ),
            ]
        );
        $restClient->getClient()->getEmitter()->attach($mock);

        $request = $restClient->getClient()->createRequest('GET', '/query?param=value');
        $restClient->getClient()->send($request);

        self::assertEquals(
            'param=value&authParam=secretCodeWow',
            (string) $request->getQuery()
        );
    }

    public function testRequestInfo(): void
    {
        $urlTokenParam = (object) [
            'function' => 'concat',
            'args' => [
                (object) ['request' => 'url'],
                (object) ['attr' => 'token'],
                (object) ['query' => 'param'],
            ],
        ];

        $authentication = [
            'query' => [
                'urlTokenParamHash' => (object) [
                    'function' => 'md5',
                    'args' => [
                        $urlTokenParam,
                    ],
                ],
                'urlTokenParam' => $urlTokenParam,
            ],
        ];
        $configAttributes = [
            'token' => 'asdf1234',
        ];

        $auth = new Query($configAttributes, $authentication);
        $restClient = new RestClient(new NullLogger(), ['base_url' => 'http://example.com'], [], []);
        $auth->attachToClient($restClient);

        $mock = new Mock(
            [
            new Response(
                200,
                [],
                Stream::factory(
                    (string) json_encode(
                        (object) [
                        'data' => [1,2,3],
                        ]
                    )
                )
            ),
            ]
        );
        $restClient->getClient()->getEmitter()->attach($mock);

        $request = $restClient->getClient()->createRequest('GET', '/query?param=value');
        $originalUrl = $request->getUrl();
        self::sendRequest($restClient->getClient(), $request);
        self::assertEquals(
            'param=value&urlTokenParamHash=' .
                md5($originalUrl . $configAttributes['token'] . 'value') .
                '&urlTokenParam=' . urlencode($originalUrl) . $configAttributes['token'] . 'value',
            (string) $request->getQuery()
        );
    }
}
