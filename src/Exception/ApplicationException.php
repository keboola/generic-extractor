<?php

namespace Keboola\GenericExtractor\Exception;

class ApplicationException extends \RuntimeException
{
    private ?array $data = null;

    public function __construct($message = "", $code = 0, \Throwable $previous = null, array $data = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}
