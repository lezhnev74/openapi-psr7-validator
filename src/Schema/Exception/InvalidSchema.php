<?php

declare(strict_types=1);

namespace OpenAPIValidation\Schema\Exception;

use RuntimeException;
use Throwable;
use function sprintf;

// Something wrong with the OpenAPI schema. This sort of errors should have been caught by cebe's underlying package.
final class InvalidSchema extends RuntimeException
{
    /**
     * This exception can be thrown if unexpected schema values, or data values are detected
     * for example, 'minLength' keyword expects data to be 'string', if something else given - this exception raises
     *
     * @return InvalidSchema
     */
    public static function becauseDefensiveSchemaValidationFailed(Throwable $e) : self
    {
        return new static('Schema(or data) validation failed: ' . $e->getMessage(), $e->getCode(), $e);
    }

    public static function becauseTypeIsNotKnown(string $type) : self
    {
        return new static(sprintf("Type '%s' is unexpected.", $type));
    }
}
