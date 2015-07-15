<?php

namespace Keboola\GenericExtractor\Config;

use	Keboola\GenericExtractor\Authentication\AuthInterface,
	Keboola\GenericExtractor\Pagination\ScrollerInterface;

/**
 * API Description
 */
class Api
{
	/**
	 * @var string
	 */
	protected $baseUrl;

	/**
	 * @var AuthInterface
	 */
	protected $auth;

	/**
	 * @var ScrollerInterface
	 */
	protected $scroller;

	public function __construct(array $config)
	{
		if (!empty($config['baseUrl'])) {
			$this->setBaseUrl($config['baseUrl']);
		}
		if (!empty($config['scroller'])) {
			$this->setScroller($config['scroller']);
		}
		if (!empty($config['auth'])) {
			$this->setAuth($config['auth']);
		}
	}

	public function setBaseUrl($url)
	{
		$this->baseUrl = $url;
	}

	public function getBaseUrl()
	{
		return $this->baseUrl;
	}

	public function setScroller(ScrollerInterface $scroller)
	{
		$this->scroller = $scroller;
	}

	public function getScroller()
	{
		return $this->scroller;
	}

	public function setAuth(AuthInterface $auth)
	{
		$this->auth = $auth;
	}

	public function getAuth()
	{
		return $this->auth;
	}
}
