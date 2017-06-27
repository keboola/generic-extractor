<?php

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Query;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class QueryTest extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $client = new Client(['base_url' => 'http://example.com']);
        $authentication = [
            'query' => [
                'paramOne' => (object) ['attr' => 'first'],
                'paramTwo' => (object) [
                    'function' => 'md5',
                    'args' => [(object) ['attr' => 'second']]
                ],
                'paramThree' => 'string'
            ]
        ];
        $configAttributes = ['first' => 1, 'second' => 'two'];

        $auth = new Query($configAttributes, $authentication);
        $auth->authenticateClient(new RestClient($client, new NullLogger()));

        $request = $client->createRequest('GET', '/');
        $client->send($request);

        self::assertEquals(
            'paramOne=1&paramTwo=' . md5($configAttributes['second']) . '&paramThree=string',
            (string) $request->getQuery()
        );
    }

    public function testAuthenticateClientQuery()
    {
        $client = new Client(['base_url' => 'http://example.com']);
        $auth = new Query([], ['authParam' => 'secretCodeWow']);
        $restClient = new RestClient($client, new NullLogger());
        $auth->authenticateClient($restClient);

        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode((object) [
                'data' => [1,2,3]
            ])))
        ]);
        $client->getEmitter()->attach($mock);

        $request = $client->createRequest('GET', '/query?param=value');
        $client->send($request);

        self::assertEquals(
            'param=value&authParam=secretCodeWow',
            (string) $request->getQuery()
        );
    }

    public function testRequestInfo()
    {
        $client = new Client(['base_url' => 'http://example.com']);
        $urlTokenParam = (object) [
            'function' => 'concat',
            'args' => [
                (object) ['request' => 'url'],
                (object) ['attr' => 'token'],
                (object) ['query' => 'param']
            ]
        ];

        $authentication = [
            'query' => [
                'urlTokenParamHash' => (object) [
                    'function' => 'md5',
                    'args' => [
                        $urlTokenParam
                    ]
                ],
                'urlTokenParam' => $urlTokenParam
            ]
        ];
        $configAttributes = [
            'token' => 'asdf1234'
        ];

        $auth = new Query($configAttributes, $authentication);
        $auth->authenticateClient(new RestClient($client, new NullLogger()));

        $mock = new Mock([
            new Response(200, [], Stream::factory(json_encode((object) [
                'data' => [1,2,3]
            ])))
        ]);
        $client->getEmitter()->attach($mock);

        $request = $client->createRequest('GET', '/query?param=value');
        $originalUrl = $request->getUrl();
        self::sendRequest($client, $request);
        self::assertEquals(
            'param=value&urlTokenParamHash=' .
                md5($originalUrl . $configAttributes['token'] . 'value') .
                '&urlTokenParam=' . urlencode($originalUrl) . $configAttributes['token'] . 'value',
            (string) $request->getQuery()
        );
    }
}
