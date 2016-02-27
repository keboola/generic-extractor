<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestClient;
use Keboola\Utils\Utils;

/**
 * OAuth 2.0 Bearer implementation
 * https://tools.ietf.org/html/rfc6750#section-2.1
 *
 * This class allows using the OAuth data in any way.
 *
 * Possible #data:
 *
 * ```
 * rawTokenString
 * ```
 * accessed by `authorization: data`
 *
 * ```
 * {
 *  'access_token': 'tokenString',
 *  'some': '...'
 * }
 * ```
 * accessed by `authorization: { data: access_token }` & `format: json`
 */
class OAuth20 implements AuthInterface
{
    /**
     * @var string|object
     */
    protected $data;
    /**
     * @var string
     */
    protected $clientId;
    /**
     * @var string
     */
    protected $clientSecret;
    /**
     * @var array
     */
    protected $headers;
    /**
     * @var array
     */
    protected $query;

    public function __construct(array $authorization, array $apiAuth)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException("OAuth API credentials not supplied in config");
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        foreach(['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 2.0 authorization");
            }
        }

        if (!empty($apiAuth['format'])) {
            switch ($apiAuth['format']) {
                case 'json':
                    // authorization: { data: key }
                    $this->data = Utils::json_decode($oauthApiDetails['#data']);
                    break;
                default:
                    throw new UserException("Unknown OAuth data format '{$apiAuth['format']}'");
            }
        } else {
            // authorization: data
            $this->data = $oauthApiDetails['#data'];
        }

        // authorization: clientId
        $this->clientId = $oauthApiDetails['appKey'];
        // authorization: clientSecret
        $this->clientSecret = $oauthApiDetails['#appSecret'];

        $this->headers = empty($apiAuth['headers']) ? [] : $apiAuth['headers'];
        $this->query = empty($apiAuth['query']) ? [] : $apiAuth['query'];
    }

    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
//         $client->getClient()->setDefaultOption(
//             'headers',
//             array_replace(
//                 $client->getClient()->getDefaultOption('headers'),
//                 [
//                     'Authorization' => 'Bearer ' . $this->token
//                 ]
//             )
//         );
    }
}
