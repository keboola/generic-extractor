<?php

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;
use Keboola\Utils\Utils;

/**
 * Might better be able to work with ANY type of auth, and tweak the request accordingly
 */
class UrlSignature extends AbstractSignature implements SubscriberInterface
{
    /**
     * @param RequestInterface $request
     */
    protected function addSignature(RequestInterface $request)
    {
        $query = [];
        foreach($request->getQuery() as $param => $val) {
            $query[$param] = $val;
        }
        $requestInfo = [
            'url' => $request->getUrl(),
            'path' => $request->getPath()
        ];

        $authQuery = call_user_func($this->generator, ['query' => $query, 'request' => $requestInfo]);
        $rQuery = $request->getQuery()->merge($authQuery);
    }
}
