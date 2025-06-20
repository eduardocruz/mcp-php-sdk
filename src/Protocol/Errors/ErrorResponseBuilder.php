<?php

namespace ModelContextProtocol\Protocol\Errors;

use ModelContextProtocol\Protocol\Messages\ErrorData;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Constants;
use Throwable;

/**
 * Utility class for building standardized JSON-RPC error responses.
 * 
 * This class provides a consistent way to create error responses across all MCP components,
 * following the JSON-RPC 2.0 specification and MCP-specific error codes.
 */
class ErrorResponseBuilder
{
    /**
     * MCP-specific error codes extending JSON-RPC 2.0
     */
    public const ERROR_CODE_TOOL_NOT_FOUND = -32001;
    public const ERROR_CODE_TOOL_EXECUTION_ERROR = -32012;
    public const ERROR_CODE_RESOURCE_NOT_FOUND = -32013;
    public const ERROR_CODE_RESOURCE_ERROR = -32014;
    public const ERROR_CODE_PROMPT_NOT_FOUND = -32015;
    public const ERROR_CODE_PROMPT_ERROR = -32016;
    public const ERROR_CODE_VALIDATION_ERROR = -32017;
    public const ERROR_CODE_SUBSCRIPTION_ERROR = -32018;
    public const ERROR_CODE_TRANSPORT_ERROR = -32019;
    public const ERROR_CODE_SESSION_ERROR = -32020;
    
    // Alias for Constants::ERROR_CODE_INTERNAL_ERROR for convenience
    public const ERROR_CODE_INTERNAL_ERROR = Constants::ERROR_CODE_INTERNAL_ERROR;
    
    /**
     * Error code to message mapping for consistency
     */
    private const ERROR_MESSAGES = [
        Constants::ERROR_CODE_PARSE_ERROR => 'Parse error',
        Constants::ERROR_CODE_INVALID_REQUEST => 'Invalid request',
        Constants::ERROR_CODE_METHOD_NOT_FOUND => 'Method not found',
        Constants::ERROR_CODE_INVALID_PARAMS => 'Invalid parameters',
        Constants::ERROR_CODE_INTERNAL_ERROR => 'Internal error',
        Constants::ERROR_CODE_CONNECTION_CLOSED => 'Connection closed',
        Constants::ERROR_CODE_REQUEST_TIMEOUT => 'Request timeout',
        self::ERROR_CODE_TOOL_NOT_FOUND => 'Tool not found',
        self::ERROR_CODE_TOOL_EXECUTION_ERROR => 'Tool execution error',
        self::ERROR_CODE_RESOURCE_NOT_FOUND => 'Resource not found',
        self::ERROR_CODE_RESOURCE_ERROR => 'Resource error',
        self::ERROR_CODE_PROMPT_NOT_FOUND => 'Prompt not found',
        self::ERROR_CODE_PROMPT_ERROR => 'Prompt error',
        self::ERROR_CODE_VALIDATION_ERROR => 'Validation error',
        self::ERROR_CODE_SUBSCRIPTION_ERROR => 'Subscription error',
        self::ERROR_CODE_TRANSPORT_ERROR => 'Transport error',
        self::ERROR_CODE_SESSION_ERROR => 'Session error',
    ];
    
    /**
     * Create a standardized error response for a request.
     *
     * @param Request $request The original request
     * @param int $code The error code
     * @param string|null $message The error message (auto-generated if null)
     * @param mixed $data Additional error data
     * @param array $context Additional context for debugging
     * @return Response The error response
     */
    public static function createErrorResponse(
        Request $request,
        int $code,
        ?string $message = null,
        mixed $data = null,
        array $context = []
    ): Response {
        $errorData = self::createErrorData($code, $message, $data, $context);
        return new Response($request->id, null, $errorData);
    }
    
    /**
     * Create standardized error data.
     *
     * @param int $code The error code
     * @param string|null $message The error message (auto-generated if null)
     * @param mixed $data Additional error data
     * @param array $context Additional context for debugging
     * @return ErrorData The error data
     */
    public static function createErrorData(
        int $code,
        ?string $message = null,
        mixed $data = null,
        array $context = []
    ): ErrorData {
        // Use standard message if none provided
        if ($message === null) {
            $message = self::ERROR_MESSAGES[$code] ?? 'Unknown error';
        }
        
        // Enhance data with debugging context if provided
        if (!empty($context) || $data !== null) {
            $enhancedData = [];
            
            if ($data !== null) {
                $enhancedData['details'] = $data;
            }
            
            if (!empty($context)) {
                $enhancedData['context'] = $context;
                
                // Add timestamp for debugging
                $enhancedData['timestamp'] = date('c');
            }
            
            $data = $enhancedData;
        }
        
        return new ErrorData($code, $message, $data);
    }
    
    /**
     * Create an error response from an exception.
     *
     * @param Request $request The original request
     * @param Throwable $exception The exception
     * @param int|null $code The error code (auto-determined if null)
     * @param array $context Additional context for debugging
     * @return Response The error response
     */
    public static function fromException(
        Request $request,
        Throwable $exception,
        ?int $code = null,
        array $context = []
    ): Response {
        // Auto-determine error code based on exception type
        if ($code === null) {
            $code = self::getErrorCodeFromException($exception);
        }
        
        // Build context with exception details
        $exceptionContext = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        // Merge with provided context
        $context = array_merge($context, $exceptionContext);
        
        return self::createErrorResponse(
            $request,
            $code,
            $exception->getMessage(),
            null,
            $context
        );
    }
    
    /**
     * Create a validation error response.
     *
     * @param Request $request The original request
     * @param array $errors Validation errors
     * @param string|null $message Custom error message
     * @return Response The error response
     */
    public static function validationError(
        Request $request,
        array $errors,
        ?string $message = null
    ): Response {
        $message = $message ?? 'Parameter validation failed';
        
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_VALIDATION_ERROR,
            $message,
            ['errors' => $errors]
        );
    }
    
    /**
     * Create a tool not found error response.
     *
     * @param Request $request The original request
     * @param string $toolName The tool name
     * @return Response The error response
     */
    public static function toolNotFound(Request $request, string $toolName): Response
    {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_TOOL_NOT_FOUND,
            "Tool not found: {$toolName}",
            ['tool' => $toolName]
        );
    }
    
    /**
     * Create a tool execution error response.
     *
     * @param Request $request The original request
     * @param string $toolName The tool name
     * @param string $error The execution error
     * @return Response The error response
     */
    public static function toolExecutionError(
        Request $request,
        string $toolName,
        string $error
    ): Response {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_TOOL_EXECUTION_ERROR,
            "Tool execution failed: {$error}",
            ['tool' => $toolName, 'error' => $error]
        );
    }
    
    /**
     * Create a resource not found error response.
     *
     * @param Request $request The original request
     * @param string $uri The resource URI
     * @return Response The error response
     */
    public static function resourceNotFound(Request $request, string $uri): Response
    {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_RESOURCE_NOT_FOUND,
            "Resource not found: {$uri}",
            ['uri' => $uri]
        );
    }
    
    /**
     * Create a resource error response.
     *
     * @param Request $request The original request
     * @param string $uri The resource URI
     * @param string $error The resource error
     * @return Response The error response
     */
    public static function resourceError(
        Request $request,
        string $uri,
        string $error
    ): Response {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_RESOURCE_ERROR,
            "Resource error: {$error}",
            ['uri' => $uri, 'error' => $error]
        );
    }
    
    /**
     * Create a prompt not found error response.
     *
     * @param Request $request The original request
     * @param string $promptName The prompt name
     * @return Response The error response
     */
    public static function promptNotFound(Request $request, string $promptName): Response
    {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_PROMPT_NOT_FOUND,
            "Prompt not found: {$promptName}",
            ['prompt' => $promptName]
        );
    }
    
    /**
     * Create a prompt error response.
     *
     * @param Request $request The original request
     * @param string $promptName The prompt name
     * @param string $error The prompt error
     * @return Response The error response
     */
    public static function promptError(
        Request $request,
        string $promptName,
        string $error
    ): Response {
        return self::createErrorResponse(
            $request,
            self::ERROR_CODE_PROMPT_ERROR,
            "Prompt error: {$error}",
            ['prompt' => $promptName, 'error' => $error]
        );
    }
    
    /**
     * Create an array-format error response (for handlers that return arrays).
     *
     * @param int $code The error code
     * @param string|null $message The error message (auto-generated if null)
     * @param mixed $data Additional error data
     * @param array $context Additional context for debugging
     * @return array The error response array
     */
    public static function createErrorArray(
        int $code,
        ?string $message = null,
        mixed $data = null,
        array $context = []
    ): array {
        $errorData = self::createErrorData($code, $message, $data, $context);
        return ['error' => $errorData->toArray()];
    }
    
    /**
     * Determine error code from exception type.
     *
     * @param Throwable $exception The exception
     * @return int The appropriate error code
     */
    private static function getErrorCodeFromException(Throwable $exception): int
    {
        // Map common exception types to error codes
        return match (get_class($exception)) {
            'InvalidArgumentException' => Constants::ERROR_CODE_INVALID_PARAMS,
            'ModelContextProtocol\Server\Tools\Schema\ValidationException' => self::ERROR_CODE_VALIDATION_ERROR,
            'ModelContextProtocol\Transport\Exception\ConnectionException' => Constants::ERROR_CODE_CONNECTION_CLOSED,
            'ModelContextProtocol\Transport\Exception\MessageException' => self::ERROR_CODE_TRANSPORT_ERROR,
            'ModelContextProtocol\Transport\Exception\TransportException' => self::ERROR_CODE_TRANSPORT_ERROR,
            default => Constants::ERROR_CODE_INTERNAL_ERROR,
        };
    }
    
    /**
     * Get all available error codes with their descriptions.
     *
     * @return array<int, string> Error codes mapped to descriptions
     */
    public static function getErrorCodes(): array
    {
        return self::ERROR_MESSAGES;
    }
    
    /**
     * Check if an error code is valid.
     *
     * @param int $code The error code to check
     * @return bool True if the code is valid
     */
    public static function isValidErrorCode(int $code): bool
    {
        return isset(self::ERROR_MESSAGES[$code]);
    }
} 