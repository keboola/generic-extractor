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

class OAuth20Test extends ExtractorTestCase
{
    public function testAuthenticateClientJson()
    {
        $config = YamlFile::create(ROOT_PATH . '/tests/data/oauth20bearer/config.yml');

        $client = new Client;
        $client->setDefaultOption('headers', ['X-Test' => 'test']);
        $restClient = new RestClient($client);
        $auth = new OAuth20(
            $config->get('authorization'),
            $config->get('parameters', 'api', 'authentication')
        );
        $auth->authenticateClient($restClient);

//         self::assertEquals('Bearer testToken', $client->getDefaultOption('headers')['Authorization']);
//         self::assertArrayHasKey('X-Test', $client->getDefaultOption('headers'));
    }
}
