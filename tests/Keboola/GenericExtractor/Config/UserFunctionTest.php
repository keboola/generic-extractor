<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Config\UserFunction;

class UserFunctionTest extends ExtractorTestCase
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
