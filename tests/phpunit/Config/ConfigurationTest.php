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
                'https://example.com/users?page=1',
            ],
            $urls
        );
    }

    public function testBuildUrlsWithMultipleParams(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = 'https://api.example.com';
        $endpoints = [
            [
                'endpoint' => 'users',
                'params' => [
                    'page' => 1,
                    'limit' => 100,
                    'sort' => 'name',
                    'filter' => 'active',
                ],
                'placeholders' => [],
            ],
        ];

        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);

        self::assertEquals([
            'https://api.example.com/users?page=1&limit=100&sort=name&filter=active',
        ], $urls);
    }

    public function testBuildUrlsWithBaseUrlFromFunction(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = [
            'function' => 'concat',
            'args' => [
                'https://',
                'keboola',
                '.example.com/',
            ],
        ];
        $endpoints = [
            [
                'endpoint' => 'users/{id}',
                'params' => ['page' => 1],
                'placeholders' => ['id' => 123],
            ],
        ];
        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);
        self::assertEquals([
            'https://keboola.example.com/users/123?page=1',
        ], $urls);
    }

    public function testBuildUrlsWithBaseUrlFunctionAndMultipleEndpoints(): void
    {
        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());

        $baseUrl = [
            'function' => 'concat',
            'args' => [
                'https://',
                'keboola',
                '.example.com/',
            ],
        ];
        $endpoints = [
            [
                'endpoint' => 'users/{id}',
                'params' => ['page' => 1],
                'placeholders' => ['id' => 123],
            ],
            [
                'endpoint' => 'orders/{orderId}',
                'params' => ['status' => 'completed'],
                'placeholders' => ['orderId' => 456],
            ],
        ];
        $urls = $this->invokeMethod($extractor, 'buildUrls', [$baseUrl, $endpoints]);
        self::assertEquals([
            'https://keboola.example.com/users/123?page=1',
            'https://keboola.example.com/orders/456?status=completed',
        ], $urls);
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
                    if (is_string($host)) {
                        $parsedUrl = parse_url($host);
                        return [
                            'scheme' => $parsedUrl['scheme'] ?? null,
                            'host' => $parsedUrl['host'] ?? '',
                            'port' => $parsedUrl['port'] ?? null,
                            'endpoint' => $parsedUrl['path'] ?? null,
                        ];
                    }
                    return [
                        'scheme' => $host['scheme'] ?? null,
                        'host' => $host['host'] ?? $host,
                        'port' => $host['port'] ?? null,
                        'endpoint' => $host['endpoint'] ?? null,
                    ];
                }, $allowedHosts),
            ];
        }

        return $config;
    }

    public function testValidateAllowedHostsExactMatch(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsExactMatchWithoutScheme(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentQueryString(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api?x=1',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentTrailingSlash(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsDifferentProtocol(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'http', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsDifferentPort(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'https', 'host' => 'example.com', 'port' => 443, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsLongerPath(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/resource',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleLevels(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1/data',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsTrailingSlashInWhitelist(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api/']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsNotRealPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/ap']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsDomainVsSubdomain(): void
    {
        $config = $this->createTestConfig(
            'https://sub.example.com/path',
            [['scheme' => 'https', 'host' => 'example.com']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsIpAddressExactMatch(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8080/api',
            [['scheme' => 'http', 'host' => '127.0.0.1', 'port' => 8080, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsIpAddressPrefixMatch(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8080/api/v1/data',
            [['scheme' => 'http', 'host' => '127.0.0.1', 'port' => 8080, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsIpAddressDifferentPort(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:8000/api',
            [['scheme' => 'http', 'host' => '127.0.0.1', 'port' => 8080, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsIpAddressNoPortVsPort(): void
    {
        $config = $this->createTestConfig(
            'http://127.0.0.1:80/api',
            [['scheme' => 'http', 'host' => '127.0.0.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsSubdomain(): void
    {
        $config = $this->createTestConfig(
            'https://sub.example.com/path1',
            [['scheme' => 'https', 'host' => 'sub.example.com']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsShorterPathPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/path/1/2',
            [['scheme' => 'https', 'host' => 'sub.domain.com']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsNoTrailingSlash(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/path',
            [['scheme' => 'https', 'host' => 'sub.domain.com']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsLongerPrefix(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/extra/data',
            [['scheme' => 'https', 'host' => 'sub.domain.com', 'endpoint' => '/extra']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsStringPrefixNotPath(): void
    {
        $config = $this->createTestConfig(
            'https://sub.domain.com/pathology',
            [['scheme' => 'https', 'host' => 'sub.domain.com', 'endpoint' => '/path']]
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

    public function testValidateAllowedHostsPortPrefixNotAllowed(): void
    {
        $config = $this->createTestConfig(
            'https://example.com:8/api',
            [['scheme' => 'https', 'host' => 'example.com', 'port' => 88, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsMultipleHosts(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api'],
                ['scheme' => 'https', 'host' => 'other.com', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleHostsWithDifferentPorts(): void
    {
        $config = $this->createTestConfig(
            'https://example.com:8080/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'port' => 8080, 'endpoint' => '/api'],
                ['scheme' => 'https', 'host' => 'other.com', 'port' => 443, 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleHostsWithMixedPorts(): void
    {
        $config = $this->createTestConfig(
            'https://example.com:8080/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api'], // no port specified
                ['scheme' => 'https', 'host' => 'other.com', 'port' => 8080, 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleHostsWithDifferentEndpoints(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1/users',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api/v1'],
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api/v2'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleHostsWithDifferentSchemes(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api'],
                ['scheme' => 'http', 'host' => 'example.com', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsMultipleHostsWithInvalidPort(): void
    {
        $config = $this->createTestConfig(
            'https://example.com:8080/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'port' => 443, 'endpoint' => '/api'],
                ['scheme' => 'https', 'host' => 'other.com', 'port' => 80, 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsMultipleHostsWithInvalidEndpoint(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api/v1/users',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api/v2'],
                ['scheme' => 'https', 'host' => 'other.com', 'endpoint' => '/api/v3'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsMultipleHostsWithInvalidHost(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [
                ['scheme' => 'https', 'host' => 'other.com', 'endpoint' => '/api'],
                ['scheme' => 'https', 'host' => 'another.com', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithScheme(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithoutScheme(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithDifferentSchemes(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [
                ['scheme' => 'http', 'host' => 'example.com', 'endpoint' => '/api'],
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithInvalidScheme(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [['scheme' => 'http', 'host' => 'example.com', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithMultipleHostsAndSchemes(): void
    {
        $config = $this->createTestConfig(
            'https://example.com/api',
            [
                ['scheme' => 'https', 'host' => 'example.com', 'endpoint' => '/api'],
                ['host' => 'other.com', 'endpoint' => '/api'],
                ['scheme' => 'http', 'host' => 'another.com', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddress(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1/api',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressAndPort(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1:8080/api',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'port' => 8080, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressDifferentPort(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1:8080/api',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'port' => 80, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithIpAddressNoPort(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1:8080/api',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressAndHttps(): void
    {
        $config = $this->createTestConfig(
            'https://192.168.1.1/api',
            [['scheme' => 'https', 'host' => '192.168.1.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressAndDifferentScheme(): void
    {
        $config = $this->createTestConfig(
            'https://192.168.1.1/api',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithIpAddressAndMultiplePorts(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1:8080/api',
            [
                ['scheme' => 'http', 'host' => '192.168.1.1', 'port' => 8080, 'endpoint' => '/api'],
                ['scheme' => 'http', 'host' => '192.168.1.1', 'port' => 80, 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressAndMultipleSchemes(): void
    {
        $config = $this->createTestConfig(
            'https://192.168.1.1/api',
            [
                ['scheme' => 'https', 'host' => '192.168.1.1', 'endpoint' => '/api'],
                ['scheme' => 'http', 'host' => '192.168.1.1', 'endpoint' => '/api'],
            ]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressAndInvalidHost(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1/api',
            [['scheme' => 'http', 'host' => '192.168.1.2', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithIpAddressAndInvalidEndpoint(): void
    {
        $config = $this->createTestConfig(
            'http://192.168.1.1/api/v1',
            [['scheme' => 'http', 'host' => '192.168.1.1', 'endpoint' => '/api/v2']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsWithIpAddressNoScheme(): void
    {
        $config = $this->createTestConfig(
            '192.168.1.1/api',
            [['host' => '192.168.1.1', 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressNoSchemeWithPort(): void
    {
        $config = $this->createTestConfig(
            '192.168.1.1:8080/api',
            [['host' => '192.168.1.1', 'port' => 8080, 'endpoint' => '/api']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressNoSchemeWithPortAndApi(): void
    {
        $config = $this->createTestConfig(
            '192.168.1.1:8080/api/v1/users',
            [['host' => '192.168.1.1', 'port' => 8080, 'endpoint' => '/api/v1']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        self::assertNull($this->invokeMethod($extractor, 'validateAllowedHosts', [$config]));
    }

    public function testValidateAllowedHostsWithIpAddressNoSchemeWithPortAndDifferentApi(): void
    {
        $config = $this->createTestConfig(
            '192.168.1.1:8080/api/v1/users',
            [['host' => '192.168.1.1', 'port' => 8080, 'endpoint' => '/api/v2']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }

    public function testValidateAllowedHostsRootUrl(): void
    {
        $config = $this->createTestConfig(
            'https://catfact.ninja/',
            [['host' => 'catfact.ninja', 'endpoint' => '/fact']]
        );

        $extractor = new Extractor(__DIR__ . '/../data/simple_basic', new NullLogger());
        $this->expectException(UserException::class);
        $this->invokeMethod($extractor, 'validateAllowedHosts', [$config]);
    }
}
