<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\NoDataException;
use Keboola\Juicer\Exception\UserException;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;

/**
 * {@inheritdoc}
 */
class Configuration
{
    /**
     * @var Temp
     */
    protected $temp;

    /**
     * @var string
     */
    protected $dataDir;

    /**
     * @var []
     */
    protected $jsonFiles;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    const CACHE_TTL = 604800;

    /**
     * Configuration constructor.
     * @param $dataDir
     * @param Temp $temp
     * @param LoggerInterface $logger
     */
    public function __construct($dataDir, Temp $temp, LoggerInterface $logger)
    {
        $this->temp = $temp;
        $this->dataDir = $dataDir;
        $this->logger = $logger;
    }

    /**
     * @return Config[]
     */
    public function getMultipleConfigs()
    {
        try {
            $iterations = $this->getJSON('/config.json', 'parameters', 'iterations');
        } catch (NoDataException $e) {
            $iterations = [null];
        }

        $configs = [];
        foreach ($iterations as $params) {
            $configs[] = $this->getConfig($params);
        }

        return $configs;
    }

    /**
     * @param array $params Values to override in the config
     * @return Config
     * @throws UserException
     */
    public function getConfig(array $params = null)
    {
        try {
            $configJson = $this->getJSON('/config.json', 'parameters', 'config');
        } catch (NoDataException $e) {
            throw new UserException($e->getMessage(), 0, $e);
        }

        if (!is_null($params)) {
            $configJson = array_replace($configJson, $params);
        }

        $configName = empty($configJson['id']) ? '' : $configJson['id'];
        $runtimeParams = []; // TODO get runtime params from console

        if (empty($configJson['jobs'])) {
            throw new UserException("No 'jobs' specified in the config!");
        }

        $jobs = $configJson['jobs'];
        $jobConfigs = [];
        foreach ($jobs as $job) {
            $jobConfig = $this->createJob($job);
            $jobConfigs[$jobConfig->getJobId()] = $jobConfig;
        }
        unset($configJson['jobs']); // weird

        $config = new Config($configName, $runtimeParams);
        $config->setJobs($jobConfigs);
        $config->setAttributes($configJson);

        return $config;
    }

    /**
     * @param object $job
     * @return JobConfig
     * @throws UserException
     */
    protected function createJob($job)
    {
        if (!is_array($job)) {
            throw new UserException("Invalid format for job configuration.", 0, null, ['job' => $job]);
        }

        return JobConfig::create($job);
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return json_decode(file_get_contents($this->dataDir . "/in/state.json"), true);
    }

    public function saveConfigMetadata(array $data)
    {
        $dirPath = $this->dataDir . '/out';

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0700, true);
        }

        file_put_contents($dirPath . '/state.json', json_encode($data));
    }


    /**
     * @param string $filePath
     * @return mixed
     */
    protected function getJSON($filePath)
    {
        $path = func_get_args();
        $filePath = array_shift($path);

        if (empty($this->jsonFiles[$filePath])) {
            $this->jsonFiles[$filePath] = json_decode(file_get_contents($this->dataDir . $filePath), true);
        }

        return call_user_func_array([$this->jsonFiles[$filePath], 'get'], $path);
    }

    /**
     * @param Table[] $csvFiles
     * @param string $bucketName
     * @param bool $sapiPrefix whether to prefix the output bucket with "in.c-"
     * @param bool $incremental Set the incremental flag in manifest
     */
    public function storeResults(array $csvFiles, $bucketName = null, $sapiPrefix = true, $incremental = false)
    {
        $path = "{$this->dataDir}/out/tables/";

        if (!is_null($bucketName)) {
            $path .= $bucketName . '/';
            $bucketName = $sapiPrefix ? 'in.c-' . $bucketName : $bucketName;
        }

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
            chown($path, fileowner("{$this->dataDir}/out/tables/"));
            chgrp($path, filegroup("{$this->dataDir}/out/tables/"));
        }

        foreach ($csvFiles as $key => $file) {
            $manifest = [];

            if (!is_null($bucketName)) {
                $manifest['destination'] = "{$bucketName}.{$key}";
            }

            $manifest['incremental'] = is_null($file->getIncremental())
                ? $incremental
                : $file->getIncremental();

            if (!empty($file->getPrimaryKey())) {
                $manifest['primary_key'] = $file->getPrimaryKey(true);
            }

            file_put_contents($path . $key . '.manifest', json_encode($manifest));
            copy($file->getPathname(), $path . $key);
        }
    }

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
        return Api::create($this->logger, $this->getJSON('/config.json', 'parameters', 'api'), $config, $authorization);
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
}
