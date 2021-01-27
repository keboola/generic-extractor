<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use Keboola\GenericExtractor\Context\OAuth20LoginContext;
use PHPUnit\Framework\TestCase;

class OAuth20LoginContextTest extends TestCase
{
    public function testComplex(): void
    {
        $configAttributes = ['attr1' => 123, 'attr2' => 'xyz'];
        $key = 'my-key';
        $secret = 'my-secret';
        $data = [
            'status' => 'ok',
            'access_token' => '1234',
        ];
        $context = OAuth20LoginContext::create($key, $secret, $data, $configAttributes);
        $expected = [
            'consumer' => [
                'client_id' => 'my-key',
                'client_secret' => 'my-secret',
            ],
            'user' => [
                'status' => 'ok',
                'access_token' => '1234',
            ],
            'attr' => [
                'attr1' => 123,
                'attr2' => 'xyz',
            ],
        ];
        $this->assertSame($expected, $context);
    }
}
