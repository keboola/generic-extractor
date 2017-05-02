<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Response\Filter;
use Keboola\Juicer\Config\JobConfig;

class FilterTest extends ExtractorTestCase
{
    public function testRun()
    {
        $jobConfig = JobConfig::create([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = Filter::create($jobConfig);

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
        $jobConfig = JobConfig::create([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[]'
        ]);

        $filter = Filter::create($jobConfig);

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
        $jobConfig = JobConfig::create([
            'endpoint' => 'ep',
            'responseFilter' => 'out.arr[].in'
        ]);

        $filter = Filter::create($jobConfig);

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
}
