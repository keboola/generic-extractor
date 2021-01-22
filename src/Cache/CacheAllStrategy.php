<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Cache;

use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cache ALL response, useful for the development of templates.
 * It is not publicly documented.
 */
class CacheAllStrategy extends PublicCacheStrategy
{
    private int $ttl;

    public function __construct(CacheStorageInterface $cache, int $ttl)
    {
        parent::__construct($cache);
        $this->ttl = $ttl;
    }

    protected function getCacheObject(RequestInterface $request, ResponseInterface $response): CacheEntry
    {
        return new CacheEntry(
            $request,
            $response,
            new \DateTime(sprintf('+%d seconds', $this->ttl))
        );
    }

    public function fetch(RequestInterface $request): ?CacheEntry
    {
        return $this->storage->fetch($this->getCacheKey($request));
    }
}
