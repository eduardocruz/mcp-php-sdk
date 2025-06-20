<?php

namespace ModelContextProtocol\Server\Prompts;

/**
 * Represents a response from a prompt execution
 */
class PromptResponse
{
    private array $messages;
    private ?string $description;

    /**
     * Create a new prompt response
     * 
     * @param array $messages The messages array
     * @param string|null $description Optional description
     */
    public function __construct(array $messages, ?string $description = null)
    {
        $this->messages = $messages;
        $this->description = $description;
    }

    /**
     * Get the messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get the description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Convert to array format for MCP response
     */
    public function toArray(): array
    {
        $result = [
            'messages' => $this->messages
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    /**
     * Create a simple text prompt response
     */
    public static function text(string $text, string $role = 'user', ?string $description = null): self
    {
        return new self([
            [
                'role' => $role,
                'content' => [
                    'type' => 'text',
                    'text' => $text
                ]
            ]
        ], $description);
    }

    /**
     * Create a prompt response with multiple messages
     */
    public static function messages(array $messages, ?string $description = null): self
    {
        return new self($messages, $description);
    }
}