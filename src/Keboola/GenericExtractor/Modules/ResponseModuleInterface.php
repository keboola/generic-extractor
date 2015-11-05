<?php
namespace Keboola\GenericExtractor\Modules;

use Keboola\Juicer\Config\JobConfig;

interface ResponseModuleInterface
{
    public function process($response, JobConfig $jobConfig);
}
