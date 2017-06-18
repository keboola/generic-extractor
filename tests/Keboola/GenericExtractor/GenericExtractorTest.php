<?php

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\Api;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use Psr\Log\NullLogger;

class GenericExtractorTest extends ExtractorTestCase
{
    /**
     * No change to JSON parser structure should happen when nothing is parsed!
     */
    public function testRunMetadataUpdate()
    {
        $meta = [
            'json_parser.struct' => [
                'tickets.via' => ['channel' => 'scalar', 'source' => 'object']
            ],
            'time' => [
                'previousStart' => 123
            ]
        ];

        $cfg = new Config('testApp', 'testCfg', []);
        $api = Api::create(new NullLogger(), ['baseUrl' => 'http://example.com'], $cfg);

        $ex = new GenericExtractor(new Temp(), new NullLogger());
        $ex->setApi($api);

        $ex->setMetadata($meta);
        $ex->run($cfg);
        $after = $ex->getMetadata();

        self::assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
        self::assertArrayHasKey('time', $after);
    }

    public function testGetParser()
    {
        $temp = new Temp();
        $parser = Json::create(new Config('testApp', 'testCfg', []), new NullLogger(), $temp);

        $extractor = new GenericExtractor($temp, new NullLogger());
        $extractor->setParser($parser);
        self::assertEquals($parser, $extractor->getParser());
    }
}
