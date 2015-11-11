#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use Keboola\GenericExtractor\Command\ModuleCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ModuleCommand());
$application->run();
