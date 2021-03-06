<?php

declare(strict_types=1);

namespace OpenAPIValidation\PSR7\Exception\Validation;

use OpenAPIValidation\PSR7\OperationAddress;
use OpenAPIValidation\Schema\Exception\SchemaMismatch;
use function sprintf;

class InvalidPath extends AddressValidationFailed
{
    public static function becauseValueDoesNotMatchSchema(string $parameterName, string $parameterValue, OperationAddress $address, SchemaMismatch $prev) : self
    {
        $exception          = static::fromAddrAndPrev($address, $prev);
        $exception->message = sprintf('Value "%s" for parameter "%s" is invalid for %s', $parameterValue, $parameterName, $address);

        return $exception;
    }

    public static function becausePathDoesNotMatchPattern(string $path, OperationAddress $address) : self
    {
        $exception          = static::fromAddr($address);
        $exception->message = sprintf('Unable to parse "%s" against the pattern "%s" for %s', $path, $address->path(), $address);

        return $exception;
    }
}
