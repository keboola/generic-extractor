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
}
