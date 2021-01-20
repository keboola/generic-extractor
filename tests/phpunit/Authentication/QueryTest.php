<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use GuzzleHttp\Psr7\Query as Psr7Query;
use Keboola\GenericExtractor\Authentication\Query;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;

class QueryTest extends ExtractorTestCase
{
    public function testAuthenticateClient(): void
    {
        // Create Query auth
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

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        self::assertEquals(
            (object) ['data' => [1, 2, 3]],
            $restClient->download($restClient->createRequest(['endpoint' => '/']))
        );

        // Assert request query
        $apiCall = $history->shift();
        self::assertEquals(
            'paramOne=1&paramTwo=' . md5($configAttributes['second']) . '&paramThree=string',
            $apiCall->getRequest()->getUri()->getQuery()
        );

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testAuthenticateClientQuery(): void
    {
        // Create Query auth
        $authentication = ['query' => ['authParam' => 'secretCodeWow']];
        $configAttributes = [];
        $auth = new Query($configAttributes, $authentication);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        self::assertEquals(
            (object) ['data' => [1, 2, 3]],
            $restClient->download(
                $restClient->createRequest(['endpoint' => '/query', 'params' => ['param' => 'value']])
            )
        );

        // Assert request query
        $apiCall = $history->shift();
        self::assertEquals(
            'param=value&authParam=secretCodeWow',
            $apiCall->getRequest()->getUri()->getQuery()
        );

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testRequestInfo(): void
    {
        // Create Query auth
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
        $configAttributes = ['token' => 'asdf1234'];
        $auth = new Query($configAttributes, $authentication);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        self::assertEquals(
            (object) ['data' => [1, 2, 3]],
            $restClient->download(
                $restClient->createRequest(['endpoint' => '/query', 'params' => ['param' => 'value']])
            )
        );

        // Assert request query
        $apiCall = $history->shift();
        $originalUrl = 'http://example.com/query?param=value';
        self::assertEquals(
            (string) Psr7Query::build([
                'param' => 'value',
                'urlTokenParamHash' => md5($originalUrl . $configAttributes['token'] . 'value'),
                'urlTokenParam' => $originalUrl . $configAttributes['token'] . 'value',
            ]),
            $apiCall->getRequest()->getUri()->getQuery()
        );

        // No more history items
        self::assertTrue($history->isEmpty());
    }
}
