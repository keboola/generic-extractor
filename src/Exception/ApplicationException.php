<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Exception;

class ApplicationException extends \RuntimeException
{
    private ?array $data = null;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?array $data = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}
