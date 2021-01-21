<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Temp\Temp;
use Symfony\Component\Process\Process;

class SSH
{
    public const SSH_SERVER_ALIVE_INTERVAL = 15;

    private Temp $temp;

    public function __construct()
    {
        $this->temp = new Temp('ssh-tunnel');
    }

    /**
     * $user, $sshHost, $localPort, $remoteHost, $remotePort, $privateKey, $sshPort = '22'
     *
     * @param array $config
     *  - user
     *  - sshHost
     *  - sshPort
     *  - localPort
     *  - remoteHost
     *  - remotePort
     *  - privateKey
     *
     * @throws UserException
     */
    public function openTunnel(array $config): void
    {
        $missingParams = array_diff(
            ['user', 'sshHost', 'sshPort', 'localPort','privateKey'],
            array_keys($config)
        );

        if (!empty($missingParams)) {
            throw new UserException(sprintf("Missing parameters '%s'", implode(',', $missingParams)));
        }

        $cmd = sprintf(
            'ssh -D %s %s@%s -p %s -i %s -fN ' .
            '-o ExitOnForwardFailure=yes -o StrictHostKeyChecking=no -o ServerAliveInterval=%d',
            $config['localPort'],
            $config['user'],
            $config['sshHost'],
            $config['sshPort'],
            $this->writeKeyToFile($config['privateKey']),
            self::SSH_SERVER_ALIVE_INTERVAL
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(60);
        $process->start();

        while ($process->isRunning()) {
            sleep(1);
        }

        if ($process->getExitCode() !== 0) {
            throw new UserException(
                sprintf(
                    'Unable to create ssh tunnel. Output: %s ErrorOutput: %s',
                    $process->getOutput(),
                    $process->getErrorOutput()
                )
            );
        }
    }

    private function writeKeyToFile(string $key): string
    {
        if (empty($key)) {
            throw new UserException('Key must not be empty');
        }
        $path = (string) $this->temp->createFile('ssh-key')->getRealPath();
        (string) file_put_contents($path, $key);
        chmod($path, 0600);
        return $path;
    }
}
