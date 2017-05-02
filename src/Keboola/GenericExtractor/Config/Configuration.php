<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Config\Configuration as BaseConfiguration;
use Keboola\Juicer\Exception\FileNotFoundException;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Exception\NoDataException;
use Keboola\Juicer\Filesystem\JsonFile;

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
            return $this->getJSON('/config.json', 'parameters', 'cache');
        } catch (NoDataException $e) {
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
        return Api::create($this->getJSON('/config.json', 'parameters', 'api'), $config, $authorization);
    }

    /**
     * @return array
     */
    public function getAuthorization()
    {
        try {
            return $this->getJSON('/config.json', 'authorization');
        } catch (NoDataException $e) {
            return [];
        }
    }

    /**
     * @return array
     * @throws ApplicationException
     * @todo 'tis flawed - the path shouldn't be hardcoded for tests
     */
    public function getModules()
    {
        $modules = ['response' => []];

        try {
            $modulesCfg = JsonFile::create(ROOT_PATH . '/config/modules.json')->getData();
        } catch (FileNotFoundException $e) {
            $modulesCfg = [];
        }

        foreach ($modulesCfg as $moduleCfg) {
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

        foreach ($modules as $type => &$typeModules) {
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
