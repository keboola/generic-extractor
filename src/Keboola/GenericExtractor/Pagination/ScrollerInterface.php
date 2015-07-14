<?php
namespace Keboola\GenericExtractor\Pagination;

interface ScrollerInterface
{
	/**
	 * @param mixed $response Can be either array for offset method, or object for param
	 * @param array $data
	 * @param string $endpoint
	 * @param array $params
	 * @return string|false
	 */
	public function getNextPageUrl($response, $data, $endpoint, array $params = null);

	/**
	 * Reset the pageination pointer
	 */
	public function reset();
}
