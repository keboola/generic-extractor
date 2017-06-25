<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Client\RestClient;

/**
 * Basic HTTP Authentication using name and password
 */
class Basic implements AuthInterface
{
    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * Basic constructor.
     * @param array $config
     * @throws UserException
     */
    public function __construct(array $config)
    {
        if (empty($config['username']) && empty($config['#username'])) {
            throw new UserException("Missing the required '#username' (or 'username') attribute in config.");
        }
        if (empty($config['password']) && empty($config['#password'])) {
            throw new UserException("Missing the required '#password' attribute in config.");
        }

        $this->username = empty($config['username']) ? $config['#username'] : $config['username'];
        $this->password = empty($config['password']) ? $config['#password'] : $config['password'];
    }

    /**
     * @inheritdoc
     */
    public function authenticateClient(RestClient $client)
    {
        $client->getClient()->setDefaultOption('auth', [$this->username, $this->password]);
    }
}
