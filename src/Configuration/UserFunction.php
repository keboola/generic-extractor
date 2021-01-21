<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Configuration;

use Keboola\Code\Builder;
use Keboola\Code\Exception\UserScriptException;
use Keboola\GenericExtractor\Exception\UserException;
use function Keboola\Utils\arrayToObject;

/**
 * Keboola\Code\Builder wrapper
 */
class UserFunction
{
    /**
     * @param array|\stdClass $functions
     * @param array $params ['attr' => $attributesArray, ...]
     * @throws UserException
     */
    public static function build($functions, array $params = []): array
    {
        /** @var array|\stdClass|mixed $functions */
        if (!is_object($functions) && !is_array($functions)) {
            throw new UserException(
                sprintf(
                    "Expected 'object' type, given '%s' type, value '%s'.",
                    gettype($functions),
                    json_encode($functions)
                )
            );
        }

        $builder = new Builder();
        $functions = (array) arrayToObject((array) $functions);
        try {
            array_walk(
                $functions,
                function (&$value, $key) use ($params, $builder): void {
                    $value = $value instanceof \stdClass ? $builder->run($value, $params) : $value;
                }
            );
        } catch (UserScriptException $e) {
            throw new UserException('User script error: ' . $e->getMessage());
        }

        return $functions;
    }
}
