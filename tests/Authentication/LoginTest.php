<?php

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Login;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Client\RestClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoginTest extends TestCase
{
    public function testAuthenticateClient()
    {
        $guzzle = new Client(['base_url' => 'http://example.com/api']);
        $guzzle->setDefaultOption('headers', ['X-Test' => '1']);

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
        $guzzle->getEmitter()->attach($mock);

        $history = new History();
        $guzzle->getEmitter()->attach($history);

        $restClient = new RestClient($guzzle, new NullLogger());

        $attrs = ['first' => 1, 'second' => 'two'];

        $api = [
            'authentication' => [
                'loginRequest' => [
                    'endpoint' => 'login',
                    'params' => ['par' => ['attr' => 'first']],
                    'headers' => ['X-Header' => ['attr' => 'second']],
                    'method' => 'POST'
                ],
                'apiRequest' => [
                    'headers' => ['X-Test-Auth' => 'headerToken'],
                    'query' => ['qToken' => 'queryToken']
                ],
                'expires' => ['response' => 'expiresIn', 'relative' => true]
            ]
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
}
