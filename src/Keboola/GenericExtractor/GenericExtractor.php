<?php

namespace Keboola\GenericExtractor;

use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Subscriber\Cache\CacheStorage;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use Keboola\GenericExtractor\Config\JuicerRest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Extractor\Extractor;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Parser\Json;
use Keboola\Juicer\Parser\JsonMap;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\GenericExtractor\Authentication\AuthInterface;
use Keboola\GenericExtractor\Config\Api;
use Keboola\GenericExtractor\Subscriber\LogRequest;
use Keboola\GenericExtractor\Config\UserFunction;
use Keboola\Code\Builder;

class GenericExtractor extends Extractor
{
    protected $name = "generic";
    protected $prefix = "ex-api";
    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var array
     */
    protected $headers;
    /**
     * @var ScrollerInterface
     */
    protected $scroller;
    /**
     * @var AuthInterface
     */
    protected $auth;
    /**
     * @var Json
     */
    protected $parser;
    /**
     * ['response' => ResponseModuleInterface[], ...]
     * @var array
     */
    protected $modules;
    /**
     * @var array
     */
    protected $defaultRequestOptions = [];
    /**
     * @var array
     */
    protected $retryConfig = [];

    /**
     * @var CacheStorage
     */
    protected $cache;

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
            [
                'base_url' => $this->baseUrl,
                'defaults' => [
                    'headers' => UserFunction::build($this->headers, ['attr' => $config->getAttributes()])
                ]
            ],
            JuicerRest::convertRetry($this->retryConfig)
        );

        if (!empty($this->defaultRequestOptions)) {
            $client->setDefaultRequestOptions($this->defaultRequestOptions);
        }

        $this->auth->authenticateClient($client);
        // Verbose Logging of all requests
        $client->getClient()->getEmitter()->attach(new LogRequest);

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

        $builder = new Builder();

        foreach ($config->getJobs() as $jobConfig) {
            $this->runJob($jobConfig, $client, $config, $builder);
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
    protected function runJob($jobConfig, $client, $config, $builder)
    {
        // FIXME this is rather duplicated in RecursiveJob::createChild()
        $job = new GenericExtractorJob($jobConfig, $client, $this->parser);
        $job->setScroller($this->scroller);
        $job->setAttributes($config->getAttributes());
        $job->setMetadata($this->metadata);
        $job->setBuilder($builder);
        $job->setResponseModules($this->modules['response']);
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
     * @param string $name
     */
    public function setAppName($name)
    {
        $this->name = $name;
    }

    /**
     * @param Api $api
     */
    public function setApi(Api $api)
    {
        $this->setBaseUrl($api->getBaseUrl());
        $this->setAuth($api->getAuth());
        $this->setScroller($api->getScroller());
        $this->setHeaders($api->getHeaders()->getHeaders());
        $this->setAppName($api->getName());
        $this->setDefaultRequestOptions($api->getDefaultRequestOptions());
        $this->setRetryConfig($api->getRetryConfig());
    }

    public function setRetryConfig(array $config)
    {
        $this->retryConfig = $config;
    }

    /**
     * Get base URL from Config
     * @param string $url
     */
    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }

    /**
     * @param AuthInterface $auth
     */
    public function setAuth(AuthInterface $auth)
    {
        $this->auth = $auth;
    }

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * @param ScrollerInterface $scroller
     */
    public function setScroller(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
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

        $parser = Json::create($config, $this->getLogger(), $this->getTemp(), $this->metadata);
        $parser->getParser()->getStruct()->setAutoUpgradeToArray(true);
        $parser->getParser()->setCacheMemoryLimit('2M');
        $parser->getParser()->getAnalyzer()->setNestedArrayAsJson(true);

        if (empty($config->getAttribute('mappings'))) {
            $this->parser = $parser;
        } else {
            $this->parser = JsonMap::create($config, $parser);
        }

        return $this->parser;
    }

    /**
     * @param array $modules ['response' => ResponseModuleInterface[]]
     */
    public function setModules(array $modules)
    {
        $this->modules = $modules;
    }

    public function setDefaultRequestOptions(array $options)
    {
        $this->defaultRequestOptions = $options;
    }
}
