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
        $authQuery = call_user_func($this->generator, $this->getRequestAndQuery($request));
        $rQuery = $request->getQuery()->merge($authQuery);
    }
}
