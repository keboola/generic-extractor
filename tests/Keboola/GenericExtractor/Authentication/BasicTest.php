<?php

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\Basic;
use GuzzleHttp\Client;
use Keboola\Juicer\Client\RestClient;
use Psr\Log\NullLogger;

class BasicTest extends ExtractorTestCase
{

    /**
     * @param array $credentials
     * @dataProvider credentialsProvider
     */
    public function testAuthenticateClient($credentials)
    {
        $client = new Client;
        $auth = new Basic($credentials);
        $auth->authenticateClient(new RestClient($client, new NullLogger()));

        self::assertEquals(['test','pass'], $client->getDefaultOption('auth'));

        $request = $client->createRequest('GET', '/');
        self::assertArrayHasKey('Authorization', $request->getHeaders());
        self::assertEquals(['Basic dGVzdDpwYXNz'], $request->getHeaders()['Authorization']);
    }

    public static function credentialsProvider()
    {
        return [
            [
                ['username' => 'test', 'password' => 'pass']
            ],
            [
                ['#username' => 'test', '#password' => 'pass']
            ]
        ];
    }
}
