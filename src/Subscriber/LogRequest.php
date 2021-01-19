<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Might better be able to work with ANY type of auth, and tweak the request accordingly
 */
class LogRequest implements SubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function getEvents(): array
    {
        return ['before' => ['onBefore', RequestEvents::LATE]];
    }

    public function onBefore(BeforeEvent $event): void
    {
        $this->logger->debug((string) $event->getRequest());
    }
}
