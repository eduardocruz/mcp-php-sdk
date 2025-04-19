<?php

namespace ModelContextProtocol\Protocol\Models;

/**
 * Result of a successful initialization request.
 */
class InitializeResult
{
    /**
     * Constructor.
     *
     * @param string $protocolVersion The version of the MCP protocol that the server wants to use
     * @param ServerCapabilities $capabilities The capabilities of the server
     * @param Implementation $serverInfo Information about the server implementation
     * @param string|null $instructions Optional instructions describing how to use the server and its features
     */
    public function __construct(
        public string $protocolVersion,
        public ServerCapabilities $capabilities,
        public Implementation $serverInfo,
        public ?string $instructions = null
    ) {}
    
    /**
     * Convert the initialization result to an array.
     *
     * @return array<string, mixed> The result as an array
     */
    public function toArray(): array
    {
        $result = [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->toArray(),
            'server' => $this->serverInfo->toArray(),
        ];
        
        if ($this->instructions !== null) {
            $result['instructions'] = $this->instructions;
        }
        
        return $result;
    }
    
    /**
     * Create an InitializeResult instance from an array.
     *
     * @param array<string, mixed> $data The data array
     * @return self The created instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['protocolVersion'],
            ServerCapabilities::fromArray($data['capabilities'] ?? []),
            Implementation::fromArray($data['server']),
            $data['instructions'] ?? null
        );
    }
}