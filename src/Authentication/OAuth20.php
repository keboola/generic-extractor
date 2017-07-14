<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Subscriber\AbstractSignature;
use Keboola\Juicer\Client\RestClient;
use Keboola\GenericExtractor\Subscriber\UrlSignature;
use Keboola\GenericExtractor\Subscriber\HeaderSignature;

/**
 * OAuth 2.0 Bearer implementation
 * https:// tools.ietf.org/html/rfc6750#section-2.1
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
 * accessed by `authorization: { data.access_token }` & `format: json`
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
     * @var array
     */
    protected $configAttributes;

    /**
     * OAuth20 constructor.
     * @param array $authorization
     * @param array $authentication
     * @param array $configAttributes
     * @throws UserException
     */
    public function __construct(array $configAttributes, array $authorization, array $authentication)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException("OAuth API credentials not supplied in configuration.");
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        foreach (['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 2.0 authorization.");
            }
        }

        if (empty($authentication['format'])) {
            $authentication['format'] = 'text';
        }
        switch ($authentication['format']) {
            case 'json':
                // authorization: { data: key }
                $this->data = \Keboola\Utils\jsonDecode($oauthApiDetails['#data']);
                break;
            case 'text':
                // authorization: data
                $this->data = $oauthApiDetails['#data'];
                break;
            default:
                throw new UserException("Unknown OAuth data format '{$authentication['format']}'.");
        }

        // authorization: clientId
        $this->clientId = $oauthApiDetails['appKey'];
        // authorization: clientSecret
        $this->clientSecret = $oauthApiDetails['#appSecret'];
        $this->headers = empty($authentication['headers']) ? [] : $authentication['headers'];
        $this->query = empty($authentication['query']) ? [] : $authentication['query'];
        $this->configAttributes = $configAttributes;
    }

    /**
     * @inheritdoc
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

        $authorization = [
            'clientId' => $this->clientId,
            'nonce' => substr(sha1(uniqid(microtime(), true)), 0, 16),
            'timestamp' => time()
        ];

        if (!is_scalar($this->data)) {
            $authorization = array_merge(
                $authorization,
                \Keboola\Utils\flattenArray(\Keboola\Utils\objectToArray($this->data), 'data.')
            );
        } else {
            $authorization['data'] = $this->data;
        }

        foreach ($subscribers as $subscriber) {
            if (empty($subscriber['definitions'])) {
                continue;
            }

            $this->addGenerator($subscriber['subscriber'], $subscriber['definitions'], $authorization);
            $client->getClient()->getEmitter()->attach($subscriber['subscriber']);
        }
    }

    /**
     * @param AbstractSignature $subscriber
     * @param array|object $definitions
     * @param array $authorization
     */
    protected function addGenerator($subscriber, $definitions, $authorization)
    {
        // Create array of objects instead of arrays from YML
        $q = (array) \Keboola\Utils\arrayToObject($definitions);
        $subscriber->setSignatureGenerator(
            function (array $requestInfo = []) use ($q, $authorization) {
                $params = array_merge($requestInfo, ['authorization' => $authorization]);
                return UserFunction::build($q, $params);
            }
        );
    }
}
