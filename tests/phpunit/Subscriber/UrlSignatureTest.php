<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Subscriber;

use Keboola\GenericExtractor\Subscriber\UrlSignature;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Transaction;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class UrlSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('TODO fix test');
        parent::setUp();
    }

    public function testAddSignature(): void
    {
        $request = new Request('GET', '/endpoint');
        $transaction = new Transaction(new Client(), $request);
        $event = new BeforeEvent($transaction);

        $subscriber = new UrlSignature();
        $subscriber->setSignatureGenerator(
            fn() => ['token' => 'tokenValue']
        );

        $subscriber->onBefore($event);
        self::assertEquals('tokenValue', $request->getQuery()->get('token'));
    }

    public function testKeepSignature(): void
    {
        $request = new Request('GET', '/endpoint?token=originalValue');
        $transaction = new Transaction(new Client(), $request);
        $event = new BeforeEvent($transaction);

        $subscriber = new UrlSignature();
        $subscriber->setSignatureGenerator(
            fn() => ['token' => 'tokenValue']
        );

        $subscriber->onBefore($event);
        self::assertEquals('originalValue', $request->getQuery()->get('token'));
    }
}
