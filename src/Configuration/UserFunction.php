<?php

namespace Keboola\GenericExtractor\Configuration;

use Keboola\Code\Builder;
use Keboola\Code\Exception\UserScriptException;
use Keboola\GenericExtractor\Exception\UserException;

/**
 * Keboola\Code\Builder wrapper
 */
class UserFunction
{
    /**
     * @param array|\stdClass $functions
     * @param array $params ['attr' => $attributesArray, ...]
     * @throws UserException
     * @return array
     */
    public static function build($functions, array $params = [])
    {
        $builder = new Builder();
        $functions = (array) \Keboola\Utils\arrayToObject($functions);
        try {
            array_walk($functions, function (&$value, $key) use ($params, $builder) {
                $value = !is_object($value) ? $value : $builder->run($value, $params);
            });
        } catch (UserScriptException $e) {
            throw new UserException('User script error: ' . $e->getMessage());
        }

        return $functions;
    }
}
