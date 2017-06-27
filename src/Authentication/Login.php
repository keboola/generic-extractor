<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Client\RestRequest;
use Keboola\Juicer\Client\RestClient;
use Keboola\GenericExtractor\Subscriber\LoginSubscriber;
use Keboola\Utils\Exception\NoDataFoundException;

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
 */
class Login implements AuthInterface
{
    /**
     * @var array
     */
    protected $configAttributes;

    /**
     * @var array
     */
    protected $auth;

    /**
     * Login constructor.
     * @param array $configAttributes
     * @param array $authentication
     * @throws UserException
     */
    public function __construct(array $configAttributes, array $authentication)
    {
        $this->configAttributes = $configAttributes;
        $this->auth = $authentication;
        if (empty($authentication['loginRequest'])) {
            throw new UserException("'loginRequest' is not configured for Login authentication");
        }
        if (empty($authentication['loginRequest']['endpoint'])) {
            throw new UserException('Request endpoint must be set for the Login authentication method.');
        }
        if (!empty($authentication['expires']) &&
            (!is_integer($authentication['expires']) && empty($authentication['expires']['response']))
        ) {
            throw new UserException(
                "The 'expires' attribute must be either an integer or an array with 'response' " .
                "key containing a path in the response"
            );
        }
    }

    /**
     * @param array $config
     * @throws UserException
     * @return RestRequest
     */
    protected function getAuthRequest(array $config) : RestRequest
    {
        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], ['attr' => $this->configAttributes]);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], ['attr' => $this->configAttributes]);
        }
        return RestRequest::create($config);
    }

    /**
     * @inheritdoc
     */
    public function authenticateClient(RestClient $client)
    {
        $loginRequest = $this->getAuthRequest($this->auth['loginRequest']);
        $sub = new LoginSubscriber();

        $sub->setLoginMethod(
            function () use ($client, $loginRequest, $sub) {
                // Need to bypass the subscriber for the login call
                $client->getClient()->getEmitter()->detach($sub);
                $response = $client->download($loginRequest);
                $client->getClient()->getEmitter()->attach($sub);

                return [
                    'query' => $this->getResults($response, 'query'),
                    'headers' => $this->getResults($response, 'headers'),
                    'expires' => $this->getExpiry($response)
                ];
            }
        );

        $client->getClient()->getEmitter()->attach($sub);
    }

    /**
     * Maps data from login result into $type (header/query)
     *
     * @param \stdClass $response
     * @param string $type
     * @return array
     * @throws UserException
     */
    protected function getResults(\stdClass $response, $type) : array
    {
        $result = [];
        if (!empty($this->auth['apiRequest'][$type])) {
            foreach ($this->auth['apiRequest'][$type] as $key => $path) {
                try {
                    $result[$key] = \Keboola\Utils\getDataFromPath($path, $response, '.', false);
                } catch (NoDataFoundException $e) {
                    throw new UserException("Key '{$key}' not found at path '{$path}' in the Login response");
                }
            }
        }
        return $result;
    }

    /**
     * @param \stdClass $response
     * @return int|null
     * @throws UserException
     */
    protected function getExpiry(\stdClass $response) : ?int
    {
        if (!isset($this->auth['expires'])) {
            return null;
        } elseif (is_numeric($this->auth['expires'])) {
            return time() + (int) $this->auth['expires'];
        } elseif (is_array($this->auth['expires'])) {
            $rExpiry = \Keboola\Utils\getDataFromPath($this->auth['expires']['response'], $response, '.');
            $expiry = is_int($rExpiry) ? $rExpiry : strtotime($rExpiry);

            if (!empty($this->auth['expires']['relative'])) {
                $expiry += time();
            }

            if ($expiry < time()) {
                throw new UserException("Login authentication returned expiry time before current time: '{$rExpiry}'");
            }

            return $expiry;
        }
        return null;
    }
}
