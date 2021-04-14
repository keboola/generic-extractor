<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Traits;

use GuzzleHttp\Client;

trait ToxiproxyTrait
{
    protected function clearAllProxies(): void
    {
        $client = $this->getToxiClient();
        $body = json_decode($client->get('/proxies')->getBody()->getContents(), true);
        $names = array_map(fn (array $proxy) => $proxy['name'], $body);
        foreach ($names as $name) {
            $client->delete("/proxies/${name}");
        }
    }

    protected function createProxy(string $proxyName, string $upstream, int $listenPort): void
    {
        $this->getToxiClient()->post('/proxies', [
            'body' => sprintf(
                '{"name": "%s", "upstream": "%s", "listen": "0.0.0.0:%s"}',
                $proxyName,
                $upstream,
                $listenPort
            ),
        ]);
    }

    protected function simulateNetworkLimitDataThenDown(string $proxyName, int $bytes): string
    {
        $response = $this->getToxiClient()->post("/proxies/${proxyName}/toxics", [
            'body' => sprintf(
                '{"type": "limit_data", "attributes": {"bytes": %d }}',
                $bytes,
            ),
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        return $body['name'];
    }

    protected function removeToxic(string $proxyName, string $toxicName): void
    {
        $this->getToxiClient()->delete("/proxies/${proxyName}/toxics/${toxicName}");
    }

    private function getToxiClient(): Client
    {
        return new Client([
            'base_uri' => 'http://toxiproxy:8474',
        ]);
    }
}
