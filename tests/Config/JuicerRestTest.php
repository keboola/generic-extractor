<?php

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Config\JuicerRest;
use PHPUnit\Framework\TestCase;

class JuicerRestTest extends TestCase
{
    public function testConvert()
    {
        $oldConfig = [
            'maxRetries' => 6,
            'curlCodes' => [6],
            'httpCodes' => [503],
            'headerName' => 'Retry-After',
            'custom' => 'value',
        ];

        $newConfig = JuicerRest::convertRetry($oldConfig);

        // items
        self::assertArrayHasKey('maxRetries', $newConfig);
        self::assertArrayHasKey('custom', $newConfig);
        self::assertArrayHasKey('curl', $newConfig);
        self::assertArrayHasKey('codes', $newConfig['curl']);
        self::assertArrayHasKey('http', $newConfig);
        self::assertArrayHasKey('codes', $newConfig['http']);
        self::assertArrayHasKey('retryHeader', $newConfig['http']);

        // item values
        self::assertSame($oldConfig['custom'], $newConfig['custom']);
        self::assertSame($oldConfig['maxRetries'], $newConfig['maxRetries']);
        self::assertSame($oldConfig['curlCodes'], $newConfig['curl']['codes']);
        self::assertSame($oldConfig['httpCodes'], $newConfig['http']['codes']);
        self::assertSame($oldConfig['headerName'], $newConfig['http']['retryHeader']);

        // removed items
        self::assertArrayNotHasKey('curlCodes', $newConfig);
        self::assertArrayNotHasKey('httpCodes', $newConfig);
        self::assertArrayNotHasKey('headerName', $newConfig);
    }
}
