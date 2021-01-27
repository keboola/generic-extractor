<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Authentication;

use Keboola\GenericExtractor\Authentication\Basic;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Tests\HistoryContainer;
use Keboola\Juicer\Tests\RestClientMockBuilder;
use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    /**
     * @dataProvider credentialsProvider
     */
    public function testAuthenticateClient(array $credentials): void
    {
        $auth = new Basic($credentials);

        // Create RestClient
        $history = new HistoryContainer();
        $restClient = RestClientMockBuilder::create()
            ->addResponse200('{"foo": "bar1"}')
            ->addResponse200('{"foo": "bar2"}')
            ->setHistoryContainer($history)
            ->setInitCallback(function (RestClient $restClient) use ($auth): void {
                $auth->attachToClient($restClient);
            })
            ->getRestClient();

        // Each request contains Authorization header
        self::assertEquals((object) ['foo' => 'bar1'], $restClient->download(new RestRequest(['endpoint' => 'ep'])));
        self::assertSame(
            'Basic dGVzdDpwYXNz',
            $history->pop()->getRequest()->getHeaderLine('Authorization')
        );
        self::assertEquals((object) ['foo' => 'bar2'], $restClient->download(new RestRequest(['endpoint' => 'ep'])));
        self::assertSame(
            'Basic dGVzdDpwYXNz',
            $history->pop()->getRequest()->getHeaderLine('Authorization')
        );
    }

    public function credentialsProvider(): array
    {
        return [
            [
                ['username' => 'test', 'password' => 'pass'],
            ],
            [
                ['#username' => 'test', '#password' => 'pass'],
            ],
        ];
    }
}
