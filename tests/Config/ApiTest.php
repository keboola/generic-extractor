<?php

namespace Keboola\GenericExtractor\Tests\Config;

use GuzzleHttp\Client;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\Emitter;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\Response;
use Keboola\GenericExtractor\Authentication\OAuth20;
use Keboola\GenericExtractor\Authentication\OAuth20Login;
use Keboola\GenericExtractor\Authentication\Query;
use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Subscriber\AbstractSignature;
use Keboola\Juicer\Client\RestClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ApiTest extends TestCase
{
    private function getClientMock(Request $request)
    {
        $beforeEventMock = self::createMock(BeforeEvent::class);
        $beforeEventMock->method('getRequest')->willReturn($request);
        /** @var BeforeEvent $beforeEventMock */
        $emitterMock = self::createMock(Emitter::class);
        $a = 1;
        $emitterMock->method('attach')->willReturnCallback(function ($arg) use ($beforeEventMock, $request, &$a) {
            /** @var AbstractSignature $arg */
            if ($a === 1) {
                // make sure the onBefore is triggered only once because of LoginRequests
                $a++;
                $arg->onBefore($beforeEventMock);
            }
        });
        $guzzleClientMock = self::createMock(Client::class);
        $guzzleClientMock->method('getEmitter')->willReturn($emitterMock);
        $guzzleClientMock->method('send')->willReturn(new Response(200));
        $restClientMock = self::createMock(RestClient::class);
        $restClientMock->method('getClient')->willReturn($guzzleClientMock);
        $restClientMock->method('getGuzzleRequest')->willReturn(new Request('POST', 'http://example.com'));
        return $restClientMock;
    }

    public function testCreateBaseUrlString()
    {
        $string = 'https://third.second.com/TEST/Something/';
        $api = new Api(new NullLogger(), ['baseUrl' => $string], [], []);
        self::assertEquals($string, $api->getBaseUrl());
    }

    public function testCreateInvalidUrlString()
    {
        try {
            new Api(new NullLogger(), ['baseUrl' => 'htt//this is not valid'], [], []);
            self::fail("Invalid URL must fail");
        } catch (UserException $e) {
            self::assertContains('is not a valid URL', $e->getMessage());
        }
    }

    public function testCreateBaseUrlFunction()
    {
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://',
                (object) ['attr' => 'domain'],
                '.example.com/'
            ]
        ];
        $api = new Api(new NullLogger(), ['baseUrl' => $fn], ['domain' => 'keboola'], []);
        self::assertEquals('https://keboola.example.com/', $api->getBaseUrl());
    }

    /**
     * @expectedException \Keboola\GenericExtractor\Exception\UserException
     */
    public function testCreateBaseUrlFunctionError()
    {
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://keboola.com/',
                (object) ['attr' => 'path']
            ]
        ];
        new Api(new NullLogger(), ['baseUrl' => $fn], [], []);
    }

    public function testCreateAuthQueryDeprecated()
    {
        $attributes = ['key' => 'val'];
        // Deprecated way
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            'authentication' => [
                'type' => 'url.query'
            ],
            'query' => [
                'param' => [
                    'attr' => 'key'
                ]
            ]
        ];
        $api = new Api(new NullLogger(), $apiConfig, $attributes, []);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        /** @var RestClient $restClientMock */
        $api->getAuth()->authenticateClient($restClientMock);
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], $request->getQuery()->toArray());
        self::assertInstanceOf(Query::class, $api->getAuth());
    }

    public function testCreateAuthQuery()
    {
        $apiConfig = [
            'baseUrl' => 'http://example.com',
            'authentication' => [
                'type' => 'query',
                'query' => [
                    'param' => [
                        'attr' => 'key'
                    ]
                ]
            ]
        ];

        $api = new Api(new NullLogger(), $apiConfig, ['key' => 'val'], []);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        /** @var RestClient $restClientMock */
        $api->getAuth()->authenticateClient($restClientMock);
        self::assertEquals(['foo' => 'bar', 'param' => 'val'], $request->getQuery()->toArray());
        self::assertInstanceOf(Query::class, $api->getAuth());
    }

    public function testCreateAuthOAuth20Bearer()
    {
        $config = json_decode(file_get_contents(__DIR__ . '/../data/oauth20bearer/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        /** @var RestClient $restClientMock */
        $api->getAuth()->authenticateClient($restClientMock);
        self::assertInstanceOf(OAuth20::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar'], $request->getQuery()->toArray());
        self::assertEquals(
            ['Host' => ['example.com'], 'Authorization' => ['Bearer testToken']],
            $request->getHeaders()
        );
    }

    public function testCreateOauth2Login()
    {
        $config = json_decode(file_get_contents(__DIR__ . '/../data/oauth20login/config.json'), true);
        $api = new Api(new NullLogger(), $config['parameters']['api'], [], $config['authorization']);
        $request = new Request('GET', 'http://example.com?foo=bar');
        $restClientMock = $this->getClientMock($request);
        $restClientMock->method('getObjectFromResponse')->willReturn((object)['access_token' => 'baz']);
        /** @var RestClient $restClientMock */
        $api->getAuth()->authenticateClient($restClientMock);
        self::assertInstanceOf(OAuth20Login::class, $api->getAuth());
        self::assertEquals(['foo' => 'bar', 'oauth2_access_token' => 'baz'], $request->getQuery()->toArray());
        self::assertEquals(['Host' => ['example.com']], $request->getHeaders());
    }

    public function testNoCaCertificate()
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


    public function testCustomCaCertificate()
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

    public function testInvalidFunctionBaseUrlThrowsUserException()
    {
        $apiConfig = [
            "baseUrl" => [
                "function" => "concat",
                "args" => [
                    "http://",
                    "/087-function-baseurl/",
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
