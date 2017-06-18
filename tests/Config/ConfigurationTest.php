<?php

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Config\Configuration;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Filesystem\JsonFile;
use Keboola\Temp\Temp;

class ConfigurationTest extends ExtractorTestCase
{
    public function testCreateModule()
    {
        $config = [
            'class' => 'Keboola\ExGenericModule\FindResponseArray',
            'type' => 'response',
            'level' => 100
        ];

        $configuration = new Configuration('./Tests/data/getPost', 'test', new Temp);

        $module = self::callMethod($configuration, 'createModule', [$config]);

        self::assertInstanceOf($config['class'], $module['class']);
    }

    public function testGetModules()
    {
        $configuration = new Configuration('./Tests/data/getPost', 'test', new Temp);

        $modules = $configuration->getModules();

        $modulesCfg = JsonFile::create(ROOT_PATH . '/config/modules.json')->getData();

        foreach ($modulesCfg as $moduleCfg) {
            self::assertInstanceOf($moduleCfg['class'], $modules[$moduleCfg['type']][$moduleCfg['level']]);
        }
    }
}
