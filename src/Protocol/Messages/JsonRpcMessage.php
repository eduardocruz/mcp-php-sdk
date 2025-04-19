<?php

namespace ModelContextProtocol\Protocol\Messages;

/**
 * Base class for all JSON-RPC 2.0 messages.
 * 
 * This class defines the common structure and behavior for all message types
 * according to the JSON-RPC 2.0 specification.
 */
abstract class JsonRpcMessage
{
    /**
     * The JSON-RPC 2.0 protocol version.
     */
    public const VERSION = '2.0';
    
    /**
     * The JSON-RPC protocol version string.
     */
    public string $jsonrpc = self::VERSION;
    
    /**
     * Convert the message to an associative array for serialization.
     *
     * @return array<string, mixed> The message as an associative array.
     */
    public function toArray(): array
    {
        return ['jsonrpc' => $this->jsonrpc];
    }
    
    /**
     * Convert the message to a JSON string.
     *
     * @return string The message as a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}