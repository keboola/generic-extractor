<?php

namespace Keboola\GenericExtractor\Response;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\GenericExtractor;
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

    protected array $filters;

    protected string $delimiter;

    /**
     * Compatibility level
     */
    private int $compatLevel;

    public function __construct(JobConfig $config, int $compatLevel)
    {
        $this->filters = empty($config->getConfig()['responseFilter'])
            ? []
            : (is_array($config->getConfig()['responseFilter'])
                ? $config->getConfig()['responseFilter']
                : [$config->getConfig()['responseFilter']]);

        $this->delimiter = empty($config->getConfig()['responseFilterDelimiter'])
            ? self::DEFAULT_DELIMITER
            : $config->getConfig()['responseFilterDelimiter'];
        $this->compatLevel = $compatLevel;
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

        if ($this->compatLevel <= GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY_SCALAR) {
            if (empty($item->{$key})) {
                return $item;
            }
        } else {
            if (!is_object($item) || !property_exists($item, $key)) {
                return $item;
            }
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
        if ($this->compatLevel <= GenericExtractor::COMPAT_LEVEL_FILTER_EMPTY_SCALAR) {
            return is_scalar($item) ? $item : json_encode($item);
        } else {
            return json_encode($item);
        }
    }
}
