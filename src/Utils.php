<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use GuzzleHttp\Psr7\Query;

class Utils
{
    /**
     * Merge HTTP queries, query1 values take precedence over query2 values.
     * @param string|array $query1
     * @param string|array $query2
     */
    public static function mergeQueries($query1, $query2): string
    {
        $query1 = is_array($query1) ? $query1 : Query::parse($query1);
        $query2 = is_array($query2) ? $query2 : Query::parse($query2);

        foreach ($query2 as $key => $value) {
            $query1[$key] = $value;
        }

        return Query::build($query1);
    }
}
