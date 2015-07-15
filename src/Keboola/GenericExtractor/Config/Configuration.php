<?php

namespace Keboola\GenericExtractor\Config;

use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;
use	Keboola\Juicer\Config\Configuration as BaseConfiguration,
	Keboola\Juicer\Config\Config,
	Keboola\Juicer\Common\Logger;
use	Keboola\GenericExtractor\Authentication,
	Keboola\GenericExtractor\Pagination;
use	Keboola\Code\Builder;
use	Keboola\Utils\Utils,
	Keboola\Utils\Exception\JsonDecodeException;

// TODO move to parent class with a loader fn
use Symfony\Component\Yaml\Yaml;

/**
 * {@inheritdoc}
 */
class Configuration extends BaseConfiguration
{
	/**
	 * @var Api
	 */
	protected $api;

	/**
	 * @param string $dataDir Path to folder containing config.yml
	 * @return Config
	 */
	public function getConfig($dataDir)
	{
		$config = parent::getConfig($dataDir);

		// TODO check if it exists (have some getter fn in parent Configuration)
		$apiYml = Yaml::parse(file_get_contents($dataDir . "/config.yml"))['api'];
		// TODO init api
		$this->api = new Api([
			'baseUrl' => $this->getBaseUrl($apiYml, $config),
			'auth' => $this->getAuth($apiYml, $config),
			'scroller' => $this->getScroller($apiYml)
		]);

		return $config;
	}

	public function getApi()
	{
		return $this->api;
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
	public function getAuth($api, Config $config)
	{
		if (empty($api['authentication']['type'])) {
			Logger::log("INFO", "Using NO Auth");
			return new Authentication\NoAuth();
		}

		Logger::log("INFO", "Using '{$api['authentication']['type']}' Auth");
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
	 */
	public function getBaseUrl($api, Config $config)
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
	 * @param string $configName
	 * @return array
	 */
	public function getHeaders($configName)
	{
		$bucket = $this->configBucket;
		$tableAttrs = $bucket['items'][$configName];

		if (!empty($bucket['http']['requiredHeaders'])) {
			$requiredHeaders = explode(",", $bucket['http']['requiredHeaders']);
			array_walk($requiredHeaders, function(&$value) {$value = trim($value);});

			foreach($requiredHeaders as $reqHeader) {
				if (empty($tableAttrs['header'][$reqHeader])) {
					throw new UserException("Missing required header {$reqHeader} in configuration table attributes!");
				}
			}
		}

		$tHeaders = empty($tableAttrs['header']) ? [] : $tableAttrs['header'];
		$bHeaders = empty($bucket['http']['header']) ? [] : $bucket['http']['header'];

		return array_replace($bHeaders, $tHeaders);
	}

	/**
	 * Return pagination scoller
	 * @param array $api
	 * @return Pagination\ScrollerInterface
	 * @todo refactor Scrollers to use config arrays
	 */
	public function getScroller($api)
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
}
