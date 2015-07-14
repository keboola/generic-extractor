<?php

namespace Keboola\GenericExtractor\Pagination;

use	Keboola\Utils\Utils;

/**
 * Scrolls using simple "limit" and "page" query parameters.
 *
 * Limit can be overriden in job's config's query parameters
 * and it will be used instead of extractor's default.
 * Pagination will stop if an empty response is received,
 * or when $limit is set and
 */
class PageScroller implements ScrollerInterface
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
	protected $pageParam;
	/**
	 * @var int
	 */
	protected $page;
	/**
	 * @var int
	 */
	protected $firstPage;


	public function __construct($pageParam = 'page', $limit = null, $limitParam = 'limit', $firstPage = 1)
	{
		$this->pageParam = $pageParam;
		$this->limit = $limit;
		$this->limitParam = $limitParam;
		$this->firstPage = $this->page = $firstPage;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNextPageUrl($response, $data, $endpoint, array $params = [])
	{
		$limit = empty($params[$this->limitParam]) ? $this->limit : $params[$this->limitParam];

		if ((is_null($limit) && empty($data)) || (count($data) < $limit)) {
			$this->reset();
			return false;
		} else {
			$this->page++;

			if (!empty($this->limitParam) && !is_null($limit)) {
				$params[$this->limitParam] = $limit;
			}

			return Utils::buildUrl($endpoint, array_replace($params, [
				$this->pageParam => $this->page
			]));
		}
	}

	public function reset()
	{
		$this->page = $this->firstPage;
	}
}
