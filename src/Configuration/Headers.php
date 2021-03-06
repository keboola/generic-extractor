<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration;

use Keboola\GenericExtractor\Exception\UserException;

/**
 * API Headers wrapper
 */
class Headers
{
    private array $apiHeaders = [];

    private array $configHeaders = [];

    private array $requiredHeaders = [];

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

    private function loadConfig(array $configAttributes): void
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

    public function getHeaders(): array
    {
        return array_replace($this->apiHeaders, $this->configHeaders);
    }
}
