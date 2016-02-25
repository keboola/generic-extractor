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

    public function testCreateAuthQuery()
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
}
