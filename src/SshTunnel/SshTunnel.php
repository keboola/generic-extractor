<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\SshTunnel;

use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Symfony\Component\Process\Process;
use Keboola\GenericExtractor\Exception\SshTunnelOpenException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Temp\Temp;

class SshTunnel
{
    public const SSH_SERVER_ALIVE_INTERVAL = 10;

    private const CREATE_SSH_MAX_RETRY = 5;

    private LoggerInterface $logger;
    private Temp $temp;
    private ?Process $process = null;
    private string $sshUser;
    private string $sshHost;
    private int $sshPort;
    private int $localPort;
    private string $privateKey;
    private bool $opened = false;

    public function __construct(
        LoggerInterface $logger,
        string $sshUser,
        string $sshHost,
        int $sshPort,
        int $localPort,
        string $privateKey
    ) {
        $this->logger = $logger;
        $this->temp = new Temp('ssh-tunnel');
        $this->sshUser = $sshUser;
        $this->sshHost = $sshHost;
        $this->sshPort = $sshPort;
        $this->localPort = $localPort;
        $this->privateKey = $privateKey;
    }

    public function __destruct()
    {
        $this->close();
        $this->temp->remove();
    }

    public function open(): void
    {
        if ($this->isOpened()) {
            return;
        }

        $this->logger->info("Creating SSH tunnel to '$this->sshHost' ...");
        $cmd = sprintf(
            'ssh ' .
            '-4 ' . // bind port to IPv4
            '-D %s ' . // local port
            '%s@%s -p %s ' . // user, sshHost, sshPort
            '-i %s ' . // private key
            '-N ' . // only port forwarding
            '-o BatchMode=yes ' . // don't ask for password
            '-o ExitOnForwardFailure=yes ' . // exit on error
            '-o StrictHostKeyChecking=no ' .
            '-o ServerAliveInterval=%d -o ServerAliveCountMax=1', // exit if server not alive
            $this->localPort,
            $this->sshUser,
            $this->sshHost,
            $this->sshPort,
            $this->writeKeyToFile($this->privateKey),
            self::SSH_SERVER_ALIVE_INTERVAL
        );

        $simplyRetryPolicy = new SimpleRetryPolicy(
            self::CREATE_SSH_MAX_RETRY,
            [SshTunnelOpenException::class,\Throwable::class]
        );

        $exponentialBackOffPolicy = new ExponentialBackOffPolicy();
        $proxy = new RetryProxy(
            $simplyRetryPolicy,
            $exponentialBackOffPolicy,
            $this->logger
        );

        $proxy->call(function () use ($cmd): void {
            // SSH tunnel process
            $this->process = Process::fromShellCommandline($cmd, null);
            $this->process->setTimeout(null);
            $this->process->start();

            // Wait until:
            // - SSH tunnel process is not running -> error
            // - SSH tunnel ready -> ok
            while ($this->process->isRunning() && !$this->isAlive()) {
                sleep(1);
            }

            // Throw exception if error when creating tunnel
            if (!$this->process->isRunning()) {
                throw new SshTunnelOpenException(
                    sprintf(
                        'Unable to create ssh tunnel. Output: %s ErrorOutput: %s',
                        $this->process->getOutput(),
                        $this->process->getErrorOutput()
                    )
                );
            }
        });

        $this->logger->debug('SSH tunnel created.');
    }

    public function close(): void
    {
        if ($this->process && $this->process->isRunning()) {
            $this->process->stop();
        }

        $this->opened = false;
    }

    public function isOpened(): bool
    {
        return $this->opened;
    }

    public function isAlive(): bool
    {
        $checkProcess = Process::fromShellCommandline(sprintf('nc -z 127.0.0.1 %d', $this->localPort));
        $checkProcess->setTimeout(3);
        $this->opened = $checkProcess->run() === 0;
        if (!$this->process || !$this->process->isRunning()) {
            $this->opened = false;
        }

        return $this->isOpened();
    }

    public function reopenIfNotAlive(): void
    {
        if ($this->isAlive()) {
            return;
        }

        $this->close();
        $this->logger->debug('SSH tunnel is not alive. Reconnecting ...');
        try {
            $this->open();
        } catch (SshTunnelOpenException $e) {
            // ignore, request will fail with correct ConnectionError
        }
    }

    public function getProxy(): string
    {
        return sprintf('socks5h://127.0.0.1:%s', $this->localPort);
    }

    public function getMiddleware(): callable
    {
        return Middleware::tap(function (RequestInterface $request, array $options): void {
            $retryNumber = $options['retries'] ?? null;
            if ($retryNumber === null) {
                throw new ApplicationException(
                    'Missing "retires" key in $options. ' .
                    'SSH tunnel middleware must be registered after retry middleware.'
                );
            }

            if (!$this->isOpened()) {
                $this->open();
            } elseif ($retryNumber > 0) {
                // If retrying -> check if problem is not in the SSH tunnel
                $this->reopenIfNotAlive();
            }
        });
    }

    protected function writeKeyToFile(string $key): string
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
