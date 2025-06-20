<?php

namespace ModelContextProtocol\Server\Tools;

use ModelContextProtocol\Protocol\Notifications\NotificationManager;
use ModelContextProtocol\Server\Tools\Schema\ToolSchema;
use ModelContextProtocol\Server\Tools\Schema\Validator;
use ModelContextProtocol\Server\Tools\Schema\ValidationException;

/**
 * Manages tool registration, discovery, and execution
 */
class ToolManager
{
    /** @var Tool[] */
    private array $tools = [];
    private Validator $validator;
    
    /**
     * @var NotificationManager|null The notification manager for sending notifications
     */
    private ?NotificationManager $notificationManager = null;
    
    /**
     * Create a new tool manager
     */
    public function __construct()
    {
        $this->validator = new Validator();
    }
    
    /**
     * Set the notification manager for sending automatic notifications.
     *
     * @param NotificationManager|null $notificationManager The notification manager
     * @return void
     */
    public function setNotificationManager(?NotificationManager $notificationManager): void
    {
        $this->notificationManager = $notificationManager;
    }
    
    /**
     * Register a new tool
     * 
     * @param string $name The tool name
     * @param array|ToolSchema $schema The tool schema
     * @param callable $handler The handler function
     * @return Tool The registered tool
     */
    public function register(
        string $name,
        array|ToolSchema $schema,
        callable $handler
    ): Tool {
        // Convert array schema to ToolSchema object if needed
        if (is_array($schema)) {
            $schema = ToolSchema::fromArray($name, $schema);
        }
        
        $tool = new Tool($name, $schema, $handler);
        $this->tools[$name] = $tool;
        
        // Send tools list changed notification if notification manager is available
        if ($this->notificationManager !== null) {
            $this->notificationManager->sendToolsListChanged();
        }
        
        return $tool;
    }
    
    /**
     * Get a tool by name
     */
    public function getTool(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }
    
    /**
     * Get a tool's schema
     */
    public function getSchema(string $name): ?ToolSchema
    {
        $tool = $this->getTool($name);
        return $tool ? $tool->getSchema() : null;
    }
    
    /**
     * Execute a tool with the given parameters
     * 
     * @param string $name The tool to execute
     * @param array $params The parameters for the tool
     * @return mixed The result of the tool execution
     * @throws \InvalidArgumentException If the tool is not found
     * @throws ValidationException If the parameters are invalid
     */
    public function execute(string $name, array $params): mixed
    {
        $tool = $this->getTool($name);
        
        if ($tool === null) {
            throw new \InvalidArgumentException("Tool not found: $name");
        }
        
        // Validate parameters against schema
        try {
            $this->validator->validate($params, $tool->getSchema());
        } catch (ValidationException $e) {
            throw new \InvalidArgumentException("Schema validation failed");
        }
        
        // Execute tool
        return $tool->execute($params);
    }
    
    /**
     * List all registered tools
     * 
     * @return array The list of tool metadata
     */
    public function list(): array
    {
        $result = [];
        
        foreach ($this->tools as $tool) {
            $result[] = $tool->getMetadata();
        }
        
        return ['tools' => $result];
    }
    
    /**
     * Check if a tool exists
     */
    public function exists(string $name): bool
    {
        return isset($this->tools[$name]);
    }
    
    /**
     * Get the number of registered tools
     */
    public function count(): int
    {
        return count($this->tools);
    }
    
    /**
     * Remove a tool
     */
    public function remove(string $name): bool
    {
        if (isset($this->tools[$name])) {
            unset($this->tools[$name]);
            
            // Send tools list changed notification if notification manager is available
            if ($this->notificationManager !== null) {
                $this->notificationManager->sendToolsListChanged();
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Check if a tool exists (alias for exists())
     */
    public function has(string $name): bool
    {
        return $this->exists($name);
    }

    /**
     * Unregister a tool (alias for remove())
     */
    public function unregister(string $name): bool
    {
        return $this->remove($name);
    }

    /**
     * Get a tool by name (alias for getTool())
     */
    public function get(string $name): ?Tool
    {
        return $this->getTool($name);
    }

    /**
     * Get all tools
     */
    public function getAll(): array
    {
        return $this->tools;
    }
}