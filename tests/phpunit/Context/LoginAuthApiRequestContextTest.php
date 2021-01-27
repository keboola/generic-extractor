<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use Keboola\GenericExtractor\Context\LoginAuthApiRequestContext;
use PHPUnit\Framework\TestCase;

class LoginAuthApiRequestContextTest extends TestCase
{
    public function testComplex(): void
    {
        $loginResponse = (object) [
            'data' => (object) [
                'token' => '1234',
                'expires' => 5000,
            ],
        ];
        $configAttributes = ['attr1' => 123, 'attr2' => 'xyz'];
        $context = LoginAuthApiRequestContext::create($loginResponse, $configAttributes);
        $expected = [
            'response' => [
                'data' => [
                    'token' => '1234',
                    'expires' => 5000,
                ],
            ],
            'attr' => [
                'attr1' => 123,
                'attr2' => 'xyz',
            ],
        ];
        $this->assertSame($expected, $context);
    }
}
