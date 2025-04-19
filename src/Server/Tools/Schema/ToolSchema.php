<?php

namespace ModelContextProtocol\Server\Tools\Schema;

/**
 * Represents the schema for a tool
 */
class ToolSchema
{
    private string $name;
    private array $properties;
    private array $required;
    private ?string $description;

    /**
     * Create a new tool schema
     * 
     * @param string $name The name of the tool
     * @param array $properties The properties of the tool schema
     * @param array $required The required properties
     * @param string|null $description The tool description
     */
    public function __construct(
        string $name,
        array $properties,
        array $required = [],
        ?string $description = null
    ) {
        $this->name = $name;
        $this->properties = $properties;
        $this->required = $required;
        $this->description = $description;
    }

    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get the required properties
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * Get the tool description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        $schema = [
            'properties' => $this->properties,
        ];

        if (!empty($this->required)) {
            $schema['required'] = $this->required;
        }

        if ($this->description !== null) {
            $schema['description'] = $this->description;
        }

        return $schema;
    }

    /**
     * Create from array representation
     */
    public static function fromArray(string $name, array $schema): self
    {
        return new self(
            $name,
            $schema['properties'] ?? [],
            $schema['required'] ?? [],
            $schema['description'] ?? null
        );
    }
}