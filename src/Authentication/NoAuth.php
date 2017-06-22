<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Client\RestClient;

class NoAuth implements AuthInterface
{
    /**
     * @param RestClient $client
     */
    public function authenticateClient(RestClient $client)
    {
    }
}
