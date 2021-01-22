<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Authentication\OAuth20;
use Keboola\GenericExtractor\Authentication\OAuth20Login;
use Keboola\GenericExtractor\Authentication\Query;
use GuzzleHttp\Psr7\Query as Psr7Query;
use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ApiTest extends TestCase
{
    public function testCreateBaseUrlString(): void
    {
        $string = 'https://third.second.com/TEST/Something/';
        $api = new Api(new NullLogger(), ['baseUrl' => $string], [], []);
        self::assertEquals($string, $api->getBaseUrl());
    }

    public function testCreateBaseUrlStringWithUnderscore(): void
    {
        $string = 'https://foo_export.test.example.com';
        $api = new Api(new NullLogger(), ['baseUrl' => $string], [], []);
        self::assertEquals($string, $api->getBaseUrl());
    }

    public function testCreateInvalidUrlString(): void
    {
        try {
            new Api(new NullLogger(), ['baseUrl' => 'htt//this is not valid'], [], []);
            self::fail('Invalid URL must fail');
        } catch (UserException $e) {
            self::assertStringContainsString('is not a valid URL', $e->getMessage());
        }
    }

    public function testCreateBaseUrlFunction(): void
    {
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://',
                (object) ['attr' => 'domain'],
                '.example.com/',
            ],
        ];
        $api = new Api(new NullLogger(), ['baseUrl' => $fn], ['domain' => 'keboola'], []);
        self::assertEquals('https://keboola.example.com/', $api->getBaseUrl());
    }

    public function testCreateBaseUrlFunctionError(): void
    {
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://keboola.com/',
                (object) ['attr' => 'path'],
            ],
        ];
        $this->expectException(UserException::class);
        new Api(new NullLogger(), ['baseUrl' => $fn], [], []);
    }

    public function testCreateAuthQueryDeprecated(): void
    {
        $attributes = ['key' => 'val'];
        // Deprecated way
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            'authentication' => [
                'type' => 'url.query',
            ],
            'query' => [
                'param' => [
                    'attr' => 'key',
                ],
            ],
        ];
        $api = new Api(new NullLogger(), $apiConfig, $attributes, []);
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar"}')
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $client) use ($api): void {
                $api->getAuth()->attachToClient($client);
            })
            ->getRestClient();

        self::assertEquals(
            (object) ['foo' => 'bar'],
            $restClient->download($restClient->createRequest(['endpoint' => 'http://example.com?foo=bar']))
        );

        $request = $history->shift()->getRequest();
        self::assertInstanceOf(Query::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], Psr7Query::parse($request->getUri()->getQuery()));
    }

    public function testCreateAuthQuery(): void
    {
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            'authentication' => [
                'type' => 'query',
                'query' => [
                    'param' => [
                        'attr' => 'key',
                    ],
                ],
            ],
        ];

        $api = new Api(new NullLogger(), $apiConfig, ['key' => 'val'], []);
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar"}')
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $client) use ($api): void {
                $api->getAuth()->attachToClient($client);
            })
            ->getRestClient();

        self::assertEquals(
            (object) ['foo' => 'bar'],
            $restClient->download($restClient->createRequest(['endpoint' => 'http://example.com?foo=bar']))
        );

        $request = $history->shift()->getRequest();
        self::assertInstanceOf(Query::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], Psr7Query::parse($request->getUri()->getQuery()));
    }

    public function testCreateAuthOAuth20Bearer(): void
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20bearer/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar"}')
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $client) use ($api): void {
                $api->getAuth()->attachToClient($client);
            })
            ->getRestClient();

        self::assertEquals(
            (object) ['foo' => 'bar'],
            $restClient->download($restClient->createRequest(['endpoint' => 'http://example.com?foo=bar']))
        );

        $request = $history->shift()->getRequest();
        $headers = $request->getHeaders();
        unset($headers['User-Agent']);
        self::assertInstanceOf(OAuth20::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar'], Psr7Query::parse($request->getUri()->getQuery()));
        self::assertEquals(
            ['Host' => ['example.com'], 'Authorization' => ['Bearer testToken']],
            $headers
        );
    }

    public function testCreateOauth2Login(): void
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20login/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);

        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            // Login response
            ->addResponse200('{"access_token": "baz"}')
            // Api response
            ->addResponse200('{"foo": "bar"}')
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $client) use ($api): void {
                $api->getAuth()->attachToClient($client);
            })
            ->getRestClient();

        self::assertEquals(
            (object) ['foo' => 'bar'],
            $restClient->download($restClient->createRequest(['endpoint' => 'http://example.com?foo=bar']))
        );

        // Login request
        $loginRequest = $history->shift()->getRequest();
        $headers = $loginRequest->getHeaders();
        unset($headers['User-Agent']);
        self::assertEquals('POST', (string) $loginRequest->getMethod());
        self::assertEquals('/uas/oauth2/accessToken', (string) $loginRequest->getUri()->getPath());

        // Api request
        $apiRequest = $history->shift()->getRequest();
        $headers = $apiRequest->getHeaders();
        unset($headers['User-Agent']);
        self::assertInstanceOf(OAuth20Login::class, $api->getAuth());
        self::assertEquals(
            ['foo' => 'bar', 'oauth2_access_token' => 'baz'],
            Psr7Query::parse($apiRequest->getUri()->getQuery())
        );
        self::assertEquals(['Host' => ['example.com']], $headers);

        // No more history items
        self::assertTrue($history->isEmpty());
    }

    public function testNoCaCertificate(): void
    {
        $apiConfig = [
            'baseUrl' => 'http://example.com',
        ];

        $api = new Api(new NullLogger(), $apiConfig, [], []);
        self::assertFalse($api->hasCaCertificate());

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Key "api.caCertificate" is not configured.');
        $api->getCaCertificate();
    }


    public function testCustomCaCertificate(): void
    {
        $crtContent = "-----BEGIN CERTIFICATE-----\nMIIFazCCA1OgAwIBAgIUGzl\n....\n-----END CERTIFICATE-----\n";
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            'caCertificate' => $crtContent,
        ];

        $api = new Api(new NullLogger(), $apiConfig, [], []);
        self::assertTrue($api->hasCaCertificate());
        self::assertSame($crtContent, $api->getCaCertificate());
        self::assertSame($crtContent, file_get_contents($api->getCaCertificateFile()));
    }

    public function testCustomClientCertificate(): void
    {
        $crtContent =
            "-----BEGIN CERTIFICATE-----\nMIIFazCCA1OgAwIBAgIUGzl\n...."."\n-----END CERTIFICATE-----\n".
            "-----BEGIN RSA PRIVATE KEY-----\nMIIFazCCA1OgAwIBAgIUGzl\n-----END RSA PRIVATE KEY-----";
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            '#clientCertificate' => $crtContent,
        ];

        $api = new Api(new NullLogger(), $apiConfig, [], []);
        self::assertTrue($api->hasClientCertificate());
        self::assertSame($crtContent, $api->getClientCertificate());
        self::assertSame($crtContent, file_get_contents($api->getClientCertificateFile()));
    }

    public function testInvalidFunctionBaseUrlThrowsUserException(): void
    {
        $apiConfig = [
            'baseUrl' => [
                'function' => 'concat',
                'args' => [
                    'http://',
                    '/087-function-baseurl/',
                ],
            ],
        ];
        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            'The "baseUrl" attribute in API configuration resulted in an invalid URL (http:///087-function-baseurl/)'
        );
        new Api(new NullLogger(), $apiConfig, [], []);
    }
}
