<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\GenericExtractorJob;
use Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Pagination\ResponseUrlScroller,
    Keboola\Juicer\Config\Config,
    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use Keboola\Utils\Utils;
use Keboola\Code\Builder;
// use GuzzleHttp\Client;

class GenericExtractorJobTest extends ExtractorTestCase
{
    /**
     * @dataProvider parentIdProvider
     */
    public function testGetParentId($cfg, $expected)
    {
        $job = $this->getJob($cfg);

        self::assertEquals($expected, self::callMethod($job, 'getParentId', []));
    }

    public function parentIdProvider()
    {
        return [
            [
                JobConfig::create([
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
                JobConfig::create([
                    'endpoint' => 'ep'
                ]),
                null
            ],
            [
                JobConfig::create([
                    'endpoint' => 'ep',
                    'userData' => 'v'
                ]),
                [
                    'job_parent_id' => 'v'
                ]
            ],
            [
                JobConfig::create([
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
        $job = $this->getJob(JobConfig::create([
            'endpoint' => 'ep'
        ]));
        $job->setUserParentId($value);

        self::assertEquals($value, self::callMethod($job, 'getParentId', []));
    }

    public function testUserParentIdMerge()
    {
        $job = $this->getJob(JobConfig::create([
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
        $cfg = JobConfig::create([
            'endpoint' => 'ep',
            'params' => [
                'first' => 1
            ]
        ]);
        $job = $this->getJob($cfg);

        $req = self::callMethod($job, 'firstPage', [$cfg]);
        self::assertEquals('ep', $req->getEndpoint());
    }

    public function testNextPage()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'ep',
            'params' => [
                'first' => 1
            ]
        ]);
        $job = $this->getJob($cfg);

        $job->setScroller(new ResponseUrlScroller(['urlKey' => 'nextPage']));

        $response = new \stdClass();
        $response->nextPage = "http://example.com/api/ep?something=2";
        $response->results = [1, 2];

        $req = self::callMethod($job, 'nextPage', [
            $cfg,
            $response,
            $response->results
        ]);

        self::assertEquals('http://example.com/api/ep?something=2', $req->getEndpoint());
    }

    /**
     * doesn't work because params are set in run!
     */
    public function testNextPageWithParam()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'ep',
            'params' => [
                'first' => 1
            ]
        ]);
        $job = $this->getJob($cfg);

        $job->setScroller(new ResponseUrlScroller(['urlKey' => 'nextPage', 'includeParams' => true]));

        $response = new \stdClass();
        $response->nextPage = "http://example.com/api/ep?something=2";
        $response->results = [1, 2];

        $req = self::callMethod($job, 'nextPage', [
            $cfg,
            $response,
            $response->results
        ]);

        self::assertEquals('http://example.com/api/ep?something=2', $req->getEndpoint());
    }

    public function testBuildParams()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'ep',
            'params' => Utils::json_decode('{
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

        $cfg = JobConfig::create([
            'endpoint' => 'ep',
            'params' => Utils::json_decode('{
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

        $params = self::callMethod($job, 'buildParams', [
            $cfg
        ]);
    }

    public function testFilterResponse()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'ep',
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

    protected function getJob(JobConfig $config)
    {
        return new GenericExtractorJob(
            $config,
            RestClient::create([
                'base_url' => 'http://example.com/api/'
            ]),
            Json::create(
                new Config('ex-generic-test', 'test', []),
                $this->getLogger(),
                new Temp()
            )
        );
    }
}
