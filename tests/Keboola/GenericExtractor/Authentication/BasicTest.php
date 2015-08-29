<?php
namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\Authentication\Basic;
use	GuzzleHttp\Client;

class BasicTest extends ExtractorTestCase
{
	public function testAuthenticateClient()
	{
		$client = new Client;
		$auth = new Basic(['username' => 'test', 'password' => 'pass']);
		$auth->authenticateClient($client);

		$this->assertEquals(['test','pass'], $client->getDefaultOption('auth'));

		$request = $client->createRequest('GET', '/');
		$this->assertArrayHasKey('Authorization', $request->getHeaders());
		$this->assertEquals(['Basic dGVzdDpwYXNz'], $request->getHeaders()['Authorization']);
	}
}
