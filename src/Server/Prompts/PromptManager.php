<?php

namespace ModelContextProtocol\Server\Prompts;

use ModelContextProtocol\Protocol\Notifications\NotificationManager;
use ModelContextProtocol\Server\Prompts\Schema\PromptSchema;
use ModelContextProtocol\Server\Tools\Schema\Validator;
use ModelContextProtocol\Server\Tools\Schema\ValidationException;

/**
 * Manages prompt registration, discovery, and execution
 */
class PromptManager
{
    /** @var Prompt[] */
    private array $prompts = [];
    private Validator $validator;
    
    /**
     * @var NotificationManager|null The notification manager for sending notifications
     */
    private ?NotificationManager $notificationManager = null;
    
    /**
     * Create a new prompt manager
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
     * Register a new prompt
     * 
     * @param string $name The prompt name
     * @param array|PromptSchema $schema The prompt schema
     * @param callable $handler The handler function
     * @return Prompt The registered prompt
     */
    public function register(
        string $name,
        array|PromptSchema $schema,
        callable $handler
    ): Prompt {
        // Convert array schema to PromptSchema object if needed
        if (is_array($schema)) {
            $schema = PromptSchema::fromArray($name, $schema);
        }
        
        $prompt = new Prompt($name, $schema, $handler);
        $this->prompts[$name] = $prompt;
        
        // Send prompts list changed notification if notification manager is available
        if ($this->notificationManager !== null) {
            $this->notificationManager->sendPromptsListChanged();
        }
        
        return $prompt;
    }
    
    /**
     * Get a prompt by name
     */
    public function getPrompt(string $name): ?Prompt
    {
        return $this->prompts[$name] ?? null;
    }
    
    /**
     * Get a prompt's schema
     */
    public function getSchema(string $name): ?PromptSchema
    {
        $prompt = $this->getPrompt($name);
        return $prompt ? $prompt->getSchema() : null;
    }
    
    /**
     * Execute a prompt with the given parameters
     * 
     * @param string $name The prompt to execute
     * @param array $params The parameters for the prompt
     * @return array The result of the prompt execution
     * @throws \InvalidArgumentException If the prompt is not found
     * @throws ValidationException If the parameters are invalid
     */
    public function execute(string $name, array $params): array
    {
        $prompt = $this->getPrompt($name);
        
        if ($prompt === null) {
            throw new \InvalidArgumentException("Prompt not found: $name");
        }
        
        // Validate parameters against schema
        try {
            $this->validator->validate($params, $prompt->getSchema());
        } catch (ValidationException $e) {
            throw new \InvalidArgumentException("Schema validation failed");
        }
        
        // Execute prompt
        return $prompt->execute($params);
    }
    
    /**
     * List all registered prompts
     * 
     * @return array The list of prompt metadata
     */
    public function list(): array
    {
        $result = [];
        
        foreach ($this->prompts as $prompt) {
            $result[] = $prompt->getMetadata();
        }
        
        return $result;
    }
    
    /**
     * Check if a prompt exists
     */
    public function exists(string $name): bool
    {
        return isset($this->prompts[$name]);
    }
    
    /**
     * Remove a prompt
     */
    public function remove(string $name): bool
    {
        if (isset($this->prompts[$name])) {
            unset($this->prompts[$name]);
            
            // Send prompts list changed notification if notification manager is available
            if ($this->notificationManager !== null) {
                $this->notificationManager->sendPromptsListChanged();
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all prompt names
     */
    public function getNames(): array
    {
        return array_keys($this->prompts);
    }
    
    /**
     * Clear all prompts
     */
    public function clear(): void
    {
        $this->prompts = [];
        
        // Send prompts list changed notification if notification manager is available
        if ($this->notificationManager !== null) {
            $this->notificationManager->sendPromptsListChanged();
        }
    }
}