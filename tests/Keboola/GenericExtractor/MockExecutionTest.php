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

	public function testBasicAuth()
	{
		$output = shell_exec('php ./run.php --data=./tests/data/basicAuth');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
			'./tests/data/basicAuth/expected/tables/',
			'./tests/data/basicAuth/out/tables/'
		);

		$this->rmDir('./tests/data/basicAuth/out');
	}

	public function testMultipleOutputs()
	{
		$output = shell_exec('php ./run.php --data=./tests/data/multipleOutputs');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
			'./tests/data/multipleOutputs/expected/tables/',
			'./tests/data/multipleOutputs/out/tables/'
		);

		$this->rmDir('./tests/data/multipleOutputs/out');
	}

	public function testMultipleOutputsUserData()
	{
		$output = shell_exec('php ./run.php --data=./tests/data/multipleOutputsUserData');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
			'./tests/data/multipleOutputsUserData/expected/tables/',
			'./tests/data/multipleOutputsUserData/out/tables/'
		);

		$this->rmDir('./tests/data/multipleOutputsUserData/out');
	}
/*
	public function testIterationDifferentColumns()
	{
		$output = shell_exec('php ./run.php --data=./tests/data/iterationDifferentColumns');

		$this->assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertDirectoryEquals(
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
			$this->assertFileEquals($file->getPathname(), $pathToActual . $relPath);
		}
	}
}
