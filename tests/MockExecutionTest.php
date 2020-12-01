<?php

namespace Keboola\GenericExtractor\Tests;

use PHPUnit\Framework\TestCase;

class MockExecutionTest extends TestCase
{
    /**
     * @dataProvider configProvider
     * @param string $configDir
     */
    public function testRun($configDir)
    {
        $this->rmDir(__DIR__ . "/data/{$configDir}/out");
        exec("php " . __DIR__ . "/../run.php --data=" . __DIR__ . "/data/{$configDir} 2>&1", $output, $retval);

        self::assertStringContainsString('Extractor finished successfully.', implode("\n", $output));
        self::assertDirectoryEquals(
            __DIR__ . "/data/{$configDir}/expected/tables",
            __DIR__ . "/data/{$configDir}/out/tables"
        );

        self::assertEquals(0, $retval);
        $this->rmDir(__DIR__ . "/data/{$configDir}/out");
    }

    public function configProvider()
    {
        return [
            ['responseUrlScroll'],
            ['jobUserData'],
            ['getPost'],
            ['basicAuth'],
            ['multipleOutputs'],
            ['multipleOutputsUserData'],
            ['defaultBucket'],
            ['jsonMap']
        ];
    }

    public function testDefaultRequestOptions()
    {
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/defaultOptions', $output);
        self::assertRegexp('/GET \/defaultOptions\?param=value/', implode("\n", $output));
        $this->rmDir(__DIR__ . '/data/defaultOptions/out');
    }

    public function testEmptyCfg()
    {
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/emptyCfg 2>&1', $output, $retval);
        self::assertStringContainsString('is not a valid JSON: Syntax error', implode("\n", $output));
        self::assertEquals(2, $retval);
    }

    public function testDynamicUserData()
    {
        exec('php ' . __DIR__ . '/../run.php --data=' . __DIR__ . '/data/dynamicUserData 2>&1', $output, $retval);
        $expectedFile = file(__DIR__ . '/data/dynamicUserData/expected/tables/get');
        foreach ($expectedFile as &$row) {
            $row = str_replace('{{date}}', date('Y-m-d'), $row);
        }

        self::assertEquals($expectedFile, file(__DIR__ . '/data/dynamicUserData/out/tables/get'));
        // 2nd row; 3rd column should contain the date
        self::assertEquals(date('Y-m-d'), str_getcsv(file(__DIR__ . '/data/dynamicUserData/out/tables/get')[1])[2]);

        $this->rmDir(__DIR__ . '/data/dynamicUserData/out');
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

    protected function assertDirectoryEquals($pathToExpected, $pathToActual)
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
