<?php

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Config\Configuration;
use Keboola\GenericExtractor\Response\FindResponseArray;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Filesystem\JsonFile;
use Keboola\Temp\Temp;
use Psr\Log\NullLogger;

class ConfigurationTest extends ExtractorTestCase
{
    public function testCreateModule()
    {
        $config = [
            'class' => FindResponseArray::class,
            'type' => 'response',
            'level' => 100
        ];

        $configuration = new Configuration('./Tests/data/getPost', new Temp(), new NullLogger());

        $module = self::callMethod($configuration, 'createModule', [$config]);

        self::assertInstanceOf($config['class'], $module['class']);
    }

    public function testGetModules()
    {
        $configuration = new Configuration('./Tests/data/getPost', new Temp(), new NullLogger());

        $modules = $configuration->getModules();

        $modulesCfg = JsonFile::create(__DIR__ . '/../../config/modules.json')->getData();

        foreach ($modulesCfg as $moduleCfg) {
            self::assertInstanceOf($moduleCfg['class'], $modules[$moduleCfg['type']][$moduleCfg['level']]);
        }
    }
}
