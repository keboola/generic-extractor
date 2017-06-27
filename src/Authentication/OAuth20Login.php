<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Utils\Exception\JsonDecodeException;

/**
 * config:
 *
 * loginRequest:
 *    endpoint: string
 *    params: array (optional)
 *    method: GET|POST|FORM (optional)
 *    headers: array (optional)
 * apiRequest:
 *    headers: array # [$headerName => $responsePath]
 *    query: array # same as with headers
 * expires: int|array # # of seconds OR ['response' => 'path', 'relative' => false] (optional)
 *
 * The response MUST be a JSON object containing credentials
 *
 */
class OAuth20Login extends Login
{
    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $auth;

    /**
     * OAuth20Login constructor.
     * @param array $configAttributes
     * @param array $authorization
     * @param array $authentication
     * @throws UserException
     */
    public function __construct(array $configAttributes, array $authorization, array $authentication)
    {
        parent::__construct($configAttributes, $authentication);
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException("OAuth API credentials not supplied in config");
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];
        foreach (['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 2.0 authorization");
            }
        }

        try {
            $oAuthData = \Keboola\Utils\jsonDecode($oauthApiDetails['#data'], true);
        } catch (JsonDecodeException $e) {
            throw new UserException("The OAuth data is not a valid JSON");
        }

        $consumerData = [
            'client_id' => $oauthApiDetails['appKey'],
            'client_secret' => $oauthApiDetails['#appSecret']
        ];

        $this->params = [
            'consumer' => $consumerData,
            'user' => $oAuthData,
            'attr' => $this->configAttributes
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getAuthRequest(array $config) : RestRequest
    {
        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], $this->params);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], $this->params);
        }

        return RestRequest::create($config);
    }
}
