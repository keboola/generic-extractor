<?php

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\BeforeEvent;
// use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use Keboola\Juicer\Common\Logger;

/**
 * Might better be able to work with ANY type of auth, and tweak the request accordingly
 */
class LogRequest implements SubscriberInterface
{
    public function getEvents()
    {
        return ['before' => ['onBefore', -1]];
    }

    public function onBefore(BeforeEvent $event)
    {
        Logger::log("DEBUG", (string) $event->getRequest());
    }
}
