<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

/**
 * https://developers.keboola.com/extend/generic-extractor/functions/#oauth-20-login-authentication-context
 */
class OAuth20LoginContext
{
    public static function create(string $key, string $secret, array $data, array $configAttributes): array
    {
        return [
            'consumer' => [
                'client_id' => $key,
                'client_secret' => $secret,
            ],
            'user' => $data,
            'attr' => $configAttributes,
        ];
    }
}
