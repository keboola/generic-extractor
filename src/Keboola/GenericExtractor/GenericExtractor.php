<?php

namespace Keboola\GenericExtractor;

use	Keboola\Juicer\Extractor\Extractor,
	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Client\RestClient,
	Keboola\Juicer\Parser\Json,
	Keboola\Juicer\Pagination\ScrollerInterface,
	Keboola\Juicer\Exception\ApplicationException;
// use	GuzzleHttp\Client;
use	Keboola\GenericExtractor\GenericExtractorJob,
	Keboola\GenericExtractor\Authentication\AuthInterface,
	Keboola\GenericExtractor\Config\Api,
	Keboola\GenericExtractor\Subscriber\LogRequest;
use	Keboola\Code\Builder;
use	Keboola\Utils\Utils;

class GenericExtractor extends Extractor
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
		$client = RestClient::create([
			'base_url' => $this->baseUrl,
			'defaults' => [
				'headers' => $this->headers
			]
		]);

		$this->auth->authenticateClient($client->getClient());
		// Verbose Logging of all requests
		$client->getClient()->getEmitter()->attach(new LogRequest);

		$parser = Json::create($config, $this->getLogger(), $this->getTemp(), $this->metadata);
		$parser->getParser()->setAllowArrayStringMix(true);

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

		$this->metadata = array_replace_recursive($this->metadata, $parser->getMetadata());

		return $parser->getResults();
	}

	/**
	 * @param string $name
	 */
	public function setAppName($name)
	{
		$this->name = $name;
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
