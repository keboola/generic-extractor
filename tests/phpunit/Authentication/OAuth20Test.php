<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use Keboola\GenericExtractor\Authentication\OAuth20;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\NullLogger;

class OAuth20Test extends ExtractorTestCase
{
    public function testAuthenticateClientJson(): void
    {
        // Create OAuth20 auth
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20bearer/config.json'), true);
        $auth = new OAuth20(
            $config['authorization'],
            $config['parameters']['api']['authentication']
        );

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar1"}')
            ->addResponse200('{"foo": "bar2"}')
            ->setGuzzleConfig(['headers' => ['X-Test' => 'test']])
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Each request contains Authorization header
        // 1. request
        self::assertEquals((object) ['foo' => 'bar1'], $restClient->download(new RestRequest(['endpoint' => 'ep'])));
        $request1 = $history->pop()->getRequest();
        self::assertSame('Bearer testToken', $request1->getHeaderLine('Authorization'));
        self::assertSame('test', $request1->getHeaderLine('X-Test'));
        // 2. request
        self::assertEquals((object) ['foo' => 'bar2'], $restClient->download(new RestRequest(['endpoint' => 'ep'])));
        $request2 = $history->pop()->getRequest();
        self::assertSame('Bearer testToken', $request2->getHeaderLine('Authorization'));
        self::assertSame('test', $request2->getHeaderLine('X-Test'));

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testMACAuth(): void
    {
        // Create OAuth20 auth
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20mac/config.json'), true);
        $authData = json_decode($config['authorization']['oauth_api']['credentials']['#data']);
        $auth = new OAuth20(
            $config['authorization'],
            $config['parameters']['api']['authentication']
        );

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar1"}')
            ->addResponse200('{"foo": "bar2"}')
            ->setGuzzleConfig(['headers' => ['X-Test' => 'test']])
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Each request contains Authorization header
        // 1. request
        self::assertEquals(
            (object) ['foo' => 'bar1'],
            $restClient->download(new RestRequest(['endpoint' => '/resource', 'params' => ['k1' => 'v1']]))
        );
        $this->assertMacRequest($history->pop()->getRequest(), $authData);
        // 2. request
        self::assertEquals(
            (object) ['foo' => 'bar2'],
            $restClient->download(new RestRequest(['endpoint' => '/resource', 'params' => ['k2' => 'v2']]))
        );
        $this->assertMacRequest($history->pop()->getRequest(), $authData);

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    private function assertMacRequest(RequestInterface $request, \stdClass $authData): void
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $match = preg_match(
            '/MAC id="testToken", ts="([0-9]{10})", nonce="([0-9a-zA-Z]{16})", mac="([0-9a-zA-Z]{32})"/',
            $authHeader,
            $matches
        );
        if ($match !== 1) {
            throw new \Exception('MAC Header does not match the expected pattern');
        }

        $timestamp = $matches[1];
        $nonce = $matches[2];

        $originalUri = $this->getOriginalUri($request->getUri());
        $resource = $originalUri->getPath() . ($originalUri->getQuery() ? '?' . $originalUri->getQuery() : '');
        $macString = join(
            "\n",
            [
                $timestamp,
                $nonce,
                strtoupper($request->getMethod()),
                $resource,
                $originalUri->getHost(),
                80,
                "\n",
            ]
        );

        $expectedAuthHeader = sprintf(
            'MAC id="%s", ts="%s", nonce="%s", mac="%s"',
            $authData->access_token,
            $timestamp,
            $nonce,
            md5(hash_hmac('sha256', $macString, $authData->mac_secret))
        );
        self::assertEquals($expectedAuthHeader, $authHeader);
        // Header gets last newline trimmed
        self::assertEquals($macString, $request->getHeaderLine('Test') . "\n\n");
    }

    private function getOriginalUri(UriInterface $uri): UriInterface
    {
        $query = Query::parse($uri->getQuery());
        unset($query['Authorization']);
        unset($query['Test']);
        return $uri->withQuery(Query::build($query));
    }
}
