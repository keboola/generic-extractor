<?php

namespace Keboola\GenericExtractor\Config;

use	Keboola\GenericExtractor\Authentication\AuthInterface,
	Keboola\GenericExtractor\Authentication;
use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Pagination\ScrollerInterface,
	Keboola\Juicer\Pagination,
	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Exception\UserException,
	Keboola\Juicer\Common\Logger;
use	Keboola\Code\Builder;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;

/**
 * API Description
 * @todo TEST
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

	public static function create(array $api, Config $config)
	{
		return new static([
			'baseUrl' => self::createBaseUrl($api, $config),
			'auth' => self::createAuth($api, $config),
			'scroller' => self::createScroller($api),
			'headers' => self::createHeaders($api, $config),
			'name' => self::createName($api)
		]);
	}

	/**
	 * Should return a class that contains
	 * - info about what attrs store the Auth keys
	 * - callback method that generates a signature
	 * - OR an array of defaults for the client (possibly using the callback ^^)
	 * - type of the auth method TODO - is that needed?
	 * - Method that accepts GuzzleClient as parameter and adds the emitter/defaults to it
	 *
	 * @param array $api
	 * @param Config $config
	 * @return Authentication\AuthInterface
	 */
	public static function createAuth($api, Config $config)
	{
		if (empty($api['authentication']['type'])) {
			Logger::log("DEBUG", "Using NO Auth");
			return new Authentication\NoAuth();
		}

		Logger::log("DEBUG", "Using '{$api['authentication']['type']}' Auth");
		switch ($api['authentication']['type']) {
			case 'basic':
				return new Authentication\Basic($config->getAttributes());
				break;
			case 'bearer':
				throw new ApplicationException("The bearer method is not implemented yet");
				break;
			case 'url.query':
				if (empty($api['query'])) {
					throw new UserException("The query authentication method requires query parameters to be defined in the configuration bucket attributes.");
				}

				return new Authentication\Query(new Builder(), $config->getAttributes(), $api['query']);
				break;
			default:
				throw new UserException("Unknown authorization type '{$api['authentication']['type']}'");
				break;
		}
	}

	/**
	 * @param array $api
	 * @param Config $config
	 * @return string
	 * @todo Allow storing URL function as an actual object, not a JSON
	 */
	public static function createBaseUrl($api, Config $config)
	{
		if (empty($api['baseUrl'])) {
			throw new UserException("The 'baseUrl' attribute must be set in API configuration");
		}

		if (filter_var($api['baseUrl'], FILTER_VALIDATE_URL)) {
			return $api['baseUrl'];
		} else {
			try {
				$fn = Utils::json_decode($api['baseUrl']);
			} catch(JsonDecodeException $e) {
				throw new UserException("The 'baseUrl' attribute in API configuration is not an URL string, neither a valid JSON containing an user function! Error: " . $e->getMessage(), $e);
			}
			return (new Builder())->run($fn, ['attr' => $config->getAttributes()]);
		}
	}

	/**
	 * Return pagination scoller
	 * @param array $api
	 * @return Pagination\ScrollerInterface
	 * @todo refactor Scrollers to use config arrays
	 */
	public static function createScroller($api)
	{
		if (empty($api['pagination']) || empty($api['pagination']['method'])) {
			return Pagination\NoScroller::create([]);
		}
		$pagination = $api['pagination'];

		switch ($pagination['method']) {
			case 'offset':
				return Pagination\OffsetScroller::create($pagination);
				break;
			case 'response.param':
				throw new ApplicationException("Pagination by param Not yet implemented", 501);
				break;
			case 'response.url':
				return Pagination\ResponseUrlScroller::create($pagination);
				break;
			case 'pagenum':
				return Pagination\PageScroller::create($pagination);
				break;
			default:
				throw new UserException("Unknown pagination method '{$pagination['method']}'");
				break;
		}
	}

	/**
	 * @param array $api
	 * @param Config $config
	 * @return array
	 */
	public static function createHeaders($api, Config $config)
	{
		return Headers::create($api, $config);
	}

	/**
	 * @param array $api
	 */
	public static function createName($api)
	{
		return empty($api['name']) ? 'generic' : $api['name'];
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
