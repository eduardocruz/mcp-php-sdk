<?php

namespace ModelContextProtocol\Protocol\Models;

/**
 * Capabilities that a server may support.
 * 
 * This is not a closed set - any server can define its own additional capabilities.
 */
class ServerCapabilities
{
    /**
     * Constructor.
     *
     * @param array<string, mixed>|null $experimental Experimental, non-standard capabilities that the server supports
     * @param array<string, mixed>|null $logging Whether the server supports sending log messages to the client
     * @param array<string, mixed>|null $completions Whether the server supports sending completions to the client
     * @param array<string, mixed>|null $prompts Whether the server offers any prompt templates
     * @param array<string, mixed>|null $resources Whether the server offers any resources to read
     * @param array<string, mixed>|null $tools Whether the server offers any tools to call
     * @param array<string, mixed> $additional Additional capabilities not defined in the core specification
     */
    public function __construct(
        public ?array $experimental = null,
        public ?array $logging = null,
        public ?array $completions = null,
        public ?array $prompts = null,
        public ?array $resources = null,
        public ?array $tools = null,
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
        
        if ($this->logging !== null) {
            $result['logging'] = $this->logging;
        }
        
        if ($this->completions !== null) {
            $result['completions'] = $this->completions;
        }
        
        if ($this->prompts !== null) {
            $result['prompts'] = $this->prompts;
        }
        
        if ($this->resources !== null) {
            $result['resources'] = $this->resources;
        }
        
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }
        
        return array_merge($result, $this->additional);
    }
    
    /**
     * Get a specific capability.
     *
     * @param string $name The name of the capability
     * @return mixed|null The capability value, or null if not set
     */
    public function get(string $name): mixed
    {
        switch ($name) {
            case 'experimental':
                return $this->experimental;
            case 'logging':
                return $this->logging;
            case 'completions':
                return $this->completions;
            case 'prompts':
                return $this->prompts;
            case 'resources':
                return $this->resources;
            case 'tools':
                return $this->tools;
            default:
                return $this->additional[$name] ?? null;
        }
    }
    
    /**
     * Set a capability.
     *
     * @param string $name The name of the capability
     * @param mixed $value The capability value
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'experimental':
                $this->experimental = $value;
                break;
            case 'logging':
                $this->logging = $value;
                break;
            case 'completions':
                $this->completions = $value;
                break;
            case 'prompts':
                $this->prompts = $value;
                break;
            case 'resources':
                $this->resources = $value;
                break;
            case 'tools':
                $this->tools = $value;
                break;
            default:
                $this->additional[$name] = $value;
                break;
        }
    }
    
    /**
     * Check if a capability is set.
     *
     * @param string $name The name of the capability
     * @return bool True if the capability is set, false otherwise
     */
    public function has(string $name): bool
    {
        switch ($name) {
            case 'experimental':
                return $this->experimental !== null;
            case 'logging':
                return $this->logging !== null;
            case 'completions':
                return $this->completions !== null;
            case 'prompts':
                return $this->prompts !== null;
            case 'resources':
                return $this->resources !== null;
            case 'tools':
                return $this->tools !== null;
            default:
                return isset($this->additional[$name]);
        }
    }
    
    /**
     * Merge this set of capabilities with another set, creating a new object.
     *
     * @param ServerCapabilities $other The other capabilities to merge with
     * @return self A new ServerCapabilities instance with merged capabilities
     */
    public function merge(ServerCapabilities $other): self
    {
        $result = clone $this;
        
        if ($other->experimental !== null) {
            $result->experimental = $this->experimental !== null
                ? array_merge($this->experimental, $other->experimental)
                : $other->experimental;
        }
        
        if ($other->logging !== null) {
            $result->logging = $this->logging !== null
                ? array_merge($this->logging, $other->logging)
                : $other->logging;
        }
        
        if ($other->completions !== null) {
            $result->completions = $this->completions !== null
                ? array_merge($this->completions, $other->completions)
                : $other->completions;
        }
        
        if ($other->prompts !== null) {
            $result->prompts = $this->prompts !== null
                ? array_merge($this->prompts, $other->prompts)
                : $other->prompts;
        }
        
        if ($other->resources !== null) {
            $result->resources = $this->resources !== null
                ? array_merge($this->resources, $other->resources)
                : $other->resources;
        }
        
        if ($other->tools !== null) {
            $result->tools = $this->tools !== null
                ? array_merge($this->tools, $other->tools)
                : $other->tools;
        }
        
        foreach ($other->additional as $key => $value) {
            if (is_array($value) && isset($result->additional[$key]) && is_array($result->additional[$key])) {
                $result->additional[$key] = array_merge($result->additional[$key], $value);
            } else {
                $result->additional[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Create a ServerCapabilities instance from an array.
     *
     * @param array<string, mixed> $data The capabilities data
     * @return self The created instance
     */
    public static function fromArray(array $data): self
    {
        $experimental = $data['experimental'] ?? null;
        $logging = $data['logging'] ?? null;
        $completions = $data['completions'] ?? null;
        $prompts = $data['prompts'] ?? null;
        $resources = $data['resources'] ?? null;
        $tools = $data['tools'] ?? null;
        
        $additional = $data;
        unset(
            $additional['experimental'],
            $additional['logging'],
            $additional['completions'],
            $additional['prompts'],
            $additional['resources'],
            $additional['tools']
        );
        
        return new self(
            $experimental,
            $logging,
            $completions,
            $prompts,
            $resources,
            $tools,
            $additional
        );
    }
}