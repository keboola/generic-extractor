<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\GenericExtractor;
use Keboola\GenericExtractor\GenericExtractorJob;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Pagination\ResponseUrlScroller;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use Psr\Log\NullLogger;

class GenericExtractorJobTest extends ExtractorTestCase
{
    /**
     * @dataProvider parentIdProvider
     */
    public function testGetParentId(JobConfig $cfg, ?array $expected): void
    {
        $job = $this->createJob($cfg, [], []);

        self::assertEquals($expected, self::callMethod($job, 'getParentId', []));
    }

    public function parentIdProvider(): array
    {
        return [
            [
                new JobConfig(
                    [
                        'endpoint' => 'ep',
                        'userData' => [
                            'k' => 'v',
                        ],
                    ]
                ),
                [
                    'k' => 'v',
                ],
            ],
            [
                new JobConfig(
                    [
                        'endpoint' => 'ep',
                    ]
                ),
                null,
            ],
            [
                new JobConfig(
                    [
                        'endpoint' => 'ep',
                        'userData' => 'v',
                    ]
                ),
                [
                    'job_parent_id' => 'v',
                ],
            ],
            [
                new JobConfig(
                    [
                        'endpoint' => 'ep',
                        'userData' => [
                            'hash' => [
                                'function' => 'md5',
                                'args' => [
                                    'a',
                                ],
                            ],
                        ],
                    ]
                ),
                [
                    'hash' => md5('a'),
                ],
            ],
        ];
    }

    public function testUserParentId(): void
    {
        $value = ['parent' => 'val'];
        $job = $this->createJob(
            new JobConfig(
                [
                    'endpoint' => 'ep',
                ]
            ),
            [],
            []
        );
        $job->setUserParentId($value);

        self::assertEquals($value, self::callMethod($job, 'getParentId', []));
    }

    public function testUserParentIdMerge(): void
    {
        $job = $this->createJob(
            new JobConfig(
                [
                    'endpoint' => 'ep',
                    'userData' => [
                        'cfg' => 'cfgVal',
                        'both' => 'cfgVal',
                    ],
                ]
            ),
            [],
            []
        );
        $job->setUserParentId(
            [
                'inj' => 'injVal',
                'both' => 'injVal',
            ]
        );

        self::assertEquals(
            [
                'cfg' => 'cfgVal',
                'both' => 'cfgVal',
                'inj' => 'injVal',
            ],
            self::callMethod($job, 'getParentId', [])
        );
    }

    public function testFirstPage(): void
    {
        $cfg = new JobConfig(
            [
                'endpoint' => 'ep',
                'params' => [
                    'first' => 1,
                ],
            ]
        );
        $job = $this->createJob($cfg, [], []);

        $req = self::callMethod($job, 'firstPage', [$cfg]);
        self::assertEquals('ep', $req->getEndpoint());
    }

    /**
     * @dataProvider nextPageProvider
     */
    public function testNextPage(array $config, array $expectedParams): void
    {
        $cfg = new JobConfig(
            [
                'endpoint' => 'ep',
                'params' => [
                    'first' => 1,
                ],
            ]
        );
        $job = $this->createJob($cfg, [], [], new ResponseUrlScroller($config, new NullLogger()));
        self::callMethod($job, 'buildParams', [$cfg]);

        $response = new \stdClass();
        $response->nextPage = 'http://example.com/api/ep?something=2';
        $response->results = [1, 2];

        $req = self::callMethod(
            $job,
            'nextPage',
            [
                $cfg,
                $response,
                $response->results,
            ]
        );

        self::assertEquals($response->nextPage, $req->getEndpoint());
        self::assertEquals($expectedParams, $req->getParams());
    }

    public function nextPageProvider(): array
    {
        return [
            [['urlKey' => 'nextPage', 'includeParams' => true], ['first' => 1]],
            [['urlKey' => 'nextPage'], []],
        ];
    }

    public function testBuildParams(): void
    {
        $cfg = new JobConfig(
            [
                'endpoint' => 'fooBar',
                'params' => [
                    'timeframe' => 'this_24_hours',
                    'filters' => [
                        'function' => 'concat',
                        'args' => [
                            [
                                'function' => 'date',
                                'args' => [
                                    'Y-m-d',
                                ],
                            ],
                            'string',
                            [
                                'attr' => 'das.attribute',
                            ],
                        ],
                    ],
                ],
            ]
        );
        $job = $this->createJob(
            $cfg,
            ['das.attribute' => 'something interesting'],
            [
                'time' => [
                    'previousStart' => 0,
                    'currentStart' => time(),
                ],
            ]
        );
        $params = self::callMethod(
            $job,
            'buildParams',
            [
                $cfg,
            ]
        );

        self::assertEquals(
            [
                'timeframe' => 'this_24_hours',
                'filters' => date('Y-m-d') . 'stringsomething interesting',
            ],
            $params
        );
    }

    public function testBuildParamsException(): void
    {
        $cfg = new JobConfig(
            [
                'endpoint' => 'fooBar',
                'params' => [
                    'filters' => [
                        'function' => 'date',
                    ],
                ],
            ]
        );
        $job = $this->createJob(
            $cfg,
            ['das.attribute' => 'something interesting'],
            [
                'time' => [
                    'previousStart' => 0,
                    'currentStart' => time(),
                ],
            ]
        );
        $this->expectException(UserException::class);
        $this->expectExceptionMessage('User script error: date() expects at least 1 parameter, 0 given');
        self::callMethod($job, 'buildParams', [$cfg]);
    }

    public function testFilterResponse(): void
    {
        $cfg = new JobConfig(
            [
                'endpoint' => 'fooBar',
                'responseFilter' => 'complexItem',
            ]
        );

        $job = $this->createJob($cfg, [], []);

        $data = [
            (object) [
                'simpleItem' => 1,
                'complexItem' => (object) [
                    'data' => [1, 2, 3],
                ],
                'anotherItem' => (object) [
                    'id' => 1,
                    'data' => [4, 5, 6],
                ],
            ],
        ];

        $filtered = self::callMethod($job, 'filterResponse', [$cfg, $data]);

        self::assertTrue(is_scalar($filtered[0]->complexItem));
        self::assertEquals($data[0]->anotherItem, $filtered[0]->anotherItem);
    }

    public function testRun(): void
    {
        $jobConfig = new JobConfig(['endpoint' => 'ep']);
        $parser = new Json(new NullLogger(), [], Json::LATEST_VERSION);
        $job = $this->createJob($jobConfig, [], [], null, $parser);
        $job->run();

        self::assertCount(1, $parser->getResults());
        self::assertContainsOnlyInstancesOf('\Keboola\CsvTable\Table', $parser->getResults());
    }

    protected function createJob(
        JobConfig $config,
        array $attributes = [],
        array $metadata = [],
        ?ScrollerInterface $scroller = null,
        ?ParserInterface $parser = null
    ): GenericExtractorJob {
        $logger = new NullLogger();
        $scroller = $scroller ?? new NoScroller();
        $parser = $parser ?? new Json($logger, [], Json::LATEST_VERSION);
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('[{"result": "data"}]')
            ->setBaseUri('http://example.com/api/')
            ->getRestClient();
        return new GenericExtractorJob(
            $config,
            $restClient,
            $parser,
            $logger,
            $scroller,
            $attributes,
            $metadata,
            GenericExtractor::COMPAT_LEVEL_LATEST
        );
    }
}
