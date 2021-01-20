<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Config;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Authentication\OAuth20;
use Keboola\GenericExtractor\Authentication\OAuth20Login;
use Keboola\GenericExtractor\Authentication\Query;
use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
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
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->createMockClient($request);

        $api->getAuth()->attachToClient($restClientMock);
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], $request->getQuery()->toArray());
        self::assertInstanceOf(Query::class, $api->getAuth());
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
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        /**
 * @var RestClient $restClientMock
*/
        $api->getAuth()->attachToClient($restClientMock);
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], $request->getQuery()->toArray());
        self::assertInstanceOf(Query::class, $api->getAuth());
    }

    public function testCreateAuthOAuth20Bearer(): void
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20bearer/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        /**
 * @var RestClient $restClientMock
*/
        $api->getAuth()->attachToClient($restClientMock);
        self::assertInstanceOf(OAuth20::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar'], $request->getQuery()->toArray());
        self::assertEquals(
            ['Host' => ['example.com'], 'Authorization' => ['Bearer testToken']],
            $request->getHeaders()
        );
    }

    public function testCreateOauth2Login(): void
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/../data/oauth20login/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        $restClientMock->method('getObjectFromResponse')->willReturn((object) ['access_token' => 'baz']);
        /**
 * @var RestClient $restClientMock
*/
        $api->getAuth()->attachToClient($restClientMock);
        self::assertInstanceOf(OAuth20Login::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar', 'oauth2_access_token' => 'baz'], $request->getQuery()->toArray());
        self::assertEquals(['Host' => ['example.com']], $request->getHeaders());
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

    private function createMockClient(
        array $queue,
        array $options = [],
        array $retryOptions = [],
        array $defaultOptions = [],
        array $ignoreErrors = []
    ): RestClient {
        $handler = HandlerStack::create(new MockHandler($queue));
        $options['handler'] = $handler;
        $restClient = new RestClient(new NullLogger(), $options, $retryOptions, $defaultOptions, $ignoreErrors);

        // To log retries, history middleware must be pushed after retry middleware in RestClient.
        $handler->push(Middleware::history($this->history));

        return $restClient;
    }
}
