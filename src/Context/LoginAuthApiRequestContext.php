<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Context;

use function Keboola\Utils\objectToArray;

/**
 * https://developers.keboola.com/extend/generic-extractor/functions/#login-authentication-context
 */
class LoginAuthApiRequestContext
{
    public static function create(\stdClass $loginResponse, array $configAttributes): array
    {
        return [
            'response' => objectToArray($loginResponse),
            'attr' => $configAttributes,
        ];
    }
}
