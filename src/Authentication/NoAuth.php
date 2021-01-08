<?php
namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Client\RestClient;

class NoAuth implements AuthInterface
{
    public function authenticateClient(RestClient $client)
    {
    }
}
