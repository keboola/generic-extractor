<?php

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\RequestInterface;

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
        foreach ($authQuery as $key => $value) {
            if (!$request->getQuery()->hasKey($key)) {
                $request->getQuery()->set($key, $value);
            }
        }
    }
}
