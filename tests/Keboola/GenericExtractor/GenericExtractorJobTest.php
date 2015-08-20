<?php
namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\GenericExtractorJob;
use	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Pagination\ResponseUrlScroller,
	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Parser\Json;
use	Keboola\Temp\Temp;
// use	GuzzleHttp\Client;

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
		$job = $this->getJob($cfg);

		$req = self::callMethod($job, 'firstPage', [$cfg]);
		$this->assertEquals('ep', $req->getEndpoint());
	}

	public function testNextPage()
	{
		$cfg = JobConfig::create([
			'endpoint' => 'ep',
			'params' => [
				'first' => 1
			]
		]);
		$job = $this->getJob($cfg);

		$job->setScroller(new ResponseUrlScroller('nextPage'));

		$response = new \stdClass();
		$response->nextPage = "http://example.com/api/ep?something=2";
		$response->results = [1, 2];

		$req = self::callMethod($job, 'nextPage', [
			$cfg,
			$response,
			$response->results
		]);

		$this->assertEquals('http://example.com/api/ep?something=2', $req->getEndpoint());
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
		$job = $this->getJob($cfg);

		$job->setScroller(new ResponseUrlScroller('nextPage', true));

		$response = new \stdClass();
		$response->nextPage = "http://example.com/api/ep?something=2";
		$response->results = [1, 2];

		$req = self::callMethod($job, 'nextPage', [
			$cfg,
			$response,
			$response->results
		]);

		$this->assertEquals('http://example.com/api/ep?something=2', $req->getEndpoint());
	}

	protected function getJob(JobConfig $config)
	{
		return new GenericExtractorJob(
			$config,
			RestClient::create([
				'base_url' => 'http://example.com/api/'
			]),
			Json::create(
				new Config('ex-generic-test', 'test', []),
				$this->getLogger(),
				new Temp()
			)
		);
	}
}
