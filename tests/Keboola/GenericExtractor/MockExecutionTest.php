<?php
namespace Keboola\GenericExtractor;

class MockExecutionTest extends ExtractorTestCase
{
    public function testScroll()
    {
        // copy the config replacing URL from env_var? TODO
        $output = shell_exec('php ./run.php --data=./tests/data/responseUrlScroll');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/responseUrlScroll/expected/tables/',
            './tests/data/responseUrlScroll/out/tables/'
        );

        $this->rmDir('./tests/data/responseUrlScroll/out');
    }

    public function testGetPost()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/getPost');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/getPost/expected/tables/',
            './tests/data/getPost/out/tables/'
        );

        $this->rmDir('./tests/data/getPost/out');
    }

    public function testBasicAuth()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/basicAuth');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/basicAuth/expected/tables/',
            './tests/data/basicAuth/out/tables/'
        );

        $this->rmDir('./tests/data/basicAuth/out');
    }

    public function testMultipleOutputs()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/multipleOutputs');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/multipleOutputs/expected/tables/',
            './tests/data/multipleOutputs/out/tables/'
        );

        $this->rmDir('./tests/data/multipleOutputs/out');
    }

    public function testMultipleOutputsUserData()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/multipleOutputsUserData');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/multipleOutputsUserData/expected/tables/',
            './tests/data/multipleOutputsUserData/out/tables/'
        );

        $this->rmDir('./tests/data/multipleOutputsUserData/out');
    }

    public function testDefaultRequestOptions()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/defaultOptions');

        self::assertRegexp('/GET \/defaultOptions\?param=value/', $output);

        $this->rmDir('./tests/data/defaultOptions/out');
    }

    public function testDefaultBucket()
    {
        $result = exec('php ./run.php --data=./tests/data/defaultBucket 2>&1', $output, $retval);

        self::assertDirectoryEquals(
            './tests/data/defaultBucket/expected/tables/',
            './tests/data/defaultBucket/out/tables/'
        );

        $this->rmDir('./tests/data/defaultBucket/out');
    }

    public function testEmptyCfg()
    {
        $result = exec('php ./run.php --data=./tests/data/emptyCfg 2>&1', $output, $retval);

        self::assertEquals(1, $retval);
    }
/*
    public function testIterationDifferentColumns()
    {
        $output = shell_exec('php ./run.php --data=./tests/data/iterationDifferentColumns');

        self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

        self::assertDirectoryEquals(
            './tests/data/iterationDifferentColumns/expected/tables/',
            './tests/data/iterationDifferentColumns/out/tables/'
        );

        $this->rmDir('./tests/data/iterationDifferentColumns/out');
    }*/

    protected function rmDir($dirPath)
    {
        foreach(
            new \RecursiveIteratorIterator(
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
        foreach(new \RecursiveIteratorIterator(
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
