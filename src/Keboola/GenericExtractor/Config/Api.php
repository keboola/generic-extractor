<?php

namespace Keboola\GenericExtractor\Config;

use Keboola\GenericExtractor\Authentication\AuthInterface;
use Keboola\GenericExtractor\Authentication;
use Keboola\Juicer\Exception\ApplicationException;
use Keboola\Juicer\Exception\UserException;
use Keboola\Juicer\Pagination\ScrollerInterface;
use Keboola\Juicer\Pagination\ScrollerFactory;
use Keboola\Juicer\Config\Config;
use Keboola\Juicer\Common\Logger;
use Keboola\Code\Builder;
use Keboola\Utils\Exception\JsonDecodeException;

/**
 * API Description
 * @todo TEST
 */
class Api
{
    /**
     * @var string
     */
    protected $baseUrl;
    /**
     * @var string
     */
    protected $name;

    /**
     * @var AuthInterface
     */
    protected $auth;

    /**
     * @var ScrollerInterface
     */
    protected $scroller;

    /**
     * @var Headers
     */
    protected $headers;

    /**
     * @var array
     */
    protected $defaultRequestOptions = [];

    /**
     * @var array
     */
    protected $retryConfig = [];

    public function __construct(array $config)
    {
        if (!empty($config['baseUrl'])) {
            $this->setBaseUrl($config['baseUrl']);
        }
        if (!empty($config['scroller'])) {
            $this->setScroller($config['scroller']);
        }
        if (!empty($config['auth'])) {
            $this->setAuth($config['auth']);
        }
        if (!empty($config['headers'])) {
            $this->setHeaders($config['headers']);
        }
        if (!empty($config['name'])) {
            $this->setName($config['name']);
        }
        if (!empty($config['defaultRequestOptions'])) {
            $this->setDefaultRequestOptions($config['defaultRequestOptions']);
        }
        if (!empty($config['retryConfig'])) {
            $this->setRetryConfig($config['retryConfig']);
        }
    }

    public static function create(array $api, Config $config, array $authorization = [])
    {
        return new static([
            'baseUrl' => self::createBaseUrl($api, $config),
            'auth' => self::createAuth($api, $config, $authorization),
            'scroller' => self::createScroller($api),
            'headers' => self::createHeaders($api, $config),
            'name' => self::createName($api),
            'defaultRequestOptions' => self::createDefaultRequestOptions($api),
            'retryConfig' => self::createRetryConfig($api)
        ]);
    }

    public static function createRetryConfig(array $api)
    {
        return !empty($api['retryConfig']) && is_array($api['retryConfig'])
            ? $api['retryConfig'] : [];
    }

    /**
     * Should return a class that contains
     * - info about what attrs store the Auth keys
     * - callback method that generates a signature
     * - OR an array of defaults for the client (possibly using the callback ^^)
     * - Method that accepts GuzzleClient as parameter and adds the emitter/defaults to it
     *
     * @param array $api
     * @param Config $config
     * @param array $authorization
     * @throws UserException
     * @throws ApplicationException
     * @return AuthInterface
     */
    public static function createAuth($api, Config $config, array $authorization)
    {
        if (empty($api['authentication']['type'])) {
            Logger::log("DEBUG", "Using NO Auth");
            return new Authentication\NoAuth();
        }

        Logger::log("DEBUG", "Using '{$api['authentication']['type']}' Auth");
        switch ($api['authentication']['type']) {
            case 'basic':
                return new Authentication\Basic($config->getAttributes());
                break;
            case 'bearer':
                throw new ApplicationException("The bearer method is not implemented yet");
                break;
            case 'query':
            case 'url.query':
                if (empty($api['authentication']['query']) && empty($api['query'])) {
                    throw new UserException("The query authentication method requires query parameters to be defined in the API configuration.");
                }

                $query = empty($api['authentication']['query']) ? $api['query'] : $api['authentication']['query'];
                return new Authentication\Query(new Builder(), $config->getAttributes(), $query);
                break;
            case 'login':
                return new Authentication\Login($config->getAttributes(), $api);
                break;
            case 'oauth10':
                return new Authentication\OAuth10($authorization);
            case 'oauth20':
                return new Authentication\OAuth20($authorization, $api['authentication'], new Builder());
            case 'oauth20.login':
                return new Authentication\OAuth20Login($authorization, $api);
            default:
                throw new UserException("Unknown authorization type '{$api['authentication']['type']}'");
                break;
        }
    }

    /**
     * @param array $api
     * @param Config $config
     * @throws UserException
     * @return string
     * @todo Allow storing URL function as an actual object, not a JSON
     */
    public static function createBaseUrl($api, Config $config)
    {
        if (empty($api['baseUrl'])) {
            throw new UserException("The 'baseUrl' attribute must be set in API configuration");
        }

        if (filter_var($api['baseUrl'], FILTER_VALIDATE_URL)) {
            return $api['baseUrl'];
        } elseif (is_string($api['baseUrl'])) {
            // For backwards compatibility
            try {
                $fn = \Keboola\Utils\jsonDecode($api['baseUrl']);
            } catch (JsonDecodeException $e) {
                throw new UserException("The 'baseUrl' attribute in API configuration is not an URL string, neither a valid JSON containing an user function! Error: " . $e->getMessage(), $e);
            }
            return UserFunction::build([$fn], ['attr' => $config->getAttributes()])[0];
        } else {
            return UserFunction::build(
                [$api['baseUrl']],
                ['attr' => $config->getAttributes()]
            )[0];
        }
    }

    /**
     * Return pagination scoller
     * @param array $api
     * @return ScrollerInterface
     * @todo ditch the switch and simply camelize the method
     *     to create the class name, then throw 501 if it doesn't
     *     exist (should be UserException really?)
     */
    public static function createScroller($api)
    {
        if (empty($api['pagination'])) {
            return ScrollerFactory::getScroller([]);
        } else {
            return ScrollerFactory::getScroller($api['pagination']);
        }
    }

    /**
     * @param array $api
     * @param Config $config
     * @return Headers
     */
    public static function createHeaders($api, Config $config)
    {
        return Headers::create($api, $config);
    }

    /**
     * @param array $api
     * @return string
     */
    public static function createName($api)
    {
        return empty($api['name']) ? 'generic' : $api['name'];
    }

    /**
     * @param array $api
     * @return array
     */
    public static function createDefaultRequestOptions($api)
    {
        return empty($api['http']['defaultOptions']) ? [] : $api['http']['defaultOptions'];
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setBaseUrl($url)
    {
        $this->baseUrl = $url;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function setScroller(ScrollerInterface $scroller)
    {
        $this->scroller = $scroller;
    }

    public function getScroller()
    {
        return $this->scroller;
    }

    public function setAuth(AuthInterface $auth)
    {
        $this->auth = $auth;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function setHeaders(Headers $headers)
    {
        $this->headers = $headers;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setDefaultRequestOptions(array $options)
    {
        $this->defaultRequestOptions = $options;
    }

    public function getDefaultRequestOptions()
    {
        return $this->defaultRequestOptions;
    }

    public function setRetryConfig(array $config)
    {
        $this->retryConfig = $config;
    }

    public function getRetryConfig()
    {
        return $this->retryConfig;
    }
}
