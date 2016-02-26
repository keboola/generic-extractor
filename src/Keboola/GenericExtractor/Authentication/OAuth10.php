<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestClient;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Keboola\Utils\Utils;

/**
 * OAuth 1.0 implementation
 */
class OAuth10 implements AuthInterface
{
    /**
     * @var string
     */
    protected $token;
    /**
     * @var string
     */
    protected $tokenSecret;
    /**
     * @var string
     */
    protected $consumerKey;
    /**
     * @var string
     */
    protected $consumerSecret;

    public function __construct(array $authorization)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException("OAuth API credentials not supplied in config");
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        foreach(['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 1.0 authorization");
            }
        }

        $data = Utils::json_decode($oauthApiDetails['#data']);
        $this->token = $data->oauth_token;
        $this->tokenSecret = $data->oauth_token_secret;
        $this->consumerKey = $oauthApiDetails['appKey'];
        $this->consumerSecret = $oauthApiDetails['#appSecret'];
    }

    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
        $sub = new Oauth1([
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'token'           => $this->token,
            'token_secret'    => $this->tokenSecret
        ]);

        $client->getClient()->getEmitter()->attach($sub);
        $client->getClient()->setDefaultOption('auth', 'oauth');
    }
}
