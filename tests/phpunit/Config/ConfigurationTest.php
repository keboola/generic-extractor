<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Config;

use Keboola\GenericExtractor\Configuration\Extractor;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Tests\ExtractorTestCase;
use Keboola\Juicer\Config\Config;
use Keboola\Temp\Temp;
use Keboola\CsvTable\Table;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

class ConfigurationTest extends ExtractorTestCase
{
    public function testStoreResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $this->storeResults($resultsPath, 'full', false);
    }

    public function testIncrementalResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $this->storeResults($resultsPath, 'incremental', true);
    }

    public function testDefaultBucketResults(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $files = [
            new Table('first', ['col1', 'col2']),
            new Table('second', ['col11', 'col12']),
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);
        $files[1]->setPrimaryKey(['col11']);

        $configuration->storeResults($files);

        /** @var \SplFileInfo $file */
        foreach (new \FilesystemIterator(__DIR__ . '/../data/storeResultsDefaultBucket/out/tables/') as $file) {
            self::assertFileEquals($file->getPathname(), $resultsPath . '/out/tables/' . $file->getFilename());
        }

        $this->rmDir($resultsPath);
    }

    protected function storeResults(string $resultsPath, string $name, bool $incremental): void
    {
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $files = [
            new Table('first', ['col1', 'col2']),
            new Table('second', ['col11', 'col12']),
        ];

        $files[0]->writeRow(['a', 'b']);
        $files[1]->writeRow(['c', 'd']);

        $configuration->storeResults($files, $name, true, $incremental);

        /** @var \SplFileInfo $file */
        foreach (new \FilesystemIterator(__DIR__ . '/../data/storeResultsTest/out/tables/' . $name) as $file) {
            self::assertFileEquals(
                $file->getPathname(),
                $resultsPath . '/out/tables/' . $name . '/' . $file->getFilename()
            );
        }

        $this->rmDir($resultsPath);
    }

    public function testGetConfigMetadata(): void
    {
        $path = __DIR__ . '/../data/metadataTest';
        $configuration = new Extractor($path, new NullLogger());
        $json = $configuration->getMetadata();

        self::assertEquals(json_decode('{"some":"data","more": {"woah": "such recursive"}}', true), $json);
        $path = __DIR__ . '/../data/noCache';
        $noConfiguration = new Extractor($path, new NullLogger());
        self::assertEquals([], $noConfiguration->getMetadata());
    }

    public function testSaveConfigMetadata(): void
    {
        $temp = new Temp();
        $resultsPath = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'data';
        $config = '{"parameters":{}}';
        $fs = new Filesystem();
        $fs->dumpFile($resultsPath . DIRECTORY_SEPARATOR . 'config.json', $config);
        $configuration = new Extractor($resultsPath, new NullLogger());

        $configuration->saveConfigMetadata(
            [
            'some' => 'data',
            'more' => [
                'woah' => 'such recursive',
            ],
            ]
        );

        self::assertFileEquals(__DIR__ . '/../data/metadataTest/out/state.json', $resultsPath . '/out/state.json');

        $this->rmDir($resultsPath);
    }

    public function testGetMultipleConfigs(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/iterations', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        $json = json_decode((string) file_get_contents(__DIR__ . '/../data/iterations/config.json'), true);

        foreach ($json['parameters']['iterations'] as $i => $params) {
            self::assertEquals(
                array_replace(
                    [
                        'id' => $json['parameters']['config']['id'],
                        'outputBucket' => $json['parameters']['config']['outputBucket'],
                    ],
                    $params
                ),
                $configs[$i]->getAttributes()
            );
        }
        self::assertEquals($configs[0]->getJobs(), $configs[1]->getJobs());
        self::assertContainsOnlyInstancesOf(Config::class, $configs);
        self::assertCount(count($json['parameters']['iterations']), $configs);
        self::assertEquals($json['parameters']['config']['outputBucket'], $configs[0]->getAttribute('outputBucket'));
    }

    public function testGetMultipleConfigsSingle(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        self::assertContainsOnlyInstancesOf(Config::class, $configs);
        self::assertCount(1, $configs);
    }

    public function testGetJson(): void
    {
        $configuration = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $configs = $configuration->getMultipleConfigs();
        self::assertEquals('multiCfg', $configs[0]->getAttribute('id'));
    }

    public function testGetInvalidConfig(): void
    {
        $temp = new Temp();
        $fs = new Filesystem();
        $fs->dumpFile($temp->getTmpFolder() . '/config.json', 'invalidJSON');
        try {
            new Extractor($temp->getTmpFolder(), new NullLogger());
            self::fail('Invalid JSON must cause exception');
        } catch (ApplicationException $e) {
            self::assertStringContainsString('Configuration file is not a valid JSON: Syntax error', $e->getMessage());
        }
    }

    public function testInvalidValuesInApiNode(): void
    {
        $temp = new Temp();
        $config['parameters'] = [
            'api' => [
                'baseUrl' => 'test',
                'authentication' => [
                    'type' => 'basic',
                ],
                'caCertificate' => false,
            ],
            'config' => ['outputBucket' => 'someBucket', 'jobs' => [['endpoint' => 'GET']]],
        ];
        $fs = new Filesystem();
        $fs->dumpFile($temp->getTmpFolder() . '/config.json', json_encode($config));
        try {
            $extractor = new Extractor($temp->getTmpFolder(), new NullLogger());
            foreach ($extractor->getMultipleConfigs() as $config) {
                $extractor->getApi($config->getAttributes());
            }
            self::fail('Invalid config value must cause exception');
        } catch (UserException $e) {
            self::assertStringContainsString("The 'caCertificate' must be string.", $e->getMessage());
        }
    }

    public function testBuildUrls(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://api.example.com';
        $endpoints = [
            [
                'endpoint' => 'users/{id}',
                'params' => ['page' => 1],
                'placeholders' => ['id' => 123],
            ],
            [
                'endpoint' => 'orders',
                'params' => ['status' => 'completed'],
                'placeholders' => [],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://api.example.com',
            'https://api.example.com/users/123?page=1',
            'https://api.example.com/orders?status=completed',
        ], $urls);
    }

    public function testBuildUrlsWithDomainName(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        // Test bez lomítka na konci
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'https://example.com',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'https://example.com',
                'https://example.com/users?page=1',
            ],
            $urls
        );

        // Test s lomítkem na konci
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'https://example.com/',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'https://example.com/',
                'https://example.com/users?page=1',
            ],
            $urls
        );

        // Test s subdoménou a cestou
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'https://sub.domain.example.com/path/',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'https://sub.domain.example.com/path/',
                'https://sub.domain.example.com/path/users?page=1',
            ],
            $urls
        );
    }

    public function testBuildUrlsWithPort(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://api.example.com:8080';
        $endpoints = [
            [
                'endpoint' => 'users/{id}',
                'params' => ['page' => 1],
                'placeholders' => ['id' => 123],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://api.example.com:8080',
            'https://api.example.com:8080/users/123?page=1',
        ], $urls);
    }

    public function testBuildUrlsWithPathInBaseUrl(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://api.example.com:8080/v1';
        $endpoints = [
            [
                'endpoint' => 'users/{id}',
                'params' => ['page' => 1],
                'placeholders' => ['id' => 123],
            ],
            [
                'endpoint' => '/orders',
                'params' => ['status' => 'completed'],
                'placeholders' => [],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://api.example.com:8080/v1',
            'https://api.example.com:8080/v1/users/123?page=1',
            'https://api.example.com:8080/v1/orders?status=completed',
        ], $urls);
    }

    public function testBuildUrlsWithIpAddress(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://192.168.1.1';
        $endpoints = [
            [
                'endpoint' => 'api/users',
                'params' => ['page' => 1],
                'placeholders' => [],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://192.168.1.1',
            'https://192.168.1.1/api/users?page=1',
        ], $urls);
    }

    public function testBuildUrlsWithIpAddressAndPort(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://192.168.1.1:8080';
        $endpoints = [
            [
                'endpoint' => 'api/users',
                'params' => ['page' => 1],
                'placeholders' => [],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://192.168.1.1:8080',
            'https://192.168.1.1:8080/api/users?page=1',
        ], $urls);
    }

    public function testBuildUrlsWithLocalhost(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        // Test s localhost IP
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'http://127.0.0.1',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'http://127.0.0.1',
                'http://127.0.0.1/users?page=1',
            ],
            $urls
        );

        // Test s localhost IP a portem
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'http://127.0.0.1:5000',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'http://127.0.0.1:5000',
                'http://127.0.0.1:5000/users?page=1',
            ],
            $urls
        );
    }

    public function testBuildUrlsWithDifferentProtocols(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        // Test s HTTP
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'http://example.com',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'http://example.com',
                'http://example.com/users?page=1',
            ],
            $urls
        );

        // Test s HTTPS
        $urls = $this->invokeMethod($extractor, 'buildUrls', [
            'https://example.com',
            [
                [
                    'endpoint' => 'users',
                    'params' => ['page' => 1],
                    'placeholders' => [],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'https://example.com',
                'https://example.com/users?page=1',
            ],
            $urls
        );
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object $object Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array $parameters Array of parameters to pass into method
     * @return mixed Method return
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function rmDir(string $dirPath): bool
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dirPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $path) {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        return rmdir($dirPath);
    }

    /**
     * URL Validation Tests
     */
    private function createTestConfig(
        string $baseUrl,
        ?array $allowedHosts = null,
        array $endpoints = ['/path/']
    ): array {
        $config = [
            'parameters' => [
                'api' => [
                    'baseUrl' => $baseUrl,
                ],
                'config' => [
                    'jobs' => array_map(function ($endpoint) {
                        return ['endpoint' => $endpoint];
                    }, $endpoints),
                ],
            ],
        ];

        if ($allowedHosts !== null) {
            $config['image_parameters'] = [
                'allowed_hosts' => array_map(function ($host) {
                    return ['host' => $host];
                }, $allowedHosts),
            ];
        }

        return $config;
    }

    public function testValidateAllowedHostsExactMatch(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            ['https://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentQueryString(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api?x=1',
            ['https://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentTrailingSlash(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/',
            ['https://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentProtocol(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            ['http://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsDifferentPort(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            ['https://example.com:443/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsLongerPath(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/resource',
            ['https://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleLevels(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1/data',
            ['https://example.com/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsTrailingSlashInWhitelist(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1',
            ['https://example.com/api/']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsNotRealPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            ['https://example.com/ap']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsDomainVsSubdomain(): void
    {
        $config = $this->createTestConfig(
            'https://sub.example.com/path',
            ['https://example.com/']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsIpAddressExactMatch(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8080/api',
            ['http://127.0.0.1:8080/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsIpAddressPrefixMatch(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8080/api/v1/data',
            ['http://127.0.0.1:8080/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsIpAddressDifferentPort(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8000/api',
            ['http://127.0.0.1:8080/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsIpAddressNoPortVsPort(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:80/api',
            ['http://127.0.0.1/api']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsSubdomain(): void
    {
        $config = $this->createTestConfig(
            'https://sub.example.com/path1',
            ['https://sub.example.com/']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsShorterPathPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/path/1/2',
            ['https://sub.domain.com/']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsNoTrailingSlash(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/path',
            ['https://sub.domain.com']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsLongerPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/extra/data',
            ['https://sub.domain.com/extra']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsStringPrefixNotPath(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/pathology',
            ['https://sub.domain.com/path']
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsNoWhitelist(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            null
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsEmptyWhitelist(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            []
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }
}
