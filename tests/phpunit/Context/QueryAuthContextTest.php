<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Context;

use Keboola\GenericExtractor\Context\QueryAuthContext;
use PHPUnit\Framework\TestCase;

class QueryAuthContextTest extends TestCase
{
    public function testComplex(): void
    {
        $request = RequestContextTest::createRequest();
        $configAttributes = ['attr1' => 123, 'attr2' => 'xyz'];
        $context = QueryAuthContext::create($request, $configAttributes);
        $expected = [
            'query' => [
                'param1' => 'abc',
                'param2' => 'ěšč',
            ],
            'request' => RequestContextTest::getExpectedContext(),
            'attr' => [
                'attr1' => 123,
                'attr2' => 'xyz',
            ],
        ];
        $this->assertSame($expected, $context);
    }
}
