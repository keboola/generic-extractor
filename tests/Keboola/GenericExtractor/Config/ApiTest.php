<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\Api;
use Keboola\Juicer\Config\Config;

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

        $this->assertEquals($string, $url);
    }

    // TODO JSON, array (and object?)

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

        $queryAuth = Api::createAuth($api, $config, []);

        self::assertEquals($api['query'], self::getProperty($queryAuth, 'query'));
        self::assertEquals($config->getAttributes(), self::getProperty($queryAuth, 'attrs'));
        self::assertInstanceOf('\Keboola\GenericExtractor\Authentication\Query', $queryAuth);
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

        $queryAuth = Api::createAuth($api, $config, []);

        self::assertEquals($api['authentication']['query'], self::getProperty($queryAuth, 'query'));
        self::assertEquals($config->getAttributes(), self::getProperty($queryAuth, 'attrs'));
        self::assertInstanceOf('\Keboola\GenericExtractor\Authentication\Query', $queryAuth);
    }

    public function testCreateAuthOAuth20Bearer()
    {
        $ymlConfig = YamlFile::create(ROOT_PATH . '/tests/data/oauth20bearer/config.yml');

        $config = new Config('testApp', 'testCfg', []);
        $config->setAttributes(['key' => 'val']);

        $api = $ymlConfig->get('parameters', 'api', 'authentication');

        $authorization = $ymlConfig->get('authorization');

        $oauth = Api::createAuth($api, $config, $authorization);
//         self::assertEquals($authorization['oauth_api']['credentials']['#token'], self::getProperty($oauth, 'token'));
    }
}
