<?php

namespace Keboola\GenericExtractor;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use Keboola\GenericExtractor\Configuration\Api;
use Keboola\GenericExtractor\Configuration\JuicerRest;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Parser\JsonMap;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\GenericExtractor\Subscriber\LogRequest;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;

class GenericExtractor
{
    const COMPAT_LEVEL_OLD_PARSER = 1;
    const COMPAT_LEVEL_FILTER_EMPTY_SCALAR = 2;
    const COMPAT_LEVEL_LATEST = 3;

    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * @var CacheStorage
     */
    protected $cache;

    /**
     * @var Temp
     */
    protected $temp;

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var array
     */
    private $baseUrlReplacement;

    /**
     * GenericExtractor constructor.
     * @param Temp $temp
     * @param LoggerInterface $logger
     * @param Api $api
     */
    public function __construct(Temp $temp, LoggerInterface $logger, Api $api, $baseUrlReplacement = null)
    {
        $this->temp = $temp;
        $this->logger = $logger;
        $this->api = $api;
        $this->baseUrlReplacement = $baseUrlReplacement;
    }

    /**
     * @param CacheStorage $cache
     * @return $this
     */
    public function enableCache(CacheStorage $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    public function run(Config $config)
    {
        $client = new RestClient(
            $this->logger,
            [
                'base_url' => $this->resolveBaseUrl(),
                'defaults' => [
                    'headers' => UserFunction::build(
                        $this->api->getHeaders()->getHeaders(),
                        ['attr' => $config->getAttributes()]
                    )
                ]
            ],
            JuicerRest::convertRetry($this->api->getRetryConfig()),
            $this->api->getDefaultRequestOptions()
        );

        $this->api->getAuth()->authenticateClient($client);
        // Verbose Logging of all requests
        $client->getClient()->getEmitter()->attach(new LogRequest($this->logger));

        if ($this->cache) {
            CacheSubscriber::attach(
                $client->getClient(),
                [
                    'storage' => $this->cache,
                    'validate' => false,
                    'can_cache' => function (RequestInterface $requestInterface) {
                        return true;
                    }
                ]
            );
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

    private function resolveBaseUrl()
    {
        $baseUrl = $this->api->getBaseUrl();
        if (empty($this->baseUrlReplacement)) {
            return $baseUrl;
        }

        $baseUrlParsed = parse_url($baseUrl);
        $baseUrlParsed['host'] = $this->baseUrlReplacement['host'];
        $baseUrlParsed['port'] = $this->baseUrlReplacement['port'];

        return sprintf(
            "%s://%s:%s%s",
            $baseUrlParsed['scheme'],
            $baseUrlParsed['host'],
            $baseUrlParsed['port'],
            isset($baseUrlParsed['path']) ? $baseUrlParsed['path'] : null
        );
    }

    /**
     * @param JobConfig $jobConfig
     * @param RestClient $client
     * @param Config $config
     */
    protected function runJob($jobConfig, $client, $config)
    {
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

    /**
     * @param ParserInterface $parser
     */
    public function setParser(ParserInterface $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @return ParserInterface
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * @param Config $config
     * @return int
     */
    private function getCompatLevel(Config $config)
    {
        if (empty($config->getAttribute('compatLevel'))) {
            return self::COMPAT_LEVEL_LATEST;
        }
        return (int)$config->getAttribute('compatLevel');
    }

    /**
     * @param Config $config
     * @return ParserInterface
     */
    protected function initParser(Config $config)
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

    public function setMetadata(array $data)
    {
        $this->metadata = $data;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }
}
