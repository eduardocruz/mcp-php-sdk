<?php

namespace MCP\Server\Tools;

use MCP\Server\Tools\Schema\ToolSchema;

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
     */
    public function execute(array $params)
    {
        return ($this->handler)($params);
    }

    /**
     * Get the tool metadata for discovery
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->name,
            'schema' => $this->schema->toArray()
        ];
    }
}