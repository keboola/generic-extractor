<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Context\OAuth20Context;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Utils;
use Keboola\Juicer\Client\RestClient;
use Psr\Http\Message\RequestInterface;

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
    private $data;

    private string $clientId;

    private string $clientSecret;

    private array $headers;

    private array $query;

    public function __construct(array $authorization, array $authentication)
    {
        if (empty($authorization['oauth_api']['credentials'])) {
            throw new UserException('OAuth API credentials not supplied in configuration.');
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
                /** @var object $data */
                $data = \Keboola\Utils\jsonDecode((string) $oauthApiDetails['#data']);
                $this->data = $data;
                break;
            case 'text':
                // authorization: data
                $this->data = (string) $oauthApiDetails['#data'];
                break;
            default:
                throw new UserException("Unknown OAuth data format '{$authentication['format']}'.");
        }

        // authorization: clientId
        $this->clientId = (string) $oauthApiDetails['appKey'];
        // authorization: clientSecret
        $this->clientSecret = (string) $oauthApiDetails['#appSecret'];
        $this->headers = empty($authentication['headers']) ? [] : $authentication['headers'];
        $this->query = empty($authentication['query']) ? [] : $authentication['query'];
    }

    /**
     * @inheritdoc
     */
    public function attachToClient(RestClient $client): void
    {
        $client->getHandlerStack()->push(Middleware::mapRequest(
            function (RequestInterface $request): RequestInterface {
                // Create context
                $fnContext = OAuth20Context::create($request, $this->clientId, $this->data);

                // Add query params
                $uri = $request->getUri();
                $query = UserFunction::build($this->query, $fnContext);
                $request = $request->withUri($uri->withQuery(
                    Utils::mergeQueries($uri->getQuery(), $query)
                ));

                // Add headers
                $headers = UserFunction::build($this->headers, $fnContext);
                $request = Utils::mergeHeaders($request, $headers);

                return $request;
            }
        ));
    }
}
