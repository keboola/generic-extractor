<?php

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\MissingTableHelper;
use Keboola\Juicer\Config\Config;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;

class MissingTableHelperTest extends TestCase
{
    public function testMissingTables()
    {
        $temp = new Temp();
        $config = [
            'jobs' => [
                [
                    'endpoint' => 'users',
                    'dataType' => 'users',
                ],
            ],
            'outputBucket' => 'mock-server',
            'incremental' => true,
            'mappings' => [
                'users' => [
                    'id' => [
                        'type' => 'column',
                        'mapping' => [
                            'destination' => 'id',
                            'primaryKey' => true,
                        ],
                    ],
                    'name' => [
                        'mapping' => [
                            'destination' => 'name',
                        ],
                    ],
                    'contacts' => [
                        'type' => 'table',
                        'destination' => 'user-contact',
                        'parentKey' => [
                            'primaryKey' => true,
                            'destination' => 'userId',
                        ],
                        'tableMapping' => [
                            'email' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'email',
                                ],
                            ],
                            'phone' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'phone',
                                ],
                            ],
                        ],
                    ],
                    'contacts.addresses.0' => [
                        'type' => 'table',
                        'destination' => 'primary-address',
                        'tableMapping' => [
                            'street' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'street',
                                ],
                            ],
                            'country' => [
                                'type' => 'column',
                                'mapping' => [
                                    'destination' => 'country',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $configs = [new Config($config)];
        $temp->initRunFolder();

        mkdir($temp->getTmpFolder() . '/out/');
        $baseDir = $temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        MissingTableHelper::checkConfigs($configs, $temp->getTmpFolder());
        self::assertFileExists($baseDir . 'mock-server.primary-address');
        self::assertFileExists($baseDir . 'mock-server.primary-address.manifest');
        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');

        self::assertEquals(
            '"street","country","users_pk"',
            trim(file_get_contents($baseDir . 'mock-server.primary-address'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.primary-address', 'incremental' => true],
            json_decode(file_get_contents($baseDir . 'mock-server.primary-address.manifest'), true)
        );
        self::assertEquals(
            '"email","phone","userId"',
            trim(file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => true,
                'primary_key' => ['userId'],
            ],
            json_decode(file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim(file_get_contents($baseDir . 'mock-server.users'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.users', 'incremental' => true, 'primary_key' => ['id']],
            json_decode(file_get_contents($baseDir . 'mock-server.users.manifest'), true)
        );
    }
}
