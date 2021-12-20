<?php

declare(strict_types=1);

namespace Keboola\GenericExtractor\AwsSignature;

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use Psr\Http\Message\RequestInterface;

class AwsSignatureMiddleware
{
    public static function create(array $awsSignatureCredentials): callable
    {

        // Signature request
        return function (callable $handler) use ($awsSignatureCredentials) {
            return function (RequestInterface $request, array $options) use ($handler, $awsSignatureCredentials) {
                $awsCredentials = new Credentials(
                    $awsSignatureCredentials['accessKeyId'],
                    $awsSignatureCredentials['#secretKey']
                );

                $signatureV4 = new SignatureV4(
                    $awsSignatureCredentials['serviceName'],
                    $awsSignatureCredentials['regionName']
                );

                return $handler(
                    $signatureV4->signRequest($request, $awsCredentials),
                    $options
                );
            };
        };
    }
}
