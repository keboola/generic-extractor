<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Config\Configuration as BaseConfiguration,
    Keboola\Juicer\Filesystem\YamlFile,
    Keboola\Juicer\Exception\FileNotFoundException,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\NoDataException;

/**
 * {@inheritdoc}
 */
class Configuration extends BaseConfiguration
{
    const CACHE_TTL = 604800;

    /**
     * @return array
     */
    public function getCache()
    {
        try {
            return $this->getYaml('/config.yml', 'parameters', 'cache');
        } catch(NoDataException $e) {
            return [];
        }
    }

    /**
     * @return string
     */
    public function getDataDir()
    {
        return $this->dataDir;
    }

    /**
     * @param $config
     * @param $authorization
     * @return Api
     */
    public function getApi($config, $authorization)
    {
        // TODO check if it exists (have some getter fn in parent Configuration)
        return Api::create($this->getYaml('/config.yml', 'parameters', 'api'), $config, $authorization);
    }

    /**
     * @return array
     */
    public function getAuthorization()
    {
        try {
            return $this->getYaml('/config.yml', 'authorization');
        } catch(NoDataException $e) {
            return [];
        }
    }

    /**
     * @return $modules
     * @throws ApplicationException
     * @todo 'tis flawed - the path shouldn't be hardcoded for tests
     */
    public function getModules()
    {
        $modules = ['response' => []];

        try {
            $modulesCfg = YamlFile::create(ROOT_PATH . '/config/modules.yml')->getData();
        } catch(FileNotFoundException $e) {
            $modulesCfg = [];
        }

        foreach($modulesCfg as $moduleCfg) {
            $module = $this->createModule($moduleCfg);
            if (isset($modules[$module['type']][$module['level']])) {
                throw new ApplicationException(
                    "Multiple modules cannot share the same 'level'",
                    0,
                    null,
                    [
                        'newModule' => $moduleCfg['class'],
                        'existingModule' => gettype($modules[$module['type']][$module['level']])
                    ]
                );
            }

            $modules[$module['type']][$module['level']] = $module['class'];
        }

        foreach($modules as $type => &$typeModules) {
            ksort($typeModules);
        }

        return $modules;
    }

    /**
     * @param $config
     * @return array
     * @throws ApplicationException
     */
    protected function createModule($config)
    {
        if (empty($config['type'])) {
            throw new ApplicationException("Module 'type' not set!");
        }

        if (!isset($config['level'])) {
            $config['level'] = 9999999;
        }

        if (!class_exists($config['class'])) {
            throw new ApplicationException("Class '{$config['class']}' not found!");
        }

        return [
            'class' => new $config['class'](isset($config['config']) ? $config['config'] : null),
            'type' => $config['type'],
            'level' => $config['level']
        ];
    }
}
