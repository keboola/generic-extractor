<?php

namespace Keboola\GenericExtractor\Configuration;

use Keboola\GenericExtractor\Authentication;
use Keboola\GenericExtractor\Authentication\AuthInterface;
use Keboola\GenericExtractor\Exception\ApplicationException;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Log\LoggerInterface;

/**
 * API Description
 */
class Api
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $name = 'generic';

    /**
     * @var AuthInterface
     */
    private $auth;

    /**
     * @var string|null
     */
    private $caCertificate;

    /**
     * @var array
     */
    private $scrollerConfig = [];

    /**
     * @var Headers
     */
    private $headers;

    /**
     * @var array
     */
    private $defaultRequestOptions = [];

    /**
     * @var array
     */
    private $retryConfig = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $ignoreErrors = [];

    /**
     * Api constructor.
     * @param LoggerInterface $logger
     * @param array $api
     * @param array $configAttributes
     * @param array $authorization
     */
    public function __construct(LoggerInterface $logger, array $api, array $configAttributes, array $authorization)
    {
        $this->logger = $logger;
        $this->auth = $this->createAuth($api, $configAttributes, $authorization);
        $this->caCertificate = $api['caCertificate'] ?? null;
        $this->headers = new Headers($api, $configAttributes);
        if (!empty($api['pagination']) && is_array($api['pagination'])) {
            $this->scrollerConfig = $api['pagination'];
        }
        if (!empty($api['retryConfig']) && is_array($api['retryConfig'])) {
            $this->retryConfig = $api['retryConfig'];
        }
        if (!empty($api['http']['ignoreErrors']) && is_array($api['http']['ignoreErrors'])) {
            $this->ignoreErrors = $api['http']['ignoreErrors'];
        }
        $this->baseUrl = $this->createBaseUrl($api, $configAttributes);
        if (!empty($api['name'])) {
            $this->name = $api['name'];
        }
        if (!empty($api['http']['defaultOptions'])) {
            $this->defaultRequestOptions = $api['http']['defaultOptions'];
        }
    }

    /**
     * Create Authentication class that accepts a Guzzle client.
     *
     * @param array $api
     * @param array $configAttributes
     * @param array $authorization
     * @return AuthInterface
     * @throws UserException
     */
    private function createAuth(array $api, array $configAttributes, array $authorization) : AuthInterface
    {
        if (empty($api['authentication']['type'])) {
            $this->logger->debug("Using no authentication.");
            return new Authentication\NoAuth();
        }
        $this->logger->debug("Using '{$api['authentication']['type']}' authentication.");
        switch ($api['authentication']['type']) {
            case 'basic':
                if (!empty($config['password']) && empty($config['#password'])) {
                    $this->logger->warning("Using deprecated 'password', use '#password' instead.");
                }
                return new Authentication\Basic($configAttributes);
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'url.query':
                $this->logger->warning("Method 'url.query' auth is deprecated, use 'query'.");
                // intentional, no break
            case 'query':
                if (empty($api['authentication']['query']) && !empty($api['query'])) {
                    $this->logger->warning("Using 'api.query' is deprecated, use 'api.authentication.query");
                    $api['authentication']['query'] = $api['query'];
                }
                return new Authentication\Query($configAttributes, $api['authentication']);
                break;
            case 'login':
                return new Authentication\Login($configAttributes, $api['authentication']);
                break;
            case 'oauth10':
                return new Authentication\OAuth10($authorization);
            case 'oauth20':
                return new Authentication\OAuth20($configAttributes, $authorization, $api['authentication']);
            case 'oauth20.login':
                return new Authentication\OAuth20Login($configAttributes, $authorization, $api['authentication']);
            default:
                throw new UserException("Unknown authorization type '{$api['authentication']['type']}'.");
                break;
        }
    }

    /**
     * @param array $api
     * @param array $configAttributes
     * @throws UserException
     * @return string
     */
    private function createBaseUrl(array $api, array $configAttributes) : string
    {
        if (empty($api['baseUrl'])) {
            throw new UserException("The 'baseUrl' attribute must be set in API configuration");
        }

        if (filter_var($api['baseUrl'], FILTER_VALIDATE_URL)) {
            return $api['baseUrl'];
        }

        if (is_string($api['baseUrl'])) {
            // For backwards compatibility
            try {
                $fn = \Keboola\Utils\jsonDecode($api['baseUrl']);
                $this->logger->warning("Passing json-encoded baseUrl is deprecated.");
            } catch (JsonDecodeException $e) {
                throw new UserException("The 'baseUrl' attribute in API configuration is not a valid URL");
            }
            $baseUrl = UserFunction::build([$fn], ['attr' => $configAttributes])[0];
        } else {
            $baseUrl = UserFunction::build([$api['baseUrl']], ['attr' => $configAttributes])[0];
        }

        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new UserException(sprintf(
                'The "baseUrl" attribute in API configuration resulted in an invalid URL (%s)',
                $baseUrl
            ));
        }

        return $baseUrl;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getBaseUrl() : string
    {
        return $this->baseUrl;
    }

    /**
     * @return ScrollerInterface
     */
    public function getNewScroller() : ScrollerInterface
    {
        return ScrollerFactory::getScroller($this->scrollerConfig);
    }

    /**
     * @return AuthInterface
     */
    public function getAuth() : AuthInterface
    {
        return $this->auth;
    }

    public function hasCaCertificate(): bool
    {
        return $this->caCertificate !== null;
    }

    public function getCaCertificate(): string
    {
        if (!$this->hasCaCertificate()) {
            throw new ApplicationException('Key "api.caCertificate" is not configured.');
        }

        return $this->caCertificate;
    }

    public function getCaCertificateFile(): string
    {
        $filePath = '/tmp/generic-extractor-ca-certificate-' . uniqid((string) rand(), true) . '.crt';
        file_put_contents($filePath, $this->getCaCertificate());
        return $filePath;
    }

    /**
     * @return Headers
     */
    public function getHeaders() : Headers
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getDefaultRequestOptions() : array
    {
        return $this->defaultRequestOptions;
    }

    /**
     * @return array
     */
    public function getRetryConfig() : array
    {
        return $this->retryConfig;
    }

    public function getIgnoreErrors()
    {
        return $this->ignoreErrors;
    }
}
