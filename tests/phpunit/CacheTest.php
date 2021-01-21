<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\Csv\CsvFile;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('TODO fix test');
        parent::setUp();
    }

    public function testCacheTTL(): void
    {
        // first execution
        $dataDir = __DIR__ . '/data/requestCacheTTL';
        $runPhp = __DIR__ . '/../../src/run.php';
        $this->rmDir("{$dataDir}/out");
        $this->rmDir("{$dataDir}/cache");
        $filePath = "{$dataDir}/out/tables/getPost.get";
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int) $data[1];
        $this->rmDir("{$dataDir}/out");

        sleep(3);

        // second execution
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int) $data[1];
        self::assertTrue($firstDateTime === $secondDateTime);
        $this->rmDir("{$dataDir}/out");

        sleep(10);

        // third execution
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $thirdDateTime = (int) $data[1];
        self::assertTrue($secondDateTime < $thirdDateTime);

        $this->rmDir("{$dataDir}/out");
        $this->rmDir("{$dataDir}/cache");
    }

    protected function rmDir(string $dirPath): void
    {
        if (!file_exists($dirPath)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dirPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        rmdir($dirPath);
    }

    public function testCache(): void
    {
        $this->rmDir(__DIR__ . '/data/requestCache/out');
        $this->rmDir(__DIR__ . '/data/requestCache/cache');
        $filePath = __DIR__ . '/data/requestCache/out/tables/getPost.get';

        // first execution
        $dataDir = __DIR__ . '/data/requestCache';
        $runPhp = __DIR__ . '/../../src/run.php';
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int) $data[1];
        $this->rmDir(__DIR__ . '/data/requestCache/out');

        sleep(3);

        // second execution
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int) $data[1];
        self::assertTrue($firstDateTime === $secondDateTime);

        $this->rmDir(__DIR__ . '/data/requestCache/out');

        $this->rmDir(__DIR__ . '/data/requestCache/cache');
    }

    public function testNoCache(): void
    {
        $this->rmDir(__DIR__ . '/data/noCache/out');
        $this->rmDir(__DIR__ . '/data/noCache/cache');
        $filePath = __DIR__ . '/data/noCache/out/tables/getPost.get';

        // first execution
        $dataDir = __DIR__ . '/data/noCache';
        $runPhp = __DIR__ . '/../../src/run.php';
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int) $data[1];
        $this->rmDir(__DIR__ . '/data/noCache/out');

        sleep(3);

        // second execution
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);
        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);

        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int) $data[1];
        self::assertTrue($firstDateTime < $secondDateTime);
        $this->rmDir(__DIR__ . '/data/noCache/out');
    }
}
