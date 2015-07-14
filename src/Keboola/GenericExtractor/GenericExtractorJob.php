<?php

namespace Keboola\GenericExtractor;

use	Keboola\Juicer\Extractor\Jobs\JsonRecursiveJob,
	Keboola\Juicer\Common\JobConfig,
	Keboola\Juicer\Common\Logger;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;
use	Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\GenericExtractor\Pagination\ScrollerInterface,
	Keboola\Code\Builder;

class GenericExtractorJob extends JsonRecursiveJob
{
	protected $configName;
	/**
	 * @var array
	 */
	protected $params;
	/**
	 * @var ScrollerInterface
	 */
	protected $scroller;
	/**
	 * @var array
	 */
	protected $attributes;
	/**
	 * @var string
	 */
	protected $lastResponseHash;
	/**
	 * @var Builder
	 */
	protected $stringBuilder;

	/**
	 * {@inheritdoc}
	 * Verify the latest response isn't identical as the last one
	 * to prevent infinite loop on awkward pagination APIs
	 */
	public function run()
	{
		try {
			$this->params = empty($this->config["params"])
				? []
				: Utils::json_decode($this->config["params"]);
		} catch(JsonDecodeException $e) {
			$e = new UserException("Error decoding parameters Json: " . $e->getMessage());
			$e->setData(['params' => $this->config["params"]]);
			throw $e;
		}

		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		$request = $this->firstPage();
		while ($request !== false) {
			$response = $this->download($request);

			$responseHash = sha1(serialize($response));
			if ($responseHash == $this->lastResponseHash) {
				Logger::log("DEBUG", sprintf("Job '%s' finished when last response matched the previous!", $this->getJobId()));
				$this->scroller->reset();
				break;
			} else {
				try {
					$data = $this->parse($response);
				} catch(\Keboola\Json\Exception\JsonParserException $e) {
					throw new UserException(
						"[500] Error parsing response JSON: " . $e->getMessage(),
						$e,
						$e->getData()
					);
				}

				$this->lastResponseHash = $responseHash;
			}

			$request = $this->nextPage($response, $data);
		}
	}

	/**
	 * Return a download request
	 * @todo add replaceDates?
	 *
	 * @return \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		$url = Utils::buildUrl(trim($this->config["endpoint"], "/"), $this->getParams());

		return $this->client->createRequest("GET", $url);
	}

	/**
	 * Return a download request OR false if no next page exists
	 *
	 * @param mixed $response
	 * @param array $data
	 * @return \GuzzleHttp\Message\Request | false
	 */
	protected function nextPage($response, $data)
	{
		$url = $this->scroller->getNextPageUrl($response, $data, $this->config['endpoint'], $this->getParams());

		if (false === $url) {
			return false;
		} else {
			return $this->client->createRequest("GET", $url);
		}
	}

	protected function getParams()
	{
		$params = (array) $this->params;
		array_walk($params, function(&$value, $key){
			$value = is_scalar($value) ? $value : $this->stringBuilder->run($value, ['attr' => $this->attributes]);
		});
		return $params;
	}

	/**
	 * @param ScrollerInterface $scroller
	 */
	public function setScroller(ScrollerInterface $scroller)
	{
		$this->scroller = $scroller;
	}

	/**
	 * Inject $scroller into a child job
	 * {@inheritdoc}
	 */
	protected function createChild(JobConfig $config)
	{
		$job = new static($config, $this->client, $this->parser);
		$scroller = clone $this->scroller;
		$scroller->reset();
		$job->setScroller($scroller);
		return $job;
	}

	/**
	 * @param array $attributes
	 */
	public function setAttributes(array $attributes)
	{
		$this->attributes = $attributes;
	}

	/**
	 * @param Builder $builder
	 */
	public function setBuilder(Builder $builder)
	{
		$this->stringBuilder = $builder;
	}
}
