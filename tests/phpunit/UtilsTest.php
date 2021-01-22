<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use GuzzleHttp\Psr7\Request;
use Keboola\GenericExtractor\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class UtilsTest extends TestCase
{
    /**
     * @dataProvider getTestUris
     */
    public function testGerResources(string $uri, string $resource): void
    {
        $this->assertSame($resource, Utils::getResource(\GuzzleHttp\Psr7\Utils::uriFor($uri)));
    }

    public function getTestUris(): iterable
    {
        yield ['http://example.com', ''];
        yield ['http://example.com/', '/'];
        yield ['http://example.com/abc', '/abc'];
        yield ['http://example.com/abc?k1=v1', '/abc?k1=v1'];
        yield ['http://example.com/abc?k1=v1&k2=v2', '/abc?k1=v1&k2=v2'];
        yield ['', ''];
        yield ['/', '/'];
        yield ['/abc', '/abc'];
        yield ['/abc?k1=v1', '/abc?k1=v1'];
        yield ['/abc?k1=v1&k2=v2', '/abc?k1=v1&k2=v2'];
    }

    /**
     * @dataProvider getTestQueries
     * @param string|array $query1
     * @param string|array $query2
     */
    public function testMergeQueries($query1, $query2, string $expected): void
    {
        $this->assertSame($expected, Utils::mergeQueries($query1, $query2));
    }

    public function getTestQueries(): iterable
    {
        yield ['', '', ''];
        yield [[], [], ''];
        yield ['', [], ''];
        yield [[], '', ''];
        yield ['param1=value1', 'param2=value2', 'param1=value1&param2=value2'];
        yield [['param1' => 'value1'], ['param2' => 'value2'], 'param1=value1&param2=value2'];
        yield ['param1=value1', ['param2' => 'value2'], 'param1=value1&param2=value2'];
        yield [['param1' => 'value1'], 'param2=value2', 'param1=value1&param2=value2'];
        yield ['a=x&b=y&c=z', 'a=xx&c=zz', 'a=xx&b=y&c=zz'];
        yield [['a' => 'x', 'b' => 'y', 'c' => 'z'], ['a' => 'xx', 'c' => 'zz'], 'a=xx&b=y&c=zz'];
        yield ['a=x&b=y&c=z', ['a' => 'xx', 'c' => 'zz'], 'a=xx&b=y&c=zz'];
        yield [['a' => 'x', 'b' => 'y', 'c' => 'z'], 'a=xx&c=zz', 'a=xx&b=y&c=zz'];
        yield ['param1=!@#', 'param2=úěš', 'param1=%21%40%23&param2=%C3%BA%C4%9B%C5%A1'];
        yield ['param1=%21%40%23', 'param2=%C3%BA%C4%9B%C5%A1', 'param1=%21%40%23&param2=%C3%BA%C4%9B%C5%A1'];
        yield [
            ['param1' => '%21%40%23'],
            ['param2' => '%C3%BA%C4%9B%C5%A1'],
            'param1=%2521%2540%2523&param2=%25C3%25BA%25C4%259B%25C5%25A1',
        ];
    }
}
