<?php

namespace Keboola\GenericExtractor;

use	Keboola\Juicer\Extractor\Extractors\JsonExtractor,
	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Exception\ApplicationException;
use	GuzzleHttp\Client;
use	Keboola\GenericExtractor\GenericExtractorJob,
	Keboola\GenericExtractor\Authentication\AuthInterface,
	Keboola\GenericExtractor\Pagination\ScrollerInterface,
	Keboola\GenericExtractor\Config\Api;
use	Keboola\Code\Builder;
use	Keboola\Utils\Utils;

class GenericExtractor extends JsonExtractor
{
	protected $name = "generic";
	protected $prefix = "ex-api";
	/**
	 * @var string
	 */
	protected $baseUrl;
	/**
	 * @var array
	 */
	protected $headers;
	/**
	 * @var ScrollerInterface
	 */
	protected $scroller;
	/**
	 * @var AuthInterface
	 */
	protected $auth;

	public function run(Config $config)
	{
		/**
		 * @var Client
		 */
		$client = new Client(
			[
				"base_url" => $this->baseUrl,
// 				"defaults" => $this->getClientDefaults()
			]
		);
		$client->setDefaultOption('headers', $this->headers);
		$client->getEmitter()->attach($this->getBackoff());

		$this->auth->authenticateClient($client);

		$parser = $this->getParser($config);
		$parser->setAllowArrayStringMix(true);

		$builder = new Builder();

		$this->metadata['time']['previousStart'] = empty($this->metadata['time']['previousStart']) ? 0 : $this->metadata['time']['previousStart'];
		$this->metadata['time']['currentStart'] = time();

		foreach($config->getJobs() as $jobConfig) {
			$job = new GenericExtractorJob($jobConfig, $client, $parser);
			$job->setScroller($this->scroller);
			$job->setAttributes($config->getAttributes());
			$job->setMetadata($this->metadata);
			$job->setBuilder($builder);

			$job->run();
		}

		$this->metadata['time']['previousStart'] = $this->metadata['time']['currentStart'];
		unset($this->metadata['time']['currentStart']);

		$this->updateParserMetadata($parser);

		return $parser->getCsvFiles();
	}

	public function setAppName($api)
	{
		$this->name = $api;
	}

	/**
	 * @param Api $api
	 */
	public function setApi(Api $api)
	{
		$this->setBaseUrl($api->getBaseUrl());
		$this->setAuth($api->getAuth());
		$this->setScroller($api->getScroller());
		$this->setHeaders($api->getHeaders()->getHeaders());
		$this->setAppName($api->getName());
	}

	/**
	 * Get base URL from Config
	 * @param string $url
	 */
	public function setBaseUrl($url)
	{
		$this->baseUrl = $url;
	}

	/**
	 * @param AuthInterface $auth
	 */
	public function setAuth(AuthInterface $auth)
	{
		$this->auth = $auth;
	}

	/**
	 * @param array $headers
	 */
	public function setHeaders(array $headers)
	{
		$this->headers = $headers;
	}

	/**
	 * @param ScrollerInterface $scroller
	 */
	public function setScroller(ScrollerInterface $scroller)
	{
		$this->scroller = $scroller;
	}
}
