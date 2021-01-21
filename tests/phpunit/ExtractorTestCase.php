<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Ring\Future\FutureInterface;
use http\Env\Response;
use PHPUnit\Framework\TestCase;

class ExtractorTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('TODO fix test');
        parent::setUp();
    }

    /**
     * @return mixed
     */
    protected static function callMethod(object $obj, string $name, array $args = [])
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    /**
     * @return mixed
     */
    protected static function getProperty(object $obj, string $name)
    {
        $class = new \ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    protected static function sendRequest(Client $client, RequestInterface $request): ?ResponseInterface
    {
        try {
            return $client->send($request);
        } catch (ClientException $e) {
            // this is expected, just need to send the request somewhere!
            return null;
        }
    }
}
