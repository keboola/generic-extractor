<?php

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;

/**
 * Might better be able to work with ANY type of auth, and tweak the request accordingly
 */
class HeaderSignature extends AbstractSignature implements SubscriberInterface
{
    protected function addSignature(RequestInterface $request)
    {
        $authHeaders = call_user_func($this->generator, $this->getRequestAndQuery($request));
        foreach ($authHeaders as $key => $header) {
            $request->setHeader($key, $header);
        }
    }
}
