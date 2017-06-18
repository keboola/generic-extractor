<?php

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\OAuth20;
use Keboola\GenericExtractor\Authentication\OAuth20Login;
use Keboola\GenericExtractor\Authentication\Query;
use Keboola\GenericExtractor\Config\Api;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Filesystem\JsonFile;
use Psr\Log\NullLogger;

class ApiTest extends ExtractorTestCase
{
    public function testCreateBaseUrlString()
    {
        $config = new Config('testApp', 'testCfg', []);
        $string = 'https://third.second.com/TEST/Something/';

        $url = Api::createBaseUrl(
            ['baseUrl' => $string],
            $config
        );

        self::assertEquals($string, $url);
    }

    public function testCreateBaseUrlFunction()
    {
        $config = new Config('testApp', 'testCfg', []);
        $config->setAttributes(['domain' => 'keboola']);
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://',
                (object) ['attr' => 'domain'],
                '.example.com/'
            ]
        ];

        $url = Api::createBaseUrl(
            ['baseUrl' => $fn],
            $config
        );

        self::assertEquals('https://keboola.example.com/', $url);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     */
    public function testCreateBaseUrlFunctionError()
    {
        $config = new Config('testApp', 'testCfg', []);
        $fn = [
            'function' => 'concat',
            'args' => [
                'https://keboola.com/',
                (object) ['attr' => 'path']
            ]
        ];

        Api::createBaseUrl(
            ['baseUrl' => $fn],
            $config
        );
    }

    public function testCreateAuthQueryDeprecated()
    {
        $config = new Config('testApp', 'testCfg', []);
        $config->setAttributes(['key' => 'val']);

        // Deprecated way
        $api = [
            'authentication' => [
                'type' => 'url.query'
            ],
            'query' => [
                'param' => [
                    'attr' => 'key'
                ]
            ]
        ];

        $queryAuth = Api::createAuth(new NullLogger(), $api, $config, []);

        self::assertEquals($api['query'], self::getProperty($queryAuth, 'query'));
        self::assertEquals($config->getAttributes(), self::getProperty($queryAuth, 'attrs'));
        self::assertInstanceOf(Query::class, $queryAuth);
    }

    public function testCreateAuthQuery()
    {
        $config = new Config('testApp', 'testCfg', []);
        $config->setAttributes(['key' => 'val']);

        $api = [
            'authentication' => [
                'type' => 'query',
                'query' => [
                    'param' => [
                        'attr' => 'key'
                    ]
                ]
            ]
        ];

        $queryAuth = Api::createAuth(new NullLogger(), $api, $config, []);

        self::assertEquals($api['authentication']['query'], self::getProperty($queryAuth, 'query'));
        self::assertEquals($config->getAttributes(), self::getProperty($queryAuth, 'attrs'));
        self::assertInstanceOf(Query::class, $queryAuth);
    }

    public function testCreateAuthOAuth20Bearer()
    {
        $jsonConfig = JsonFile::create(ROOT_PATH . '/tests/data/oauth20bearer/config.json');

        $config = new Config('testApp', 'testCfg', []);

        $api = $jsonConfig->get('parameters', 'api');

        $authorization = $jsonConfig->get('authorization');

        $oauth = Api::createAuth(new NullLogger(), $api, $config, $authorization);
        self::assertInstanceOf(OAuth20::class, $oauth);
    }

    public function testCreateOauth2Login()
    {
        $jsonConfig = JsonFile::create(ROOT_PATH . '/tests/data/oauth20login/config.json');

        $config = new Config('testApp', 'testCfg', []);

        $api = $jsonConfig->get('parameters', 'api');

        $authorization = $jsonConfig->get('authorization');

        $oauth = Api::createAuth(new NullLogger(), $api, $config, $authorization);
        self::assertInstanceOf(OAuth20Login::class, $oauth);
    }
}
