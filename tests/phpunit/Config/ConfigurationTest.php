<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Configuration\Extractor;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Config\Config;
use Keboola\Temp\Temp;
use Keboola\CsvTable\Table;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class ConfigurationTest extends ExtractorTestCase
{
    public function testStoreResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $this->storeResults($resultsPath, 'full', false);
    }

    public function testIncrementalResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $this->storeResults($resultsPath, 'incremental', true);
    }

    public function testDefaultBucketResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $files = [
            new Table('first', ['col1', 'col2']),
            new Table('second', ['col11', 'col12']),
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);
        $files[1]->setPrimaryKey(['col11']);

        $configuration->storeResults($files);

        /** @var \SplFileInfo $file */
        foreach (new \FilesystemIterator(__DIR__ . '/../data/storeResultsDefaultBucket/out/tables/') as $file) {
            self::assertFileEquals($file->getPathname(), $resultsPath . '/out/tables/' . $file->getFilename());
        }

        $this->rmDir($resultsPath);
    }

    protected function storeResults(string $resultsPath, string $name, bool $incremental): void
    {
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $files = [
            new Table('first', ['col1', 'col2']),
            new Table('second', ['col11', 'col12']),
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);

        $configuration->storeResults($files, $name, true, $incremental);

        /** @var \SplFileInfo $file */
        foreach (new \FilesystemIterator(__DIR__ . '/../data/storeResultsTest/out/tables/' . $name) as $file) {
            self::assertFileEquals(
                $file->getPathname(),
                $resultsPath . '/out/tables/' . $name . '/' . $file->getFilename()
            );
        }

        $this->rmDir($resultsPath);
    }

    public function testGetConfigMetadata(): void
    {
        $path = __DIR__ . '/../data/metadataTest';
        $configuration = new Extractor($path, new NullLogger());
        $json = $configuration->getMetadata();

        self::assertEquals(json_decode('{"some":"data","more": {"woah": "such recursive"}}', true), $json);
        $path = __DIR__ . '/../data/noCache';
        $noConfiguration = new Extractor($path, new NullLogger());
        self::assertEquals([], $noConfiguration->getMetadata());
    }

    public function testSaveConfigMetadata(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $configuration->saveConfigMetadata(
            [
            'some' => 'data',
            'more' => [
                'woah' => 'such recursive',
            ],
            ]
        );

        self::assertFileEquals(__DIR__ . '/../data/metadataTest/out/state.json', $resultsPath . '/out/state.json');

        $this->rmDir($resultsPath);
    }

    public function testGetMultipleConfigs(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/iterations', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        $json = json_decode((string) file_get_contents(__DIR__ . '/../data/iterations/config.json'), true);

        foreach ($json['parameters']['iterations'] as $i => $params) {
            self::assertEquals(
                array_replace(
                    [
                        'id' => $json['parameters']['config']['id'],
                        'outputBucket' => $json['parameters']['config']['outputBucket'],
                    ],
                    $params
                ),
                $configs[$i]->getAttributes()
            );
        }
        self::assertEquals($configs[0]->getJobs(), $configs[1]->getJobs());
        self::assertContainsOnlyInstancesOf(Config::class, $configs);
        self::assertCount(count($json['parameters']['iterations']), $configs);
        self::assertEquals($json['parameters']['config']['outputBucket'], $configs[0]->getAttribute('outputBucket'));
    }

    public function testGetMultipleConfigsSingle(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        self::assertContainsOnlyInstancesOf(Config::class, $configs);
        self::assertCount(1, $configs);
    }

    public function testGetJson(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        self::assertEquals('multiCfg', $configs[0]->getAttribute('id'));
    }

    public function testGetInvalidConfig(): void
    {
        $temp = new Temp();
        $fs = new Filesystem();
        $fs->dumpFile($temp->getTmpFolder() . '/config.json', 'invalidJSON');
        try {
            new Extractor($temp->getTmpFolder(), new NullLogger());
            self::fail('Invalid JSON must cause exception');
        } catch (ApplicationException $e) {
            self::assertStringContainsString('Configuration file is not a valid JSON: Syntax error', $e->getMessage());
        }
    }

    protected function rmDir(string $dirPath): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dirPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        return rmdir($dirPath);
    }
}
