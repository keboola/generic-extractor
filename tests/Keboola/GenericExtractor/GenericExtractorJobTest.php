<?php
namespace Keboola\GenericExtractor;

use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use Keboola\Code\Builder;
use Psr\Log\NullLogger;

class GenericExtractorJobTest extends ExtractorTestCase
{
    /**
     * @dataProvider parentIdProvider
     * @param JobConfig $cfg
     * @param string $expected
     */
    public function testGetParentId(JobConfig $cfg, $expected)
    {
        $job = $this->getJob($cfg);

        self::assertEquals($expected, self::callMethod($job, 'getParentId', []));
    }

    public function parentIdProvider()
    {
        return [
            [
                new JobConfig(1, [
                    'endpoint' => 'ep',
                    'userData' => [
                        'k' => 'v'
                    ]
                ]),
                [
                    'k' => 'v'
                ]
            ],
            [
                new JobConfig(1, [
                    'endpoint' => 'ep'
                ]),
                null
            ],
            [
                new JobConfig(1, [
                    'endpoint' => 'ep',
                    'userData' => 'v'
                ]),
                [
                    'job_parent_id' => 'v'
                ]
            ],
            [
                new JobConfig(1, [
                    'endpoint' => 'ep',
                    'userData' => [
                        'hash' => [
                            'function' => 'md5',
                            'args' => [
                                'a'
                            ]
                        ]
                    ]
                ]),
                [
                    'hash' => md5('a')
                ]
            ]
        ];
    }

    public function testUserParentId()
    {
        $value = ['parent' => 'val'];
        $job = $this->getJob(new JobConfig(1, [
            'endpoint' => 'ep'
        ]));
        $job->setUserParentId($value);

        self::assertEquals($value, self::callMethod($job, 'getParentId', []));
    }

    public function testUserParentIdMerge()
    {
        $job = $this->getJob(new JobConfig(1, [
            'endpoint' => 'ep',
            'userData' => [
                'cfg' => 'cfgVal',
                'both' => 'cfgVal'
            ]
        ]));
        $job->setUserParentId([
            'inj' => 'injVal',
            'both' => 'injVal'
        ]);

        self::assertEquals(
            [
                'cfg' => 'cfgVal',
                'both' => 'cfgVal',
                'inj' => 'injVal'
            ],
            self::callMethod($job, 'getParentId', [])
        );
    }

    public function testFirstPage()
    {
        $cfg = new JobConfig(1, [
            'endpoint' => 'ep',
            'params' => [
                'first' => 1
            ]
        ]);
        $job = $this->getJob($cfg);

        $req = self::callMethod($job, 'firstPage', [$cfg]);
        self::assertEquals('ep', $req->getEndpoint());
    }

    /**
     * @dataProvider nextPageProvider
     * @param array $config
     * @param array $expectedParams
     */
    public function testNextPage($config, $expectedParams)
    {
        $cfg = new JobConfig(1, [
            'endpoint' => 'ep',
            'params' => [
                'first' => 1
            ]
        ]);
        $job = $this->getJob($cfg);

        self::callMethod($job, 'buildParams', [$cfg]);

        $job->setScroller(new ResponseUrlScroller($config));

        $response = new \stdClass();
        $response->nextPage = "http://example.com/api/ep?something=2";
        $response->results = [1, 2];

        $req = self::callMethod($job, 'nextPage', [
            $cfg,
            $response,
            $response->results
        ]);

        self::assertEquals($response->nextPage, $req->getEndpoint());
        self::assertEquals($expectedParams, $req->getParams());
    }

    public function nextPageProvider()
    {
        return [
            [['urlKey' => 'nextPage', 'includeParams' => true], ['first' => 1]],
            [['urlKey' => 'nextPage'], []]
        ];
    }

    public function testBuildParams()
    {
        $cfg = new JobConfig(1, [
            'params' => \Keboola\Utils\jsonDecode('{
                "timeframe": "this_24_hours",
                "filters": {
                    "function": "concat",
                    "args": [
                        {
                            "function": "date",
                            "args": ["Y-m-d"]
                        },
                        "string",
                        {"attr": "das.attribute"}
                    ]
                }
            }')
        ]);

        $job = $this->getJob($cfg);
        $job->setAttributes([
            'das.attribute' => "something interesting"
        ]);
        $job->setMetadata([
            'time' => [
                'previousStart' => 0,
                'currentStart' => time()
            ]
        ]);
        $job->setBuilder(new Builder());

        $params = self::callMethod($job, 'buildParams', [
            $cfg
        ]);

        self::assertEquals([
            'timeframe' => 'this_24_hours',
            'filters' => date("Y-m-d") . 'stringsomething interesting'
        ], $params);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage User script error: date() expects at least 1 parameter, 0 given
     */
    public function testBuildParamsException()
    {

        $cfg = new JobConfig(1, [
            'params' => \Keboola\Utils\jsonDecode('{
                "filters": {
                    "function": "date"
                }
            }')
        ]);

        $job = $this->getJob($cfg);
        $job->setAttributes([
            'das.attribute' => "something interesting"
        ]);
        $job->setMetadata([
            'time' => [
                'previousStart' => 0,
                'currentStart' => time()
            ]
        ]);
        $job->setBuilder(new Builder());

        self::callMethod($job, 'buildParams', [
            $cfg
        ]);
    }

    public function testFilterResponse()
    {
        $cfg = new JobConfig(1, [
            'responseFilter' => 'complexItem'
        ]);

        $job = $this->getJob($cfg);

        $data = [
            (object) [
                'simpleItem' => 1,
                'complexItem' => (object) [
                    'data' => [1,2,3]
                ],
                'anotherItem' => (object) [
                    'id' => 1,
                    'data' => [4,5,6]
                ]
            ]
        ];

        $filtered = self::callMethod($job, 'filterResponse', [$cfg, $data]);

        self::assertTrue(is_scalar($filtered[0]->complexItem));
        self::assertEquals($data[0]->anotherItem, $filtered[0]->anotherItem);
    }

    public function testRun()
    {
        $jobConfig = new JobConfig(1, [
            'endpoint' => 'ep'
        ]);

        $parser = Json::create(
            new Config('ex-generic-test', 'test', []),
            new NullLogger(),
            new Temp()
        );

        $job = $this->getMockBuilder(GenericExtractorJob::class)
            ->setMethods(['download'])
            ->setConstructorArgs([
                $jobConfig,
                RestClient::create(new NullLogger()),
                $parser,
                new NullLogger()
            ])
            ->getMock();

        $job->expects(self::once())
            ->method('download')
            ->willReturn([
                (object) ['result' => 'data']
            ]);

        /** @var GenericExtractorJob $job */
        $job->run();

        self::assertCount(1, $parser->getResults());
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getResults());
    }

    /**
     * @param JobConfig $config
     * @return GenericExtractorJob
     */
    protected function getJob(JobConfig $config)
    {
        return new GenericExtractorJob(
            $config,
            RestClient::create(
                new NullLogger(),
                ['base_url' => 'http://example.com/api/']
            ),
            Json::create(
                new Config('ex-generic-test', 'test', []),
                new NullLogger(),
                new Temp()
            ),
            new NullLogger()
        );
    }
}
