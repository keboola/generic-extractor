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
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

/**
 * API Description
 */
class Api
{
    private string $baseUrl;

    private string $name = 'generic';

    private AuthInterface $auth;

    private ?string $caCertificate = null;

    private array $scrollerConfig = [];

    private Headers $headers;

    private array $defaultRequestOptions = [];

    private array $retryConfig = [];

    private LoggerInterface $logger;

    private array $ignoreErrors = [];

    private ?string $clientCertificate = null;

    public function __construct(LoggerInterface $logger, array $api, array $configAttributes, array $authorization)
    {
        $this->logger = $logger;
        $this->auth = $this->createAuth($api, $configAttributes, $authorization);
        $this->caCertificate = $api['caCertificate'] ?? null;
        $this->clientCertificate = $api['#clientCertificate'] ?? null;
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
            case 'login':
                return new Authentication\Login($configAttributes, $api['authentication']);
            case 'oauth10':
                return new Authentication\OAuth10($authorization);
            case 'oauth20':
                return new Authentication\OAuth20($configAttributes, $authorization, $api['authentication']);
            case 'oauth20.login':
                return new Authentication\OAuth20Login($configAttributes, $authorization, $api['authentication']);
            default:
                throw new UserException("Unknown authorization type '{$api['authentication']['type']}'.");
        }
    }

    /**
     * @throws UserException
     */
    private function createBaseUrl(array $api, array $configAttributes) : string
    {
        if (empty($api['baseUrl'])) {
            throw new UserException("The 'baseUrl' attribute must be set in API configuration");
        }

        if (self::isValidUrl($api['baseUrl'])) {
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

        if (!self::isValidUrl($baseUrl)) {
            throw new UserException(sprintf(
                'The "baseUrl" attribute in API configuration resulted in an invalid URL (%s)',
                $baseUrl
            ));
        }

        return $baseUrl;
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getBaseUrl() : string
    {
        return $this->baseUrl;
    }

    public function getNewScroller() : ScrollerInterface
    {
        return ScrollerFactory::getScroller($this->scrollerConfig);
    }

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


    public function hasClientCertificate(): bool
    {
        return $this->clientCertificate !== null;
    }

    public function getClientCertificate(): string
    {
        if (!$this->hasClientCertificate()) {
            throw new ApplicationException('Key "api.clientCertificate" is not configured.');
        }

        return $this->clientCertificate;
    }

    public function getClientCertificateFile(): string
    {
        $filePath = '/tmp/generic-extractor-client-certificate-' . uniqid((string) rand(), true) . '.pem';
        file_put_contents($filePath, $this->getClientCertificate());
        return $filePath;
    }

    public function getHeaders() : Headers
    {
        return $this->headers;
    }

    public function getDefaultRequestOptions() : array
    {
        return $this->defaultRequestOptions;
    }

    public function getRetryConfig() : array
    {
        return $this->retryConfig;
    }

    public function getIgnoreErrors()
    {
        return $this->ignoreErrors;
    }

    /**
     * @param mixed $url
     */
    public static function isValidUrl($url): bool
    {
        if (!is_string($url)) {
            return false;
        }

        $constraint = new Url();
        $validator = Validation::createValidator();
        $errors = $validator->validate($url, $constraint);
        return $errors->count() === 0;
    }
}
