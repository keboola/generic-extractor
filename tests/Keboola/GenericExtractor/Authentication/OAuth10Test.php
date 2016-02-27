<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\OAuth10;
use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Client\RestClient;

class OAuth10Test extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $client = new Client;
        $restClient = new RestClient($client);
        $auth = new OAuth10([
            'oauth_api' => [
                'credentials' => [
                    '#data' => '{"oauth_token": "token", "oauth_token_secret": "token_secret"}',
                    'appKey' => 'aaa',
                    '#appSecret' => 'bbb'
                ]
            ]
        ]);
        $auth->authenticateClient($restClient);

        self::assertEquals('oauth', $client->getDefaultOption('auth'));

        $request = $restClient->createRequest(['endpoint' => '/']);

        $mock = new Mock([
            new Response(200, [], Stream::factory('{}'))
        ]);
        $client->getEmitter()->attach($mock);

        $history = new History();
        $client->getEmitter()->attach($history);

        $restClient->download($request);

        $authHeader = $history->getLastRequest()->getHeaders()['Authorization'][0];
        self::assertRegexp('/^OAuth oauth_consumer_key="aaa", oauth_nonce="([0-9a-zA-Z]*)", oauth_signature="([0-9a-zA-Z%]*)", oauth_signature_method="HMAC-SHA1", oauth_timestamp="([0-9]{10})", oauth_token="token", oauth_version="1.0"$/', $authHeader);
    }
}
