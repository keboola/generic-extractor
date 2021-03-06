<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\RequestInterface;

/**
 * https://developers.keboola.com/extend/generic-extractor/functions/#query-authentication-context
 */
class QueryAuthContext
{
    public static function create(RequestInterface $request, array $configAttributes): array
    {
        return [
            'query' => Query::parse($request->getUri()->getQuery()),
            'request' => RequestContext::create($request),
            'attr' => $configAttributes,
        ];
    }
}
