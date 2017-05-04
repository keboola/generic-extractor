<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\Api;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Common\Logger;
use Keboola\Temp\Temp;

class GenericExtractorTest extends ExtractorTestCase
{
    /**
     * No change to JSON parser structure should happen when nothing is parsed!
     */
    public function testRunMetadataUpdate()
    {
        $logger = $this->getLogger('test', true);

        Logger::setLogger($logger);

        $meta = [
            'json_parser.struct' => [
                'tickets.via' => ['channel' => 'scalar', 'source' => 'object']
            ],
            'time' => [
                'previousStart' => 123
            ]
        ];

        $cfg = new Config('testApp', 'testCfg', []);
        $api = Api::create(['baseUrl' => 'http://example.com'], $cfg);

        $ex = new GenericExtractor(new Temp);
        $ex->setLogger($logger);
        $ex->setApi($api);

        $ex->setMetadata($meta);
        $ex->run($cfg);
        $after = $ex->getMetadata();

        self::assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
        self::assertArrayHasKey('time', $after);
    }

    public function testGetParser()
    {
        $temp = new Temp;
        $parser = Json::create(new Config('testApp', 'testCfg', []), $this->getLogger(), $temp);

        $extractor = new GenericExtractor($temp);
        $extractor->setParser($parser);
        self::assertEquals($parser, $extractor->getParser());
    }
}
