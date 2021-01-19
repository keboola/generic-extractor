<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\OAuth10;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Client\RestClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class OAuth10Test extends TestCase
{
    public function testAuthenticateClient(): void
    {
        $restClient = new RestClient(new NullLogger(), [], [], []);
        $auth = new OAuth10(
            [
            'oauth_api' => [
                'credentials' => [
                    '#data' => '{"oauth_token": "token", "oauth_token_secret": "token_secret"}',
                    'appKey' => 'aaa',
                    '#appSecret' => 'bbb',
                ],
            ],
            ]
        );
        $auth->authenticateClient($restClient);

        self::assertEquals('oauth', $restClient->getClient()->getDefaultOption('auth'));

        $request = $restClient->createRequest(['endpoint' => '/']);

        $mock = new Mock(
            [
            new Response(200, [], Stream::factory('{}')),
            ]
        );
        $restClient->getClient()->getEmitter()->attach($mock);

        $history = new History();
        $restClient->getClient()->getEmitter()->attach($history);

        $restClient->download($request);

        $authHeader = $history->getLastRequest()->getHeaders()['Authorization'][0];
        self::assertMatchesRegularExpression(
            '/^OAuth oauth_consumer_key="aaa", oauth_nonce="([0-9a-zA-Z]*)", '.
            'oauth_signature="([0-9a-zA-Z%]*)", oauth_signature_method="HMAC-SHA1", '.
            'oauth_timestamp="([0-9]{10})", oauth_token="token", oauth_version="1.0"$/',
            $authHeader
        );
    }
}
