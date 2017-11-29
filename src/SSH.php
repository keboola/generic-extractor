<?php

namespace Keboola\GenericExtractor;

use Symfony\Component\Process\Process;

class SSH
{
    const SSH_SERVER_ALIVE_INTERVAL = 15;

    /**
     *
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
     * @return Process
     * @throws SSHException
     */
    public function openTunnel(array $config)
    {
        $missingParams = array_diff(
            ['user', 'sshHost', 'sshPort', 'localPort','privateKey'],
            array_keys($config)
        );

        if (!empty($missingParams)) {
            throw new SSHException(sprintf("Missing parameters '%s'", implode(',', $missingParams)));
        }

        $cmd = sprintf(
            'ssh -D %s %s@%s -p %s -i %s -fN -o ExitOnForwardFailure=yes -o StrictHostKeyChecking=no -o ServerAliveInterval=%d',
            $config['localPort'],
            $config['user'],
            $config['sshHost'],
            $config['sshPort'],
            $this->writeKeyToFile($config['privateKey']),
            self::SSH_SERVER_ALIVE_INTERVAL
        );

        $process = new Process($cmd);
        $process->setTimeout(60);
        $process->start();

        while ($process->isRunning()) {
            sleep(1);
        }

        if ($process->getExitCode() !== 0) {
            throw new SSHException(sprintf(
                "Unable to create ssh tunnel. Output: %s ErrorOutput: %s",
                $process->getOutput(),
                $process->getErrorOutput()
            ));
        }

        return $process;
    }

    /**
     * @param string $key
     * @return string
     * @throws SSHException
     */
    private function writeKeyToFile($key)
    {
        if (empty($key)) {
            throw new SSHException("Key must not be empty");
        }
        $fileName = tempnam('/tmp/', 'ssh-key-');
        file_put_contents($fileName, $key);
        chmod($fileName, 0600);
        return realpath($fileName);
    }
}
