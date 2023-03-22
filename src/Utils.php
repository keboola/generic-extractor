<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use GuzzleHttp\Psr7\Query;
use Keboola\GenericExtractor\Exception\UserException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class Utils
{
    public static function getResource(UriInterface $uri): string
    {
        return $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : '');
    }

    /**
     * Merge HTTP queries, query2 values take precedence over query1 values.
     * @param string|array $query1
     * @param string|array $query2
     * @param bool $mergeToArray
     */
    public static function mergeQueries($query1, $query2, bool $mergeToArray = false): string
    {
        $query1 = is_array($query1) ? $query1 : Query::parse($query1);
        $query2 = is_array($query2) ? $query2 : Query::parse($query2);
        $mergeToArrayValues = [];

        foreach ($query2 as $key => $value) {
            if (array_key_exists($key, $query1) && $mergeToArray) {
                $mergeToArrayValues[$key] = array_merge(
                    $mergeToArrayValues[$key] ?? [$query1[$key]],
                    [$value]
                );
                unset($query1[$key]);
            } else {
                $query1[$key] = $value;
            }
        }

        foreach ($mergeToArrayValues as $key => $values) {
            foreach (array_unique($values) as $i => $value) {
                $query1[$key. "[$i]"] = $value;
            }
        }

        return Query::build($query1);
    }

    /**
     * Merge HTTP headers, new headers values take precedence.
     */
    public static function mergeHeaders(RequestInterface $request, array $headers): RequestInterface
    {
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    public static function checkHeadersForStdClass(array $array, array $path = []): void
    {
        foreach ($array as $key => $value) {
            $currentPath = array_merge($path, [$key]);
            if (is_array($value)) {
                self::checkHeadersForStdClass($value, $currentPath);
            } elseif (!is_scalar($value) && !is_null($value)) {
                throw new UserException(sprintf(
                    'Invalid configuration: invalid type "%s" in headers at path: %s',
                    gettype($value),
                    implode('.', $currentPath)
                ));
            }
        }
    }
}
