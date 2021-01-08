<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\GenericExtractor\Configuration\UserFunction;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use Keboola\GenericExtractor\Subscriber\UrlSignature;

/**
 * Authentication method using query parameters
 */
class Query implements AuthInterface
{
    protected array $query;

    protected array $configAttributes;

    /**
     * @throws UserException
     */
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

    public function authenticateClient(RestClient $client)
    {
        $sub = new UrlSignature();
        // Create array of objects instead of arrays from YML
        $q = (array) \Keboola\Utils\arrayToObject($this->query);
        $sub->setSignatureGenerator(
            function (array $requestInfo = []) use ($q) {
                $params = array_merge($requestInfo, ['attr' => $this->configAttributes]);
                return UserFunction::build($q, $params);
            }
        );
        $client->getClient()->getEmitter()->attach($sub);
    }
}
