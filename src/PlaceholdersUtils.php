<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use Keboola\Code\Builder;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Utils\Exception\NoDataFoundException;
use function Keboola\Utils\arrayToObject;
use function Keboola\Utils\getDataFromPath;

class PlaceholdersUtils
{
    public static function getParamsForChildJobs(array $placeholders, array $parentResults, array $parentParams): array
    {
        $params = [];
        foreach ($placeholders as $placeholder => $field) {
            $params[$placeholder] = self::getPlaceholder($placeholder, $field, $parentResults);
        }

        // Add parent params as well (for 'tagging' child-parent data)
        // Same placeholder in deeper nesting replaces parent value
        $params = array_replace($parentParams, $params);

        // Create all combinations if there are some parameter values as array.
        // Each combination will be one child job.
        return self::flattenParameters($params);
    }

    /**
     * @param  string|array $field Path or a function with a path
     * @return array ['placeholder', 'field', 'value']
     */
    public static function getPlaceholder(string $placeholder, $field, array $parentResults): array
    {
        // TODO allow using a descriptive ID(level) by storing the result by `task(job) id` in $parentResults
        $level = strpos($placeholder, ':') === false
            ? 0
            : (int) strtok($placeholder, ':') -1;

        // Check function (defined as array)
        if (!is_scalar($field)) {
            if (empty($field['path'])) {
                throw new UserException(
                    "The path for placeholder '{$placeholder}' must be a string value or an object ".
                    "containing 'path' and 'function'."
                );
            }

            $fn = (object) arrayToObject($field);
            $field = $field['path'];
            unset($fn->path);
        }

        // Get value
        $value = self::getPlaceholderValue($field, $parentResults, $level, $placeholder);

        // Run function
        if (isset($fn)) {
            $builder = new Builder;
            $builder->allowFunction('urlencode');
            $value = $builder->run($fn, ['placeholder' => ['value' => $value]]);
        }

        // Return definition
        return [
            'placeholder' => $placeholder,
            'field' => $field,
            'value' => $value,
        ];
    }

    /**
     * @return mixed
     */
    public static function getPlaceholderValue(string $field, array $parentResults, int $level, string $placeholder)
    {
        try {
            if (!array_key_exists($level, $parentResults)) {
                $maxLevel = empty($parentResults) ? 0 : (int) max(array_keys($parentResults)) +1;
                throw new UserException(
                    'Level ' . ++$level . ' not found in parent results! Maximum level: ' . $maxLevel
                );
            }

            return getDataFromPath($field, $parentResults[$level], '.', false);
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

    public static function flattenParameters(array $params): array
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
}
