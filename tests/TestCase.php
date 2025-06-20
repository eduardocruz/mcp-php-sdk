<?php

namespace ModelContextProtocol\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;

/**
 * Base test case class providing common functionality for all tests
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Create a mock JSON-RPC request
     */
    protected function createMockRequest(string $method, array $params = [], ?string $id = null): Request
    {
        return new Request($id ?? 'test-' . uniqid(), $method, $params);
    }

    /**
     * Create a mock JSON-RPC response
     */
    protected function createMockResponse($result = null, ?string $id = null): Response
    {
        return new Response($id ?? 'test-' . uniqid(), $result);
    }

    /**
     * Assert that a response has the expected structure
     */
    protected function assertValidJsonRpcResponse($response, ?string $expectedId = null): void
    {
        $this->assertInstanceOf(Response::class, $response);
        
        if ($expectedId !== null) {
            $this->assertEquals($expectedId, $response->id);
        }
        
        $this->assertNotNull($response->id);
    }

    /**
     * Assert that an error response has the expected structure
     */
    protected function assertValidErrorResponse(array $errorResponse, int $expectedCode = null): void
    {
        $this->assertArrayHasKey('error', $errorResponse);
        $this->assertArrayHasKey('code', $errorResponse['error']);
        $this->assertArrayHasKey('message', $errorResponse['error']);
        
        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $errorResponse['error']['code']);
        }
        
        $this->assertIsInt($errorResponse['error']['code']);
        $this->assertIsString($errorResponse['error']['message']);
    }

    /**
     * Assert that a tool response has the expected MCP structure
     */
    protected function assertValidToolResponse(array $response): void
    {
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        
        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertIsString($content['type']);
        }
    }

    /**
     * Assert that a resource response has the expected MCP structure
     */
    protected function assertValidResourceResponse(array $response): void
    {
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        
        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertIsString($content['type']);
        }
    }

    /**
     * Assert that a prompt response has the expected MCP structure
     */
    protected function assertValidPromptResponse(array $response): void
    {
        $this->assertArrayHasKey('messages', $response);
        $this->assertIsArray($response['messages']);
        
        foreach ($response['messages'] as $message) {
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
            $this->assertIsString($message['role']);
        }
    }

    /**
     * Create a temporary file for testing
     */
    protected function createTempFile(string $content = ''): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'mcp_test_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Clean up temporary files after test
     */
    protected function tearDown(): void
    {
        // Clean up any temporary files created during tests
        $tempFiles = glob(sys_get_temp_dir() . '/mcp_test_*');
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        parent::tearDown();
    }

    /**
     * Get reflection property value (for testing private/protected properties)
     */
    protected function getPropertyValue(object $object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Set reflection property value (for testing private/protected properties)
     */
    protected function setPropertyValue(object $object, string $propertyName, $value): void
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Call private/protected method for testing
     */
    protected function callMethod(object $object, string $methodName, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
} 