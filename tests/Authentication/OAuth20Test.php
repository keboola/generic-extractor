<?php

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\OAuth20;
use GuzzleHttp\Client;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Filesystem\JsonFile;
use Keboola\Code\Builder;
use Psr\Log\NullLogger;

class OAuth20Test extends ExtractorTestCase
{
    public function testAuthenticateClientJson()
    {
        $config = JsonFile::create(__DIR__ . '/../data/oauth20bearer/config.json');

        // FIXME base_url from cfg
        $client = new Client(['base_url' => 'http://example.com']);
        $client->setDefaultOption('headers', ['X-Test' => 'test']);
        $restClient = new RestClient($client, new NullLogger());
        $auth = new OAuth20(
            $config->get('authorization'),
            $config->get('parameters', 'api', 'authentication'),
            new Builder
        );
        $auth->authenticateClient($restClient);

        $request = $client->createRequest('GET', '/');
        $client->send($request);

        self::assertEquals('Bearer testToken', $request->getHeader('Authorization'));
        self::assertEquals('test', $request->getHeader('X-Test'));
    }

    public function testMACAuth()
    {
        $config = JsonFile::create(__DIR__ . '/../data/oauth20mac/config.json');

        // FIXME base_url from cfg
        $client = new Client(['base_url' => 'http://example.com']);
        $restClient = new RestClient($client, new NullLogger());
        $auth = new OAuth20(
            $config->get('authorization'),
            $config->get('parameters', 'api', 'authentication'),
            new Builder
        );
        $auth->authenticateClient($restClient);

        $request = $client->createRequest('GET', '/resource?k=v');
        self::sendRequest($client, $request);

        $authData = json_decode($config->get('authorization', 'oauth_api', 'credentials', '#data'));

        $authHeader = $request->getHeader('Authorization');
        $match = preg_match(
            '/MAC id="testToken", ts="([0-9]{10})", nonce="([0-9a-zA-Z]{16})", mac="([0-9a-zA-Z]{32})"/',
            $authHeader,
            $matches
        );
        if (1 !== $match) {
            throw new \Exception("MAC Header does not match the expected pattern");
        }

        $timestamp = $matches[1];
        $nonce = $matches[2];

        $macString = join("\n", [
            $timestamp,
            $nonce,
            strtoupper($request->getMethod()),
            $request->getResource(),
            $request->getHost(),
            $request->getPort(),
            "\n"
        ]);

        $expectedAuthHeader = sprintf(
            'MAC id="%s", ts="%s", nonce="%s", mac="%s"',
            $authData->access_token,
            $timestamp,
            $nonce,
            md5(hash_hmac('sha256', $macString, $authData->mac_secret))
        );

        self::assertEquals($expectedAuthHeader, $authHeader);
        // Header gets last newline trimmed
        self::assertEquals($macString, $request->getHeader('Test') . PHP_EOL . PHP_EOL);
    }
}
