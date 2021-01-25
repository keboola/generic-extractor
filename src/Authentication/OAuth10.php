<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Keboola\Utils\Exception\JsonDecodeException;
use Psr\Http\Message\RequestInterface;
use function Keboola\Utils\jsonDecode;

/**
 * OAuth 1.0 implementation
 */
class OAuth10 implements AuthInterface
{
    protected string $token;

    protected string $tokenSecret;

    protected string $consumerKey;

    protected string $consumerSecret;

    public function __construct(array $authorization)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException('OAuth API credentials not supplied in configuration.');
        }

        $oauthApiDetails = $authorization['oauth_api']['credentials'];

        foreach (['#data', 'appKey', '#appSecret'] as $key) {
            if (empty($oauthApiDetails[$key])) {
                throw new UserException("Missing '{$key}' for OAuth 1.0 authorization.");
            }
        }

        // Decode data
        try {
            $data = jsonDecode($oauthApiDetails['#data']);
        } catch (JsonDecodeException $e) {
            throw new UserException('The OAuth #data is not a valid JSON.');
        }

        if (!$data instanceof \stdClass) {
            throw new UserException(sprintf(
                "Key 'oauth_api.credentials'.#data must be object, given '%s'.",
                gettype($data)
            ));
        }

        if (!isset($data->oauth_token)) {
            throw new UserException(
                "Missing 'oauth_api.credentials.#data.oauth_token' for OAuth 1.0 authorization."
            );
        }

        if (!isset($data->oauth_token_secret)) {
            throw new UserException(
                "Missing 'oauth_api.credentials.#data.oauth_token_secret' for OAuth 1.0 authorization."
            );
        }

        $this->token = $data->oauth_token;
        $this->tokenSecret = $data->oauth_token_secret;
        $this->consumerKey = $oauthApiDetails['appKey'];
        $this->consumerSecret = $oauthApiDetails['#appSecret'];
    }

    /**
     * @inheritdoc
     */
    public function attachToClient(RestClient $client): void
    {
        $middleware = new Oauth1([
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
            'token' => $this->token,
            'token_secret' => $this->tokenSecret,
        ]);

        // Before OAuth we need to set option "auth" = "oauth",
        // otherwise, OAuth middleware will not start (this is how it is implemented).
        $client->getHandlerStack()->push(static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler) {
                $options['auth'] = 'oauth';
                return $handler($request, $options);
            };
        });

        // Add OAuth middleware
        $client->getHandlerStack()->push($middleware);
    }
}
