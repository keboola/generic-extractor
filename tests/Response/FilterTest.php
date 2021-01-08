<?php

namespace Keboola\GenericExtractor\Tests\Response;

use Keboola\GenericExtractor\GenericExtractor;
use Keboola\GenericExtractor\Response\Filter;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function testRun(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'id' => 1,
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'in' => 'string'
                        ],
                        (object) [
                            'in' => [(object) ['array' => 'of objects!']]
                        ]
                    ]
                ]
            ]
        ];

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'out' => (object) [
                        'arr' => [
                            (object) [
                                'in' => '"string"'
                            ],
                            (object) [
                                'in' => '[{"array":"of objects!"}]'
                            ]
                        ]
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testArray(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[]'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'id' => 1,
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'in' => 'object'
                        ],
                        (object) [
                            'in' => [(object) ['array' => 'of objects!']]
                        ],
                        "string"
                    ]
                ]
            ]
        ];

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'out' => (object) [
                        'arr' => [
                            '{"in":"object"}',
                            '{"in":[{"array":"of objects!"}]}',
                            '"string"'
                        ]
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testMissingData(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'id' => 1,
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'in' => 'string'
                        ],
                        (object) [
                            'uh' => 'no "in" here!'
                        ],
                        (object) [
                            'in' => ['str','ing']
                        ]
                    ]
                ]
            ]
        ];

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'out' => (object) [
                        'arr' => [
                            (object) [
                                'in' => '"string"'
                            ],
                            (object) [
                                'uh' => 'no "in" here!' // <- correct because this is not filtered prop!
                            ],
                            (object) [
                                'in' => '["str","ing"]'
                            ]
                        ]
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testMultipleFilters(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => ['out.arr[]', 'out.in']
        ]);
        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'id' => 1,
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'in' => 'string'
                        ],
                        (object) [
                            'in' => [(object) ['array' => 'of objects!']]
                        ]
                    ],
                    'in' => 'string'
                ]
            ],
            (object) [
                'id' => 2,
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'something' => [(object) ['more' => 'objects!']]
                        ]
                    ],
                    'in' => (object) ['second' => 'object']
                ]
            ]
        ];

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'out' => (object) [
                        'arr' => [
                            '{"in":"string"}',
                            '{"in":[{"array":"of objects!"}]}'
                        ],
                        'in' => '"string"'
                    ]
                ],
                (object) [
                    'id' => 2,
                    'out' => (object) [
                        'arr' => [
                            '{"something":[{"more":"objects!"}]}'
                        ],
                        'in' => '{"second":"object"}'
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testDelimiter(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out/in',
            'responseFilterDelimiter' => '/'
        ]);
        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'i.d' => 1,
                'out' => (object) ['in' => ['string']]
            ]
        ];

        self::assertEquals(
            [
                (object) [
                    'i.d' => 1,
                    'out' => (object) [
                        'in' => '["string"]'
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testNestedArrays(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].arr2[]'
        ]);
        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

        $data = [
            (object) [
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'arr2' => [1,[2],(object) ['three' => 3]]
                        ]
                    ]
                ]
            ]
        ];

        self::assertEquals(
            [
            (object) [
                'out' => (object) [
                    'arr' => [
                        (object) [
                            'arr2' => [1,'[2]','{"three":3}']
                        ]
                    ]
                ]
            ]
            ],
            $filter->run($data)
        );
    }

    public function testRunEmptyValuesLegacy(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY_SCALAR);
        $data = json_decode(
            '[
                {
                    "id": 1,
                    "data": false
                },
                {
                    "id": 2,
                    "data": 0
                },
                {
                    "id": 3,
                    "data": null
                },
                {
                    "id": 4,
                    "data": ""
                },
                {
                    "id": 5,
                    "data": []
                },
                {
                    "id": 6,
                    "data": ["something"]
                },
                {
                    "id": 7,
                    "data": [42]
                }
            ]'
        );

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'data' => false
                ],
                (object) [
                    'id' => 2,
                    'data' => 0
                ],
                (object) [
                    'id' => 3,
                    'data' => null
                ],
                (object) [
                    'id' => 4,
                    'data' => ""
                ],
                (object) [
                    'id' => 5,
                    'data' => []
                ],
                (object) [
                    'id' => 6,
                    'data' => "[\"something\"]"
                ],
                (object) [
                    'id' => 7,
                    'data' => "[42]"
                ],
            ],
            $filter->run($data)
        );
    }

    public function testRunEmptyValuesFilterLatest(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);
        $data = json_decode(
            '[
                {
                    "id": 1,
                    "data": false
                },
                {
                    "id": 2,
                    "data": 0
                },
                {
                    "id": 3,
                    "data": null
                },
                {
                    "id": 4,
                    "data": ""
                },
                {
                    "id": 5,
                    "data": []
                },
                {
                    "id": 6,
                    "data": ["something"]
                },
                {
                    "id": 7,
                    "data": [42]
                }
            ]'
        );

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'data' => 'false'
                ],
                (object) [
                    'id' => 2,
                    'data' => '0'
                ],
                (object) [
                    'id' => 3,
                    'data' => 'null'
                ],
                (object) [
                    'id' => 4,
                    'data' => '""'
                ],
                (object) [
                    'id' => 5,
                    'data' => '[]'
                ],
                (object) [
                    'id' => 6,
                    'data' => "[\"something\"]"
                ],
                (object) [
                    'id' => 7,
                    'data' => "[42]"
                ],
            ],
            $filter->run($data)
        );
    }

    public function testRunEmptyValuesArrayLegacy(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data[]'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY_SCALAR);
        $data = json_decode(
            '[
                {
                    "id": 1,
                    "data": [0, false, []]
                },
                {
                    "id": 2,
                    "data": ["foo", 0, ["bar"]]
                }
            ]'
        );

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'data' => [0, false, '[]']
                ],
                (object) [
                    'id' => 2,
                    'data' => ["foo", 0, '["bar"]']
                ],
            ],
            $filter->run($data)
        );
    }

    public function testRunEmptyValuesArrayLatest(): void
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data[]'
        ]);

        $filter = new Filter($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);
        $data = json_decode(
            '[
                {
                    "id": 1,
                    "data": [0, false, []]
                },
                {
                    "id": 2,
                    "data": ["foo", 0, ["bar"]]
                }
            ]'
        );

        self::assertEquals(
            [
                (object) [
                    'id' => 1,
                    'data' => ['0', 'false', '[]']
                ],
                (object) [
                    'id' => 2,
                    'data' => ['"foo"', '0', '["bar"]']
                ],
            ],
            $filter->run($data)
        );
    }
}
