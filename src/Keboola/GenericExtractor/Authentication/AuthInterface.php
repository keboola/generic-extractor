<?php
namespace Keboola\GenericExtractor\Authentication;

use	GuzzleHttp\Client;

interface AuthInterface
{
	public function authenticateClient(Client $client);
}
