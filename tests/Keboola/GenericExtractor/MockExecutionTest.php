<?php
namespace Keboola\GenericExtractor;

class MockExecutionTest extends ExtractorTestCase
{
    /**
     * @dataProvider configProvider
     */
    public function testRun($configDir)
    {
        exec("php ./run.php --data=./tests/data/{$configDir} 2>&1", $output, $retval);

        self::assertDirectoryEquals(
            "./tests/data/{$configDir}/expected/tables/",
            "./tests/data/{$configDir}/out/tables/"
        );

        self::assertEquals('Extractor finished successfully.', $output[0]);
        self::assertEquals(0, $retval);
        $this->rmDir("./tests/data/{$configDir}/out");
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
        $output = shell_exec('php ./run.php --data=./tests/data/defaultOptions');

        self::assertRegexp('/GET \/defaultOptions\?param=value/', $output);

        $this->rmDir('./tests/data/defaultOptions/out');
    }

    public function testEmptyCfg()
    {
        exec('php ./run.php --data=./tests/data/emptyCfg 2>&1', $output, $retval);
        self::assertEquals(1, $retval);
    }

    public function testDynamicUserData()
    {
        exec('php ./run.php --data=./tests/data/dynamicUserData 2>&1', $output, $retval);
        $expectedFile = file('./tests/data/dynamicUserData/expected/tables/get');
        foreach ($expectedFile as &$row) {
            $row = str_replace('{{date}}', date('Y-m-d'), $row);
        }

        self::assertEquals($expectedFile, file('./tests/data/dynamicUserData/out/tables/get'));
        // 2nd row; 3rd column should contain the date
        self::assertEquals(date('Y-m-d'), str_getcsv(file('./tests/data/dynamicUserData/out/tables/get')[1])[2]);

        $this->rmDir('./tests/data/dynamicUserData/out');
    }

    public function testJsonMapError()
    {
    }

    protected function rmDir($dirPath)
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dirPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        return rmdir($dirPath);
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
