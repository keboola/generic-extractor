<?php

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Query;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use Keboola\Code\Builder;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class QueryTest extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $client = new Client(['base_url' => 'http://example.com']);

        $builder = new Builder;
        $definitions = [
            'paramOne' => (object) ['attr' => 'first'],
            'paramTwo' => (object) [
                'function' => 'md5',
                'args' => [(object) ['attr' => 'second']]
            ],
            'paramThree' => 'string'
        ];
        $attrs = ['first' => 1, 'second' => 'two'];

        $auth = new Query($builder, $attrs, $definitions);
        $auth->authenticateClient(new RestClient($client, new NullLogger()));

        $request = $client->createRequest('GET', '/');
        $client->send($request);

        self::assertEquals(
            'paramOne=1&paramTwo=' . md5($attrs['second']) . '&paramThree=string',
            (string) $request->getQuery()
        );
    }

    public function testAuthenticateClientQuery()
    {
        $client = new Client(['base_url' => 'http://example.com']);

        $builder = new Builder;
        $auth = new Query($builder, [], ['authParam' => 'secretCodeWow']);
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

        $builder = new Builder;

        $urlTokenParam = (object) [
            'function' => 'concat',
            'args' => [
                (object) ['request' => 'url'],
                (object) ['attr' => 'token'],
                (object) ['query' => 'param']
            ]
        ];

        $definitions = [
            'urlTokenParamHash' => (object) [
                'function' => 'md5',
                'args' => [
                    $urlTokenParam
                ]
            ],
            'urlTokenParam' => $urlTokenParam
        ];
        $attrs = [
            'token' => 'asdf1234'
        ];

        $auth = new Query($builder, $attrs, $definitions);
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
                md5($originalUrl . $attrs['token'] . 'value') .
                '&urlTokenParam=' . urlencode($originalUrl) . $attrs['token'] . 'value',
            (string) $request->getQuery()
        );
    }
}
