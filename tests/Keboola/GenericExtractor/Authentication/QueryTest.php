<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\Query;
use GuzzleHttp\Client,
    GuzzleHttp\Exception\ClientException;
use Keboola\Code\Builder;
use Keboola\Juicer\Client\RestClient;

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
        $auth->authenticateClient(new RestClient($client));

        $request = $client->createRequest('GET', '/');
        $client->send($request);

        $this->assertEquals(
            'paramOne=1&paramTwo=' . md5($attrs['second']) . '&paramThree=string',
            (string) $request->getQuery()
        );
    }

    public function testAuthenticateClientQuery()
    {
        $client = new Client(['base_url' => 'http://example.com']);

        $builder = new Builder;
        $auth = new Query($builder, [], ['authParam' => 'secretCodeWow']);
        $auth->authenticateClient(new RestClient($client));

        $request = $client->createRequest('GET', '/query?param=value');
        $this->sendRequest($client, $request);

        $this->assertEquals(
            'param=value&authParam=secretCodeWow',
            (string) $request->getQuery()
        );
    }

    protected function sendRequest($client, $request)
    {
        try {
            return $client->send($request);
        } catch(ClientException $e) {
            // this is expected, just need to send the request somewhere!
        }
    }
}
