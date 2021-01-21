<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
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
