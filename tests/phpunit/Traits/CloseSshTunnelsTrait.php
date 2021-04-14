<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Tests\Traits;

use Symfony\Component\Process\Process;

trait CloseSshTunnelsTrait
{
    protected function closeSshTunnels(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }
}
