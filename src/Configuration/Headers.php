<?php

namespace Keboola\GenericExtractor\Configuration;

use Keboola\GenericExtractor\Exception\UserException;

/**
 * API Headers wrapper
 */
class Headers
{
    /**
     * @var array
     */
    private $apiHeaders = [];

    /**
     * @var array
     */
    private $configHeaders = [];

    /**
     * @var array
     */
    private $requiredHeaders = [];

    /**
     * Headers constructor.
     * @param array $api
     * @param array $configAttributes
     */
    public function __construct(array $api, array $configAttributes)
    {
        if (!empty($api['http']['headers']) && is_array($api['http']['headers'])) {
            $this->apiHeaders = $api['http']['headers'];
        }
        if (!empty($api['http']['requiredHeaders']) && is_array($api['http']['requiredHeaders'])) {
            $this->requiredHeaders = $api['http']['requiredHeaders'];
        }

        $this->loadConfig($configAttributes);
    }

    /**
     * @param array $configAttributes
     * @throws UserException
     */
    private function loadConfig(array $configAttributes)
    {
        if (!empty($configAttributes['http']['headers']) && is_array($configAttributes['http']['headers'])) {
            $configHeaders = $configAttributes['http']['headers'];
        } else {
            $configHeaders = [];
        }

        if (!empty($this->requiredHeaders)) {
            foreach ($this->requiredHeaders as $rHeader) {
                if (empty($configHeaders[$rHeader])) {
                    throw new UserException("Missing required header {$rHeader} in 'config.http.headers'.");
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
