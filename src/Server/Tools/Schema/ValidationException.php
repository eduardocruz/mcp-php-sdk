<?php

namespace MCP\Server\Tools\Schema;

/**
 * Exception thrown when schema validation fails
 */
class ValidationException extends \Exception
{
    private array $errors;

    /**
     * Create a new validation exception
     */
    public function __construct(array $errors, string $message = "Schema validation failed")
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}