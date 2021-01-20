<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Context\QueryAuthContext;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\GenericExtractor\Utils;
use Keboola\Juicer\Client\RestClient;
use Psr\Http\Message\RequestInterface;
use function Keboola\Utils\arrayToObject;

/**
 * Authentication method using query parameters
 */
class Query implements AuthInterface
{
    protected array $query;

    protected array $configAttributes;

    public function __construct(array $configAttributes, array $authentication)
    {
        if (empty($authentication['query'])) {
            throw new UserException(
                "The query authentication method requires 'query' configuration in 'authentication' section."
            );
        }
        $this->query = $authentication['query'];
        $this->configAttributes = $configAttributes;
    }

    public function attachToClient(RestClient $client): void
    {
        $client->getHandlerStack()->push(Middleware::mapRequest(
            function (RequestInterface $request): RequestInterface {
                $context = QueryAuthContext::create($request, $this->configAttributes);
                $authQuery = UserFunction::build((array) arrayToObject($this->query), $context);

                // Append auth query
                $uri = $request->getUri();
                return $request->withUri($uri->withQuery(
                    Utils::mergeQueries($uri->getQuery(), $authQuery)
                ));
            }
        ));
    }
}
