<?php

declare(strict_types=1);

namespace OpenAPIValidation\PSR7\Exception\Validation;

use OpenAPIValidation\PSR7\OperationAddress;
use Throwable;
use function sprintf;

class InvalidBody extends AddressValidationFailed
{
    public static function becauseBodyDoesNotMatchSchema(
        string $contentType,
        OperationAddress $addr,
        ?Throwable $prev = null
    ) : self {
        $exception          = new static($addr, $prev);
        $exception->message = sprintf('Body does not match schema for content-type "%s" for %s', $contentType, $addr);

        return $exception;
    }

    public static function becauseBodyIsNotValidJson(string $error, OperationAddress $addr) : self
    {
        $exception          = new static($addr);
        $exception->message = sprintf('JSON parsing failed with "%s" for %s', $error, $addr);

        return $exception;
    }

    public static function becauseContentTypeIsNotExpected(string $contentType, OperationAddress $addr) : self
    {
        $exception          = new static($addr);
        $exception->message = sprintf('Content-Type "%s" is not expected for %s', $contentType, $addr);

        return $exception;
    }
}