<?php

namespace ModelContextProtocol\Server\Tools;

/**
 * Represents a response from a tool execution
 */
class ToolResponse
{
    private array $content;

    /**
     * Create a new tool response
     */
    public function __construct(array $content)
    {
        $this->content = $content;
    }

    /**
     * Create a text response
     */
    public static function text(string $text): self
    {
        return new self([
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ]);
    }

    /**
     * Create a JSON response
     */
    public static function json(mixed $data): self
    {
        return new self([
            'content' => [
                [
                    'type' => 'application/json',
                    'data' => $data
                ]
            ]
        ]);
    }

    /**
     * Create an error response
     */
    public static function error(string $message, ?string $code = null): self
    {
        $response = [
            'error' => [
                'message' => $message
            ]
        ];

        if ($code !== null) {
            $response['error']['code'] = $code;
        }

        return new self($response);
    }

    /**
     * Get the response content
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->content;
    }
}
