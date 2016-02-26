<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\OAuth20;
use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Client\RestClient;

class OAuth20Test extends ExtractorTestCase
{
    public function testAuthenticateClient()
    {
        $client = new Client;
        $client->setDefaultOption('headers', ['X-Test' => 'test']);
        $restClient = new RestClient($client);
        $auth = new OAuth20([
            'oauth_api' => [
                'credentials' => [
                    '#token' => 'asdf'
                ]
            ]
        ]);
        $auth->authenticateClient($restClient);

        self::assertEquals('Bearer asdf', $client->getDefaultOption('headers')['Authorization']);
        self::assertArrayHasKey('X-Test', $client->getDefaultOption('headers'));
    }
}
