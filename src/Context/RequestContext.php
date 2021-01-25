<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

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
            'port' => self::getPort($uri),
            'resource' => $uri->getPath() . ($uri->getQuery() ? '?' . $uri->getQuery() : ''),
        ];
    }

    private static function getPort(UriInterface $uri): ?int
    {
        $port = $uri->getPort();
        if ($port) {
            return $port;
        }

        switch (strtolower($uri->getScheme())) {
            case 'http':
                return 80;

            case 'https':
                return 443;
        }

        return null;
    }
}
