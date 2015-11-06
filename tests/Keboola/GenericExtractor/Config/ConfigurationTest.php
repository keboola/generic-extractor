<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\Configuration;
use Keboola\Temp\Temp;
use Keboola\Juicer\Filesystem\YamlFile;

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

        $modulesCfg = YamlFile::create(ROOT_PATH . '/config/modules.yml')->getData();

        foreach($modulesCfg as $moduleCfg) {
            self::assertInstanceOf($moduleCfg['class'], $modules[$moduleCfg['type']][$moduleCfg['level']]);
        }
    }
}
