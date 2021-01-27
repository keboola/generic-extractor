<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use Keboola\GenericExtractor\Context\LoginAuthLoginRequestContext;
use PHPUnit\Framework\TestCase;

class LoginAuthLoginRequestContextTest extends TestCase
{
    public function testComplex(): void
    {
        $configAttributes = ['attr1' => 123, 'attr2' => 'xyz'];
        $context = LoginAuthLoginRequestContext::create($configAttributes);
        $expected = [
            'attr' => [
                'attr1' => 123,
                'attr2' => 'xyz',
            ],
        ];
        $this->assertSame($expected, $context);
    }
}
