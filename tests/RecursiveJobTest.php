<?php

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\GenericExtractorJob;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RecursiveJobTest extends TestCase
{
    public function testParse()
    {
        $temp = new Temp();
        $jobConfig = new JobConfig([
            "id" => "multiCfg",
            "endpoint" => "exports/tickets.json",
            "dataType" => "tickets_export",
            'userData' => ['userData' => 'hello']
        ]);
        $parser = new Json(new NullLogger(), $temp);
        $response = json_decode('{
            "data": [
                {
                    "a": "first",
                    "id": 1,
                    "c": ["jedna","one",1]
                },
                {
                    "a": "second",
                    "id": 2,
                    "c": ["dva","two",2]
                }
            ]
        }');
        $client = self::createMock(RestClient::class);
        $client->method('download')->willReturn($response);
        $client->method('createRequest')->willReturn(new RestRequest($jobConfig->getConfig()));
        /** @var RestClient $client */
        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger(), new NoScroller(), [], []);
        $job->run();
        self::assertEquals(
            ['tickets_export', 'tickets_export_c'],
            array_keys($parser->getResults())
        );

        self::assertEquals(
            '"a","id","c","userData"' . "\n" .
            '"first","1","tickets_export_708eef46be0d529f9495cf672287fbb5","hello"' . "\n" .
            '"second","2","tickets_export_2e8ef466fbc672e6eb065306273f60f6","hello"' . "\n",
            file_get_contents($parser->getResults()['tickets_export']->getPathname())
        );
        self::assertEquals(
            '"data","JSON_parentId"'. "\n" .
            '"jedna","tickets_export_708eef46be0d529f9495cf672287fbb5"' . "\n" .
            '"one","tickets_export_708eef46be0d529f9495cf672287fbb5"'. "\n" .
            '"1","tickets_export_708eef46be0d529f9495cf672287fbb5"' . "\n" .
            '"dva","tickets_export_2e8ef466fbc672e6eb065306273f60f6"' . "\n" .
            '"two","tickets_export_2e8ef466fbc672e6eb065306273f60f6"' . "\n" .
            '"2","tickets_export_2e8ef466fbc672e6eb065306273f60f6"' . "\n",
            file_get_contents($parser->getResults()['tickets_export_c']->getPathname())
        );
    }

    public function testNestedPlaceholder()
    {
        $temp = new Temp();
        $jobConfig = new JobConfig([
            "id" => "first",
            "endpoint" => "first/",
            "dataType" => "first",
            "children" => [
                [
                    "id" => "second",
                    "endpoint" => "first/{first-id}",
                    "dataType" => "second",
                    "placeholders" => [
                        "first-id" => "id"
                    ],
                    "children" => [
                        [
                            "id" => "third",
                            "dataType" => "third",
                            "endpoint" => "first/{first-id}/second/{second-id}",
                            "placeholders" => [
                                "second-id" => "id"
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $parser = new Json(new NullLogger(), $temp);
        $client = self::createMock(RestClient::class);
        $passes = 0;
        $client->method('download')->willReturnCallback(function ($request) use (&$passes) {
            /** @var RestRequest $request */
            $passes++;
            switch ($request->getEndpoint()) {
                case 'first/':
                    return \Keboola\Utils\arrayToObject(["data" => [["id" => 123, "1st" => 1]]]);
                case 'first/123':
                    return \Keboola\Utils\arrayToObject(
                        ["data" => [["id" => 456, "2nd" => 2], ["id" => 789, "2nd" => 3]]]
                    );
                case 'first/123/second/456':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 4]]]);
                case 'first/123/second/789':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 5]]]);
                default:
                    throw new \RuntimeException("Invalid request " . $request->getEndpoint());
            }
        });
        $client->method('createRequest')->willReturnCallback(function ($config) {
            return new RestRequest($config);
        });
        /** @var RestClient $client */
        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger(), new NoScroller(), [], []);
        $job->run();
        self::assertEquals(
            ['first', 'second', 'third'],
            array_keys($parser->getResults())
        );

        self::assertEquals(4, $passes);
        self::assertEquals(
            "\"id\",\"1st\"\n\"123\",\"1\"\n",
            file_get_contents($parser->getResults()['first']->getPathname())
        );
        self::assertEquals(
            "\"id\",\"2nd\",\"parent_id\"\n\"456\",\"2\",\"123\"\n\"789\",\"3\",\"123\"\n",
            file_get_contents($parser->getResults()['second']->getPathname())
        );
        self::assertEquals(
            "\"3rd\",\"parent_id\"\n\"4\",\"456\"\n\"5\",\"789\"\n",
            file_get_contents($parser->getResults()['third']->getPathname())
        );
    }

    /**
     * Differently named placeholders, order 2-1, parent_id in result contains 2nd level id
     */
    public function testNestedSamePlaceholder1()
    {
        $temp = new Temp();
        $jobConfig = new JobConfig([
            "id" => "first",
            "endpoint" => "first/",
            "dataType" => "first",
            "children" => [
                [
                    "id" => "second",
                    "endpoint" => "first/{1:id}",
                    "dataType" => "second",
                    "placeholders" => [
                        "1:id" => "id"
                    ],
                    "children" => [
                        [
                            "id" => "third",
                            "dataType" => "third",
                            "endpoint" => "first/{2:id}/second/{1:first-id}",
                            "placeholders" => [
                                "2:id" => "id",
                                "1:first-id" => "id"
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $parser = new Json(new NullLogger(), $temp);
        $client = self::createMock(RestClient::class);
        $passes = 0;
        $client->method('download')->willReturnCallback(function ($request) use (&$passes) {
            /** @var RestRequest $request */
            $passes++;
            switch ($request->getEndpoint()) {
                case 'first/':
                    return \Keboola\Utils\arrayToObject(["data" => [["id" => 123, "1st" => 1]]]);
                case 'first/123':
                    return \Keboola\Utils\arrayToObject(
                        ["data" => [["id" => 456, "2nd" => 2], ["id" => 789, "2nd" => 3]]]
                    );
                case 'first/123/second/456':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 4]]]);
                case 'first/123/second/789':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 5]]]);
                default:
                    throw new \RuntimeException("Invalid request " . $request->getEndpoint());
            }
        });
        $client->method('createRequest')->willReturnCallback(function ($config) {
            return new RestRequest($config);
        });
        /** @var RestClient $client */
        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger(), new NoScroller(), [], []);
        $job->run();
        self::assertEquals(
            ['first', 'second', 'third'],
            array_keys($parser->getResults())
        );

        self::assertEquals(4, $passes);
        self::assertEquals(
            "\"id\",\"1st\"\n\"123\",\"1\"\n",
            file_get_contents($parser->getResults()['first']->getPathname())
        );
        self::assertEquals(
            "\"id\",\"2nd\",\"parent_id\"\n\"456\",\"2\",\"123\"\n\"789\",\"3\",\"123\"\n",
            file_get_contents($parser->getResults()['second']->getPathname())
        );
        self::assertEquals(
            "\"3rd\",\"parent_id\"\n\"4\",\"456\"\n\"5\",\"789\"\n",
            file_get_contents($parser->getResults()['third']->getPathname())
        );
    }

    /**
     * Differently named placeholders, order 1-2, parent_id in result contains 1st level id
     */
    public function testNestedSamePlaceholder2()
    {
        $temp = new Temp();
        $jobConfig = new JobConfig([
            "id" => "first",
            "endpoint" => "first/",
            "dataType" => "first",
            "children" => [
                [
                    "id" => "second",
                    "endpoint" => "first/{1:id}",
                    "dataType" => "second",
                    "placeholders" => [
                        "1:id" => "id"
                    ],
                    "children" => [
                        [
                            "id" => "third",
                            "dataType" => "third",
                            "endpoint" => "first/{2:id}/second/{1:first-id}",
                            "placeholders" => [
                                "1:first-id" => "id",
                                "2:id" => "id"
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $parser = new Json(new NullLogger(), $temp);
        $client = self::createMock(RestClient::class);
        $passes = 0;
        $client->method('download')->willReturnCallback(function ($request) use (&$passes) {
            /** @var RestRequest $request */
            $passes++;
            switch ($request->getEndpoint()) {
                case 'first/':
                    return \Keboola\Utils\arrayToObject(["data" => [["id" => 123, "1st" => 1]]]);
                case 'first/123':
                    return \Keboola\Utils\arrayToObject(
                        ["data" => [["id" => 456, "2nd" => 2], ["id" => 789, "2nd" => 3]]]
                    );
                case 'first/123/second/456':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 4]]]);
                case 'first/123/second/789':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 5]]]);
                default:
                    throw new \RuntimeException("Invalid request " . $request->getEndpoint());
            }
        });
        $client->method('createRequest')->willReturnCallback(function ($config) {
            return new RestRequest($config);
        });
        /** @var RestClient $client */
        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger(), new NoScroller(), [], []);
        $job->run();
        self::assertEquals(
            ['first', 'second', 'third'],
            array_keys($parser->getResults())
        );

        self::assertEquals(4, $passes);
        self::assertEquals(
            "\"id\",\"1st\"\n\"123\",\"1\"\n",
            file_get_contents($parser->getResults()['first']->getPathname())
        );
        self::assertEquals(
            "\"id\",\"2nd\",\"parent_id\"\n\"456\",\"2\",\"123\"\n\"789\",\"3\",\"123\"\n",
            file_get_contents($parser->getResults()['second']->getPathname())
        );
        self::assertEquals(
            "\"3rd\",\"parent_id\"\n\"4\",\"123\"\n\"5\",\"123\"\n",
            file_get_contents($parser->getResults()['third']->getPathname())
        );
    }

    /**
     * Same named placeholders, order 2-1, parent_id in result contains 1st level id (order does not matter)
     */
    public function testNestedSamePlaceholder3()
    {
        $temp = new Temp();
        $jobConfig = new JobConfig([
            "id" => "first",
            "endpoint" => "first/",
            "dataType" => "first",
            "children" => [
                [
                    "id" => "second",
                    "endpoint" => "first/{1:id}",
                    "dataType" => "second",
                    "placeholders" => [
                        "1:id" => "id"
                    ],
                    "children" => [
                        [
                            "id" => "third",
                            "dataType" => "third",
                            "endpoint" => "first/{2:id}/second/{1:id}",
                            "placeholders" => [
                                "2:id" => "id",
                                "1:id" => "id"
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        $parser = new Json(new NullLogger(), $temp);
        $client = self::createMock(RestClient::class);
        $passes = 0;
        $client->method('download')->willReturnCallback(function ($request) use (&$passes) {
            /** @var RestRequest $request */
            $passes++;
            switch ($request->getEndpoint()) {
                case 'first/':
                    return \Keboola\Utils\arrayToObject(["data" => [["id" => 123, "1st" => 1]]]);
                case 'first/123':
                    return \Keboola\Utils\arrayToObject(
                        ["data" => [["id" => 456, "2nd" => 2], ["id" => 789, "2nd" => 3]]]
                    );
                case 'first/123/second/456':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 4]]]);
                case 'first/123/second/789':
                    return \Keboola\Utils\arrayToObject(["data" => [["3rd" => 5]]]);
                default:
                    throw new \RuntimeException("Invalid request " . $request->getEndpoint());
            }
        });
        $client->method('createRequest')->willReturnCallback(function ($config) {
            return new RestRequest($config);
        });
        /** @var RestClient $client */
        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger(), new NoScroller(), [], []);
        $job->run();
        self::assertEquals(
            ['first', 'second', 'third'],
            array_keys($parser->getResults())
        );

        self::assertEquals(4, $passes);
        self::assertEquals(
            "\"id\",\"1st\"\n\"123\",\"1\"\n",
            file_get_contents($parser->getResults()['first']->getPathname())
        );
        self::assertEquals(
            "\"id\",\"2nd\",\"parent_id\"\n\"456\",\"2\",\"123\"\n\"789\",\"3\",\"123\"\n",
            file_get_contents($parser->getResults()['second']->getPathname())
        );
        self::assertEquals(
            "\"3rd\",\"parent_id\"\n\"4\",\"123\"\n\"5\",\"123\"\n",
            file_get_contents($parser->getResults()['third']->getPathname())
        );
    }
}
