<?php

namespace ModelContextProtocol\Tests\Unit\Protocol;

use ModelContextProtocol\Tests\TestCase;
use ModelContextProtocol\Protocol\Errors\ErrorResponseBuilder;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Constants;

class ErrorResponseBuilderTest extends TestCase
{
    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new Request('test-123', 'test/method', ['param' => 'value']);
    }

    public function testCreateErrorResponse(): void
    {
        $response = ErrorResponseBuilder::createErrorResponse(
            $this->request,
            Constants::ERROR_CODE_INVALID_PARAMS,
            'Invalid parameters provided'
        );

        $this->assertEquals('test-123', $response->responseId);
        $this->assertEquals('2.0', $response->jsonrpc);
        $this->assertNull($response->result);
        $this->assertNotNull($response->error);

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(Constants::ERROR_CODE_INVALID_PARAMS, $error->code);
        $this->assertEquals('Invalid parameters provided', $error->message);
    }

    public function testCreateErrorResponseWithData(): void
    {
        $data = ['validation_errors' => ['field' => 'required']];

        $response = ErrorResponseBuilder::createErrorResponse(
            $this->request,
            Constants::ERROR_CODE_INVALID_PARAMS,
            'Validation failed',
            $data
        );

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertNotNull($error->data);
        $this->assertEquals($data, $error->data['details']);
    }

    public function testCreateErrorResponseWithContext(): void
    {
        $context = ['operation' => 'test_op', 'user_id' => 'user123'];

        $response = ErrorResponseBuilder::createErrorResponse(
            $this->request,
            Constants::ERROR_CODE_INTERNAL_ERROR,
            'Internal server error',
            null,
            $context
        );

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertNotNull($error->data);
        $this->assertArrayHasKey('context', $error->data);
        $this->assertEquals('test_op', $error->data['context']['operation']);
        $this->assertEquals('user123', $error->data['context']['user_id']);
        $this->assertArrayHasKey('timestamp', $error->data);
    }

    public function testFromException(): void
    {
        $exception = new \InvalidArgumentException('Test exception message');

        $response = ErrorResponseBuilder::fromException($this->request, $exception);

        $this->assertEquals('test-123', $response->responseId);

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(Constants::ERROR_CODE_INVALID_PARAMS, $error->code);
        $this->assertEquals('Test exception message', $error->message);
        $this->assertArrayHasKey('context', $error->data);
        $this->assertEquals('InvalidArgumentException', $error->data['context']['exception']);
    }

    public function testFromExceptionWithCustomCode(): void
    {
        $exception = new \RuntimeException('Runtime error');

        $response = ErrorResponseBuilder::fromException(
            $this->request,
            $exception,
            ErrorResponseBuilder::ERROR_CODE_TOOL_EXECUTION_ERROR
        );

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(ErrorResponseBuilder::ERROR_CODE_TOOL_EXECUTION_ERROR, $error->code);
        $this->assertEquals('Runtime error', $error->message);
    }

    public function testCreateErrorArray(): void
    {
        $errorArray = ErrorResponseBuilder::createErrorArray(
            ErrorResponseBuilder::ERROR_CODE_TOOL_NOT_FOUND,
            'Tool not found: test-tool',
            ['tool' => 'test-tool']
        );

        $this->assertArrayHasKey('error', $errorArray);
        $this->assertEquals(ErrorResponseBuilder::ERROR_CODE_TOOL_NOT_FOUND, $errorArray['error']['code']);
        $this->assertEquals('Tool not found: test-tool', $errorArray['error']['message']);
        $this->assertEquals(['tool' => 'test-tool'], $errorArray['error']['data']['details']);
    }

    public function testToolNotFound(): void
    {
        $response = ErrorResponseBuilder::toolNotFound($this->request, 'missing-tool');

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(ErrorResponseBuilder::ERROR_CODE_TOOL_NOT_FOUND, $error->code);
        $this->assertStringContainsString('missing-tool', $error->message);
    }

    public function testResourceNotFound(): void
    {
        $response = ErrorResponseBuilder::resourceNotFound($this->request, 'file://missing.txt');

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(ErrorResponseBuilder::ERROR_CODE_RESOURCE_NOT_FOUND, $error->code);
        $this->assertStringContainsString('file://missing.txt', $error->message);
    }

    public function testValidationError(): void
    {
        $errors = ['name' => 'required', 'age' => 'must be integer'];

        $response = ErrorResponseBuilder::validationError($this->request, $errors);

        $error = $response->error;
        $this->assertNotNull($error);
        $this->assertEquals(ErrorResponseBuilder::ERROR_CODE_VALIDATION_ERROR, $error->code);
        $this->assertEquals(['errors' => $errors], $error->data['details']);
    }

    public function testIsValidErrorCode(): void
    {
        $this->assertTrue(ErrorResponseBuilder::isValidErrorCode(Constants::ERROR_CODE_PARSE_ERROR));
        $this->assertTrue(ErrorResponseBuilder::isValidErrorCode(ErrorResponseBuilder::ERROR_CODE_TOOL_NOT_FOUND));
        $this->assertFalse(ErrorResponseBuilder::isValidErrorCode(-99999));
        $this->assertFalse(ErrorResponseBuilder::isValidErrorCode(200));
    }

    public function testGetErrorCodes(): void
    {
        $errorCodes = ErrorResponseBuilder::getErrorCodes();

        $this->assertIsArray($errorCodes);
        $this->assertNotEmpty($errorCodes);
        $this->assertArrayHasKey(Constants::ERROR_CODE_PARSE_ERROR, $errorCodes);
        $this->assertArrayHasKey(ErrorResponseBuilder::ERROR_CODE_TOOL_NOT_FOUND, $errorCodes);
    }

    public function testExceptionMapping(): void
    {
        $testCases = [
            [\InvalidArgumentException::class, Constants::ERROR_CODE_INVALID_PARAMS],
            [\RuntimeException::class, Constants::ERROR_CODE_INTERNAL_ERROR],
            [\Exception::class, Constants::ERROR_CODE_INTERNAL_ERROR],
        ];

        foreach ($testCases as [$exceptionClass, $expectedCode]) {
            $exception = new $exceptionClass('Test message');
            $response = ErrorResponseBuilder::fromException($this->request, $exception);

            $error = $response->error;
            $this->assertNotNull($error);
            $this->assertEquals(
                $expectedCode,
                $error->code,
                "Exception {$exceptionClass} should map to error code {$expectedCode}"
            );
        }
    }
}
