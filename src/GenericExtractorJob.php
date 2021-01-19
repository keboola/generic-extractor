<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use GuzzleHttp\Message\RequestInterface;
use Keboola\Filter\Exception\FilterException;
use Keboola\Filter\FilterFactory;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Response\Filter;
use Keboola\GenericExtractor\Response\FindResponseArray;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Config\JobConfig;
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
    private JobConfig $config;

    private RestClient $client;

    private ParserInterface $parser;

    private ScrollerInterface $scroller;

    private string $jobId;

    private LoggerInterface $logger;

    private array $attributes = [];

    private array $metadata = [];

    private ?string $lastResponseHash = null;

    /**
     * Data to append to the root result
     */
    private ?array $userParentId = null;

    /**
     * Used to save necessary parents' data to child's output
     */
    private array $parentParams = [];

    private array $parentResults = [];

    /**
     * Compatibility level
     */
    private int $compatLevel;

    /**
     * @param RestClient      $client      A client used to communicate with the API (wrapper for Guzzle)
     * @param ParserInterface $parser      A parser to handle the result and convert it into CSV file(s)
     * @param int             $compatLevel Compatibility level, @see GenericExtractor
     */
    public function __construct(
        JobConfig $config,
        RestClient $client,
        ParserInterface $parser,
        LoggerInterface $logger,
        ScrollerInterface $scroller,
        array $attributes,
        array $metadata,
        int $compatLevel
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->client = $client;
        $this->parser = $parser;
        $this->scroller = $scroller;
        $this->jobId = $config->getJobId();
        $this->attributes = $attributes;
        $this->metadata = $metadata;
        $this->compatLevel = $compatLevel;
    }

    /**
     * Manages cycling through the requests as long as
     * scroller provides next page
     *
     * Verifies the latest response isn't identical as the last one
     * to prevent infinite loop on awkward pagination APIs
     */
    public function run(): void
    {
        $this->config->setParams($this->buildParams($this->config));

        $parentId = $this->getParentId();

        /** @var RestRequest $request */
        $request = $this->firstPage($this->config);
        while ($request !== false) {
            $response = $this->download($request);

            $responseHash = sha1(serialize($response));
            if ($responseHash === $this->lastResponseHash) {
                $this->logger->debug(
                    sprintf(
                        "Job '%s' finished when last response matched the previous!",
                        $this->getJobId()
                    )
                );
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

    private function runChildJobs(array $data): void
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
                if (!empty($filter) && ($filter->compareObject((object) $result) === false)) {
                    continue;
                }

                // Add current result to the beginning of an array, containing all parent results
                $parentResults = $this->parentResults;
                array_unshift($parentResults, $result);

                $childJobs = $this->createChild($child, $parentResults);
                foreach ($childJobs as $childJob) {
                    $childJob->run();
                }
            }
        }
    }

    /**
     * Create a child job with current client and parser
     *
     * @return static[]
     */
    private function createChild(JobConfig $config, array $parentResults): array
    {
        // Clone and reset Scroller
        $scroller = clone $this->scroller;
        $scroller->reset();

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
        $params = $this->flattenParameters($params);

        $jobs = [];
        foreach ($params as $index => $param) {
            // Clone the config to prevent overwriting the placeholder(s) in endpoint
            $job = new self(
                clone $config,
                $this->client,
                $this->parser,
                $this->logger,
                $scroller,
                $this->attributes,
                $this->metadata,
                $this->compatLevel
            );
            $job->setParams($param);
            $job->setParentResults($parentResults);
            $jobs[] = $job;
        }

        /**
 * @var static[] $jobs
*/
        return $jobs;
    }

    private function flattenParameters(array $params): array
    {
        $flatParameters = [];
        $i = 0;
        foreach ($params as $placeholderName => $placeholder) {
            $template = $placeholder;
            if (is_array($placeholder['value'])) {
                foreach ($placeholder['value'] as $value) {
                    $template['value'] = $value;
                    $flatParameters[$i][$placeholderName] = $template;
                    $i++;
                }
            } else {
                $flatParameters[$i][$placeholderName] = $template;
            }
        }
        return $flatParameters;
    }


    /**
     * @param  string|array $field Path or a function with a path
     * @return array ['placeholder', 'field', 'value']
     * @throws UserException
     */
    private function getPlaceholder(string $placeholder, $field, array $parentResults): array
    {
        // TODO allow using a descriptive ID(level) by storing the result by `task(job) id` in $parentResults
        $level = strpos($placeholder, ':') === false
            ? 0
            : (int) strtok($placeholder, ':') -1;

        if (!is_scalar($field)) {
            if (empty($field['path'])) {
                throw new UserException(
                    "The path for placeholder '{$placeholder}' must be a string value or an object ".
                    "containing 'path' and 'function'."
                );
            }

            $fn = (object) \Keboola\Utils\arrayToObject($field);
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
            'value' => $value,
        ];
    }

    /**
     * @return mixed
     * @throws UserException
     */
    private function getPlaceholderValue(string $field, array $parentResults, int $level, string $placeholder)
    {
        try {
            if (!array_key_exists($level, $parentResults)) {
                $maxLevel = empty($parentResults) ? 0 : (int) max(array_keys($parentResults)) +1;
                throw new UserException(
                    'Level ' . ++$level . ' not found in parent results! Maximum level: ' . $maxLevel
                );
            }

            return \Keboola\Utils\getDataFromPath($field, $parentResults[$level], '.', false);
        } catch (NoDataFoundException $e) {
            throw new UserException(
                "No value found for {$placeholder} in parent result. (level: " . ++$level . ')',
                0,
                null,
                [
                    'parents' => $parentResults,
                ]
            );
        }
    }

    public function setParentResults(array $results): void
    {
        $this->parentResults = $results;
    }

    /**
     *  Download an URL from REST or SOAP API and return its body as an object.
     * should handle the API call, backoff and response decoding
     *
     * @return mixed Raw response as it comes from the client
     */
    private function download(RestRequest $request)
    {
        return $this->client->download($request);
    }


    /**
     * Create subsequent requests for pagination (usually based on $response from previous request)
     * Return a download request OR false if no next page exists
     *
     * @param  mixed $response
     * @return RestRequest|false
     */
    private function nextPage(JobConfig $config, $response, array $data)
    {
        return $this->getScroller()->getNextRequest($this->client, $config, $response, $data);
    }

    /**
     * Create the first download request.
     * Return a download request
     *
     * @return RestRequest|bool
     */
    private function firstPage(JobConfig $config)
    {
        return $this->getScroller()->getFirstRequest($this->client, $config);
    }

    /**
     * Parse the result into a CSV (either using any of built-in parsers, or using own methods).
     *
     * Create subsequent jobs for recursive endpoints. Uses "children" section of the job config
     *
     * @param  array $parentId ID (or list thereof) to be passed to parser
     * @return array
     */
    private function parse(array $data, ?array $parentId = null): array
    {
        $this->parser->process($data, $this->config->getDataType(), $this->getParentCols($parentId));
        $this->runChildJobs($data);
        return $data;
    }

    private function getScroller(): ScrollerInterface
    {
        if (empty($this->scroller)) {
            $this->scroller = new NoScroller;
        }

        return $this->scroller;
    }

    private function getParentId(): ?array
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
                'time' => !empty($this->metadata['time']) ? $this->metadata['time'] : [],
            ]
        );
    }

    private function getParentCols(?array $parentIdCols = null): array
    {
        // Add parent values to the result
        $parentCols = is_null($parentIdCols) ? [] : $parentIdCols;
        foreach ($this->parentParams as $v) {
            $key = $this->prependParent($v['field']);
            $parentCols[$key] = $v['value'];
        }
        return $parentCols;
    }

    private function buildParams(JobConfig $config): array
    {
        $params = UserFunction::build(
            $config->getParams(),
            [
                'attr' => $this->attributes,
                'time' => !empty($this->metadata['time']) ? $this->metadata['time'] : [],
            ]
        );
        return $params;
    }

    /**
     * Filters the $data array according to
     * $config->getConfig()['responseFilter'] and
     * returns the filtered array
     */
    private function filterResponse(JobConfig $config, array $data): array
    {
        $filter = new Filter($config, $this->compatLevel);
        return $filter->run($data);
    }

    /**
     * @param array|object $response
     */
    private function runResponseModules($response, JobConfig $jobConfig): array
    {
        $responseModule = new FindResponseArray($this->logger);
        return $responseModule->process($response, $jobConfig);
    }

    /**
     * Add parameters from parent call to the Endpoint.
     * The parameter name in the config's endpoint has to be enclosed in {}
     */
    public function setParams(array $params): void
    {
        foreach ($params as $param) {
            $this->config->setEndpoint(
                str_replace('{' . $param['placeholder'] . '}', $param['value'], $this->config->getConfig()['endpoint'])
            );
        }

        $this->parentParams = $params;
    }


    private function prependParent(string $string): string
    {
        return (substr($string, 0, 7) === 'parent_') ? $string : "parent_{$string}";
    }

    /**
     * @param mixed $id
     */
    public function setUserParentId($id): void
    {
        if (!is_array($id)) {
            throw new UserException(
                'User defined parent ID must be a key:value pair, or multiple such pairs.',
                0,
                null,
                ['id' => $id]
            );
        }

        $this->userParentId = $id;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
