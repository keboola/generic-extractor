<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

use Psr\Http\Message\RequestInterface;

/** Used by: QueryAuthContext */
class RequestContext
{
    public static function create(RequestInterface $request): array
    {
        $uri = $request->getUri();
        return [
            'url' => (string) $uri,
            'path' => $uri->getPath(),
            'queryString' => $uri->getQuery(),
            'method' => $request->getMethod(),
            'hostname' => $uri->getHost(),
            'port' => $uri->getPort(),
            'resource' => $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : ''),
        ];
    }
}
