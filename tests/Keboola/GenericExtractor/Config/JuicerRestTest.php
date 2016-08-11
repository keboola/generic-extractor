<?php
/**
 * @author Erik Zigo <erik.zigo@keboola.com>
 */
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\JuicerRest;

class JuicerRestTest extends ExtractorTestCase
{
	public function testConvert()
	{
		$oldConfig = [
			'maxRetries' => 6,
			'curlCodes' => [ 6 ],
			'httpCodes' => [ 503 ],
			'headerName' => 'Retry-After',
			'custom' => 'value',
		];

		$newConfig = JuicerRest::convertRetry($oldConfig);

		// items
		$this->assertArrayHasKey('maxRetries', $newConfig);
		$this->assertArrayHasKey('custom', $newConfig);

		$this->assertArrayHasKey('curl', $newConfig);
		$this->assertArrayHasKey('codes', $newConfig['curl']);

		$this->assertArrayHasKey('http', $newConfig);
		$this->assertArrayHasKey('codes', $newConfig['http']);
		$this->assertArrayHasKey('retryHeader', $newConfig['http']);

		// item values
		$this->assertSame($oldConfig['custom'], $newConfig['custom']);
		$this->assertSame($oldConfig['maxRetries'], $newConfig['maxRetries']);
		$this->assertSame($oldConfig['curlCodes'], $newConfig['curl']['codes']);
		$this->assertSame($oldConfig['httpCodes'], $newConfig['http']['codes']);
		$this->assertSame($oldConfig['headerName'], $newConfig['http']['retryHeader']);

		// removed items
		$this->assertArrayNotHasKey('curlCodes', $newConfig);
		$this->assertArrayNotHasKey('httpCodes', $newConfig);
		$this->assertArrayNotHasKey('headerName', $newConfig);
	}
}