<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Keboola\GenericExtractor\Context\RequestContext;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class RequestContextTest extends TestCase
{
    public function testComplex(): void
    {
        $context = RequestContext::create(self::createRequest());
        $expected = self::getExpectedContext();
        $this->assertSame($expected, $context);
    }

    public function testMinimal(): void
    {
        $context = RequestContext::create(new Request('GET', 'http://example.com'));
        $expected = [
            'url' => 'http://example.com',
            'path' => '',
            'queryString' => '',
            'method' => 'GET',
            'hostname' => 'example.com',
            'port' => 80,
            'resource' => '',
        ];
        $this->assertSame($expected, $context);
    }

    public static function createRequest(): RequestInterface
    {
        return new Request(
            'POST',
            Utils::uriFor('https://example.com:123/api/test/?param1=abc&param2=%C4%9B%C5%A1%C4%8D'),
            [
                'X-Test-Foo' => 'Bar',
            ],
            '{"some": "body"}'
        );
    }

    public static function getExpectedContext(): array
    {
        return [
            'url' => 'https://example.com:123/api/test/?param1=abc&param2=%C4%9B%C5%A1%C4%8D',
            'path' => '/api/test/',
            'queryString' => 'param1=abc&param2=%C4%9B%C5%A1%C4%8D',
            'method' => 'POST',
            'hostname' => 'example.com',
            'port' => 123,
            'resource' => '/api/test/?param1=abc&param2=%C4%9B%C5%A1%C4%8D',
        ];
    }
}
