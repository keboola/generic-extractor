<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestClient;
use Keboola\Utils\Utils;
use Keboola\GenericExtractor\Subscriber\UrlSignature,
    Keboola\GenericExtractor\Subscriber\HeaderSignature;
use Keboola\Code\Builder,
    Keboola\Code\Exception\UserScriptException;

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
    /**
     * @var Builder
     */
    protected $builder;

    public function __construct(array $authorization, array $apiAuth, Builder $builder)
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

        $this->builder = $builder;
    }

    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
        $subscribers = [
            [
                'subscriber' => new UrlSignature(),
                'definitions' => $this->query
            ],
            [
                'subscriber' => new HeaderSignature(),
                'definitions' => $this->headers
            ]
        ];

        foreach($subscribers as $subscriber) {
            if (empty($subscriber['definitions'])) {
                continue;
            }

            $this->addGenerator($subscriber['subscriber'], $subscriber['definitions']);
            $client->getClient()->getEmitter()->attach($subscriber['subscriber']);
        }
    }

    /**
     * @param array|object $definitions
     */
    protected function addGenerator($subscriber, $definitions)
    {
        // Move to authenticateClient TODO
        $authorization = [
            'clientId' => $this->clientId
        ];

        if (!is_scalar($this->data)) {
            $authorization = array_merge(
                $authorization,
                Utils::flattenArray(Utils::objectToArray($this->data), 'data.')
            );
        } else {
            $authorization['data'] = $this->data;
        }

        // Create array of objects instead of arrays from YML
        $q = (array) Utils::arrayToObject($definitions);
        $subscriber->setSignatureGenerator(
            function () use ($q, $authorization) {
                $result = [];
                try {
                    foreach($q as $key => $value) {
                        $result[$key] = is_scalar($value)
                            ? $value
                            : $this->builder->run(
                                $value,
                                [
                                    'authorization' => $authorization
                                ]
                            );
                    }
                } catch(UserScriptException $e) {
                    throw new UserException("Error in OAuth authentication script: " . $e->getMessage());
                }

                return $result;
            }
        );
    }
}
