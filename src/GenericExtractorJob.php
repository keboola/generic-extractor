<?php

namespace Keboola\GenericExtractor;

use Keboola\Filter\Exception\FilterException;
use Keboola\Filter\FilterFactory;
use Keboola\GenericExtractor\Response\Filter;
use Keboola\GenericExtractor\Config\UserFunction;
use Keboola\GenericExtractor\Response\FindResponseArray;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Pagination\NoScroller;
use Keboola\Code\Builder;
use Keboola\Juicer\Parser\ParserInterface;
use Keboola\Utils\Exception\NoDataFoundException;
use Psr\Log\LoggerInterface;

/**
 * A generic Job class generally used to set up each API call, handle its pagination and
 * parsing into a CSV ready for Storage upload.
 * Adds a capability to process recursive calls based on
 * responses. If an endpoint contains {} enclosed
 * parameter, it'll be replaced by a value from a parent call
 * based on the values from its response and "mapping" set in
 * child's "placeholders" object
 */
class GenericExtractorJob
{
    /**
     * @var JobConfig
     */
    protected $config;

    /**
     * @var RestClient
     */
    protected $client;

    /**
     * @var ParserInterface
     */
    protected $parser;

    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    /**
     * @var string
     */
    protected $jobId;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var string
     */
    protected $lastResponseHash;

    /**
     * @var Builder
     */
    protected $stringBuilder;

    /**
     * Data to append to the root result
     * @var array
     */
    protected $userParentId;

    /**
     * Used to save necessary parents' data to child's output
     * @var array
     */
    protected $parentParams = [];

    /**
     * @var array
     */
    protected $parentResults = [];

    /**
     * @param JobConfig $config
     * @param RestClient $client A client used to communicate with the API (wrapper for Guzzle)
     * @param ParserInterface $parser A parser to handle the result and convert it into CSV file(s)
     * @param LoggerInterface $logger
     */
    public function __construct(JobConfig $config, RestClient $client, ParserInterface $parser, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->client = $client;
        $this->parser = $parser;
        $this->jobId = $config->getJobId();
        // If no dataType is set, save endpoint as dataType before replacing placeholders
        if (empty($this->config->getConfig()['dataType']) && !empty($this->config->getConfig()['endpoint'])) {
            $this->config->setDataType($this->getDataType());
        }
    }

    /**
     * Manages cycling through the requests as long as
     * scroller provides next page
     *
     * Verifies the latest response isn't identical as the last one
     * to prevent infinite loop on awkward pagination APIs
     */
    public function run()
    {
        $this->buildParams($this->config);

        $parentId = $this->getParentId();

        $request = $this->firstPage($this->config);
        while ($request !== false) {
            $response = $this->download($request);

            $responseHash = sha1(serialize($response));
            if ($responseHash == $this->lastResponseHash) {
                $this->logger->debug(sprintf(
                    "Job '%s' finished when last response matched the previous!",
                    $this->getJobId()
                ));
                $this->scroller->reset();
                break;
            } else {
                $data = $this->runResponseModules($response, $this->config);
                $data = $this->filterResponse($this->config, $data);
                $this->parse($data, $parentId);

                $this->lastResponseHash = $responseHash;
            }

            $request = $this->nextPage($this->config, $response, $data);
        }
    }

    /**
     * @param array $data
     * @throws UserException
     */
    protected function runChildJobs(array $data)
    {
        foreach ($this->config->getChildJobs() as $jobId => $child) {
            $filter = null;
            if (!empty($child->getConfig()['recursionFilter'])) {
                try {
                    $filter = FilterFactory::create($child->getConfig()['recursionFilter']);
                } catch (FilterException $e) {
                    throw new UserException($e->getMessage(), 0, $e);
                }
            }

            foreach ($data as $result) {
                if (!empty($filter) && ($filter->compareObject((object) $result) == false)) {
                    continue;
                }

                // Add current result to the beginning of an array, containing all parent results
                $parentResults = $this->parentResults;
                array_unshift($parentResults, $result);

                $childJob = $this->createChild($child, $parentResults);
                $childJob->run();
            }
        }
    }

    /**
     * Create a child job with current client and parser
     * @param JobConfig $config
     * @param array $parentResults
     * @return static
     */
    protected function createChild(JobConfig $config, array $parentResults)
    {
        // Clone the config to prevent overwriting the placeholder(s) in endpoint
        $job = new static(clone $config, $this->client, $this->parser, $this->logger);

        $params = [];
        $placeholders = !empty($config->getConfig()['placeholders']) ? $config->getConfig()['placeholders'] : [];
        if (empty($placeholders)) {
            $this->logger->warning("No 'placeholders' set for '" . $config->getConfig()['endpoint'] . "'");
        }

        foreach ($placeholders as $placeholder => $field) {
            $params[$placeholder] = $this->getPlaceholder($placeholder, $field, $parentResults);
        }

        // Add parent params as well (for 'tagging' child-parent data)
        // Same placeholder in deeper nesting replaces parent value
        if (!empty($this->parentParams)) {
            $params = array_replace($this->parentParams, $params);
        }

        $job->setParams($params);
        $job->setParentResults($parentResults);

        // Inject $scroller into a child job
        $scroller = clone $this->scroller;
        $scroller->reset();
        $job->setScroller($scroller);
        $job->setMetadata($this->metadata);
        $job->setAttributes($this->attributes);
        $job->setBuilder($this->stringBuilder);
        return $job;
    }

    /**
     * @param string $placeholder
     * @param string|object|array $field Path or a function with a path
     * @param $parentResults
     * @return array ['placeholder', 'field', 'value']
     * @throws UserException
     */
    protected function getPlaceholder($placeholder, $field, $parentResults)
    {
        // TODO allow using a descriptive ID(level) by storing the result by `task(job) id` in $parentResults
        $level = strpos($placeholder, ':') === false
            ? 0
            : strtok($placeholder, ':') -1;

        if (!is_scalar($field)) {
            if (empty($field['path'])) {
                throw new UserException(
                    "The path for placeholder '{$placeholder}' must be a string value or an object containing 'path' and 'function'."
                );
            }

            $fn = \Keboola\Utils\arrayToObject($field);
            $field = $field['path'];
            unset($fn->path);
        }

        $value = $this->getPlaceholderValue($field, $parentResults, $level, $placeholder);

        if (isset($fn)) {
            $builder = new Builder;
            $builder->allowFunction('urlencode');
            $value = $builder->run($fn, ['placeholder' => ['value' => $value]]);
        }

        return [
            'placeholder' => $placeholder,
            'field' => $field,
            'value' => $value
        ];
    }

    /**
     * @param string $field
     * @param array $parentResults
     * @param int $level
     * @param string $placeholder
     * @return mixed
     * @throws UserException
     */
    protected function getPlaceholderValue($field, $parentResults, $level, $placeholder)
    {
        try {
            if (!array_key_exists($level, $parentResults)) {
                $maxLevel = empty($parentResults) ? 0 : max(array_keys($parentResults)) +1;
                throw new UserException(
                    "Level " . ++$level . " not found in parent results! Maximum level: " . $maxLevel
                );
            }

            return \Keboola\Utils\getDataFromPath($field, $parentResults[$level], ".", false);
        } catch (NoDataFoundException $e) {
            throw new UserException(
                "No value found for {$placeholder} in parent result. (level: " . ++$level . ")",
                0,
                null,
                [
                    'parents' => $parentResults
                ]
            );
        }
    }

    public function setParentResults(array $results)
    {
        $this->parentResults = $results;
    }

    /**
     *  Download an URL from REST or SOAP API and return its body as an object.
     * should handle the API call, backoff and response decoding
     *
     * @param RestRequest $request
     * @return \StdClass $response
     */
    protected function download(RestRequest $request)
    {
        return $this->client->download($request);
    }


    /**
     * Create subsequent requests for pagination (usually based on $response from previous request)
     * Return a download request OR false if no next page exists
     *
     * @param JobConfig $config
     * @param mixed $response
     * @param array|null $data
     * @return RestRequest | false
     */
    protected function nextPage(JobConfig $config, $response, $data)
    {
        return $this->getScroller()->getNextRequest($this->client, $config, $response, $data);
    }

    /**
     * Create the first download request.
     * Return a download request
     *
     * @param JobConfig $config
     * @return RestRequest | false
     */
    protected function firstPage(JobConfig $config)
    {
        return $this->getScroller()->getFirstRequest($this->client, $config);
    }

    /**
     * Parse the result into a CSV (either using any of built-in parsers, or using own methods).
     *
     * Create subsequent jobs for recursive endpoints. Uses "children" section of the job config
     * @param array $data
     * @param array $parentId ID (or list thereof) to be passed to parser
     * @return array
     */
    protected function parse(array $data, array $parentId = null)
    {
        $this->parser->process($data, $this->getDataType(), $parentId);
        $this->runChildJobs($data);
        return $data;
    }

    /**
     * @return ScrollerInterface
     */
    protected function getScroller()
    {
        if (empty($this->scroller)) {
            $this->scroller = new NoScroller;
        }

        return $this->scroller;
    }

    /**
     * @param ScrollerInterface $scroller
     */
    public function setScroller(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
    }

    /**
     * @return null|array
     */
    protected function getParentId()
    {
        if (!empty($this->config->getConfig()['userData'])) {
            if (!is_array($this->config->getConfig()['userData'])) {
                $jobUserData = ['job_parent_id' => $this->config->getConfig()['userData']];
            } else {
                $jobUserData = $this->config->getConfig()['userData'];
            }
        } else {
            $jobUserData = [];
        }

        if (!empty($this->userParentId)) {
            $jobUserData = array_merge($this->userParentId, $jobUserData);
        }

        if (empty($jobUserData)) {
            return null;
        }

        return UserFunction::build(
            $jobUserData,
            [
                'attr' => $this->attributes,
                'time' => !empty($this->metadata['time']) ? $this->metadata['time'] : []
            ],
            $this->stringBuilder
        );
    }

    /**
     * @param array $parentIdCols
     * @return array
     */
    protected function getParentCols(array $parentIdCols = null)
    {
        // Add parent values to the result
        $parentCols = is_null($parentIdCols) ? [] : $parentIdCols;
        foreach ($this->parentParams as $v) {
            $key = $this->prependParent($v['field']);
            $parentCols[$key] = $v['value'];
        }
        return $parentCols;
    }

    /**
     * @param JobConfig $config
     * @return array
     */
    protected function buildParams(JobConfig $config)
    {
        $params = UserFunction::build(
            $config->getParams(),
            [
                'attr' => $this->attributes,
                'time' => !empty($this->metadata['time']) ? $this->metadata['time'] : []
            ],
            $this->stringBuilder
        );

        $config->setParams($params);

        return $params;
    }

    /**
     * Filters the $data array according to
     * $config->getConfig()['responseFilter'] and
     * returns the filtered array
     *
     * @param JobConfig $config
     * @param array $data
     * @return array
     */
    protected function filterResponse(JobConfig $config, array $data)
    {
        $filter = Filter::create($config);
        return $filter->run($data);
    }

    /**
     * @param array|object $response
     * @param JobConfig $jobConfig
     * @return array
     */
    protected function runResponseModules($response, JobConfig $jobConfig)
    {
        $responseModule = new FindResponseArray($this->logger);
        return $responseModule->process($response, $jobConfig);
    }

    /**
     * Add parameters from parent call to the Endpoint.
     * The parameter name in the config's endpoint has to be enclosed in {}
     * @param array $params
     */
    public function setParams(array $params)
    {
        foreach ($params as $param) {
            $this->config->setEndpoint(
                str_replace('{' . $param['placeholder'] . '}', $param['value'], $this->config->getConfig()['endpoint'])
            );
        }

        $this->parentParams = $params;
    }


    /**
     * @param string $string
     * @return string
     */
    protected function prependParent($string)
    {
        return (substr($string, 0, 7) == "parent_") ? $string : "parent_{$string}";
    }

    /**
     * @param array $attributes
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @param Builder $builder
     */
    public function setBuilder(Builder $builder)
    {
        $this->stringBuilder = $builder;
    }

    /**
     * @param array $metadata
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function setUserParentId($id)
    {
        if (!is_array($id)) {
            throw new UserException(
                "User defined parent ID must be a key:value pair, or multiple such pairs.",
                0,
                null,
                ["id" => $id]
            );
        }

        $this->userParentId = $id;
    }

    /**
     * @return string
     */
    protected function getDataType()
    {
        $config = $this->config->getConfig();
        $type = !empty($config['dataType'])
            ? $config['dataType']
            : $config['endpoint'];
        return $type;
    }

    /**
     * @return string
     */
    public function getJobId()
    {
        return $this->jobId;
    }
}
