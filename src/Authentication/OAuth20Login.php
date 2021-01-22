<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Context\OAuth20LoginContext;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Utils\Exception\JsonDecodeException;
use function Keboola\Utils\jsonDecode;

/**
 * Config:
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
 */
class OAuth20Login extends Login
{
    protected array $params;

    protected array $auth;

    private string $key;

    private string $secret;

    private array $data;

    public function __construct(array $configAttributes, array $authorization, array $authentication)
    {
        parent::__construct($configAttributes, $authentication);
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException('OAuth API credentials not supplied in config');
        }

        $credentials = $authorization['oauth_api']['credentials'];
        foreach (['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($credentials[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 2.0 authorization");
            }
        }
        $this->key = (string) $credentials['appKey'];
        $this->secret = (string) $credentials['#appSecret'];

        try {
            $this->data = jsonDecode($credentials['#data'], true);
        } catch (JsonDecodeException $e) {
            throw new UserException('The OAuth data is not a valid JSON');
        }
    }

    protected function getLoginRequest(array $config): RestRequest
    {
        $fnContext = OAuth20LoginContext::create($this->key, $this->secret, $this->data, $this->configAttributes);

        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], $fnContext);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], $fnContext);
        }

        return new RestRequest($config);
    }
}
