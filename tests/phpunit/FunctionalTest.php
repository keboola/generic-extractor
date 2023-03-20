<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests;

use Keboola\GenericExtractor\Tests\Traits\RmDirTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class FunctionalTest extends TestCase
{
    use RmDirTrait;

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
    }

    public function testInvalidHeaderConfig(): void
    {
        $dataDir = __DIR__ . '/data/invalidHeadersConfig';
        $process = $this->startPhpProcess($dataDir);
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(1, $process->getExitCode());
        Assert::assertStringContainsString(
            'Invalid configuration: invalid type "object" in headers at path: Authorization.0',
            $stdout
        );
    }

    public function testInvalidHeaderConfigOauth(): void
    {
        $dataDir = __DIR__ . '/data/invalidHeadersConfigOauth';
        $process = $this->startPhpProcess($dataDir);
        $process->wait();
        $stdout = $process->getOutput();
        Assert::assertSame(1, $process->getExitCode());
        Assert::assertStringContainsString(
            'Invalid configuration: invalid type "object" in headers at path: Authorization [] []',
            $stdout
        );
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
