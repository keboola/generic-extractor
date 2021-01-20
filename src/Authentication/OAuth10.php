<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use function Keboola\Utils\jsonDecode;

/**
 * OAuth 1.0 implementation
 */
class OAuth10 implements AuthInterface
{
    protected string $token;

    protected string $tokenSecret;

    protected string $consumerKey;

    protected string $consumerSecret;

    public function __construct(array $authorization)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException('OAuth API credentials not supplied in configuration.');
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        foreach (['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 1.0 authorization.");
            }
        }

        /**
 * @var \stdClass $data
*/
        $data = jsonDecode($oauthApiDetails['#data']);
        $this->token = $data->oauth_token;
        $this->tokenSecret = $data->oauth_token_secret;
        $this->consumerKey = $oauthApiDetails['appKey'];
        $this->consumerSecret = $oauthApiDetails['#appSecret'];
    }

    /**
     * @inheritdoc
     */
    public function attachToClient(RestClient $client): void
    {
        $sub = new Oauth1(
            [
            'consumer_key'    => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'token'           => $this->token,
            'token_secret'    => $this->tokenSecret,
            ]
        );

        $client->getClient()->getEmitter()->attach($sub);
        $client->getClient()->setDefaultOption('auth', 'oauth');
    }
}
