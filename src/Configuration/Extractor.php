<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration;

use Keboola\CsvTable\Table;
use Keboola\GenericExtractor\Cache\CacheAllStrategy;
use Keboola\GenericExtractor\Configuration\Extractor\ConfigFile;
use Keboola\GenericExtractor\Configuration\Extractor\StateFile;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Config\Config;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use League\Flysystem\Adapter\Local;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Class Extractor provides interfaces for processing configuration files and
 * obtaining parts of GE extractor configuration.
 */
class Extractor
{
    public const CACHE_TTL = 604800;

    private LoggerInterface $logger;

    private array $config;

    private array $state;

    private string $dataDir;

    public function __construct(string $dataDir, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $this->loadConfigFile($dataDir);
        if ($this->isSyncAction()) {
            $this->runSyncActionProcess();
        }
        $this->state = $this->loadStateFile($dataDir);
        $this->dataDir = $dataDir;
    }

    private function loadJSONFile(string $dataDir, string $name): array
    {
        $fileName = $dataDir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($fileName)) {
            throw new ApplicationException("Configuration file '$fileName' not found.");
        }
        $data = json_decode((string) file_get_contents($fileName), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApplicationException('Configuration file is not a valid JSON: ' . json_last_error_msg());
        }
        return $data;
    }

    private function loadConfigFile(string $dataDir): array
    {
        $data = $this->loadJSONFile($dataDir, 'config.json');
        $processor = new Processor();
        try {
            $processor->processConfiguration(new ConfigFile(), $data);
        } catch (InvalidConfigurationException $e) {
            // TODO: create issue to make this strict
            //$this->logger->warning("Configuration file configuration is invalid: " . $e->getMessage());
        }
        return $data;
    }

    private function loadStateFile(string $dataDir): array
    {
        try {
            $data = $this->loadJSONFile($dataDir, 'in' . DIRECTORY_SEPARATOR . 'state.json');
        } catch (ApplicationException $e) {
            // state file is optional so only log the error
            $this->logger->warning('State file not found ' . $e->getMessage());
            $data = [];
        }
        $processor = new Processor();
        try {
            $processor->processConfiguration(new StateFile(), $data);
        } catch (InvalidConfigurationException $e) {
            // TODO: create issue to make this strict
            //$this->logger->warning("State file configuration is invalid: " . $e->getMessage());
        }
        return $data;
    }

    /**
     * @return Config[]
     */
    public function getMultipleConfigs(): array
    {
        if (empty($this->config['parameters']['iterations'])) {
            return [$this->getConfig([])];
        }

        $configs = [];
        foreach ($this->config['parameters']['iterations'] as $params) {
            $configs[] = $this->getConfig($params);
        }
        return $configs;
    }

    private function getConfig(array $params): Config
    {
        if (empty($this->config['parameters']['config'])) {
            throw new UserException("The 'config' section is required in the configuration.");
        }
        $configuration = array_replace($this->config['parameters']['config'], $params);
        return new Config($configuration);
    }

    private function isSyncAction(): bool
    {
        if (isset($this->config['action']) && $this->config['action'] !== 'run') {
            return true;
        }

        return false;
    }

    public function getSshProxy(): ?array
    {
        if (isset($this->config['parameters']['sshProxy'])) {
            return $this->config['parameters']['sshProxy'];
        }
        return null;
    }

    public function getMetadata(): array
    {
        return $this->state;
    }

    public function getCache(): ?CacheStrategyInterface
    {
        if (empty($this->config['parameters']['cache'])) {
            return null;
        }

        $ttl = !empty($this->config['parameters']['cache']['ttl']) ?
            (int) $this->config['parameters']['cache']['ttl'] : self::CACHE_TTL;
        $cacheDir = $this->dataDir . DIRECTORY_SEPARATOR . 'cache';
        return new CacheAllStrategy(
            new FlysystemStorage(new Local($cacheDir)),
            $ttl
        );
    }

    public function getApi(array $configAttributes): Api
    {
        if (!empty($this->config['authorization'])) {
            $authorization = $this->config['authorization'];
        } else {
            $authorization = [];
        }

        $this->validateApiConfig();

        return new Api($this->logger, $this->config['parameters']['api'], $configAttributes, $authorization);
    }

    public function getAwsSignatureCredentials(): ?array
    {
        if (empty($this->config['parameters']['aws']['signature']['credentials'])) {
            return null;
        }

        $requiredParams = ['accessKeyId', '#secretKey', 'serviceName', 'regionName'];
        foreach ($requiredParams as $requiredParam) {
            if (empty($this->config['parameters']['aws']['signature']['credentials'][$requiredParam])) {
                throw new UserException(
                    sprintf(
                        'Option "%s" under "parameters.aws.signature.credentials" cannot be empty.',
                        $requiredParam
                    )
                );
            }
        }

        return $this->config['parameters']['aws']['signature']['credentials'];
    }

    public function saveConfigMetadata(array $data): void
    {
        $dirPath = $this->dataDir . DIRECTORY_SEPARATOR . 'out';
        if (!is_dir($dirPath)) {
            mkdir($dirPath);
        }
        file_put_contents($dirPath . DIRECTORY_SEPARATOR . 'state.json', json_encode($data));
    }

    /**
     * @param Table[] $csvFiles
     * @param bool    $sapiPrefix  whether to prefix the output bucket with "in.c-"
     * @param bool    $incremental Set the incremental flag in manifest
     *                             TODO: revisit this
     */
    public function storeResults(
        array $csvFiles,
        ?string $bucketName = null,
        bool $sapiPrefix = true,
        bool $incremental = false
    ): void {
        $path = "{$this->dataDir}/out/tables/";

        if (!is_null($bucketName)) {
            $path .= $bucketName . '/';
            $bucketName = $sapiPrefix ? 'in.c-' . $bucketName : $bucketName;
        }

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
            chown($path, (int) fileowner("{$this->dataDir}/out/tables/"));
            chgrp($path, (int) filegroup("{$this->dataDir}/out/tables/"));
        }

        foreach ($csvFiles as $key => $file) {
            $manifest = [];

            if (!is_null($bucketName)) {
                $manifest['destination'] = "{$bucketName}.{$key}";
            }

            $manifest['incremental'] = $file->isIncrementalSet()
                ? $file->getIncremental()
                : $incremental;

            if (!empty($file->getPrimaryKey())) {
                $manifest['primary_key'] = $file->getPrimaryKey(true);
            }

            file_put_contents($path . $key . '.manifest', json_encode($manifest));
            copy($file->getPathname(), $path . $key);
        }
    }

    protected function validateApiConfig(): void
    {
        $apiNode = $this->config['parameters']['api'];
        if (empty($apiNode) && !is_array($apiNode)) {
            throw new UserException("The 'api' section is required in configuration.");
        }

        if (array_key_exists('caCertificate', $apiNode)
            && (!is_null($apiNode['caCertificate']) && !is_string($apiNode['caCertificate']))
        ) {
            throw new UserException("The 'caCertificate' must be string.");
        }

        if (array_key_exists('clientCertificate', $apiNode)
            && (!is_null($apiNode['clientCertificate']) && !is_string($apiNode['clientCertificate']))
        ) {
            throw new UserException("The 'clientCertificate' must be string.");
        }
    }

    private function runSyncActionProcess(): void
    {
        try {
            $command = [
                'python',
                '-u',
                './python-sync-actions/src/component.py',
            ];

            $process = new Process($command);
            $process->start();

            // Delay to let the process initialize
            usleep(500000); // Sleep for 0.5 seconds

            // Check if the process has terminated already due to a startup error
            if (!$process->isRunning() && !$process->isSuccessful()) {
                $this->logger->error('Process failed to start. Error output: ' . $process->getErrorOutput());
                throw new ProcessFailedException($process);
            }
        } catch (ProcessFailedException $e) {
            $this->logger->error('Process failed to start: ' . $e->getMessage());
            throw new ApplicationException('Process error output: ' . $e->getProcess()->getErrorOutput());
        } catch (Throwable $e) {
            throw new ApplicationException('Unexpected error: ' . $e->getMessage());
        }
    }
}
