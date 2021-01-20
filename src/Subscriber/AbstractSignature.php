<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Subscriber;

use Psr\Http\Message\RequestInterface;

abstract class AbstractSignature
{
    /**
     * @var callable
     */
    protected $generator;

    public function getEvents(): array
    {
        return ['before' => ['onBefore', RequestEvents::SIGN_REQUEST]];
    }

    public function onBefore(BeforeEvent $event): void
    {
        $request = $event->getRequest();

        $this->addSignature($request);
    }

    abstract protected function addSignature(RequestInterface $request): void;

    /**
     * @param callable $generator A method that returns query parameters required for authentication
     */
    public function setSignatureGenerator(callable $generator): void
    {
        $this->generator = $generator;
    }

    /**
     * @return array ['query' => ..., 'request' => ...]
     */
    protected function getRequestAndQuery(RequestInterface $request): array
    {
        $query = [];
        foreach ($request->getQuery() as $param => $val) {
            $query[$param] = $val;
        }
        $requestInfo = [
            'url' => $request->getUrl(),
            'path' => $request->getPath(),
            'queryString' => (string) $request->getQuery(),
            'method' => $request->getMethod(),
            'hostname' => $request->getHost(),
            'port' => $request->getPort(),
            'resource' => $request->getResource(),
            // if needed, ksorted query string can come here
        ];

        return [
            'query' => $query,
            'request' => $requestInfo,
        ];
    }
}
