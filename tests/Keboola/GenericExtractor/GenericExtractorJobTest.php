<?php
namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\GenericExtractorJob,
	Keboola\GenericExtractor\Pagination\ResponseUrlScroller;
use	Keboola\Juicer\Config\JobConfig;
use	GuzzleHttp\Client;

class GenericExtractorJobTest extends ExtractorTestCase
{
	public function testFirstPage()
	{
		$cfg = JobConfig::create([
			'endpoint' => 'ep',
			'params' => [
				'first' => 1
			]
		]);
		$job = new GenericExtractorJob($cfg, new Client([
			'base_url' => 'http://example.com/api/'
		]));

		$req = self::callMethod($job, 'firstPage');
		$this->assertEquals('http://example.com/api/ep', $req->getUrl());
	}

	public function testNextPage()
	{
		$cfg = JobConfig::create([
			'endpoint' => 'ep',
			'params' => [
				'first' => 1
			]
		]);
		$job = new GenericExtractorJob($cfg, new Client([
			'base_url' => 'http://example.com/api/'
		]));

		$job->setScroller(new ResponseUrlScroller('nextPage'));

		$response = new \stdClass();
		$response->nextPage = "http://example.com/api/ep?something=2";
		$response->results = [1, 2];

		$req = self::callMethod($job, 'nextPage', [
			$response,
			$response->results,
			$cfg->getConfig()['endpoint'],
			$cfg->getConfig()['params']
		]);

		$this->assertEquals('http://example.com/api/ep?something=2', $req->getUrl());
	}

	/**
	 * doesn't work because params are set in run!
	 */
	public function testNextPageWithParam()
	{
		$cfg = JobConfig::create([
			'endpoint' => 'ep',
			'params' => [
				'first' => 1
			]
		]);
		$job = new GenericExtractorJob($cfg, new Client([
			'base_url' => 'http://example.com/api/'
		]));

		$job->setScroller(new ResponseUrlScroller('nextPage', true));

		$response = new \stdClass();
		$response->nextPage = "http://example.com/api/ep?something=2";
		$response->results = [1, 2];

		$req = self::callMethod($job, 'nextPage', [
			$response,
			$response->results
		]);

		$this->assertEquals('http://example.com/api/ep?something=2', $req->getUrl());
	}
}
