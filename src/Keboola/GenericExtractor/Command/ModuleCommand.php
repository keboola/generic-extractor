<?php

namespace Keboola\GenericExtractor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Keboola\Juicer\Filesystem\YamlFile;

class ModuleCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('gex:module')
            ->setDescription('Manage ex-generic-v2 modules')
            ->addArgument(
                'action',
                InputArgument::REQUIRED
            )
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Path to module\'s config file'
            );
            // TODO add options to override config values
            // only ADD is supported now, remove doesn't need config file!
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');

        $class = $this->addModule($input->getArgument('path'));
        $output->writeln("Done. {$class} added.");
    }

    /**
     * @param string $path
     * @return string class name.
     */
    protected function addModule($path)
    {
        $config = YamlFile::create($path)->getData();
        // TODO check for type&class
        $modulesYml = YamlFile::create(__DIR__ . '/config/modules.yml', 'w');

        $modules = $modulesYml->getData();
        $modules[] = $config;

        $modulesYml->setData($modules);
        $modulesYml->save();

        return $config['class'];
    }
}
