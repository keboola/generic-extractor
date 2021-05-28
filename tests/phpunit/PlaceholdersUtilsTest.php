<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\PlaceholdersUtils;

class PlaceholdersUtilsTest extends ExtractorTestCase
{
    /**
     * @dataProvider placeholderProvider
     * @param mixed $field
     * @param mixed $expectedValue
     */
    public function testGetPlaceholder($field, $expectedValue): void
    {
        $value = PlaceholdersUtils::getPlaceholder(
            '1:id',
            $field,
            [
                (object) [
                    'field' => 'data',
                    'id' => '1:1',
                ],
            ]
        );

        self::assertEquals(
            [
                'placeholder' => '1:id',
                'field' => 'id',
                'value' => $expectedValue,
            ],
            $value
        );
    }

    public function placeholderProvider(): array
    {
        return [
            'function' => [
                [
                    'path' => 'id',
                    'function' => 'urlencode',
                    'args' => [
                        ['placeholder' => 'value'],
                    ],
                ],
                '1%3A1',
            ],
            'scalar' => [
                'id',
                '1:1',
            ],
        ];
    }

    /**
     * @dataProvider placeholderValueProvider
     * @param mixed $level
     * @param mixed $expected
     */
    public function testGetPlaceholderValue($level, $expected): void
    {
        $value = PlaceholdersUtils::getPlaceholderValue(
            'id',
            [
                0 => ['id' => 123],
                1 => ['id' => 456],
            ],
            $level,
            '1:id',
        );

        self::assertEquals($expected, $value);
    }

    /**
     * @dataProvider placeholderErrorValueProvider
     * @param mixed $data
     * @param mixed $message
     */
    public function testGetPlaceholderValueError($data, $message): void
    {
        try {
            PlaceholdersUtils::getPlaceholderValue(
                'id',
                $data,
                0,
                '1:id',
            );
            self::fail('UserException was not thrown');
        } catch (UserException $e) {
            self::assertEquals($message, $e->getMessage());
        }
    }

    public function placeholderErrorValueProvider(): array
    {
        return [
            [[], 'Level 1 not found in parent results! Maximum level: 0'],
            [[0 => ['noId' => 'noVal']], 'No value found for 1:id in parent result. (level: 1)'],
        ];
    }

    public function placeholderValueProvider(): array
    {
        return [
            [
                0,
                123,
            ],
            [
                1,
                456,
            ],
        ];
    }

    /**
     * @dataProvider paramsProvider
     */
    public function testConvertParamsPerChildJob(array $input, array $expected): void
    {
        self::assertEquals(PlaceholdersUtils::getParamsPerChildJob($input), $expected);
    }

    public function paramsProvider(): iterable
    {
        yield 'one-scalar' => [
            [
                [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => 123,
                ],
            ],
            [
                // 1 child job with 1 parameter
                [
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                ],
            ],
        ];

        yield 'one-array' => [
            [
                [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => [123, 456, 789],
                ],
            ],
            [
                // 3 child job with 1 parameter
                [
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                ],
                [
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                ],
                [
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 789,
                    ],
                ],
            ],
        ];

        yield 'scalar+array' => [
            [
                [
                    'placeholder' => '1:category',
                    'field' => 'category',
                    'value' => 'other',
                ],
                [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => [123, 456, 789],
                ],
            ],
            [
                // 3 child job with 2 parameters
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 789,
                    ],
                ],
            ],
        ];

        yield 'multiple' => [
            [
                [
                    'placeholder' => '1:category',
                    'field' => 'category',
                    'value' => 'other',
                ],
                [
                    'placeholder' => '2:region',
                    'field' => 'region',
                    'value' => ['sk', 'cz'],
                ],
                [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => [123, 456],
                ],
                [
                    'placeholder' => '1:tags',
                    'field' => 'tags',
                    'value' => ['tag1', 'tag2'],
                ],
            ],
            [
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'sk',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag1',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'sk',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag2',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'sk',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag1',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'sk',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag2',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'cz',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag1',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'cz',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 123,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag2',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'cz',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag1',
                    ],
                ],
                [
                    [
                        'placeholder' => '1:category',
                        'field' => 'category',
                        'value' => 'other',
                    ],
                    [
                        'placeholder' => '2:region',
                        'field' => 'region',
                        'value' => 'cz',
                    ],
                    [
                        'placeholder' => '1:id',
                        'field' => 'id',
                        'value' => 456,
                    ],
                    [
                        'placeholder' => '1:tags',
                        'field' => 'tags',
                        'value' => 'tag2',
                    ],
                ],
            ],
        ];
    }
}
