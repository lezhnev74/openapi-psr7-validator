<?php

declare(strict_types=1);

namespace OpenAPIValidation\PSR7\Validators;

use cebe\openapi\spec\SecurityRequirement;
use cebe\openapi\spec\SecurityScheme;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidCookies;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidHeaders;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidQueryArgs;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use OpenAPIValidation\PSR7\Exception\ValidationFailed;
use OpenAPIValidation\PSR7\MessageValidator;
use OpenAPIValidation\PSR7\OperationAddress;
use OpenAPIValidation\PSR7\SpecFinder;
use OpenAPIValidation\Schema\Exception\InvalidSchema;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use function count;
use function preg_match;
use function sprintf;

final class SecurityValidator implements MessageValidator
{
    private const HEADER_AUTHORIZATION = 'Authorization';
    private const AUTH_PATTERN_BASIC   = '#^Basic #';
    private const AUTH_PATTERN_BEARER  = '#^Bearer #';

    /** @var SpecFinder */
    private $finder;

    public function __construct(SpecFinder $finder)
    {
        $this->finder = $finder;
    }

    /** {@inheritdoc} */
    public function validate(OperationAddress $addr, MessageInterface $message) : void
    {
        // Note: Security schemes support OR/AND union
        // That is, security is an array of hashmaps, where each hashmap contains one or more named security schemes.
        // Items in a hashmap are combined using logical AND, and array items are combined using logical OR.
        // Security schemes combined via OR are alternatives – any one can be used in the given context.
        // Security schemes combined via AND must be used simultaneously in the same request.
        // @see https://swagger.io/docs/specification/authentication/
        if (! ($message instanceof RequestInterface)) {
            return;
        }

        $this->validateServerRequest($addr, $message);
    }

    /**
     * @throws ValidationFailed
     */
    private function validateServerRequest(OperationAddress $addr, RequestInterface $request) : void
    {
        $securitySpecs = $this->finder->findSecuritySpecs($addr);

        if (! count($securitySpecs)) {
            // no auth needed
            return;
        }

        // OR-union: any of security schemes can match
        foreach ($securitySpecs as $spec) {
            try {
                $this->validateSecurityScheme($addr, $request, $spec);

                return; // this security schema matched, request is valid, stop here
            } catch (ValidationFailed $e) {
                // that security schema did not match
            }
        }

        // no schema matched, that is bad
        throw InvalidSecurity::becauseRequestDidNotMatchAnySchema($addr);
    }

    /**
     * @throws InvalidSecurity
     */
    private function validateSecurityScheme(
        OperationAddress $addr,
        RequestInterface $request,
        SecurityRequirement $spec
    ) : void {
        // Here I implement AND-union
        // Each SecurityRequirement contains 1+ security [schema_name=>scopes]
        // Scopes are not used for the purpose of validation

        $securitySchemesSpecs = $this->finder->findSecuritySchemesSpecs();
        foreach ($spec->getSerializableData() as $securitySchemeName => $scopes) {
            if (! isset($securitySchemesSpecs[$securitySchemeName])) {
                throw new InvalidSchema(
                    sprintf("Mentioned security scheme '%s' not found in the given spec", $securitySchemeName)
                );
            }
            $securityScheme = $securitySchemesSpecs[$securitySchemeName];

            try {
                switch ($securityScheme->type) {
                    case 'http':
                        $this->validateHTTPSecurityScheme($addr, $request, $securityScheme);
                        break;
                    case 'apiKey':
                        $this->validateApiKeySecurityScheme($addr, $request, $securityScheme);
                        break;
                }
            } catch (ValidationFailed $exception) {
                throw InvalidSecurity::becauseRequestDidNotMatchSchema($securitySchemeName, $addr, $exception);
            }
        }
    }

    /**
     * @throws ValidationFailed
     */
    private function validateHTTPSecurityScheme(
        OperationAddress $addr,
        RequestInterface $request,
        SecurityScheme $securityScheme
    ) : void {
        // Supported schemas: https://www.iana.org/assignments/http-authschemes/http-authschemes.xhtml

        // Token should be passed in TLS session, in header: `Authorization:....`
        if (! $request->hasHeader(self::HEADER_AUTHORIZATION)) {
            throw InvalidHeaders::becauseOfMissingRequiredHeader(self::HEADER_AUTHORIZATION, $addr);
        }

        switch ($securityScheme->scheme) {
            case 'basic':
                // Described in https://tools.ietf.org/html/rfc7617
                if (! preg_match(self::AUTH_PATTERN_BASIC, $request->getHeader(self::HEADER_AUTHORIZATION)[0])) {
                    throw InvalidSecurity::becauseAuthHeaderValueDoesNotMatchExpectedPattern(
                        self::HEADER_AUTHORIZATION,
                        self::AUTH_PATTERN_BASIC,
                        $addr
                    );
                }

                break;
            case 'bearer':
                // Described in https://tools.ietf.org/html/rfc6750
                if (! preg_match(self::AUTH_PATTERN_BEARER, $request->getHeader(self::HEADER_AUTHORIZATION)[0])) {
                    throw InvalidSecurity::becauseAuthHeaderValueDoesNotMatchExpectedPattern(
                        self::HEADER_AUTHORIZATION,
                        self::AUTH_PATTERN_BEARER,
                        $addr
                    );
                }

                break;
        }
    }

    /**
     * @throws ValidationFailed
     */
    private function validateApiKeySecurityScheme(
        OperationAddress $addr,
        RequestInterface $request,
        SecurityScheme $securityScheme
    ) : void {
        switch ($securityScheme->in) {
            case 'query':
                if (! isset($request->getQueryParams()[$securityScheme->name])) {
                    throw InvalidQueryArgs::becauseOfMissingRequiredArgument($securityScheme->name, $addr);
                }
                break;
            case 'header':
                if (! $request->hasHeader($securityScheme->name)) {
                    throw InvalidHeaders::becauseOfMissingRequiredHeader($securityScheme->name, $addr);
                }
                break;
            case 'cookie':
                if (! isset($request->getCookieParams()[$securityScheme->name])) {
                    throw InvalidCookies::becauseOfMissingRequiredCookie($securityScheme->name, $addr);
                }
                break;
        }
    }
}
