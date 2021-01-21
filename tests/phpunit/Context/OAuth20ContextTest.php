<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use Keboola\GenericExtractor\Context\OAuth20Context;
use PHPUnit\Framework\TestCase;

class OAuth20ContextTest extends TestCase
{
    public function testObjectData(): void
    {
        $request = RequestContextTest::createRequest();
        $data = (object) [
            'status' => 'ok',
            'foo' => [
                'bar' => 'baz',
            ],
        ];
        $context = OAuth20Context::create($request, 'my-client-id', $data);
        $expected = [
            'query' => [
                'param1' => 'abc',
                'param2' => 'ěšč',
            ],
            'request' => RequestContextTest::getExpectedContext(),
            'authorization' => [
                'clientId' => 'my-client-id',
                'nonce' => '****', // variable
                'timestamp' => '****', // variable
                'data.status' => 'ok',
                'data.foo.bar' => 'baz',
            ],
        ];

        // Nonce and timestamp are variable
        $context['authorization']['nonce'] = isset($context['authorization']['nonce']) ? '****' : 'UNDEFINED';
        $context['authorization']['timestamp'] = isset($context['authorization']['timestamp']) ? '****' : 'UNDEFINED';
        $this->assertSame($expected, $context);
    }

    public function testScalarData(): void
    {
        $request = RequestContextTest::createRequest();
        $data = 'my-text';
        $context = OAuth20Context::create($request, 'my-client-id', $data);
        $expected = [
            'query' => [
                'param1' => 'abc',
                'param2' => 'ěšč',
            ],
            'request' => RequestContextTest::getExpectedContext(),
            'authorization' => [
                'clientId' => 'my-client-id',
                'nonce' => '****', // variable
                'timestamp' => '****', // variable
                'data' => 'my-text',
            ],
        ];

        // Nonce and timestamp are variable
        $context['authorization']['nonce'] = isset($context['authorization']['nonce']) ? '****' : 'UNDEFINED';
        $context['authorization']['timestamp'] = isset($context['authorization']['timestamp']) ? '****' : 'UNDEFINED';
        $this->assertSame($expected, $context);
    }
}
