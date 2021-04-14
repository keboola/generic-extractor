<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\Configuration\JuicerRest;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Logger\LoggerMiddleware;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Parser\JsonMap;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\Temp\Temp;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Strategy\CacheStrategyInterface;
use Psr\Log\LoggerInterface;

class GenericExtractor
{
    public const COMPAT_LEVEL_OLD_PARSER = 1;
    public const COMPAT_LEVEL_FILTER_EMPTY_SCALAR = 2;
    public const COMPAT_LEVEL_LATEST = 3;

    protected ?ParserInterface $parser = null;

    protected ?CacheStrategyInterface $cacheStrategy = null;

    protected Temp $temp;

    protected array $metadata = [];

    protected LoggerInterface $logger;

    private Api $api;

    private ?string $proxy;

    public function __construct(Temp $temp, LoggerInterface $logger, Api $api, ?string $proxy = null)
    {
        $this->temp = $temp;
        $this->logger = $logger;
        $this->api = $api;
        $this->proxy = $proxy;
    }

    public function enableCache(CacheStrategyInterface $cacheStrategy): self
    {
        $this->cacheStrategy = $cacheStrategy;
        return $this;
    }

    public function run(Config $config): void
    {
        $defaults = [
            'headers' => UserFunction::build(
                $this->api->getHeaders()->getHeaders(),
                ['attr' => $config->getAttributes()]
            ),
            'proxy' => $this->proxy,
            // http://docs.guzzlephp.org/en/stable/request-options.html#verify-option
            'verify' => $this->api->hasCaCertificate() ? $this->api->getCaCertificateFile() : true,
        ];

        if ($this->api->hasClientCertificate()) {
            $defaults['cert'] = $this->api->getClientCertificateFile();
        }

        $client = new RestClient(
            $this->logger,
            $this->api->getBaseUrl(),
            $defaults,
            JuicerRest::convertRetry($this->api->getRetryConfig()),
            $this->api->getDefaultRequestOptions(),
            $this->api->getIgnoreErrors()
        );

        // Attach auth middleware
        $this->api->getAuth()->attachToClient($client);

        // Verbose Logging of all requests
        $client->getHandlerStack()->push(LoggerMiddleware::create($this->logger));

        if ($this->cacheStrategy) {
            $client->getHandlerStack()->push(new CacheMiddleware($this->cacheStrategy), 'cache');
        }

        $this->initParser($config);
        foreach ($config->getJobs() as $jobConfig) {
            $this->runJob($jobConfig, $client, $config);
        }

        if ($this->parser instanceof Json) {
            // FIXME fallback from JsonMap
            $this->metadata = array_replace_recursive($this->metadata, $this->parser->getMetadata());
        }
    }

    protected function runJob(JobConfig $jobConfig, RestClient $client, Config $config): void
    {
        if (!$this->parser) {
            throw new \UnexpectedValueException('Parser is not set.');
        }

        $job = new GenericExtractorJob(
            $jobConfig,
            $client,
            $this->parser,
            $this->logger,
            $this->api->getNewScroller(),
            $config->getAttributes(),
            $this->metadata,
            $this->getCompatLevel($config)
        );
        if (!empty($config->getAttribute('userData'))) {
            $job->setUserParentId(
                is_scalar($config->getAttribute('userData'))
                ? ['userData' => $config->getAttribute('userData')]
                : $config->getAttribute('userData')
            );
        }

        $job->run();
    }

    public function setParser(ParserInterface $parser): void
    {
        $this->parser = $parser;
    }

    public function getParser(): ParserInterface
    {
        if (!$this->parser) {
            throw new \LogicException('Parser is not set.');
        }

        return $this->parser;
    }

    private function getCompatLevel(Config $config): int
    {
        if (empty($config->getAttribute('compatLevel'))) {
            return self::COMPAT_LEVEL_LATEST;
        }
        return (int) $config->getAttribute('compatLevel');
    }

    protected function initParser(Config $config): ParserInterface
    {
        if (!empty($this->parser) && $this->parser instanceof ParserInterface) {
            return $this->parser;
        }

        if ($this->getCompatLevel($config) <= self::COMPAT_LEVEL_OLD_PARSER) {
            $compatLevel = Json::LEGACY_VERSION;
        } else {
            $compatLevel = Json::LATEST_VERSION;
        }
        $parser = new Json($this->logger, $this->metadata, $compatLevel, 2000000);

        if (empty($config->getAttribute('mappings'))) {
            $this->parser = $parser;
        } else {
            $this->parser = new JsonMap($config, $this->logger, $parser);
        }

        return $this->parser;
    }

    public function setMetadata(array $data): void
    {
        $this->metadata = $data;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
