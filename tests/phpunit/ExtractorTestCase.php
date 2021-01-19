<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\RequestInterface;
use PHPUnit\Framework\TestCase;

class ExtractorTestCase extends TestCase
{
    protected static function callMethod($obj, $name, array $args = [])
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($obj, $args);
    }

    protected static function getProperty($obj, $name)
    {
        $class = new \ReflectionClass($obj);
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    protected static function sendRequest(Client $client, RequestInterface $request)
    {
        try {
            return $client->send($request);
        } catch (ClientException $e) {
            // this is expected, just need to send the request somewhere!
            return null;
        }
    }
}
