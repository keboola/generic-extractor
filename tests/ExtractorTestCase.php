<?php
namespace Keboola\GenericExtractor;

use Keboola\Juicer\Common\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Message\RequestInterface;
use Monolog\Handler\NullHandler;

class ExtractorTestCase extends \PHPUnit_Framework_TestCase
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

    protected function getLogger($name = 'test', $null = false)
    {
        return new \Monolog\Logger(
            $name,
            $null ? [new NullHandler()] : []
        );
    }

    public function setUp()
    {
        Logger::initLogger('ex-generic_test');
    }

    protected static function sendRequest(Client $client, RequestInterface $request)
    {
        try {
            return $client->send($request);
        } catch (ClientException $e) {
            // this is expected, just need to send the request somewhere!
        }
    }
}
