<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Config\Configuration as BaseConfiguration;

/**
 * {@inheritdoc}
 */
class Configuration extends BaseConfiguration
{
    public function getApi($config)
    {
        // TODO check if it exists (have some getter fn in parent Configuration)
        return Api::create($this->getYmlConfig()['parameters']['api'], $config);
    }
}
