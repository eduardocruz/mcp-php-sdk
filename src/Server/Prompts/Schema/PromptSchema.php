<?php

namespace ModelContextProtocol\Server\Prompts\Schema;

use ModelContextProtocol\Server\Schema\SchemaInterface;

/**
 * Represents the schema for a prompt
 */
class PromptSchema implements SchemaInterface
{
    private string $name;
    private array $properties;
    private array $required;
    private ?string $description;

    /**
     * Create a new prompt schema
     * 
     * @param string $name The name of the prompt
     * @param array $properties The properties of the prompt schema
     * @param array $required The required properties
     * @param string|null $description The prompt description
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
     * Get the prompt name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the prompt properties
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
     * Get the prompt description
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