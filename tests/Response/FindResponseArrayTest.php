<?php

namespace Keboola\GenericExtractor\Tests\Response;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Response\FindResponseArray;
use Keboola\Juicer\Config\JobConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FindResponseArrayTest extends TestCase
{
    public function testSingleArray()
    {
        $cfg = new JobConfig([
            'endpoint' => 'a',
            'dataField' => 'results'
        ]);

        $module = new FindResponseArray(new NullLogger());

        $response = (object) [
            'results' => [
                (object) ['id' => 1],
                (object) ['id' => 2]
            ],
            'otherArray' => ['a','b']
        ];

        $data = $module->process($response, $cfg);
        $this->assertEquals($response->{$cfg->getConfig()['dataField']}, $data);
    }

    public function testNestedArray()
    {
        $cfg = new JobConfig([
            'endpoint' => 'a',
            'dataField' => 'data.results'
        ]);

        $module = new FindResponseArray(new NullLogger());

        $response = (object) [
            'data' => (object) [
                'results' => [
                    (object) ['id' => 1],
                    (object) ['id' => 2]
                ]
            ],
            'otherArray' => ['a','b']
        ];

        $data = $module->process($response, $cfg);
        $this->assertEquals($response->data->results, $data);
    }

    public function testMultipleArraysException()
    {
        $cfg = new JobConfig([
            'endpoint' => 'a'
        ]);

        $module = new FindResponseArray(new NullLogger());

        $response = (object) [
            'results' => [
                (object) ['id' => 1],
                (object) ['id' => 2]
            ],
            'otherArray' => ['a','b']
        ];

        $this->expectException(UserException::class);
        $this->expectExceptionMessage(
            "More than one array found in response! Use 'dataField' parameter to specify a key to the data array. " .
            "(endpoint: a, arrays in response root: results, otherArray)"
        );
        $module->process($response, $cfg);
    }
}
