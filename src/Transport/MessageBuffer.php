<?php

namespace ModelContextProtocol\Transport;

use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Notification;
use InvalidArgumentException;

/**
 * Buffers a continuous stream into discrete JSON-RPC messages using LSP-style Content-Length headers.
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
        // Look for the header separator (double CRLF)
        $headerEnd = strpos($this->buffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }
        
        // Extract headers
        $headers = substr($this->buffer, 0, $headerEnd);
        $contentLength = $this->parseContentLength($headers);
        
        if ($contentLength === null) {
            throw new InvalidArgumentException('Missing Content-Length header');
        }
        
        // Check if we have the complete message body
        $bodyStart = $headerEnd + 4; // Skip past "\r\n\r\n"
        $totalLength = $bodyStart + $contentLength;
        
        if (strlen($this->buffer) < $totalLength) {
            return null; // Not enough data yet
        }
        
        // Extract the message body
        $messageBody = substr($this->buffer, $bodyStart, $contentLength);
        
        // Update the buffer by removing the processed message
        $this->buffer = substr($this->buffer, $totalLength);
        
        // Deserialize the message
        return $this->deserializeMessage($messageBody);
    }
    
    /**
     * Parse the Content-Length from LSP headers.
     *
     * @param string $headers The header string
     * @return int|null The content length or null if not found
     */
    private function parseContentLength(string $headers): ?int
    {
        $lines = explode("\r\n", $headers);
        
        foreach ($lines as $line) {
            if (preg_match('/^Content-Length:\s*(\d+)$/i', $line, $matches)) {
                return (int)$matches[1];
            }
        }
        
        return null;
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
     * @param string $messageBody The JSON string to deserialize
     * @return JsonRpcMessage The parsed message
     * @throws InvalidArgumentException If the message is invalid or cannot be parsed
     */
    private function deserializeMessage(string $messageBody): JsonRpcMessage
    {
        try {
            $data = json_decode($messageBody, true, 512, JSON_THROW_ON_ERROR);
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
     * Serialize a JSON-RPC message to a string with LSP-style headers.
     *
     * @param JsonRpcMessage $message The message to serialize
     * @return string The serialized message with Content-Length header
     */
    public static function serializeMessage(JsonRpcMessage $message): string
    {
        $json = json_encode($message->toArray(), JSON_THROW_ON_ERROR);
        $contentLength = strlen($json);
        
        return "Content-Length: {$contentLength}\r\n\r\n{$json}";
    }
}