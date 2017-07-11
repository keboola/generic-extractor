<?php

namespace Keboola\GenericExtractor\Exception;

use Throwable;

class UserException extends \RuntimeException
{
    private $data;

    public function __construct($message = "", $code = 0, Throwable $previous = null, $data = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }
}
