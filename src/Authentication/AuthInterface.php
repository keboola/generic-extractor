<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Authentication;

use Keboola\Juicer\Client\RestClient;

interface AuthInterface
{
    public function authenticateClient(RestClient $client): void;
}
