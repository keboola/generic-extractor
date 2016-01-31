<?php
/**
 *
 */

namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestRequest,
    Keboola\Juicer\Client\RestClient;
use Keboola\GenericExtractor\Subscriber\LoginSubscriber,
    Keboola\GenericExtractor\Config\UserFunction;
use Keboola\Utils\Utils,
    Keboola\Utils\Exception\NoDataFoundException;

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
class Login implements AuthInterface
{
    /**
     * @var array
     */
    protected $attrs;

    /**
     * @var array
     */
    protected $auth;

    public function __construct(array $attrs, array $api)
    {
        $this->attrs = $attrs;

        $this->auth = $api['authentication'];
    }

    /**
     * @param array $config
     * @return RestRequest
     */
    protected function getAuthRequest(array $config)
    {
        if (empty($config['endpoint'])) {
            throw new UserException('Request endpoint must be set for the Login authentication method.');
        }

        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], ['attr' => $this->attrs]);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], ['attr' => $this->attrs]);
        }

        return RestRequest::create($config);
    }

    public function authenticateClient(RestClient $client)
    {
        if (empty($this->auth['loginRequest'])) {
            throw new UserException("'loginRequest' is not configured for Login authentication");
        }

        $loginRequest = $this->getAuthRequest($this->auth['loginRequest']);

        $sub = new LoginSubscriber();

        // @return [query, headers]
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
     * @param object $response
     * @param string $type
     * @return array
     */
    protected function getResults(\stdClass $response, $type)
    {
        $result = [];
        if (!empty($this->auth['apiRequest'][$type])) {
            foreach($this->auth['apiRequest'][$type] as $key => $path) {
                try {
                    $result[$key] = Utils::getDataFromPath($path, $response, '.', false);
                } catch(NoDataFoundException $e) {
                    throw new UserException("Key '{$key}' not found at path '{$path}' in the Login response");
                }
            }
        }
        return $result;
    }

    /**
     * @param object $response
     * @return int
     */
    protected function getExpiry(\stdclass $response)
    {
        if (!isset($this->auth['expires'])) {
            return null;
        } elseif (is_numeric($this->auth['expires'])) {
            return time() + (int) $this->auth['expires'];
        } elseif (is_array($this->auth['expires'])) {
            if (empty($this->auth['expires']['response'])) {
                throw new UserException("'authentication.expires' must be either an integer or an array with 'response' key containing a path in the response");
            }

            $rExpiry = Utils::getDataFromPath($this->auth['expires']['response'], $response, '.');
            $expiry = is_int($rExpiry) ? $rExpiry : strtotime($rExpiry);

            if (!empty($this->auth['expires']['relative'])) {
                $expiry += time();
            }

            if ($expiry < time()) {
                throw new UserException("Login authentication returned expiry time before current time: '{$rExpiry}'");
            }

            return $expiry;
        }
    }
}
