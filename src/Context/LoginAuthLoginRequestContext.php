<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

/**
 * https://developers.keboola.com/extend/generic-extractor/functions/#login-authentication-context
 */
class LoginAuthLoginRequestContext
{
    public static function create(array $configAttributes): array
    {
        return [
            'attr' => $configAttributes,
        ];
    }
}
