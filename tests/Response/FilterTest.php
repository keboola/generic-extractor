<?php

namespace Keboola\GenericExtractor\Tests\Response;

use Keboola\GenericExtractor\GenericExtractor;
use Keboola\GenericExtractor\Response\Filter;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function testRun()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

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
                                'in' => 'string'
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

    public function testArray()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[]'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

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
                            "string"
                        ]
                    ]
                ]
            ],
            $filter->run($data)
        );
    }

    public function testMissingData()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);

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
                                'in' => 'string'
                            ],
                            (object) [
                                'uh' => 'no "in" here!'
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

    public function testMultipleFilters()
    {
        $filter = new Filter(['out.arr[]', 'out.in']);

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
                        'in' => 'string'
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

    public function testDelimiter()
    {
        $filter = new Filter(['out/in'], '/');

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

    public function testNestedArrays()
    {
        $filter = new Filter(['out.arr[].arr2[]']);

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

    public function testRunEmptyValuesLegacy()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY);
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

    public function testRunEmptyValuesFilterScalar()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_SCALAR);
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
                    'data' => 'null'
                ],
                (object) [
                    'id' => 4,
                    'data' => ""
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

    public function testRunEmptyValuesFilterLatest()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);
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

    public function testRunEmptyValuesArrayLegacy()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data[]'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY);
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

    public function testRunEmptyValuesArrayScalar()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data[]'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_FILTER_SCALAR);
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

    public function testRunEmptyValuesArrayLatest()
    {
        $jobConfig = new JobConfig([
            'endpoint' => 'ep',
            'responseFilter' => 'data[]'
        ]);

        $filter = Filter::create($jobConfig, GenericExtractor::COMPAT_LEVEL_LATEST);
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
