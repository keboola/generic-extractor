<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\SshTunnel;

use Keboola\GenericExtractor\Exception\UserException;
use Psr\Log\LoggerInterface;

class SshTunnelFactory
{
    public const LOCAL_PORT = 33006;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function create(array $sshConfig): SshTunnel
    {
        $missingParams = array_diff(
            ['user', 'host', 'port', '#privateKey'],
            array_keys($sshConfig)
        );

        if (!empty($missingParams)) {
            throw new UserException(sprintf("Missing parameters '%s' in SSH config.", implode(',', $missingParams)));
        }

        return new SshTunnel(
            $this->logger,
            $sshConfig['user'],
            $sshConfig['host'],
            (int) $sshConfig['port'],
            self::LOCAL_PORT,
            $sshConfig['#privateKey'],
        );
    }
}
