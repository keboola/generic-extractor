<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Exception\ApplicationException,
    Keboola\Juicer\Exception\UserException,
    Keboola\Juicer\Client\RestClient;
use Keboola\GenericExtractor\Subscriber\UrlSignature;
use Keboola\Code\Builder,
    Keboola\Code\Exception\UserScriptException;
use Keboola\Utils\Utils,
    Keboola\Utils\Exception\JsonDecodeException;

/**
 * Authentication method using query parameters
 */
class Query implements AuthInterface
{
    /**
     * @var array
     */
    protected $query;
    /**
     * @var array
     */
    protected $attrs;
    /**
     * @var Builder
     */
    protected $builder;

    public function __construct(Builder $builder, $attrs, $definitions)
    {
        $this->query = $definitions;
        $this->attrs = $attrs;
        $this->builder = $builder;
    }

    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
        $sub = new UrlSignature();
        // Create array of objects instead of arrays from YML
        $q = (array) Utils::arrayToObject($this->query);
        $sub->setSignatureGenerator(
            function (array $requestInfo = []) use ($q)
            {
                $params = array_merge($requestInfo, ['attr' => $this->attrs]);

                $query = [];
                try {
                    foreach($q as $key => $value) {
                        $query[$key] = is_scalar($value)
                            ? $value
                            : $this->builder->run($value, $params);
                    }
                } catch(UserScriptException $e) {
                    throw new UserException("Error in query authentication script: " . $e->getMessage());
                }

                return $query;
            }
        );
        $client->getClient()->getEmitter()->attach($sub);
    }
}
