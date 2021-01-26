<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Request;
use Keboola\GenericExtractor\Utils;
use PHPUnit\Framework\TestCase;

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
     */
    public function testMergeQueries(string $query1, string $query2, string $expected): void
    {
        // Test all string + array representations
        $this->assertSame($expected, Utils::mergeQueries($query1, $query2));
        $this->assertSame($expected, Utils::mergeQueries(Query::parse($query1), $query2));
        $this->assertSame($expected, Utils::mergeQueries($query1, Query::parse($query2)));
        $this->assertSame($expected, Utils::mergeQueries(Query::parse($query1), Query::parse($query2)));
    }

    public function testMergeQueriesEscaping(): void
    {
        $query1 = ['param1' => '%21%40%23'];
        $query2 = ['param2' => '%C3%BA%C4%9B%C5%A1'];
        $expected = 'param1=%2521%2540%2523&param2=%25C3%25BA%25C4%259B%25C5%25A1';
        $this->assertSame($expected, Utils::mergeQueries($query1, $query2));
    }

    public function testMergeQueriesMergeToArray(): void
    {
        $query1 = ['a' => 'a1', 'b' => 'b1', 'c' => 'c1'];
        $query2 = ['c' => 'c2', 'd' => 'd2'];
        // a=a1&b=b1&d=d2&c[0]=c1&c[1]=c2
        $expected = 'a=a1&b=b1&d=d2&c%5B0%5D=c1&c%5B1%5D=c2';
        $this->assertSame($expected, Utils::mergeQueries($query1, $query2, true));
    }

    public function getTestQueries(): iterable
    {
        yield ['', '', ''];
        yield ['param1=value1', 'param2=value2', 'param1=value1&param2=value2'];
        yield ['a=x&b=y&c=z', 'a=xx&c=zz', 'a=xx&b=y&c=zz'];
        yield ['a=xx&c=zz', 'a=x&b=y&c=z', 'a=x&c=z&b=y'];
        yield ['param1=!@#', 'param2=úěš', 'param1=%21%40%23&param2=%C3%BA%C4%9B%C5%A1'];
        yield ['param1=%21%40%23', 'param2=%C3%BA%C4%9B%C5%A1', 'param1=%21%40%23&param2=%C3%BA%C4%9B%C5%A1'];
    }

    /**
     * @dataProvider getTestHeaders
     */
    public function testMergeHeaders(array $a, array $b, array $expected): void
    {
        $request = new Request('GET', 'http://example.com');
        foreach ($a as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $result = Utils::mergeHeaders($request, $b)->getHeaders();
        unset($result['Host']);

        $this->assertSame($expected, $result);
    }

    public function getTestHeaders(): iterable
    {
        yield [[],[],[]];
        yield [['k1' => 'v1'],[],['k1' => ['v1']]];
        yield [[],['k1' => 'v1'],['k1' => ['v1']]];
        yield [['k1' => 'v1'],['k2' => 'v2'],['k1' => ['v1'], 'k2' => ['v2']]];
        yield [['k1' => 'v1'],['k1' => 'v2'],['k1' => ['v2']]];
        yield [['key1' => 'v1'],['KEY1' => 'v2'],['KEY1' => ['v2']]];
        yield [['KEY1' => 'v1'],['KEY2' => 'v2'],['KEY1' => ['v1'], 'KEY2' => ['v2']]];
    }
}
