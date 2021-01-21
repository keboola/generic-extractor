<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use PHPUnit\Framework\TestCase;

class MockExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('TODO fix test');
        parent::setUp();
    }

    /**
     * @dataProvider configProvider
     */
    public function testRun(string $configDir): void
    {
        $dataDir = __DIR__ . "/data/{$configDir}";
        $runPhp = __DIR__ . '/../../src/run.php';
        $this->rmDir(__DIR__ . "{$dataDir}/out");
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertDirectoryEquals(
            __DIR__ . "/data/{$configDir}/expected/tables",
            __DIR__ . "/data/{$configDir}/out/tables"
        );

        self::assertEquals(0, $retval);
        $this->rmDir(__DIR__ . "/data/{$configDir}/out");
    }

    public function configProvider(): array
    {
        return [
            ['responseUrlScroll'],
            ['jobUserData'],
            ['getPost'],
            ['basicAuth'],
            ['multipleOutputs'],
            ['multipleOutputsUserData'],
            ['defaultBucket'],
            ['jsonMap'],
        ];
    }

    public function testDefaultRequestOptions(): void
    {
        $dataDir = __DIR__ . '/data/defaultOptions';
        $runPhp = __DIR__ . '/../../src/run.php';
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);
        self::assertMatchesRegularExpression('/GET \/defaultOptions\?param=value/', implode("\n", $output));
        $this->rmDir(__DIR__ . '/data/defaultOptions/out');
    }

    public function testEmptyCfg(): void
    {
        $dataDir = __DIR__ . '/data/emptyCfg';
        $runPhp = __DIR__ . '/../../src/run.php';
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);
        self::assertStringContainsString('is not a valid JSON: Syntax error', implode("\n", $output));
        self::assertEquals(2, $retval);
    }

    public function testDynamicUserData(): void
    {
        $dataDir = __DIR__ . '/data/dynamicUserData';
        $runPhp = __DIR__ . '/../../src/run.php';
        exec("KBC_DATADIR=$dataDir php $runPhp  2>&1", $output, $retval);
        /** @var array $expectedFile */
        $expectedFile = file(__DIR__ . '/data/dynamicUserData/expected/tables/get');
        foreach ($expectedFile as &$row) {
            $row = str_replace('{{date}}', date('Y-m-d'), $row);
        }

        self::assertEquals($expectedFile, file(__DIR__ . '/data/dynamicUserData/out/tables/get'));
        // 2nd row; 3rd column should contain the date
        /** @var array $file */
        $file = file(__DIR__ . '/data/dynamicUserData/out/tables/get');
        self::assertEquals(date('Y-m-d'), str_getcsv($file[1])[2]);

        $this->rmDir(__DIR__ . '/data/dynamicUserData/out');
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

    protected function assertDirectoryEquals(string $pathToExpected, string $pathToActual): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $pathToExpected,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        ) as $file) {
            $relPath = str_replace($pathToExpected, '', $file->getPathname());
            self::assertFileEquals($file->getPathname(), $pathToActual . $relPath);
        }
    }
}
