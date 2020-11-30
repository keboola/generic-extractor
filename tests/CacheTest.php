<?php

namespace Keboola\GenericExtractor\Tests;

use Keboola\Csv\CsvFile;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function testCacheTTL()
    {
        $this->rmDir(__DIR__ . '/data/requestCacheTTL/out');
        $this->rmDir(__DIR__ . '/data/requestCacheTTL/cache');

        $filePath = __DIR__ . '/data/requestCacheTTL/out/tables/getPost.get';

        // first execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/requestCacheTTL/', $output);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int)$data[1];
        $this->rmDir(__DIR__ . '/data/requestCacheTTL/out');

        sleep(3);

        // second execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/requestCacheTTL', $output);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int)$data[1];
        self::assertTrue($firstDateTime === $secondDateTime);

        $this->rmDir(__DIR__ . '/data/requestCacheTTL/out');

        sleep(10);

        // third execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/requestCacheTTL', $output);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $thirdDateTime = (int)$data[1];
        self::assertTrue($secondDateTime < $thirdDateTime);

        $this->rmDir(__DIR__ . '/data/requestCacheTTL/out');
        $this->rmDir(__DIR__ . '/data/requestCacheTTL/cache');
    }

    protected function rmDir($dirPath)
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

    public function testCache()
    {
        $this->rmDir(__DIR__ . '/data/requestCache/out');
        $this->rmDir(__DIR__ . '/data/requestCache/cache');
        $filePath = __DIR__ . '/data/requestCache/out/tables/getPost.get';

        // first execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/requestCache', $output);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int)$data[1];
        $this->rmDir(__DIR__ . '/data/requestCache/out');

        sleep(3);

        // second execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/requestCache', $output);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int)$data[1];
        self::assertTrue($firstDateTime === $secondDateTime);

        $this->rmDir(__DIR__ . '/data/requestCache/out');

        $this->rmDir(__DIR__ . '/data/requestCache/cache');
    }

    public function testNoCache()
    {
        $this->rmDir(__DIR__ . '/data/noCache/out');
        $this->rmDir(__DIR__ . '/data/noCache/cache');
        $filePath = __DIR__ . '/data/noCache/out/tables/getPost.get';

        // first execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/noCache', $output);

        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);
        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $firstDateTime = (int)$data[1];
        $this->rmDir(__DIR__ . '/data/noCache/out');

        sleep(3);

        // second execution
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/noCache', $output);
        self::assertStringContainsString('Extractor finished successfully', implode("\n", $output));
        self::assertFileExists($filePath);

        $csv = new CsvFile($filePath);
        self::assertEquals(3, $csv->getColumnsCount());

        $csv->next();
        $data = $csv->current();
        unset($csv);

        $secondDateTime = (int)$data[1];
        self::assertTrue($firstDateTime < $secondDateTime);
        $this->rmDir(__DIR__ . '/data/noCache/out');
    }
}
