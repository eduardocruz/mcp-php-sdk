<?php

namespace ModelContextProtocol\Protocol\Models;

/**
 * Parameters for the initialization request.
 */
class InitializeParams
{
    /**
     * Constructor.
     *
     * @param string $protocolVersion The latest version of the MCP protocol that the client supports
     * @param ClientCapabilities $capabilities The capabilities of the client
     * @param Implementation $clientInfo Information about the client implementation
     */
    public function __construct(
        public string $protocolVersion,
        public ClientCapabilities $capabilities,
        public Implementation $clientInfo
    ) {}
    
    /**
     * Convert the initialization parameters to an array.
     *
     * @return array<string, mixed> The parameters as an array
     */
    public function toArray(): array
    {
        return [
            'protocolVersion' => $this->protocolVersion,
            'capabilities' => $this->capabilities->toArray(),
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }
    
    /**
     * Create an InitializeParams instance from an array.
     *
     * @param array<string, mixed> $data The data array
     * @return self The created instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['protocolVersion'],
            ClientCapabilities::fromArray($data['capabilities'] ?? []),
            Implementation::fromArray($data['clientInfo'] ?? $data['client'] ?? [])
        );
    }
}