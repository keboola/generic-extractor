<?php

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Config\UserFunction;
use PHPUnit\Framework\TestCase;

class UserFunctionTest extends TestCase
{
    public function testBuild()
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
}
