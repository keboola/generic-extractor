<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use PHPUnit\Framework\TestCase;

class ExtractorTestCase extends TestCase
{
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
}
