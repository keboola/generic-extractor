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
class HeaderSignature extends AbstractSignature implements SubscriberInterface
{
    /**
     * @param RequestInterface $request
     */
    protected function addSignature(RequestInterface $request)
    {
        $authHeaders = call_user_func($this->generator);
        foreach($authHeaders as $key => $header) {
            $request->setHeader($key, $header);
        }
    }
}
