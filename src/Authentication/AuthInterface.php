<?php

namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Client\RestClient;

interface AuthInterface
{
    public function authenticateClient(RestClient $client);
}
