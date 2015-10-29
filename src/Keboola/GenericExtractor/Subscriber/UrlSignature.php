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
class UrlSignature implements SubscriberInterface
{
    /** @var callable */
    protected $generator;

    public function getEvents()
    {
        return ['before' => ['onBefore', RequestEvents::SIGN_REQUEST]];
    }

    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();

        $this->addSignature($request);
    }

    /**
     * @param RequestInterface $request
     */
    private function addSignature(RequestInterface $request)
    {
        $authQuery = call_user_func($this->generator);
        $rQuery = $request->getQuery()->merge($authQuery);
    }

    /**
     * @param callable $generator A method that returns query parameters required for authentication
     */
    public function setSignatureGenerator(callable $generator)
    {
        $this->generator = $generator;
    }
}
