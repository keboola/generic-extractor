<?php

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Response\Filter,
    Keboola\GenericExtractor\Config\UserFunction,
    Keboola\GenericExtractor\Modules\ResponseModuleInterface;
use Keboola\Juicer\Extractor\RecursiveJob,
    Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Common\Logger,
    Keboola\Juicer\Client\RequestInterface,
    Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Pagination\ScrollerInterface,
    Keboola\Juicer\Pagination\NoScroller;
use Keboola\Utils\Utils,
    Keboola\Utils\Exception\JsonDecodeException;
use Keboola\Code\Builder,
    Keboola\Code\Exception\UserScriptException;

class GenericExtractorJob extends RecursiveJob
{
    /**
     * @var array
     */
    protected $params;
    /**
     * @var array
     */
    protected $attributes;
    /**
     * @var array
     */
    protected $metadata;
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
     * @var ResponseModuleInterface[]
     */
    protected $responseModules = [];

    /**
     * {@inheritdoc}
     * Verify the latest response isn't identical as the last one
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
                Logger::log("DEBUG", sprintf("Job '%s' finished when last response matched the previous!", $this->getJobId()));
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
     * Create subsequent requests for pagination (usually based on $response from previous request)
     * Return a download request OR false if no next page exists
     *
     * @param JobConfig $config
     * @param mixed $response
     * @param array|null $data
     * @return RequestInterface | false
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
     * @return RequestInterface | false
     */
    protected function firstPage(JobConfig $config)
    {
        return $this->getScroller()->getFirstRequest($this->client, $config);
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
        if (!empty($this->config->getConfig()['userData']) || !empty($this->userParentId)) {
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
                $parentId = array_merge($this->userParentId, $jobUserData);
            } else {
                $parentId = $jobUserData;
            }

            if (empty($parentId)) {
                return null;
            } else {
                return UserFunction::build(
                    $parentId,
                    [
                        'attr' => $this->attributes,
                        'time' => $this->metadata['time']
                    ],
                    $this->stringBuilder
                );
            }
        }
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
                'time' => $this->metadata['time']
            ],
            $this->stringBuilder
        );

        $config->setParams($params);

        return $params;
    }

    /**
     * Inject $scroller into a child job
     * {@inheritdoc}
     */
    protected function createChild(JobConfig $config, array $parentResults)
    {
        $job = parent::createChild($config, $parentResults);
        $scroller = clone $this->scroller;
        $scroller->reset();
        $job->setScroller($scroller);
        $job->setMetadata($this->metadata);
        $job->setAttributes($this->attributes);
        $job->setBuilder($this->stringBuilder);
        $job->setResponseModules($this->responseModules);
        return $job;
    }

    /**
     * Filters the $data array according to
     * $config->getConfig()['responseFilter'] and
     * returns the filtered array
     *
     * @param JobConfig $config
     * @param array $data
     * @return array
     * @todo allow nesting
     * @todo turn into a module
     */
    protected function filterResponse(JobConfig $config, array $data)
    {
        $filter = Filter::create($config);
        return $filter->run($data);
    }

    protected function runResponseModules($response, JobConfig $jobConfig)
    {
        foreach($this->responseModules as $module) {
            $response = $module->process($response, $jobConfig);
        }

        return $response;
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
            throw new UserException("User defined parent ID must be a key:value pair, or multiple such pairs.", 0, null, ["id" => $id]);
        }

        $this->userParentId = $id;
    }

    public function setResponseModules(array $modules)
    {
        $this->responseModules = $modules;
    }
}
