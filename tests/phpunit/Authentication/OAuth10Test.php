<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\OAuth10;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use PHPUnit\Framework\TestCase;

class OAuth10Test extends TestCase
{
    public function testAuthenticateClient(): void
    {
        // Create Oauth10 auth
        $authorization = [
            'oauth_api' => [
                'credentials' => [
                    '#data' => '{"oauth_token": "token", "oauth_token_secret": "token_secret"}',
                    'appKey' => 'aaa',
                    '#appSecret' => 'bbb',
                ],
            ],
        ];
        $auth = new OAuth10($authorization);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200((string) json_encode((object) [
                'data' => [1, 2, 3],
            ]))
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Each request contains Authorization header
        // 1. requests
        self::assertEquals(
            (object) ['data' => [1, 2, 3]],
            $restClient->download($restClient->createRequest(['endpoint' => '/']))
        );
        $authHeader1 = $history->pop()->getRequest()->getHeaderLine('Authorization');
        self::assertMatchesRegularExpression(
            '/^OAuth oauth_consumer_key="aaa", oauth_nonce="([0-9a-zA-Z]*)", '.
            'oauth_signature="([0-9a-zA-Z%]*)", oauth_signature_method="HMAC-SHA1", '.
            'oauth_timestamp="([0-9]{10})", oauth_token="token", oauth_version="1.0"$/',
            $authHeader1
        );

        // 2. request
        self::assertEquals(
            (object) ['data' => [4, 5, 6]],
            $restClient->download($restClient->createRequest(['endpoint' => '/']))
        );
        $authHeader2 = $history->pop()->getRequest()->getHeaderLine('Authorization');
        self::assertMatchesRegularExpression(
            '/^OAuth oauth_consumer_key="aaa", oauth_nonce="([0-9a-zA-Z]*)", '.
            'oauth_signature="([0-9a-zA-Z%]*)", oauth_signature_method="HMAC-SHA1", '.
            'oauth_timestamp="([0-9]{10})", oauth_token="token", oauth_version="1.0"$/',
            $authHeader2
        );

        // No more history items
        self::assertTrue($history->isEmpty());
    }
}
