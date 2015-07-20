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
	 * @var string
	 */
	protected $name;

	/**
	 * @var AuthInterface
	 */
	protected $auth;

	/**
	 * @var ScrollerInterface
	 */
	protected $scroller;

	/**
	 * @var Headers
	 */
	protected $headers;

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
		if (!empty($config['headers'])) {
			$this->setHeaders($config['headers']);
		}
		if (!empty($config['name'])) {
			$this->setName($config['name']);
		}
	}

	public function setName($name)
	{
		$this->name = $name;
	}

	public function getName()
	{
		return $this->name;
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

	public function setHeaders(Headers $headers)
	{
		$this->headers = $headers;
	}

	public function getHeaders()
	{
		return $this->headers;
	}
}
