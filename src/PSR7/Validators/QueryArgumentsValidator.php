<?php

declare(strict_types=1);

namespace OpenAPIValidation\PSR7\Validators;

use cebe\openapi\spec\Parameter;
use OpenAPIValidation\PSR7\Exception\NoPath;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidQueryArgs;
use OpenAPIValidation\PSR7\MessageValidator;
use OpenAPIValidation\PSR7\OperationAddress;
use OpenAPIValidation\PSR7\SpecFinder;
use OpenAPIValidation\Schema\BreadCrumb;
use OpenAPIValidation\Schema\Exception\SchemaMismatch;
use OpenAPIValidation\Schema\SchemaValidator;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function array_key_exists;
use function explode;
use function is_array;
use function is_string;
use function json_encode;
use function parse_str;

/**
 * @see https://swagger.io/docs/specification/describing-parameters/
 */
final class QueryArgumentsValidator implements MessageValidator
{
    use ValidationStrategy;

    /** @var SpecFinder */
    private $finder;

    public function __construct(SpecFinder $finder)
    {
        $this->finder = $finder;
    }

    /** {@inheritdoc} */
    public function validate(OperationAddress $addr, MessageInterface $message) : void
    {
        if (! $message instanceof RequestInterface) {
            return;
        }

        $validationStrategy   = $this->detectValidationStrategy($message);
        $parsedQueryArguments = $this->parseQueryArguments($message);
        $this->validateQueryArguments($addr, $parsedQueryArguments, $validationStrategy);
    }

    /**
     * @param mixed[] $parsedQueryArguments [limit=>10]
     *
     * @throws InvalidQueryArgs
     * @throws NoPath
     */
    private function validateQueryArguments(OperationAddress $addr, array $parsedQueryArguments, int $validationStrategy) : void
    {
        $specs = $this->finder->findQuerySpecs($addr);
        $this->checkMissingArguments($addr, $parsedQueryArguments, $specs);
        $this->validateAgainstSchema($addr, $parsedQueryArguments, $validationStrategy, $specs);
    }

    /**
     * @param mixed[]     $parsedQueryArguments [limit=>10]
     * @param Parameter[] $specs
     */
    private function checkMissingArguments(OperationAddress $addr, array $parsedQueryArguments, array $specs) : void
    {
        foreach ($specs as $name => $spec) {
            if ($spec->required && ! array_key_exists($name, $parsedQueryArguments)) {
                throw InvalidQueryArgs::becauseOfMissingRequiredArgument($name, $addr);
            }
        }
    }

    /**
     * @param mixed[]     $parsedQueryArguments [limit=>10]
     * @param Parameter[] $specs
     */
    private function validateAgainstSchema(OperationAddress $addr, array $parsedQueryArguments, int $validationStrategy, array $specs) : void
    {
        // Note: By default, OpenAPI treats all request parameters as optional.

        foreach ($parsedQueryArguments as $name => $argumentValue) {
            // skip if there are no schema for this argument
            if (! array_key_exists($name, $specs)) {
                continue;
            }
            $spec = $specs[$name];

            if ($spec->explode === false && is_string($argumentValue)) {
                $argumentValue = explode(',', $argumentValue);
            }

            $validator = new SchemaValidator($validationStrategy);
            try {
                $validator->validate($argumentValue, $specs[$name]->schema, new BreadCrumb($name));
            } catch (SchemaMismatch $e) {
                $argumentValue = $parsedQueryArguments[$name];
                if (is_array($argumentValue)) {
                    $argumentValue = json_encode($argumentValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                throw InvalidQueryArgs::becauseValueDoesNotMatchSchema($name, $argumentValue, $addr, $e);
            }
        }
    }

    /**
     * @return mixed[] like [offset => 10]
     */
    private function parseQueryArguments(RequestInterface $message) : array
    {
        if ($message instanceof ServerRequestInterface) {
            $parsedQueryArguments = $message->getQueryParams();
        } else {
            parse_str($message->getUri()->getQuery(), $parsedQueryArguments);
        }

        return $parsedQueryArguments;
    }
}
