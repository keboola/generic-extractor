<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestClient;

/**
 * OAuth 2.0 Bearer implementation
 * https://tools.ietf.org/html/rfc6750#section-2.1
 */
class OAuth20 implements AuthInterface
{
    /**
     * @var string
     */
    protected $token;

    public function __construct(array $authorization)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException("OAuth API credentials not supplied in config");
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        if (empty($oauthApiDetails['#token'])) {
            throw new UserException("Missing '{$key}' for OAuth 1.0 authorization");
        }

        $this->token = $oauthApiDetails['#token'];
    }

    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
        $client->getClient()->setDefaultOption(
            'headers',
            array_replace(
                $client->getClient()->getDefaultOption('headers'),
                [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            )
        );
    }
}
