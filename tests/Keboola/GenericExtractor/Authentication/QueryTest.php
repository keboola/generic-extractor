<?php
namespace Keboola\GenericExtractor;

use	Keboola\GenericExtractor\Authentication\Query;
use	GuzzleHttp\Client;
use	Keboola\Code\Builder;

class QueryTest extends ExtractorTestCase
{
	public function testAuthenticateClient()
	{
		$client = new Client(['base_url' => 'http://example.com']);

		$builder = new Builder;
		$definitions = [
			'paramOne' => (object) ['attr' => 'first'],
			'paramTwo' => (object) [
				'function' => 'md5',
				'args' => [(object) ['attr' => 'second']]
			],
			'paramThree' => 'string'
		];
		$attrs = ['first' => 1, 'second' => 'two'];

		$auth = new Query($builder, $attrs, $definitions);
		$auth->authenticateClient($client);

		$request = $client->createRequest('GET', '/');
		$client->send($request);

		$this->assertEquals(
			'paramOne=1&paramTwo=' . md5($attrs['second']) . '&paramThree=string',
			(string) $request->getQuery()
		);
	}
}
