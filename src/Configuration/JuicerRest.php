<?php

namespace Keboola\GenericExtractor\Configuration;

class JuicerRest
{
    /**
     * Convert structure of config from Juicer 8 => 9
     */
    public static function convertRetry(array $config): array
    {
        // TODO: add deprecation
        if (isset($config['curlCodes'])) {
            $config['curl'] = [];
            $config['curl']['codes'] = $config['curlCodes'];
            unset($config['curlCodes']);
        }

        if (isset($config['headerName']) || isset($config['httpCodes'])) {
            $config['http'] = [];

            if (isset($config['headerName'])) {
                $config['http']['retryHeader'] = $config['headerName'];
                unset($config['headerName']);
            }
            if (isset($config['httpCodes'])) {
                $config['http']['codes'] = $config['httpCodes'];
                unset($config['httpCodes']);
            }
        }

        return $config;
    }
}
