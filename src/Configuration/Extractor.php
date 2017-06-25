<?php

namespace Keboola\GenericExtractor\Configuration;

use Keboola\CsvTable\Table;
use Keboola\GenericExtractor\Exception\NoDataException;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;

/**
 * Class Extractor provides interfaces for procesing configuration files and
 * obtaning parts of GE extractor confgiruaton.
 */
class Extractor
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $state;

    /**
     * Extractor constructor.
     * @param $dataDir
     * @param LoggerInterface $logger
     */
    public function __construct(string $dataDir, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $this->loadConfigFile($dataDir);
        $this->state = $this->loadStateFile($dataDir);
    }

    /**
     * @param string $dataDir
     * @return array
     */
    private function loadConfigFile(string $dataDir) : array
    {
        $data = $this->loadJSONFile($dataDir, 'config.json');
        $processor = new Processor();
        try {
            $processor->processConfiguration(new ConfigFile(), $data);
        } catch (InvalidConfigurationException $e) {
            // TODO: create issuse to make this strict
            $this->logger->warning("State file configuration is invalid: " . $e->getMessage());
        }
        return $data;
    }

    /**
     * @param string $dataDir
     * @return array
     */
    private function loadStateFile(string $dataDir) : array
    {
        try {
            $data = $this->loadJSONFile($dataDir, 'in' . DIRECTORY_SEPARATOR . 'state.json');
        } catch (ApplicationException $e) {
            // state file is optional so only log the error
            $this->logger->warning("State file not found " . $e->getMessage());
            $data = [];
        }
        $processor = new Processor();
        try {
            $processor->processConfiguration(new ConfigFile(), $data);
        } catch (InvalidConfigurationException $e) {
            // TODO: create issuse to make this strict
            $this->logger->warning("State file configuration is invalid: " . $e->getMessage());
        }
        return $data;
    }

    /**
     * @return Config[]
     */
    public function getMultipleConfigs()
    {
        $iterations = [null];
        $json = $this->getJSON('/config.json');
        if (!empty($json['parameters']['iterations'])) {
            $iterations = $json['parameters']['iterations'];
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
            $configJson = $this->getJSON('/config.json')['parameters']['config'];
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
        $fileName = $this->dataDir . "/in/state.json";
        if (file_exists($fileName)) {
            $json = json_decode(file_get_contents($fileName), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        return null;
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
     * @return array
     * @throws NoDataException
     */
    protected function getJSON($filePath)
    {
        $fileName = $this->dataDir . $filePath;
        if (file_exists($fileName)) {
            if (empty($this->jsonFiles[$filePath])) {
                $this->jsonFiles[$filePath] = json_decode(file_get_contents($fileName), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->jsonFiles[$filePath];
                } else {
                    throw new NoDataException("Invalid JSON: " . json_last_error_msg());
                }
            } else {
                return $this->jsonFiles[$filePath];
            }
        } else {
            throw new NoDataException("File $fileName not found.");
        }
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
        $json = $this->getJSON('/config.json');
        if (!empty($json['parameters']['cache'])) {
            return $json['parameters']['cache'];
        }
        return [];
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
        return Api::create($this->logger, $this->getJSON('/config.json')['parameters']['api'], $config, $authorization);
    }

    /**
     * @return array
     */
    public function getAuthorization()
    {
        $json = $this->getJSON('/config.json');
        if (!empty($json['authorization'])) {
            return $json['authorization'];
        }
        return [];
    }
}
