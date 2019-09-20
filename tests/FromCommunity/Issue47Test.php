<?php

declare(strict_types=1);

namespace OpenAPIValidationTests\FromCommunity;

use GuzzleHttp\Psr7\ServerRequest;
use OpenAPIValidation\PSR7\Exception\Validation\InvalidQueryArgs;
use OpenAPIValidation\PSR7\ValidatorBuilder;
use PHPUnit\Framework\TestCase;
use function http_build_query;

final class Issue47Test extends TestCase
{
    /**
     * @see https://github.com/lezhnev74/openapi-psr7-validator/issues/47
     */
    public function testValidateExplode() : void
    {
        $yaml = /** @lang yaml */
            <<<YAML
openapi: 3.0.0
info:
  title: Test
  version: '1.0'
servers:
  - url: 'http://localhost:8000/api/v1'
paths:
  /test_id:
    get:
      parameters:
      - name: ids
        in: query
        required: true
        explode: false
        schema:
          type: array
          items:
            type: integer

      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                properties:
                  result: 
                    type: string
YAML;

        $validator = (new ValidatorBuilder())->fromYaml($yaml)->getServerRequestValidator();

        $query_params = ['ids' => 'string1'];
        $psrRequest   = (new ServerRequest('get', 'http://localhost:8000/api/v1/test_id?' . http_build_query($query_params)));
        $psrRequest   = $psrRequest->withQueryParams($query_params);

        try {
            $validator->validate($psrRequest);
            self::assertFalse(true);
        } catch (InvalidQueryArgs $exception) {
            self::assertEquals('Value "string1" for argument "ids" is invalid for Request [get /test_id]', $exception->getMessage());
        }

        $query_params = ['ids' => ['string_array']];
        $psrRequest   = (new ServerRequest('get', 'http://localhost:8000/api/v1/test_id?' . http_build_query($query_params)));
        $psrRequest   = $psrRequest->withQueryParams($query_params);

        try {
            $validator->validate($psrRequest);
            self::assertFalse(true);
        } catch (InvalidQueryArgs $exception) {
            self::assertEquals('Value "["string_array"]" for argument "ids" is invalid for Request [get /test_id]', $exception->getMessage());
        }

        $query_params = ['ids' => [5]];
        $psrRequest   = (new ServerRequest('get', 'http://localhost:8000/api/v1/test_id?' . http_build_query($query_params)));
        $psrRequest   = $psrRequest->withQueryParams($query_params);

        $validator->validate($psrRequest);

        $this->addToAssertionCount(1);
    }
}
