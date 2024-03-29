<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Configuration\Extractor;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\MissingTableHelper;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MissingTableHelperTest extends TestCase
{
    private Temp $temp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temp = new Temp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->temp->remove();
    }

    public function testMissingTables(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
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
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileExists($baseDir . 'mock-server.primary-address');
        self::assertFileExists($baseDir . 'mock-server.primary-address.manifest');
        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');

        self::assertEquals(
            '"street","country","users_pk"',
            trim((string) file_get_contents($baseDir . 'mock-server.primary-address'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.primary-address', 'incremental' => true],
            json_decode((string) file_get_contents($baseDir . 'mock-server.primary-address.manifest'), true)
        );
        self::assertEquals(
            '"email","phone","userId"',
            trim((string) file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => true,
                'primary_key' => ['userId'],
            ],
            json_decode((string) file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'mock-server.users'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.users', 'incremental' => true, 'primary_key' => ['id']],
            json_decode((string) file_get_contents($baseDir . 'mock-server.users.manifest'), true)
        );
    }

    public function testMissingTablesNoOverwrite(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
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
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        file_put_contents($baseDir . 'mock-server.users', 'foo');
        file_put_contents($baseDir . 'mock-server.users.manifest', 'bar');
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');
        self::assertEquals('foo', file_get_contents($baseDir . 'mock-server.users'));
        self::assertEquals('bar', file_get_contents($baseDir . 'mock-server.users.manifest'));
    }

    public function testMissingMappings(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
                    'mappings' => null,
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileDoesNotExist($baseDir . 'mock-server.users');
        self::assertFileDoesNotExist($baseDir . 'mock-server.users.manifest');
    }

    public function testMissingTablesSimplifiedMapping(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'mappings' => [
                        'users' => [
                            'id' => 'id',
                            'name' => 'name',
                            'contacts' => [
                                'type' => 'table',
                                'destination' => 'user-contact',
                                'tableMapping' => [
                                    'email' => 'email',
                                    'phone' => 'phone',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);

        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');

        self::assertEquals(
            '"email","phone","users_pk"',
            trim((string) file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => false,
            ],
            json_decode((string) file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'mock-server.users'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.users', 'incremental' => false],
            json_decode((string) file_get_contents($baseDir . 'mock-server.users.manifest'), true)
        );
    }

    public function testMissingTablesNoOutputBucket(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'mappings' => [
                        'users' => [
                            'id' => 'id',
                            'name' => 'name',
                            'contacts' => [
                                'type' => 'table',
                                'destination' => 'user-contact',
                                'tableMapping' => [
                                    'email' => 'email',
                                    'phone' => 'phone',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);

        self::assertFileExists($baseDir . 'user-contact');
        self::assertFileExists($baseDir . 'user-contact.manifest');
        self::assertFileExists($baseDir . 'users');
        self::assertFileExists($baseDir . 'users.manifest');

        self::assertEquals(
            '"email","phone","users_pk"',
            trim((string) file_get_contents($baseDir . 'user-contact'))
        );
        self::assertEquals(
            [
                'incremental' => false,
            ],
            json_decode((string) file_get_contents($baseDir . 'user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'users'))
        );
        self::assertEquals(
            ['incremental' => false],
            json_decode((string) file_get_contents($baseDir . 'users.manifest'), true)
        );
    }

    public function testMissingBucketPresentIdPresentName(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy', 'name' => 'testName'],
                'config' => [
                    'id' => 'config-id',
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'mappings' => [
                        'users' => [
                            'id' => 'id',
                            'name' => 'name',
                            'contacts' => [
                                'type' => 'table',
                                'destination' => 'user-contact',
                                'tableMapping' => [
                                    'email' => 'email',
                                    'phone' => 'phone',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);

        self::assertFileExists($baseDir . 'ex-api-testName-config-id.user-contact');
        self::assertFileExists($baseDir . 'ex-api-testName-config-id.user-contact.manifest');
        self::assertFileExists($baseDir . 'ex-api-testName-config-id.users');
        self::assertFileExists($baseDir . 'ex-api-testName-config-id.users.manifest');

        self::assertEquals(
            '"email","phone","users_pk"',
            trim((string) file_get_contents($baseDir . 'ex-api-testName-config-id.user-contact'))
        );
        self::assertEquals(
            [
                'incremental' => false,
                'destination' => 'in.c-ex-api-testName-config-id.user-contact',
            ],
            json_decode((string) file_get_contents($baseDir . 'ex-api-testName-config-id.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'ex-api-testName-config-id.users'))
        );
        self::assertEquals(
            [
                'incremental' => false,
                'destination' => 'in.c-ex-api-testName-config-id.users',
            ],
            json_decode((string) file_get_contents($baseDir . 'ex-api-testName-config-id.users.manifest'), true)
        );
    }

    public function testMissingBucketPresentIdMissingName(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'id' => 'config-id',
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'mappings' => [
                        'users' => [
                            'id' => 'id',
                            'name' => 'name',
                            'contacts' => [
                                'type' => 'table',
                                'destination' => 'user-contact',
                                'tableMapping' => [
                                    'email' => 'email',
                                    'phone' => 'phone',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);

        self::assertFileExists($baseDir . 'ex-api-generic-config-id.user-contact');
        self::assertFileExists($baseDir . 'ex-api-generic-config-id.user-contact.manifest');
        self::assertFileExists($baseDir . 'ex-api-generic-config-id.users');
        self::assertFileExists($baseDir . 'ex-api-generic-config-id.users.manifest');

        self::assertEquals(
            '"email","phone","users_pk"',
            trim((string) file_get_contents($baseDir . 'ex-api-generic-config-id.user-contact'))
        );
        self::assertEquals(
            [
                'incremental' => false,
                'destination' => 'in.c-ex-api-generic-config-id.user-contact',
            ],
            json_decode((string) file_get_contents($baseDir . 'ex-api-generic-config-id.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'ex-api-generic-config-id.users'))
        );
        self::assertEquals(
            [
                'incremental' => false,
                'destination' => 'in.c-ex-api-generic-config-id.users',
            ],
            json_decode((string) file_get_contents($baseDir . 'ex-api-generic-config-id.users.manifest'), true)
        );
    }

    public function testMissingParentKeyDestination(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'mappings' => [
                        'users' => [
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
                                ],
                                'tableMapping' => [
                                    'email' => [
                                        'type' => 'column',
                                        'mapping' => [
                                            'destination' => 'email',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');

        self::assertEquals(
            '"email","users_pk"',
            trim((string) file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => false,
                'primary_key' => ['users_pk'],
            ],
            json_decode((string) file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"name"',
            trim((string) file_get_contents($baseDir . 'mock-server.users'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.users', 'incremental' => false],
            json_decode((string) file_get_contents($baseDir . 'mock-server.users.manifest'), true)
        );
    }

    public function testParentKeyDisableTableMapping(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
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
                                    'disable' => true,
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
                        ],
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');
        self::assertFileExists($baseDir . 'mock-server.users');
        self::assertFileExists($baseDir . 'mock-server.users.manifest');

        // no userId - parentKey is disabled
        self::assertEquals(
            '"email","phone"',
            trim((string) file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => true,
            ],
            json_decode((string) file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
        self::assertEquals(
            '"id","name"',
            trim((string) file_get_contents($baseDir . 'mock-server.users'))
        );
        self::assertEquals(
            ['destination' => 'in.c-mock-server.users', 'incremental' => true, 'primary_key' => ['id']],
            json_decode((string) file_get_contents($baseDir . 'mock-server.users.manifest'), true)
        );
    }

    public function testTableMapping(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
                    'mappings' => [
                        'contacts' => [
                            'type' => 'table',
                            'destination' => 'user-contact',
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
                    ],
                ],
            ],
        ];

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
        self::assertFileExists($baseDir . 'mock-server.user-contact');
        self::assertFileExists($baseDir . 'mock-server.user-contact.manifest');

        self::assertEquals(
            '"email","phone","contacts_pk"',
            trim((string) file_get_contents($baseDir . 'mock-server.user-contact'))
        );
        self::assertEquals(
            [
                'destination' => 'in.c-mock-server.user-contact',
                'incremental' => true,
            ],
            json_decode((string) file_get_contents($baseDir . 'mock-server.user-contact.manifest'), true)
        );
    }

    public function testMissingMappingKeyInTypeColumn(): void
    {
        $config = [
            'parameters' => [
                'api' => ['baseUrl' => 'https://dummy'],
                'config' => [
                    'jobs' => [
                        [
                            'endpoint' => 'users',
                            'dataType' => 'users',
                        ],
                    ],
                    'outputBucket' => 'mock-server',
                    'incrementalOutput' => true,
                    'mappings' => [
                        'users' => [
                            'id' => [
                                'type' => 'column',
                            ],
                            'name' => [
                                'mapping' => [
                                    'destination' => 'name',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(UserException::class);
        $this->expectExceptionMessage('Missing mapping key at item with type "column"');

        mkdir($this->temp->getTmpFolder() . '/out/');
        $baseDir = $this->temp->getTmpFolder() . '/out/tables/';
        mkdir($baseDir);
        file_put_contents($this->temp->getTmpFolder() . '/config.json', json_encode($config));
        $configuration = new Extractor($this->temp->getTmpFolder(), new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        MissingTableHelper::checkConfigs($configs, $this->temp->getTmpFolder(), $configuration);
    }
}
