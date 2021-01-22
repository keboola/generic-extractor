<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\GenericExtractor;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Parser\Json;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenericExtractorTest extends TestCase
{
    /**
     * No change to JSON parser structure should happen when nothing is parsed!
     */
    public function testRunMetadataNoUpdate(): void
    {
        $meta = [
            'json_parser.struct' => [
                'data' => [
                    '_get' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            'headerNames' => 'data',
                            '_channel' => [
                                'nodeType' => 'scalar',
                            ],
                            '_source' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                    ],
                ],
            ],
            'json_parser.structVersion' => 3,
            'time' => [
                'previousStart' => 123,
            ],
        ];

        $cfg = new Config(['jobs' => [['endpoint' => 'get']]]);
        $api = new Api(new NullLogger(), ['baseUrl' => 'http://example.com/'], [], []);
        $ex = new GenericExtractor(new Temp(), new NullLogger(), $api);

        $ex->setMetadata($meta);
        try {
            $ex->run($cfg);
        } catch (UserException $e) {
        }
        $after = $ex->getMetadata();

        self::assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
        self::assertArrayHasKey('time', $after);
    }

    public function testRunMetadataUpdate(): void
    {
        $meta = [
            'json_parser.struct' => [
                'data' => [
                    '_get' => [
                        'nodeType' => 'array',
                        '[]' => [
                            'nodeType' => 'object',
                            'headerNames' => 'data',
                            '_channel' => [
                                'nodeType' => 'scalar',
                            ],
                            '_source' => [
                                'nodeType' => 'scalar',
                            ],
                        ],
                    ],
                ],
            ],
            'json_parser.structVersion' => 3,
            'time' => [
                'previousStart' => 123,
            ],
        ];

        $cfg = new Config(['jobs' => [['endpoint' => 'get']]]);
        $api = new Api(new NullLogger(), ['baseUrl' => 'http://private-834388-extractormock.apiary-mock.com/'], [], []);
        $ex = new GenericExtractor(new Temp(), new NullLogger(), $api);

        $ex->setMetadata($meta);
        $ex->run($cfg);
        $after = $ex->getMetadata();

        $meta['json_parser.struct'] = [
            'data' => [
                '_get' => [
                    'nodeType' => 'array',
                    '[]' => [
                        'nodeType' => 'object',
                        'headerNames' => 'data',
                        '_id' => [
                            'nodeType' => 'scalar',
                            'headerNames' => 'id',
                        ],
                        '_status' => [
                            'nodeType' => 'scalar',
                            'headerNames' => 'status',
                        ],
                        '_channel' => [
                            'nodeType' => 'scalar',
                            'headerNames' => 'channel',
                        ],
                        '_source' => [
                            'nodeType' => 'scalar',
                            'headerNames' => 'source',
                        ],
                    ],
                ],
            ],
            'parent_aliases' => [

            ],
        ];
        self::assertEquals($meta['json_parser.struct'], $after['json_parser.struct']);
        self::assertArrayHasKey('time', $after);
    }

    public function testGetParser(): void
    {
        $parser = new Json(new NullLogger(), [], Json::LATEST_VERSION);
        $api = new Api(new NullLogger(), ['baseUrl' => 'http://example.com'], [], []);
        $extractor = new GenericExtractor(new Temp(), new NullLogger(), $api);
        $extractor->setParser($parser);
        self::assertEquals($parser, $extractor->getParser());
    }
}
