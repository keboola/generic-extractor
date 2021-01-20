<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use GuzzleHttp\Middleware;
use Keboola\GenericExtractor\Exception\UserException;
use Keboola\Juicer\Client\RestClient;
use Psr\Http\Message\RequestInterface;

/**
 * Basic HTTP Authentication using name and password
 */
class Basic implements AuthInterface
{
    protected string $username;

    protected string $password;

    public function __construct(array $config)
    {
        if (empty($config['username']) && empty($config['#username'])) {
            throw new UserException("Missing the required '#username' (or 'username') attribute in config.");
        }
        if (empty($config['password']) && empty($config['#password'])) {
            throw new UserException("Missing the required '#password' attribute in config.");
        }

        $this->username = empty($config['username']) ? $config['#username'] : $config['username'];
        $this->password = empty($config['password']) ? $config['#password'] : $config['password'];
    }

    /**
     * @inheritdoc
     */
    public function attachToClient(RestClient $client): void
    {
        // Add Authorization header to each request
        $client->getHandlerStack()->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request->withHeader(
                'Authorization',
                'Basic ' . \base64_encode("$this->username:$this->password")
            );
        }));
    }
}
