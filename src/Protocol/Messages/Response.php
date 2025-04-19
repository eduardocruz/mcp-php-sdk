<?php

namespace ModelContextProtocol\Protocol\Messages;

use InvalidArgumentException;

/**
 * Represents a JSON-RPC 2.0 response.
 * 
 * A response message includes an ID matching the request, and either a result or an error.
 * According to the JSON-RPC 2.0 specification, a response must contain either result or error,
 * but not both.
 */
class Response extends JsonRpcMessage
{
    /**
     * Constructor.
     *
     * @param string|int $id The response identifier. Must match the request ID.
     * @param array<string, mixed>|null $result The result of the request. Null if an error occurred.
     * @param ErrorData|null $error Error data if an error occurred. Null if the request succeeded.
     * 
     * @throws InvalidArgumentException If neither result nor error is set, or if both are set.
     */
    public function __construct(
        public string|int $id,
        public ?array $result = null,
        public ?ErrorData $error = null
    ) {
        // Either result or error must be set, but not both
        if (($result === null && $error === null) || ($result !== null && $error !== null)) {
            throw new InvalidArgumentException('Either result or error must be set, but not both');
        }
    }
    
    /**
     * Convert the response to an associative array for serialization.
     *
     * @return array<string, mixed> The response as an associative array.
     */
    public function toArray(): array
    {
        $result = parent::toArray();
        $result['id'] = $this->id;
        
        if ($this->result !== null) {
            $result['result'] = $this->result;
        } elseif ($this->error !== null) {
            $result['error'] = $this->error->toArray();
        }
        
        return $result;
    }
    
    /**
     * Create a Response instance from an associative array.
     *
     * @param array<string, mixed> $data The response data as an associative array.
     * @return self The created Response instance.
     */
    public static function fromArray(array $data): self
    {
        $error = isset($data['error']) 
            ? ErrorData::fromArray($data['error']) 
            : null;
            
        return new self(
            $data['id'],
            $data['result'] ?? null,
            $error
        );
    }
    
    /**
     * Check if the response contains an error.
     *
     * @return bool True if the response contains an error, false otherwise.
     */
    public function isError(): bool
    {
        return $this->error !== null;
    }
}