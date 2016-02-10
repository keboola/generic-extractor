<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Config\Config,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException;
/**
 * API Headers wrapper
 */
class Headers
{
    /**
     * @var array
     */
    protected $apiHeaders = [];

    /**
     * @var array
     */
    protected $configHeaders = [];

    /**
     * @var array
     */
    protected $requiredHeaders = [];

    public function __construct(array $apiHeaders = [], array $requiredHeaders = [])
    {
        $this->apiHeaders = $apiHeaders;
        $this->requiredHeaders = $requiredHeaders;
    }

    /**
     * @param array $api
     * @param Config $config
     * @return self
     */
    public static function create(array $api, Config $config)
    {
        $headers = new self(
            empty($api['http']['headers']) ? [] : $api['http']['headers'],
            empty($api['http']['requiredHeaders']) ? [] : $api['http']['requiredHeaders']
        );

        $headers->loadConfig($config);

        return $headers;
    }

    /**
     * @param Config $config
     */
    public function loadConfig(Config $config)
    {
        $attrs = $config->getAttributes();
        $configHeaders = empty($attrs['http']['headers']) ? [] : $attrs['http']['headers'];

        if (!empty($this->requiredHeaders)) {
            foreach($this->requiredHeaders as $rHeader) {
                if (empty($configHeaders[$rHeader])) {
                    throw new UserException("Missing required header {$rHeader} in config.http.headers!");
                }
            }
        }

        $this->configHeaders = $configHeaders;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return array_replace($this->apiHeaders, $this->configHeaders);
    }
}
