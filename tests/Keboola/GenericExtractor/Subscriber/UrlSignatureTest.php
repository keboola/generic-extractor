<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Subscriber\UrlSignature;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Event\BeforeEvent,
    GuzzleHttp\Transaction,
    GuzzleHttp\Client;

class UrlSignatureTest extends ExtractorTestCase
{
    public function testAddSignature()
    {
        $request = new Request('GET', '/endpoint');
        $transaction = new Transaction(new Client(), $request);
        $event = new BeforeEvent($transaction);

        $subscriber = new UrlSignature();
        $subscriber->setSignatureGenerator(
            function() {
                return ['token' => 'tokenValue'];
            }
        );

        $subscriber->onBefore($event);

        $this->assertEquals('tokenValue', $request->getQuery()->get('token'));
    }
}
