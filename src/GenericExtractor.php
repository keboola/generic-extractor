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
use Keboola\Code\Builder;
use Keboola\Temp\Temp;
use Psr\Log\LoggerInterface;

class GenericExtractor
{
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
     * GenericExtractor constructor.
     * @param Temp $temp
     * @param LoggerInterface $logger
     * @param Api $api
     */
    public function __construct(Temp $temp, LoggerInterface $logger, Api $api)
    {
        $this->temp = $temp;
        $this->logger = $logger;
        $this->api = $api;
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
        $client = RestClient::create(
            $this->logger,
            [
                'base_url' => $this->api->getBaseUrl(),
                'defaults' => [
                    'headers' => UserFunction::build(
                        $this->api->getHeaders()->getHeaders(),
                        ['attr' => $config->getAttributes()]
                    )
                ]
            ],
            JuicerRest::convertRetry($this->api->getRetryConfig())
        );

        if (!empty($this->api->getDefaultRequestOptions())) {
            $client->setDefaultRequestOptions($this->api->getDefaultRequestOptions());
        }

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

    /**
     * @param JobConfig $jobConfig
     * @param RestClient $client
     * @param Config $config
     * @param Builder $builder
     */
    protected function runJob($jobConfig, $client, $config)
    {
        // FIXME this is rather duplicated in RecursiveJob::createChild()
        $job = new GenericExtractorJob(
            $jobConfig,
            $client,
            $this->parser,
            $this->logger,
            $this->api->getScroller(),
            $config->getAttributes(),
            $this->metadata
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
     * @return ParserInterface
     */
    protected function initParser(Config $config)
    {
        if (!empty($this->parser) && $this->parser instanceof ParserInterface) {
            return $this->parser;
        }

        $parser = Json::create($config, $this->logger, $this->temp, $this->metadata);
        $parser->getParser()->getStruct()->setAutoUpgradeToArray(true);
        $parser->getParser()->setCacheMemoryLimit('2M');
        $parser->getParser()->getAnalyzer()->setNestedArrayAsJson(true);

        if (empty($config->getAttribute('mappings'))) {
            $this->parser = $parser;
        } else {
            $this->parser = JsonMap::create($config, $this->logger, $parser);
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
