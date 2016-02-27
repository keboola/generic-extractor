<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\Basic;
use GuzzleHttp\Client;
use Keboola\Juicer\Client\RestClient;

class BasicTest extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $client = new Client;
        $auth = new Basic(['username' => 'test', 'password' => 'pass']);
        $auth->authenticateClient(new RestClient($client));

        self::assertEquals(['test','pass'], $client->getDefaultOption('auth'));

        $request = $client->createRequest('GET', '/');
        self::assertArrayHasKey('Authorization', $request->getHeaders());
        self::assertEquals(['Basic dGVzdDpwYXNz'], $request->getHeaders()['Authorization']);

        $hashClient = new Client();
        $auth = new Basic(['username' => 'test', '#password' => 'pass']);
        $auth->authenticateClient(new RestClient($hashClient));

        self::assertEquals(['test','pass'], $hashClient->getDefaultOption('auth'));
    }
}
