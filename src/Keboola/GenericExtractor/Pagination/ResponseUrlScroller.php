<?php

namespace Keboola\GenericExtractor\Pagination;

use	Keboola\Utils\Utils;

/**
 * Scrolls using URL or Endpoint within page's response.
 *
 *
 */
class ResponseUrlScroller implements ScrollerInterface
{
	/**
	 * @var string
	 */
	protected $urlParam;

	/**
	 * @var bool
	 */
	protected $includeParams;

	public function __construct($urlParam = 'next_page', $includeParams = false)
	{
		$this->urlParam = $urlParam;
		$this->includeParams = $includeParams;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getNextPageUrl($response, $data, $endpoint, array $params = [])
	{
		if (empty($response->{$this->urlParam})) {
			return false;
		} else {
			return $this->includeParams
				? Utils::buildUrl($response->{$this->urlParam}, $params)
				: $response->{$this->urlParam};
		}
	}

	public function reset() {}
}
