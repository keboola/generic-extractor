<?php
namespace Keboola\GenericExtractor\Authentication;

use	GuzzleHttp\Client;
use	Keboola\Juicer\Exception\ApplicationException,
	Keboola\Juicer\Exception\UserException;

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

	public function __construct(array $config)
	{
		if (empty($config['username'])) {
			throw new UserException("Missing required 'username' attribute in config");
		}
		if (empty($config['password'])) {
			throw new UserException("Missing required 'password' attribute in config");
		}

		$this->username = $config['username'];
		$this->password = $config['password'];
	}

	/**
	 * @param Client $client
	 * @todo Add a possibility to add the option before each request, to allow refresh/signature here?
	 */
	public function authenticateClient(Client $client)
	{
		$client->setDefaultOption('auth', [$this->username, $this->password]);
	}
}
