<?php

namespace ModelContextProtocol\Server\Prompts;

use ModelContextProtocol\Server\Prompts\Schema\PromptSchema;

/**
 * Represents a prompt that can be executed
 */
class Prompt
{
    private string $name;
    private PromptSchema $schema;
    private \Closure $handler;

    /**
     * Create a new prompt
     *
     * @param string $name The prompt name
     * @param PromptSchema $schema The prompt schema
     * @param callable $handler The handler function that executes the prompt
     */
    public function __construct(
        string $name,
        PromptSchema $schema,
        callable $handler
    ) {
        $this->name = $name;
        $this->schema = $schema;
        $this->handler = \Closure::fromCallable($handler);
    }

    /**
     * Get the prompt name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the prompt schema
     */
    public function getSchema(): PromptSchema
    {
        return $this->schema;
    }

    /**
     * Execute the prompt with the given parameters
     */
    public function execute(array $params): array
    {
        return ($this->handler)($params);
    }

    /**
     * Get the prompt metadata for discovery
     */
    public function getMetadata(): array
    {
        $schemaArray = $this->schema->toArray();

        return [
            'name' => $this->name,
            'description' => $this->schema->getDescription() ?? '',
            'arguments' => [
                'type' => 'object',
                'properties' => $schemaArray['properties'] ?? [],
                'required' => $schemaArray['required'] ?? []
            ]
        ];
    }
}
