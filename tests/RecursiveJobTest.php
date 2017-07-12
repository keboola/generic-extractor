<?php

namespace Keboola\GenericExtractor\Tests;

use Keboola\Code\Builder;
use Keboola\GenericExtractor\Configuration\Extractor;
use Keboola\GenericExtractor\GenericExtractorJob;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use GuzzleHttp\Subscriber\History;
use Psr\Log\NullLogger;

class RecursiveJobTest extends ExtractorTestCase
{
    public function testParse()
    {
        /** @var Json $parser */
        list($job, $parser) = $this->getJob('simple_basic');

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

        self::callMethod($job, 'parse', [$response->data, ['userData' => 'hello']]);

        self::assertEquals(
            ['tickets_export', 'tickets_export_c'],
            array_keys($parser->getResults())
        );

        self::assertFileEquals(
            __DIR__ . '/data/recursiveJobParseResults/tickets_export',
            $parser->getResults()['tickets_export']->getPathname()
        );
        self::assertFileEquals(
            __DIR__ . '/data/recursiveJobParseResults/tickets_export_c',
            $parser->getResults()['tickets_export_c']->getPathname()
        );
    }

    /**
     * Test the correct placeholder is used if two levels have identical one
     */
    public function testSamePlaceholder()
    {
        list($job, $parser, $jobConfig) = $this->getJob('recursive_same_ph');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = self::callMethod(
            $job,
            'createChild',
            [
                $child,
                [0 => ['id' => 123]]
            ]
        );

        self::assertEquals('root/123', self::getProperty($childJob, 'config')->getEndpoint());

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = self::callMethod(
            $childJob,
            'createChild',
            [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        self::assertEquals('root/123/456', self::getProperty($grandChildJob, 'config')->getEndpoint());
    }

    public function testCreateChild()
    {
        list($job, $parser, $jobConfig) = $this->getJob('recursive');

        $children = $jobConfig->getChildJobs();
        $child = reset($children);

        $childJob = self::callMethod(
            $job,
            'createChild',
            [
                $child,
                [0 => ['id' => 123]]
            ]
        );

        self::assertEquals(
            [
                '1:id' => [
                    'placeholder' => '1:id',
                    'field' => 'id',
                    'value' => 123
                ]
            ],
            self::getProperty($childJob, 'parentParams')
        );

        self::assertEquals('comments', self::callMethod($childJob, 'getDataType', []));

        $grandChildren = $child->getChildJobs();
        $grandChild = reset($grandChildren);
        $grandChildJob = self::callMethod(
            $childJob,
            'createChild',
            [
                $grandChild,
                [0 => ['id' => 456], 1 => ['id' => 123]]
            ]
        );

        // Ensure the IDs from 2 parent levels are properly mapped
        $values = self::getProperty($grandChildJob, 'parentParams');
        self::assertEquals(456, $values['id']['value']);
        self::assertEquals(123, $values['2:id']['value']);
        // Check the dataType from endpoint has placeholders not replaced by values
        self::assertEquals('third/level/{2:id}/{id}.json', self::callMethod($grandChildJob, 'getDataType', []));

        self::assertEquals('third/level/123/456.json', self::getProperty($grandChildJob, 'config')->getEndpoint());
    }



    /**
     * I'm not too sure this is optimal!
     * If it looks stupid, but works, it ain't stupid!
     * @param string $dir
     * @return array
     */
    public function getJob($dir)
    {
        $temp = new Temp('recursion');
        $configuration = new Extractor(__DIR__ . '/data/' . $dir, new NullLogger());

        $jobConfig = array_values($configuration->getMultipleConfigs()[0]->getJobs())[0];

        $parser = Json::create($configuration->getMultipleConfigs()[0], new NullLogger(), $temp);

        $client = RestClient::create(new NullLogger());

        $history = new History();
        $client->getClient()->getEmitter()->attach($history);

        $job = new GenericExtractorJob($jobConfig, $client, $parser, new NullLogger());
        $job->setBuilder(new Builder());
        /** @var GenericExtractorJob $job */
        $job->setScroller(new NoScroller());

        return [
            $job,
            $parser,
            $jobConfig
        ];
    }
}
