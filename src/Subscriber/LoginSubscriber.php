<?php

namespace Keboola\GenericExtractor\Subscriber;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Might better be able to work with ANY type of auth, and tweak the request accordingly
 */
class LoginSubscriber implements SubscriberInterface
{
    protected ?int $expires = null;

    protected ?bool $loggedIn = null;

    /**
     * @var callable
     */
    protected $loginFunction;

    /**
     * @var array
     */
    protected $query;

    /**
     * @var array
     */
    protected $headers;

    public function getEvents()
    {
        return ['before' => ['onBefore', RequestEvents::SIGN_REQUEST]];
    }

    public function onBefore(BeforeEvent $event)
    {
        if (!$this->loggedIn || (!is_null($this->expires) && (time() > $this->expires))) {
            $details = $this->logIn();
            $this->query = $details['query'];
            $this->headers = $details['headers'];
        }

        $event->getRequest()->getQuery()->merge($this->query);
        $head = $event->getRequest()->getHeaders();
        $event->getRequest()->setHeaders(array_replace($head, $this->headers));
    }

    /**
     * @return array ['headers' => .., 'query' => ..]
     * @todo consider just setting RestClient and loginRequest here
     */
    protected function logIn(): array
    {
        $result = call_user_func($this->loginFunction);
        $this->expires = $result['expires'];
        $this->loggedIn = true;
        return $result;
    }

    /**
     * @param callable $login A method that logs the user in
     */
    public function setLoginMethod(callable $login)
    {
        $this->loginFunction = $login;
    }
}
