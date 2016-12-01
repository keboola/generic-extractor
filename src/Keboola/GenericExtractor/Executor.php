<?php

namespace Keboola\GenericExtractor;

use Doctrine\Common\Cache\FilesystemCache;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use Keboola\GenericExtractor\Config\Configuration,
    Keboola\GenericExtractor\GenericExtractor;
use Keboola\Temp\Temp;
use Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Exception\UserException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Executor
{
    /**
     * @param Configuration $config
     * @return CacheStorage|null
     */
    private function initCacheStorage(Configuration $config)
    {
        $cacheConfig = $config->getCache();

        if (!$cacheConfig) {
            return null;
        }

        return new CacheStorage(
            new FilesystemCache($config->getDataDir() . '/cache'),
            null,
            !empty($cacheConfig['ttl']) ? (int) $cacheConfig['ttl'] : Configuration::CACHE_TTL
        );
    }

    public function run()
    {
        $temp = new Temp(APP_NAME);

        Logger::initLogger(APP_NAME);

        $arguments = getopt("d::", ["data::"]);
        if (!isset($arguments["data"])) {
            throw new UserException('Data folder not set.');
        }

        $configuration = new Configuration($arguments['data'], APP_NAME, $temp);

        $configs = $configuration->getMultipleConfigs();

        $metadata = $configuration->getConfigMetadata() ?: [];
        $metadata['time']['previousStart'] = empty($metadata['time']['previousStart']) ? 0 : $metadata['time']['previousStart'];
        $metadata['time']['currentStart'] = time();

        $modules = $this->loadModules($configuration);

        $authorization = $configuration->getAuthorization();
        $cacheStorage = $this->initCacheStorage($configuration);

        $results = [];
        foreach($configs as $config) {
            // Reinitialize logger depending on debug status
            if ($config->getAttribute('debug')) {
                Logger::initLogger(APP_NAME, true);
            } else {
                Logger::initLogger(APP_NAME);
            }

            $api = $configuration->getApi($config, $authorization);

            if (!empty($config->getAttribute('outputBucket'))) {
                $outputBucket = $config->getAttribute('outputBucket');
            } elseif (!empty($config->getConfigName())) {
                $outputBucket = 'ex-api-' . $api->getName() . "-" . $config->getConfigName();
            } else {
                $outputBucket = "__kbc_default";
            }

            $extractor = new GenericExtractor($temp);
            $extractor->setLogger(Logger::getLogger());

            if ($cacheStorage) {
                $extractor->enableCache($cacheStorage);
            }

            if (!empty($results[$outputBucket])) {
                $extractor->setParser($results[$outputBucket]['parser']);
            }
            $extractor->setApi($api);
            $extractor->setMetadata($metadata);
            $extractor->setModules($modules);

            $extractor->run($config);

            $metadata = $extractor->getMetadata();

            $results[$outputBucket]['parser'] = $extractor->getParser();
            $results[$outputBucket]['incremental'] = $config->getAttribute('incrementalOutput');

        }

        foreach($results as $bucket => $result) {
            Logger::log('debug', "Processing results for {$bucket}.");
            $configuration->storeResults(
                $result['parser']->getResults(),
                $bucket == "__kbc_default" ? null : $bucket,
                true,
                $result['incremental']
            );

            // move files and flatten file structure
            $folderFinder = new Finder();
            $fs = new Filesystem();
            $folders = $folderFinder->directories()->in($arguments['data'] . "/out/tables")->depth(0);
            foreach ($folders as $folder) {
                //$files = $finder->files()->in($folder->getPathname())->depth(0);
                $filesFinder = new Finder();
                $files = $filesFinder->files()->in($folder->getPathname())->depth(0);
                foreach ($files as $file) {
                    $destination = $arguments['data'] . "/out/tables/" . basename($folder->getPathname()) . "." . basename($file->getPathname());
                    // maybe move will be better?
                    $fs->copy($file->getPathname(), $destination);
                    $fs->remove($file);
                }
            }
            $fs->remove($folders);
        }

        $metadata['time']['previousStart'] = $metadata['time']['currentStart'];
        unset($metadata['time']['currentStart']);
        $configuration->saveConfigMetadata($metadata);
    }

    /**
     * @return array ['response' => ResponseModuleInterface[]]
     */
    protected function loadModules(Configuration $configuration)
    {
        $modules = $configuration->getModules();
        return $modules;
    }
}
