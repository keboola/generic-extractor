<?php
namespace Keboola\GenericExtractor;

use Keboola\GenericExtractor\Authentication\OAuth20;
use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Filesystem\YamlFile;
use Keboola\Code\Builder,
    Keboola\Code\Exception\UserScriptException;

class OAuth20Test extends ExtractorTestCase
{
    public function testAuthenticateClientJson()
    {
        $config = YamlFile::create(ROOT_PATH . '/tests/data/oauth20bearer/config.yml');

        $client = new Client(['base_url' => 'http://example.com']);
        $client->setDefaultOption('headers', ['X-Test' => 'test']);
        $restClient = new RestClient($client);
        $auth = new OAuth20(
            $config->get('authorization'),
            $config->get('parameters', 'api', 'authentication'),
            new Builder
        );
        $auth->authenticateClient($restClient);

        $request = $client->createRequest('GET', '/');
        $client->send($request);

        self::assertEquals('Bearer testToken', $request->getHeader('Authorization'));
        self::assertEquals('test', $request->getHeader('X-Test'));
    }
}
