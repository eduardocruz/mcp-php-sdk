<?php

namespace ModelContextProtocol\Transport;

use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Notification;
use InvalidArgumentException;

/**
 * Buffers a continuous stream into discrete JSON-RPC messages.
 */
class MessageBuffer
{
    /**
     * @var string The internal buffer accumulating incoming data
     */
    private string $buffer = '';
    
    /**
     * Append new data to the buffer.
     *
     * @param string $data The data to append
     * @return void
     */
    public function append(string $data): void
    {
        $this->buffer .= $data;
    }
    
    /**
     * Read a complete JSON-RPC message from the buffer, if available.
     *
     * @return JsonRpcMessage|null A parsed JSON-RPC message or null if no complete message is available
     * @throws InvalidArgumentException If the message is invalid or cannot be parsed
     */
    public function readMessage(): ?JsonRpcMessage
    {
        // Look for a newline separator
        $newlinePos = strpos($this->buffer, "\n");
        if ($newlinePos === false) {
            return null;
        }
        
        // Extract the line and update the buffer
        $line = substr($this->buffer, 0, $newlinePos);
        $this->buffer = substr($this->buffer, $newlinePos + 1);
        
        // Remove any trailing \r (for Windows-style line endings)
        if (substr($line, -1) === "\r") {
            $line = substr($line, 0, -1);
        }
        
        // Deserialize the message
        return $this->deserializeMessage($line);
    }
    
    /**
     * Clear the buffer.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->buffer = '';
    }
    
    /**
     * Deserialize a JSON-RPC message from a string.
     *
     * @param string $line The JSON string to deserialize
     * @return JsonRpcMessage The parsed message
     * @throws InvalidArgumentException If the message is invalid or cannot be parsed
     */
    private function deserializeMessage(string $line): JsonRpcMessage
    {
        try {
            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JSON in message: ' . $e->getMessage(), 0, $e);
        }
        
        if (!is_array($data) || !isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new InvalidArgumentException('Invalid JSON-RPC message: missing or invalid jsonrpc version');
        }
        
        // Check if this is a request, response, or notification
        if (isset($data['method'])) {
            if (isset($data['id'])) {
                // It's a request
                return Request::fromArray($data);
            } else {
                // It's a notification
                return Notification::fromArray($data);
            }
        } elseif (isset($data['id'])) {
            // It's a response (either successful or error)
            return Response::fromArray($data);
        }
        
        throw new InvalidArgumentException('Invalid JSON-RPC message: cannot determine message type');
    }
    
    /**
     * Serialize a JSON-RPC message to a string.
     *
     * @param JsonRpcMessage $message The message to serialize
     * @return string The serialized message with a newline appended
     */
    public static function serializeMessage(JsonRpcMessage $message): string
    {
        return json_encode($message->toArray(), JSON_THROW_ON_ERROR) . "\n";
    }
}