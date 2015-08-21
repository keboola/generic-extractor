<?php
namespace Keboola\GenericExtractor;

class MockExecutionTest extends ExtractorTestCase
{
	public function testScroll()
	{
		// copy the config replacing URL from env_var? TODO
		$output = shell_exec('php ./run.php --data=./tests/data/responseUrlScroll');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
			'./tests/data/responseUrlScroll/expected/tables/',
			'./tests/data/responseUrlScroll/out/tables/'
		);

		$this->rmDir('./tests/data/responseUrlScroll/out');
	}

	public function testGetPost()
	{
		$output = shell_exec('php ./run.php --data=./tests/data/getPost');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
			'./tests/data/getPost/expected/tables/',
			'./tests/data/getPost/out/tables/'
		);

		$this->rmDir('./tests/data/getPost/out');
	}

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
		foreach(new \DirectoryIterator($pathToExpected) as $file) {
			$this->assertFileEquals($file->getPathname(), $pathToActual . $file->getFilename());
		}
	}
}
