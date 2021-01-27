<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\OAuth20Login;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;

class OAuth20LoginTest extends ExtractorTestCase
{
    public function testAuthenticateClient(): void
    {
        $expiresInSeconds = 3;

        // Create OAuth20Login auth
        $oauthCredentials = [
            'appKey' => 1,
            '#appSecret' => 'two',
            '#data' => (string) json_encode(
                [
                    'access_token' => '1234',
                    'refresh_token' => 'asdf',
                    'expires_in' => 3600,
                ]
            ),
        ];

        $authentication = [
            'loginRequest' => [
                'endpoint' => 'auth/refresh',
                'params' => ['refresh_token' => ['user' => 'refresh_token']],
                'method' => 'POST',
            ],
            'apiRequest' => [
                'query' => ['access_token' => 'access_token'],
            ],
            'expires' => ['response' => 'expires_in', 'relative' => true],
        ];

        $auth = new OAuth20Login([], ['oauth_api' => ['credentials' => $oauthCredentials]], $authentication);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Auth response
            ->addResponse200((string) json_encode((object) [
                'access_token' => 1234,
                'expires_in' => $expiresInSeconds,
            ]))
            // First API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [1,2,3],
            ]))
            // Second API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [4, 5, 6],
            ]))
            // Auth response (login expired_
            ->addResponse200((string) json_encode((object) [
                'access_token' => 4321,
                'expires_in' => $expiresInSeconds,
            ]))
            // Third API call response
            ->addResponse200((string) json_encode((object) [
                'data' => [7, 8, 9],
            ]))
            ->setGuzzleConfig(['base_url' => 'http://example.com/api'])
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Run
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [1, 2, 3]], $restClient->download($request));
        self::assertEquals((object) ['data' => [4, 5, 6]], $restClient->download($request));

        // Assert login call, "first" attribute is in body, "second" int the header
        $loginCall = $history->shift();
        self::assertEquals('POST', $loginCall->getRequest()->getMethod());
        self::assertEquals('{"refresh_token":"asdf"}', (string) $loginCall->getRequest()->getBody());

        // Assert API calls, must contain signature
        $apiCall1 = $history->shift();
        self::assertEquals('access_token=1234', $apiCall1->getRequest()->getUri()->getQuery());
        $apiCall2 = $history->shift();
        self::assertEquals('access_token=1234', $apiCall2->getRequest()->getUri()->getQuery());

        // No more history items
        self::assertTrue($history->isEmpty());

        // Let's wait for the login expiration
        sleep($expiresInSeconds + 1);
        $expiresSecond = time() + $expiresInSeconds;

        // Run -> expected new login
        $request = $restClient->createRequest(['endpoint' => '/api/get']);
        self::assertEquals((object) ['data' => [7, 8, 9]], $restClient->download($request));

        // Assert login call
        $loginCall = $history->shift();
        self::assertEquals('POST', $loginCall->getRequest()->getMethod());
        self::assertEquals('{"refresh_token":"asdf"}', (string) $loginCall->getRequest()->getBody());

        // Assert API call
        $apiCall3 = $history->shift();
        self::assertEquals('access_token=4321', $apiCall3->getRequest()->getUri()->getQuery());

        // No more history items
        self::assertTrue($history->isEmpty());

        // Check that the expiration meets expectations
        $expiration = $auth->getExpiration();
        self::assertTrue(is_int($expiration));
        self::assertLessThan($expiresSecond + 2, $expiration);
    }
}
