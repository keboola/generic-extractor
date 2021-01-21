<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\RequestInterface;
use function Keboola\Utils\flattenArray;
use function Keboola\Utils\objectToArray;

/**
 * https://developers.keboola.com/extend/generic-extractor/functions/#oauth-20-authentication-context
 */
class OAuth20Context
{
    /**
     * @param string|object $data
     */
    public static function create(RequestInterface $request, string $clientId, $data): array
    {
        return [
            'query' => Query::parse($request->getUri()->getQuery()),
            'request' => RequestContext::create($request),
            'authorization' => self::getAuthorizationContext($clientId, $data),
        ];
    }

    /**
     * @param string|object $data
     */
    private static function getAuthorizationContext(string $clientId, $data): array
    {
        $authorization = [
            'clientId' => $clientId,
            'nonce' => substr(sha1(uniqid(microtime(), true)), 0, 16),
            'timestamp' => time(),
        ];

        if (!is_scalar($data)) {
            $authorization = array_merge(
                $authorization,
                flattenArray(objectToArray($data), 'data.')
            );
        } else {
            $authorization['data'] = $data;
        }

        return $authorization;
    }
}
