<?php

namespace Keboola\GenericExtractor\Response;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Config\JobConfig;

/**
 * Processes data and converts them to scalar values by
 * JSON encoding it.
 *
 * To process an entire array, the path must be 'array',
 * while to process each item within an array separately,
 * the path would be 'array[]'.
 */
class Filter
{
    const DEFAULT_DELIMITER = '.';

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var string
     */
    protected $delimiter;

    public function __construct(array $filters, $delimiter = self::DEFAULT_DELIMITER)
    {
        $this->filters = $filters;
        $this->delimiter = $delimiter;
    }

    /**
     * @param JobConfig $config
     * @return Filter
     */
    public static function create(JobConfig $config)
    {
        $filters = empty($config->getConfig()['responseFilter'])
            ? []
            : (is_array($config->getConfig()['responseFilter'])
                ? $config->getConfig()['responseFilter']
                : [$config->getConfig()['responseFilter']]);

        $delimiter = empty($config->getConfig()['responseFilterDelimiter'])
            ? self::DEFAULT_DELIMITER
            : $config->getConfig()['responseFilterDelimiter'];

        return new self($filters, $delimiter);
    }

    /**
     * Filters the $data array according to
     * $config->getConfig()['responseFilter'] and
     * returns the filtered array
     *
     * @param array $data
     * @return array
     */
    public function run(array $data)
    {
        foreach ($this->filters as $path) {
            foreach ($data as &$item) {
                $item = $this->filterItem($item, $path);
            }
        }

        return $data;
    }

    /**
     * @param \stdClass $item
     * @param string $path
     * @throws UserException
     * @return \stdClass
     */
    protected function filterItem($item, $path)
    {
        $currentPath = explode($this->delimiter, $path, 2);

        if ('[]' == substr($currentPath[0], -2)) {
            $key = substr($currentPath[0], 0, -2);
            $arr = true;
        } else {
            $key = $currentPath[0];
            $arr = false;
        }

        if (empty($item->{$key})) {
            return $item;
        }

        if ($arr) {
            if (!is_array($item->{$key})) {
                throw new UserException("Error filtering response. '{$key}' is not an array.");
            }

            foreach ($item->{$key} as &$subItem) {
                if (count($currentPath) == 1) {
                    $subItem = $this->updateItem($subItem);
                } else {
                    $subItem = $this->filterItem($subItem, $currentPath[1]);
                }
            }
        } else {
            if (count($currentPath) == 1) {
                $item->{$key} = $this->updateItem($item->{$key});
            } else {
                $item->{$key} = $this->filterItem($item->{$key}, $currentPath[1]);
            }
        }

        return $item;
    }

    /**
     * @param mixed $item
     * @return string
     */
    protected function updateItem($item)
    {
        return is_scalar($item) ? $item : json_encode($item);
    }
}
