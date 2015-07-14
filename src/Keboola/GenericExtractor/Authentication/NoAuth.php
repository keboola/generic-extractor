<?php
namespace Keboola\GenericExtractor\Authentication;

use	GuzzleHttp\Client;

class NoAuth implements AuthInterface
{
	/**
	 * @param Client $client
	 */
	public function authenticateClient(Client $client)
	{

	}
}
