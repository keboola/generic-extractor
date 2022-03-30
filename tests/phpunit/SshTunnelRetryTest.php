<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Tests\Traits\CloseSshTunnelsTrait;
use Keboola\GenericExtractor\Tests\Traits\RmDirTrait;
use Keboola\GenericExtractor\Tests\Traits\ToxiproxyTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class SshTunnelRetryTest extends TestCase
{
    use CloseSshTunnelsTrait;
    use RmDirTrait;
    use ToxiproxyTrait;

    private array $rmDirs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rmDirs = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove out dirs
        foreach ($this->rmDirs as $dir) {
            $this->rmDir($dir);
        }

        $this->clearAllProxies();
        $this->closeSshTunnels();
    }

    public function testRun(): void
    {
        $dataDir = __DIR__ . '/data/ssh_tunnel';
        $process = $this->startPhpProcess($dataDir);
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(0, $process->getExitCode());
        Assert::assertSame(1, substr_count($stdout, 'SSH tunnel created.'));
        Assert::assertStringContainsString('Extractor finished successfully.', $stdout);
    }

    public function testBadConfig(): void
    {
        $dataDir = __DIR__ . '/data/ssh_tunnel_bad_config';
        $process = $this->startPhpProcess($dataDir);
        $process->wait();
        Assert::assertSame(1, $process->getExitCode());
        Assert::assertStringContainsString('Unable to create ssh tunnel', $process->getOutput());
        Assert::assertStringContainsString('Retrying... [1x]', $process->getOutput());
        Assert::assertStringContainsString('Retrying... [4x]', $process->getOutput());
    }

    public function testRetryServerError(): void
    {
        $dataDir = __DIR__ . '/data/ssh_tunnel_server_error';
        $process = $this->startPhpProcess($dataDir);

        // Server error, Guzzle did the retry -> the SSH tunnel is OK -> no SSH tunnel reconnecting
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(1, $process->getExitCode());
        Assert::assertStringContainsString('Creating SSH tunnel to \'ssh-proxy\'', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 1.0 seconds [1x].', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 2.0 seconds [2x].', $stdout);
        Assert::assertSame(1, substr_count($stdout, 'SSH tunnel created.'));
    }

    public function testNetworkProblemTemporal(): void
    {
        // Block network after 5kb (SSH tunnel already created, downloading 10kb response from API)
        $this->createProxy('ssh-server', 'ssh-proxy:22', 2222);
        $toxicName = $this->simulateNetworkLimitDataThenDown('ssh-server', 5000);

        $dataDir = __DIR__ . '/data/ssh_tunnel_net_problem';
        $process = $this->startPhpProcess($dataDir);

        // Make network working after 2 seconds
        sleep(2);
        $this->removeToxic('ssh-server', $toxicName);

        // Process failed, Guzzle did the retry -> the SSH tunnel is reconnected
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(0, $process->getExitCode());
        Assert::assertStringContainsString('Creating SSH tunnel to \'toxiproxy\'', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 1.0 seconds [1x].', $stdout);
        Assert::assertStringContainsString('SSH tunnel is not alive. Reconnecting ...', $stdout);
        Assert::assertStringContainsString('Extractor finished successfully.', $stdout);
        Assert::assertSame(1, substr_count($stdout, 'SSH tunnel is not alive. Reconnecting ...'));
        Assert::assertSame(2, substr_count($stdout, 'SSH tunnel created.'));
    }

    public function testNetworkProblemPersists(): void
    {
        // Block network after 5kb (SSH tunnel already created, downloading 10kb response from API)
        $this->createProxy('ssh-server', 'ssh-proxy:22', 2222);
        $this->simulateNetworkLimitDataThenDown('ssh-server', 5000);

        $dataDir = __DIR__ . '/data/ssh_tunnel_net_problem';
        $process = $this->startPhpProcess($dataDir);

        // Process failed, Guzzle did the retry -> the SSH tunnel is NOT reconnected, network is still down
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(1, $process->getExitCode());
        Assert::assertStringContainsString('Creating SSH tunnel to \'toxiproxy\'', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 1.0 seconds [1x].', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 2.0 seconds [2x].', $stdout);
        Assert::assertStringContainsString('Http request failed, retrying in 4.0 seconds [3x].', $stdout);
        Assert::assertSame(3, substr_count($stdout, 'SSH tunnel is not alive. Reconnecting ...'));
        Assert::assertSame(4, substr_count($stdout, 'SSH tunnel created.'));
    }

    public function startPhpProcess(string $dataDir): Process
    {
        $outDir = $dataDir . '/out';
        $runPhp = __DIR__ . '/../../src/run.php';
        $this->rmDirs[] = $outDir;
        $process = new Process(['php', "$runPhp"], null, ['KBC_DATADIR' => $dataDir]);
        $process->setTimeout(60);
        $process->start();
        return $process;
    }
}
