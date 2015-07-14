<?php

namespace Keboola\GenericExtractor\Pagination;

use	Keboola\Utils\Utils;

/**
 * Scrolls using simple "limit" and "offset" query parameters.
 * Limit can be overriden in job's config's query parameters
 * and it will be used instead of extractor's default
 */
class OffsetScroller implements ScrollerInterface
{
	/**
	 * @var int
	 */
	protected $limit;
	/**
	 * @var string
	 */
	protected $limitParam;
	/**
	 * @var string
	 */
	protected $offsetParam;
	/**
	 * @var int
	 */
	protected $pointer = 0;

	public function __construct($limit, $limitParam = 'limit', $offsetParam = 'offset')
	{
		$this->limit = $limit;
		$this->limitParam = $limitParam;
		$this->offsetParam = $offsetParam;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNextPageUrl($response, $data, $endpoint, array $params = [])
	{
		$limit = empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];

		if (count($data) < $limit) {
			$this->reset();
			return false;
		} else {
			$this->pointer += $limit;
			return Utils::buildUrl($endpoint, array_replace($params, [
				$this->limitParam => $limit,
				$this->offsetParam => $this->pointer
			]));
		}
	}

	public function reset()
	{
		$this->pointer = 0;
	}

}
