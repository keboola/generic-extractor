<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Configuration\Extractor;
use Keboola\GenericExtractor\SshTunnel\SshTunnelFactory;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class Executor manages multiple configurations (created by iterations) and executes
 * GenericExtractor for each of them.
 */
class Executor
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    private function setLogLevel(bool $debug): void
    {
        /**
         * @var AbstractHandler $handler
         */
        foreach ($this->logger->getHandlers() as $handler) {
            if ($handler instanceof AbstractHandler) {
                if ($debug) {
                    $handler->setLevel($this->logger::DEBUG);
                } else {
                    $handler->setLevel($this->logger::INFO);
                }
            }
        }
    }

    public function run(): void
    {
        $temp = new Temp();
        $dataDir = getenv('KBC_DATADIR') ?: '/data';
        $configuration = new Extractor($dataDir, $this->logger);
        $configs = $configuration->getMultipleConfigs();

        $sshTunnel = null;
        if ($configuration->getSshProxy() !== null) {
            $sshTunnelFactory = new SshTunnelFactory($this->logger);
            $sshTunnel = $sshTunnelFactory->create($configuration->getSshProxy());
        }

        $awsSignatureCredentials = $configuration->getAwsSignatureCredentials();

        $metadata = $configuration->getMetadata();
        $metadata['time']['previousStart'] =
            empty($metadata['time']['previousStart']) ? 0 : $metadata['time']['previousStart'];
        $metadata['time']['currentStart'] = time();
        $cacheStorage = $configuration->getCache();

        $results = [];

        foreach ($configs as $config) {
            $this->setLogLevel((bool) $config->getAttribute('debug'));
            $api = $configuration->getApi($config->getAttributes());

            if (!empty($config->getAttribute('outputBucket'))) {
                $outputBucket = $config->getAttribute('outputBucket');
            } elseif ($config->getAttribute('id')) {
                $outputBucket = 'ex-api-' . $api->getName() . '-' . $config->getAttribute('id');
            } else {
                $outputBucket = '__kbc_default';
            }

            $extractor = new GenericExtractor(
                $temp,
                $this->logger,
                $api,
                $sshTunnel ? $sshTunnel->getProxy() : null,
                function (RestClient $client) use ($sshTunnel): void {
                    if ($sshTunnel) {
                        $client->getHandlerStack()->after(
                            'retry',
                            $sshTunnel->getMiddleware(),
                            'ssh-tunnel'
                        );
                    }
                },
                $awsSignatureCredentials
            );

            if ($cacheStorage) {
                $extractor->enableCache($cacheStorage);
            }

            if (!empty($results[$outputBucket])) {
                $extractor->setParser($results[$outputBucket]['parser']);
            }
            $extractor->setMetadata($metadata);

            $extractor->run($config);

            $metadata = $extractor->getMetadata();

            $results[$outputBucket]['parser'] = $extractor->getParser();
            $results[$outputBucket]['incremental'] = $config->getAttribute('incrementalOutput');
        }

        foreach ($results as $bucket => $result) {
            $this->logger->debug("Processing results for {$bucket}.");
            /**
             * @var Json $parser
             */
            $parser = $result['parser'];
            $configuration->storeResults(
                $parser->getResults(),
                $bucket === '__kbc_default' ? null : (string) $bucket,
                true,
                $result['incremental']
            );

            // move files and flatten file structure
            $folderFinder = new Finder();
            $fs = new Filesystem();
            $folders = $folderFinder->directories()->in($dataDir . '/out/tables')->depth(0);
            foreach ($folders as $folder) {
                /** @var SplFileInfo $folder */
                $filesFinder = new Finder();
                $files = $filesFinder->files()->in($folder->getPathname())->depth(0);
                /** @var SplFileInfo $file */
                foreach ($files as $file) {
                    $destination =
                        $dataDir . '/out/tables/' . basename($folder->getPathname()) .
                        '.' . basename($file->getPathname());
                    // maybe move will be better?
                    $fs->rename($file->getPathname(), $destination);
                }
            }
            $fs->remove($folders);
        }

        MissingTableHelper::checkConfigs($configs, $dataDir, $configuration);
        $metadata['time']['previousStart'] = $metadata['time']['currentStart'];
        unset($metadata['time']['currentStart']);
        $configuration->saveConfigMetadata($metadata);
    }
}
