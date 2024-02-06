<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\Logger;

use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

class LoggerMiddleware
{
    public static function create(LoggerInterface $logger): callable
    {
        // GET /defaultOptions?param=value HTTP/1.1 User-Agent: GuzzleHttp/7 Host: ...
        $template = '{req_headers} {req_body}';
        $formatter = new MessageFormatter($template);

        // Log request
        return Middleware::tap(function (RequestInterface $request) use ($logger, $formatter): void {
            $logger->debug($formatter->format($request));
        });
    }
}
