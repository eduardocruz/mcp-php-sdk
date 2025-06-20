<?php

namespace ModelContextProtocol\Protocol\Models;

/**
 * Describes the name and version of an MCP implementation.
 */
class Implementation
{
    /**
     * Constructor.
     *
     * @param string $name The name of the implementation
     * @param string $version The version of the implementation
     */
    public function __construct(
        public string $name,
        public string $version
    ) {
    }

    /**
     * Convert the implementation data to an array.
     *
     * @return array<string, string> An array with name and version data
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version
        ];
    }

    /**
     * Create an Implementation instance from an array.
     *
     * @param array<string, mixed> $data The data array
     * @return self The created instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['version']
        );
    }
}
