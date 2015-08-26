<?php
namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\GenericExtractor,
	Keboola\GenericExtractor\Config\Api;
use	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Parser\Json,
	Keboola\Juicer\Common\Logger;
use	Keboola\Temp\Temp;
// use	GuzzleHttp\Client;

class GenericExtractorTest extends ExtractorTestCase
{
	/**
	 * No change to JSON parser structure should happen when nothing is parsed!
	 */
	public function testRunMetadataUpdate()
	{
		Logger::setLogger($this->getLogger('test', true));

		$meta = ['json_parser.struct' => ['tickets.via' => ['channel' => 'string', 'source' => 'object']]];

		$cfg = new Config('testApp', 'testCfg', []);
		$api = Api::create(['baseUrl' => 'http://example.com'], $cfg);

		$ex = new GenericExtractor(new Temp);
		$ex->setApi($api);

		$ex->setMetadata($meta);
		$ex->run($cfg);
		$after = $ex->getMetadata();

		$this->assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
		$this->assertArrayHasKey('time', $after);
		$this->assertArrayHasKey('previousStart', $after['time']);
	}
}
