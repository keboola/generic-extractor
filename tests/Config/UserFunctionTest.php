<?php

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Exception\UserException;
use PHPUnit\Framework\TestCase;

class UserFunctionTest extends TestCase
{
    public function testBuild(): void
    {
        $functions = [
            'str' => 'aaa',
            'attribute' => ['attr' => 'attrName'],
            'fn' => [
                'function' => 'md5',
                'args' => [
                    'hashMe'
                ]
            ]
        ];

        $data = ['attr' => ['attrName' => 'attrValue']];

        self::assertEquals(
            [
                'str' => 'aaa',
                'attribute' => $data['attr']['attrName'],
                'fn' => md5($functions['fn']['args'][0])
            ],
            UserFunction::build($functions, $data)
        );
    }

    public function testInvalidType(): void
    {
        $functions = 'not array';

        $this->expectException(UserException::class);
        $this->expectExceptionMessage("Expected 'object' type, given 'string' type, value '\"not array\"'.");
        UserFunction::build($functions, []);
    }
}
