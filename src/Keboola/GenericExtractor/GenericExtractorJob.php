<?php

namespace Keboola\GenericExtractor;

use	Keboola\Juicer\Extractor\RecursiveJob,
	Keboola\Juicer\Config\JobConfig,
	Keboola\Juicer\Common\Logger,
	Keboola\Juicer\Client\RequestInterface,
	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;
use	Keboola\GenericExtractor\Pagination\ScrollerInterface;
use	Keboola\Code\Builder,
	Keboola\Code\Exception\UserScriptException;

class GenericExtractorJob extends RecursiveJob
{
// 	protected $configName;
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
	 * @var array
	 */
	protected $metadata;
	/**
	 * @var string
	 */
	protected $lastResponseHash;
	/**
	 * @var Builder
	 */
	protected $stringBuilder;
	/**
	 * Data to append to the root result
	 * @var string|array
	 */
	protected $userParentId;

	/**
	 * {@inheritdoc}
	 * Verify the latest response isn't identical as the last one
	 * to prevent infinite loop on awkward pagination APIs
	 */
	public function run()
	{
		$this->buildParams($this->config);

		$request = $this->firstPage($this->config);
		while ($request !== false) {
			$response = $this->download($request);

			$responseHash = sha1(serialize($response));
			if ($responseHash == $this->lastResponseHash) {
				Logger::log("DEBUG", sprintf("Job '%s' finished when last response matched the previous!", $this->getJobId()));
				$this->scroller->reset();
				break;
			} else {
				$data = $this->parse($response, $this->userParentId);

				$this->lastResponseHash = $responseHash;
			}

			$request = $this->nextPage($this->config, $response, $data);
		}
	}

	/**
	 * @param JobConfig $config
	 * @return array
	 */
	protected function buildParams(JobConfig $config)
	{
		$params = (array) Utils::json_decode(json_encode($config->getParams()));
		try {
			array_walk($params, function(&$value, $key) {
				$value = !is_object($value) ? $value : $this->stringBuilder->run($value, [
					'attr' => $this->attributes,
					'time' => $this->metadata['time']
				]);
			});
		} catch(UserScriptException $e) {
			throw new UserException('User script error: ' . $e->getMessage());
		}

		$config->setParams($params);

		return $params;
	}

	/**
	 * Inject $scroller into a child job
	 * {@inheritdoc}
	 */
	protected function createChild(JobConfig $config, array $parentResults)
	{
		$job = parent::createChild($config, $parentResults);
		$scroller = clone $this->scroller;
		$scroller->reset();
		$job->setScroller($scroller);
		$job->setMetadata($this->metadata);
		$job->setAttributes($this->attributes);
		$job->setBuilder($this->stringBuilder);
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

	/**
	 * @param array $metadata
	 */
	public function setMetadata(array $metadata)
	{
		$this->metadata = $metadata;
	}

	public function setUserParentId($id)
	{
		if (!is_array($id)) {
			throw new UserException("User defined parent ID must be a key:value pair, or multiple such pairs.", 0, null, ["id" => $id]);
		}

		$this->userParentId = $id;
	}
}
