<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Context\LoginAuthApiRequestContext;
use Keboola\GenericExtractor\Context\LoginAuthLoginRequestContext;
use Keboola\GenericExtractor\Utils;
use Keboola\Utils\Exception\NoDataFoundException;
use LogicException;
use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use Keboola\Juicer\Client\RestRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Keboola\Utils\getDataFromPath;

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
 * The response MUST be a JSON (object or scalar) containing credentials or text. See "format".
 */
class Login implements AuthInterface
{
    private RestClient $client;

    private array $configAttributes;

    private array $authentication;

    private ?string $format = null;

    private bool $enabled = true;

    private bool $loggedIn = false;

    private array $signatureHeaders;

    private array $signatureQuery;

    private ?int $expiration;

    public function __construct(array $configAttributes, array $authentication)
    {
        $this->configAttributes = $configAttributes;
        $this->authentication = $authentication;
        if (empty($authentication['format'])) {
            $this->format = 'json';
        } else {
            if (in_array($authentication['format'], ['json', 'text'])) {
                $this->format = $authentication['format'];
            } else {
                throw new UserException("'format' must be either 'json' or 'text'.");
            }
        }
        if (empty($authentication['loginRequest'])) {
            throw new UserException("'loginRequest' is not configured for Login authentication");
        }
        if (empty($authentication['loginRequest']['endpoint'])) {
            throw new UserException('Request endpoint must be set for the Login authentication method.');
        }
        if (!empty($authentication['expires']) && (!filter_var($authentication['expires'], FILTER_VALIDATE_INT)
                && empty($authentication['expires']['response']))
        ) {
            throw new UserException(
                "The 'expires' attribute must be either an integer or an array with 'response' " .
                'key containing a path in the response'
            );
        }
    }

    public function attachToClient(RestClient $client): void
    {
        $this->client = $client;
        $this->client->getHandlerStack()->push(Middleware::mapRequest(
            function (RequestInterface $request): RequestInterface {
                // Skip this middleware for the log in request
                if (!$this->isEnabled()) {
                    return $request;
                }

                // Log in if not logged in
                if (!$this->isLoggedIn()) {
                    $this->logIn();
                }

                // Modify request
                return $this->addSignature($request);
            }
        ));
    }

    private function addSignature(RequestInterface $request): RequestInterface
    {
        // Add query params
        $uri = $request->getUri();
        $request = $request->withUri($uri->withQuery(
            Utils::mergeQueries($uri->getQuery(), $this->signatureQuery)
        ));

        // Add headers
        foreach ($this->signatureHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isLoggedIn(): bool
    {
        // Login request not sent yet
        if (!$this->loggedIn) {
            return false;
        }

        // Login expired
        if ($this->expiration && time() > $this->expiration) {
            return false;
        }

        return true;
    }

    public function getExpiration(): ?int
    {
        return $this->expiration;
    }

    public function logIn(): void
    {
        // Disabled middleware for login request
        $this->enabled = false;
        $rawResponse = $this->runRequest();
        $loginResponse = $this->getObjectFromResponse($rawResponse);
        $this->processResponse($loginResponse);
        $this->enabled = true;
        $this->loggedIn= true;
    }

    private function processResponse(\stdClass $loginResponse): void
    {
        $this->signatureQuery = $this->buildApiRequestFunctions(
            $this->authentication['apiRequest']['query'] ?? [],
            $loginResponse,
        );
        $this->signatureHeaders = $this->buildApiRequestFunctions(
            $this->authentication['apiRequest']['headers'] ?? [],
            $loginResponse,
        );
        $this->expiration = $this->getExpirationFromResponse($loginResponse);
    }

    private function runRequest(): ResponseInterface
    {
        $restRequest = $this->getLoginRequest();
        $guzzleRequest = $this->client->getGuzzleRequestFactory()->create($restRequest);
        return $this->client->getClient()->send($guzzleRequest);
    }

    private function getObjectFromResponse(ResponseInterface $rawResponse): \stdClass
    {
        if ($this->format === 'text') {
            return (object) ['data' => (string) $rawResponse->getBody()];
        } else if ($this->format === 'json') {
            $response = $this->client->getObjectFromResponse($rawResponse);

            if ($response instanceof \stdClass) {
                return $response;
            }

            if (is_scalar($response)) {
                return (object) ['data' => $response];
            }

            throw new UserException(sprintf(
                'The response to the login request should be an object or a scalar value, given "%s".',
                gettype($response),
            ));
        }

        throw new LogicException(sprintf('Unexpected format "%s".', $this->format));
    }

    private function getLoginRequest(): RestRequest
    {
        $config = $this->authentication['loginRequest'];
        $fnContext = LoginAuthLoginRequestContext::create($this->configAttributes);
        if (!empty($config['params'])) {
            $config['params'] = UserFunction::build($config['params'], $fnContext);
        }
        if (!empty($config['headers'])) {
            $config['headers'] = UserFunction::build($config['headers'], $fnContext);
        }
        return new RestRequest($config);
    }

    /**
     * Gets expiration from the login response
     */
    private function getExpirationFromResponse(\stdClass $response): ?int
    {
        if (!isset($this->authentication['expires'])) {
            return null;
        } elseif (is_numeric($this->authentication['expires'])) {
            return time() + (int) $this->authentication['expires'];
        } elseif (is_array($this->authentication['expires'])) {
            $rExpiry = getDataFromPath($this->authentication['expires']['response'], $response, '.');
            $expiry = is_int($rExpiry) ? $rExpiry : strtotime($rExpiry);

            if (!empty($this->authentication['expires']['relative'])) {
                $expiry += time();
            }

            if ($expiry < time()) {
                throw new UserException("Login authentication returned expiry time before current time: '{$rExpiry}'");
            }

            return $expiry;
        }

        return null;
    }

    protected function buildApiRequestFunctions(array $functions, \stdClass $loginResponse): array
    {
        $result = UserFunction::build(
            $functions,
            LoginAuthApiRequestContext::create($loginResponse, $this->configAttributes)
        );

        // for backward compatibility, check the values if they are a valid path within the response
        foreach ($result as $key => $value) {
            try {
                $result[$key] = getDataFromPath($value, $loginResponse, '.', false);
            } catch (NoDataFoundException $e) {
                // silently ignore invalid paths as they are probably values already processed by functions
            }
        }

        return $result;
    }
}
