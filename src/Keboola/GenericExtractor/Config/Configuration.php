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

    /**
     * @return ModuleInterface[]
     */
    public function getModules()
    {
        // load from where? Probably shouldn't be part of the config
        // module should be added by composer in dockerfile,
        // its configuration probably registered by some post install script
        // does a post-install script get executed by composer require?
    }
}
