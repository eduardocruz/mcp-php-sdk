<?php

namespace ModelContextProtocol\Protocol\Models;

/**
 * Capabilities that a client may support.
 * 
 * This is not a closed set - any client can define its own additional capabilities.
 */
class ClientCapabilities
{
    /**
     * Constructor.
     *
     * @param array<string, mixed>|null $experimental Experimental, non-standard capabilities that the client supports
     * @param array<string, mixed>|null $sampling Whether the client supports sampling from an LLM
     * @param array<string, mixed>|null $roots Whether the client supports listing roots
     * @param array<string, mixed>|null $additional Additional capabilities not defined in the core specification
     */
    public function __construct(
        public ?array $experimental = null,
        public ?array $sampling = null,
        public ?array $roots = null,
        private array $additional = []
    ) {}
    
    /**
     * Get all capabilities, including additional ones.
     *
     * @return array<string, mixed> All capabilities
     */
    public function toArray(): array
    {
        $result = [];
        
        if ($this->experimental !== null) {
            $result['experimental'] = $this->experimental;
        }
        
        if ($this->sampling !== null) {
            $result['sampling'] = $this->sampling;
        }
        
        if ($this->roots !== null) {
            $result['roots'] = $this->roots;
        }
        
        return array_merge($result, $this->additional);
    }
    
    /**
     * Get a specific additional capability.
     *
     * @param string $name The name of the capability
     * @return mixed|null The capability value, or null if not set
     */
    public function get(string $name): mixed
    {
        if ($name === 'experimental') {
            return $this->experimental;
        }
        
        if ($name === 'sampling') {
            return $this->sampling;
        }
        
        if ($name === 'roots') {
            return $this->roots;
        }
        
        return $this->additional[$name] ?? null;
    }
    
    /**
     * Set an additional capability.
     *
     * @param string $name The name of the capability
     * @param mixed $value The capability value
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        if ($name === 'experimental') {
            $this->experimental = $value;
            return;
        }
        
        if ($name === 'sampling') {
            $this->sampling = $value;
            return;
        }
        
        if ($name === 'roots') {
            $this->roots = $value;
            return;
        }
        
        $this->additional[$name] = $value;
    }
    
    /**
     * Check if a capability is set.
     *
     * @param string $name The name of the capability
     * @return bool True if the capability is set, false otherwise
     */
    public function has(string $name): bool
    {
        if ($name === 'experimental') {
            return $this->experimental !== null;
        }
        
        if ($name === 'sampling') {
            return $this->sampling !== null;
        }
        
        if ($name === 'roots') {
            return $this->roots !== null;
        }
        
        return isset($this->additional[$name]);
    }
    
    /**
     * Create a ClientCapabilities instance from an array.
     *
     * @param array<string, mixed> $data The capabilities data
     * @return self The created instance
     */
    public static function fromArray(array $data): self
    {
        $experimental = $data['experimental'] ?? null;
        $sampling = $data['sampling'] ?? null;
        $roots = $data['roots'] ?? null;
        
        $additional = $data;
        unset($additional['experimental'], $additional['sampling'], $additional['roots']);
        
        return new self($experimental, $sampling, $roots, $additional);
    }
}