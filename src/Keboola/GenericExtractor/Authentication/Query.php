<?php
namespace Keboola\GenericExtractor\Authentication;

use	GuzzleHttp\Client;
use	Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\GenericExtractor\Subscriber\UrlSignature,
	Keboola\Code\Builder;
use	Keboola\Utils\Utils,
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
		$query = [];
		try {
			foreach($definitions as $key => $definition) {
				$query[$key] = Utils::json_decode($definition);
			}
		} catch(JsonDecodeException $e) {
			throw new UserException($e->getMessage());
		}

		$this->query = $query;
		$this->attrs = $attrs;
		$this->builder = $builder;
	}

	/**
	 * @param Client $client
	 */
	public function authenticateClient(Client $client)
	{
		$sub = new UrlSignature();
		$sub->setSignatureGenerator(
			function () {
				$query = [];
				foreach($this->query as $key => $value) {
					$query[$key] = $this->builder->run($value, ['attr' => $this->attrs]);
				}
				return $query;
			}
		);
		$client->getEmitter()->attach($sub);
	}
}
