<?php

namespace ModelContextProtocol\Server\Tools;

use ModelContextProtocol\Server\Tools\Schema\ToolSchema;

/**
 * Represents a tool that can be executed
 */
class Tool
{
    private string $name;
    private ToolSchema $schema;
    private \Closure $handler;

    /**
     * Create a new tool
     *
     * @param string $name The tool name
     * @param ToolSchema $schema The tool schema
     * @param callable $handler The handler function that executes the tool
     */
    public function __construct(
        string $name,
        ToolSchema $schema,
        callable $handler
    ) {
        $this->name = $name;
        $this->schema = $schema;
        $this->handler = \Closure::fromCallable($handler);
    }

    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool schema
     */
    public function getSchema(): ToolSchema
    {
        return $this->schema;
    }

    /**
     * Execute the tool with the given parameters
     *
     * @param array<string, mixed> $params The parameters to pass to the tool
     * @return mixed The result of the tool execution
     */
    public function execute(array $params): mixed
    {
        return ($this->handler)($params);
    }

    /**
     * Get the tool metadata for discovery
     */
    public function getMetadata(): array
    {
        $schemaArray = $this->schema->toArray();

        return [
            'name' => $this->name,
            'description' => $this->schema->getDescription() ?? '',
            'inputSchema' => [
                'type' => 'object',
                'properties' => $schemaArray['properties'] ?? [],
                'required' => $schemaArray['required'] ?? []
            ]
        ];
    }
}
