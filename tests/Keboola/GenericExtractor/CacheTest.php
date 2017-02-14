<?php
namespace Keboola\GenericExtractor;

use Keboola\Csv\CsvFile;

class CacheTest extends ExtractorTestCase
{
	public function testCacheTTL()
	{
		$filePath = './tests/data/requestCacheTTL/out/tables/getPost.get';

		// first execution
		$output = shell_exec('php ./run.php --data=./tests/data/requestCacheTTL');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$firstDateTime = (int) $data[1];
		$this->rmDir('./tests/data/requestCacheTTL/out');

		sleep(3);

		// second execution
		$output = shell_exec('php ./run.php --data=./tests/data/requestCacheTTL');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$secondDateTime = (int) $data[1];
		$this->assertTrue($firstDateTime === $secondDateTime);

		$this->rmDir('./tests/data/requestCacheTTL/out');

		sleep(10);

		// third execution
		$output = shell_exec('php ./run.php --data=./tests/data/requestCacheTTL');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$thirdDateTime = (int) $data[1];
		$this->assertTrue($secondDateTime < $thirdDateTime);

		$this->rmDir('./tests/data/requestCacheTTL/out');

		$this->rmDir('./tests/data/requestCacheTTL/cache');
	}

	public function testCache()
	{
		$filePath = './tests/data/requestCache/out/tables/getPost.get';

		// first execution
		$output = shell_exec('php ./run.php --data=./tests/data/requestCache');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$firstDateTime = (int) $data[1];
		$this->rmDir('./tests/data/requestCache/out');

		sleep(3);

		// second execution
		$output = shell_exec('php ./run.php --data=./tests/data/requestCache');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$secondDateTime = (int) $data[1];
		$this->assertTrue($firstDateTime === $secondDateTime);

		$this->rmDir('./tests/data/requestCache/out');

		$this->rmDir('./tests/data/requestCache/cache');
	}

	public function testNoCache()
	{
		$filePath = './tests/data/noCache/out/tables/getPost.get';

		// first execution
		$output = shell_exec('php ./run.php --data=./tests/data/noCache');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$firstDateTime = (int) $data[1];
		$this->rmDir('./tests/data/noCache/out');

		sleep(3);

		// second execution
		$output = shell_exec('php ./run.php --data=./tests/data/noCache');

		self::assertEquals('Extractor finished successfully.' . PHP_EOL, $output);

		$this->assertFileExists($filePath);

		$csv = new CsvFile($filePath);
		$this->assertEquals(3, $csv->getColumnsCount());

		$csv->next();
		$data = $csv->current();
		unset($csv);

		$secondDateTime = (int) $data[1];

		$this->assertTrue($firstDateTime < $secondDateTime);

		$this->rmDir('./tests/data/noCache/out');
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
}
