<?php

namespace ModelContextProtocol\Server;

use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Protocol\Models\Implementation;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;
use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use ModelContextProtocol\Server\Tools\Schema\ToolSchema;
use ModelContextProtocol\Server\Tools\Tool;
use ModelContextProtocol\Server\Tools\ToolManager;
use ModelContextProtocol\Server\Tools\ToolResponse;
use ModelContextProtocol\Protocol\Resources\ResourceManager;
use ModelContextProtocol\Protocol\Resources\ResourceTemplate;
use ModelContextProtocol\Protocol\Resources\StaticResource;
use ModelContextProtocol\Protocol\Resources\DynamicResource;
use ModelContextProtocol\Server\Prompts\PromptManager;
use ModelContextProtocol\Server\Prompts\Prompt;
use ModelContextProtocol\Server\Prompts\Schema\PromptSchema;

/**
 * High-level MCP server that provides a simpler API for working with resources, tools, and prompts.
 * 
 * For advanced usage (like sending notifications or setting custom request handlers), use the underlying
 * Server instance available via the server property.
 */
class McpServer
{
    /**
     * @var Server The underlying Server instance, useful for advanced operations like sending notifications
     */
    private Server $server;
    
    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * @var array<string, mixed> Registered resources
     */
    private array $registeredResources = [];
    
    /**
     * @var array<string, mixed> Registered resource templates
     */
    private array $registeredResourceTemplates = [];
    
    /**
     * @var ToolManager The tool manager
     */
    private ToolManager $toolManager;
    
    /**
     * @var ResourceManager The resource manager
     */
    private ResourceManager $resourceManager;
    
    /**
     * @var PromptManager The prompt manager
     */
    private PromptManager $promptManager;
    
    /**
     * @var bool Whether tool handlers have been initialized
     */
    private bool $toolHandlersInitialized = false;
    
    /**
     * @var bool Whether resource handlers have been initialized
     */
    private bool $resourceHandlersInitialized = false;
    
    /**
     * @var bool Whether prompt handlers have been initialized
     */
    private bool $promptHandlersInitialized = false;
    
    /**
     * Constructor.
     *
     * @param string $name The server name
     * @param string $version The server version
     * @param array<string, mixed>|ServerCapabilities|null $capabilities Optional server capabilities
     * @param string|null $instructions Optional instructions describing how to use the server and its features
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(
        string $name,
        string $version,
        array|ServerCapabilities|null $capabilities = null,
        ?string $instructions = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new ConsoleLogger();
        
        if (is_array($capabilities)) {
            $capabilities = ServerCapabilities::fromArray($capabilities);
        }
        
        $this->server = new Server(
            new Implementation($name, $version),
            $capabilities,
            $instructions,
            $this->logger
        );
        
        // Initialize tool manager
        $this->toolManager = new ToolManager();
        
        // Initialize resource manager
        $this->resourceManager = new ResourceManager();
        
        // Initialize prompt manager
        $this->promptManager = new PromptManager();
        
        // Connect notification manager to managers
        $this->toolManager->setNotificationManager($this->server->getNotificationManager());
        $this->resourceManager->setNotificationManager($this->server->getNotificationManager());
        $this->promptManager->setNotificationManager($this->server->getNotificationManager());
        
        // Set up initialization callback
        $this->server->onInitialized(function () {
            $this->logger->info('Server fully initialized');
        });
    }
    
    /**
     * Get the underlying Server instance.
     *
     * @return Server The Server instance
     */
    public function getServer(): Server
    {
        return $this->server;
    }
    
    /**
     * Get the tool manager.
     *
     * @return ToolManager The tool manager
     */
    public function getToolManager(): ToolManager
    {
        return $this->toolManager;
    }
    
    /**
     * Get the resource manager.
     *
     * @return ResourceManager The resource manager
     */
    public function getResourceManager(): ResourceManager
    {
        return $this->resourceManager;
    }
    
    /**
     * Get the prompt manager.
     *
     * @return PromptManager The prompt manager
     */
    public function getPromptManager(): PromptManager
    {
        return $this->promptManager;
    }
    
    /**
     * Attaches to the given transport, starts it, and starts listening for messages.
     *
     * The server object assumes ownership of the Transport, replacing any callbacks
     * that have already been set, and expects that it is the only user of the
     * Transport instance going forward.
     *
     * @param TransportInterface $transport The transport to attach to
     * @return void
     */
    public function connect(TransportInterface $transport): void
    {
        $this->server->connect($transport);
    }
    
    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close(): void
    {
        $this->server->close();
    }
    
    /**
     * Checks if the server is connected to a transport.
     *
     * @return bool True if the server is connected
     */
    public function isConnected(): bool
    {
        return $this->server->isConnected();
    }
    
    /**
     * Sends a resource list changed notification to the client, if connected.
     *
     * @return void
     */
    public function sendResourceListChanged(): void
    {
        if (!$this->isConnected()) {
            return;
        }
        
        $this->server->getNotificationManager()->sendResourceListChanged();
    }
    
    /**
     * Send a resource updated notification for a specific resource.
     *
     * @param string $uri The URI of the updated resource
     * @param array<string, mixed> $content The updated content (optional)
     * @return void
     */
    public function sendResourceUpdated(string $uri, array $content = []): void
    {
        if (!$this->isConnected()) {
            return;
        }
        
        $this->server->getNotificationManager()->sendResourceUpdated($uri, $content);
    }
    
    /**
     * Sends a tool list changed notification to the client, if connected.
     *
     * @return void
     */
    public function sendToolListChanged(): void
    {
        if (!$this->isConnected()) {
            return;
        }
        
        $this->server->getNotificationManager()->sendToolsListChanged();
    }
    
    /**
     * Sends a prompt list changed notification to the client, if connected.
     *
     * @return void
     */
    public function sendPromptListChanged(): void
    {
        if (!$this->isConnected()) {
            return;
        }
        
        $this->server->getNotificationManager()->sendPromptsListChanged();
    }
    
    /**
     * Send a general message notification to the client.
     *
     * @param string $level The log level (info, warning, error, etc.)
     * @param string $message The message content
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    public function sendMessage(string $level, string $message, array $data = []): void
    {
        if (!$this->isConnected()) {
            return;
        }
        
        $this->server->getNotificationManager()->sendMessage($level, $message, $data);
    }
    
    /**
     * Subscribe to updates for a specific resource.
     *
     * @param string $uri The resource URI to subscribe to
     * @param array<string, mixed> $options Subscription options
     * @return void
     */
    public function subscribeToResource(string $uri, array $options = []): void
    {
        $this->server->getNotificationManager()->subscribeToResource($uri, $options);
    }
    
    /**
     * Unsubscribe from updates for a specific resource.
     *
     * @param string $uri The resource URI to unsubscribe from
     * @return void
     */
    public function unsubscribeFromResource(string $uri): void
    {
        $this->server->getNotificationManager()->unsubscribeFromResource($uri);
    }
    
    /**
     * Get all current resource subscriptions.
     *
     * @return array<string, array<string, mixed>> The resource subscriptions
     */
    public function getResourceSubscriptions(): array
    {
        return $this->server->getNotificationManager()->getResourceSubscriptions();
    }
    
    /**
     * Setup tool request handlers if not already done.
     *
     * @return void
     */
    private function setToolRequestHandlers(): void
    {
        if ($this->toolHandlersInitialized) {
            return;
        }
        
        // Register tool capabilities
        $this->server->registerCapabilities(new ServerCapabilities(tools: [
            'listChanged' => true
        ]));
        
        // Register tool handlers
        $this->server->setRequestHandler('tools/list', [$this, 'handleToolsList']);
        $this->server->setRequestHandler('tools/call', [$this, 'handleToolsCall']);
        
        $this->toolHandlersInitialized = true;
    }
    
    /**
     * Setup resource request handlers if not already done.
     *
     * @return void
     */
    private function setResourceRequestHandlers(): void
    {
        if ($this->resourceHandlersInitialized) {
            return;
        }
        
        // Register resource capabilities
        $this->server->registerCapabilities(new ServerCapabilities(resources: [
            'listChanged' => true
        ]));
        
        // Register resource handlers
        $this->server->setRequestHandler('resources/list', [$this, 'handleResourcesList']);
        $this->server->setRequestHandler('resources/templates/list', [$this, 'handleResourceTemplatesList']);
        $this->server->setRequestHandler('resources/read', [$this, 'handleResourceRead']);
        
        $this->resourceHandlersInitialized = true;
    }
    
    /**
     * Setup prompt request handlers if not already done.
     *
     * @return void
     */
    private function setPromptRequestHandlers(): void
    {
        if ($this->promptHandlersInitialized) {
            return;
        }
        
        // Register prompt capabilities
        $this->server->registerCapabilities(new ServerCapabilities(prompts: [
            'listChanged' => true
        ]));
        
        // Register prompt handlers
        $this->server->setRequestHandler('prompts/list', [$this, 'handlePromptsList']);
        $this->server->setRequestHandler('prompts/get', [$this, 'handlePromptGet']);
        
        $this->promptHandlersInitialized = true;
    }
    
    /**
     * Handle the tools/list request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handleToolsList($request): array
    {
        $tools = $this->toolManager->list();
        return ['tools' => $tools];
    }
    
    /**
     * Handle the tools/call request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handleToolsCall($request): array
    {
        try {
            $name = $request->params['name'] ?? null;
            $params = $request->params['params'] ?? [];
            
            if ($name === null) {
                return [
                    'error' => [
                        'message' => 'Missing tool name',
                        'code' => 'MISSING_TOOL_NAME'
                    ]
                ];
            }
            
            if (!$this->toolManager->exists($name)) {
                return [
                    'error' => [
                        'message' => "Tool not found: $name",
                        'code' => 'TOOL_NOT_FOUND'
                    ]
                ];
            }
            
            $result = $this->toolManager->execute($name, $params);
            
            // If the result is already a ToolResponse, return its content
            if ($result instanceof ToolResponse) {
                return $result->toArray();
            }
            
            // Otherwise, wrap the result in a text response
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => (string)$result
                    ]
                ]
            ];
        } catch (\ModelContextProtocol\Server\Tools\Schema\ValidationException $e) {
            return [
                'error' => [
                    'message' => 'Parameter validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'details' => $e->getErrors()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'EXECUTION_ERROR'
                ]
            ];
        }
    }
    
    /**
     * Handle the resources/list request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handleResourcesList($request): array
    {
        $resources = $this->resourceManager->list();
        return ['resources' => $resources];
    }
    
    /**
     * Handle the resources/templates/list request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handleResourceTemplatesList($request): array
    {
        // Get all resources and filter for templates
        $resources = $this->resourceManager->list();
        $templates = [];
        
        foreach ($resources as $resource) {
            if (isset($resource['template'])) {
                $templates[] = [
                    'name' => $resource['name'],
                    'template' => $resource['template'],
                    'listOptions' => $resource['listOptions'] ?? null
                ];
            }
        }
        
        return ['resourceTemplates' => $templates];
    }
    
    /**
     * Handle the resources/read request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handleResourceRead($request): array
    {
        $uri = $request->params['uri'] ?? null;
        
        if ($uri === null) {
            return [
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Missing required parameter: uri'
                ]
            ];
        }
        
        // Resolve the URI to a resource
        $resolved = $this->resourceManager->resolve($uri);
        
        if ($resolved === null) {
            return [
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => "Resource not found for URI: {$uri}"
                ]
            ];
        }
        
        try {
            // Handle the resource request
            $resource = $resolved['resource'];
            $params = $resolved['params'];
            $result = $resource->handle($uri, $params);
            
            // Format the response according to MCP spec
            $contents = [];
            if (isset($result['content'])) {
                foreach ($result['content'] as $item) {
                    $content = [
                        'uri' => $uri
                    ];
                    
                    // Add content based on type
                    if (isset($item['type']) && isset($item['text'])) {
                        $content['mimeType'] = $item['type'];
                        $content['text'] = $item['text'];
                    } elseif (isset($item['type']) && isset($item['data'])) {
                        $content['mimeType'] = $item['type'];
                        $content['blob'] = $item['data'];
                    } elseif (isset($item['text'])) {
                        $content['mimeType'] = 'text/plain';
                        $content['text'] = $item['text'];
                    } else {
                        // Default to JSON for complex data
                        $content['mimeType'] = 'application/json';
                        $content['text'] = json_encode($item);
                    }
                    
                    $contents[] = $content;
                }
            } else {
                // If no content array, treat the whole result as content
                $contents[] = [
                    'uri' => $uri,
                    'mimeType' => 'application/json',
                    'text' => json_encode($result)
                ];
            }
            
            return ['contents' => $contents];
            
        } catch (\Exception $e) {
            return [
                'error' => [
                    'code' => 'RESOURCE_ERROR',
                    'message' => "Error reading resource: {$e->getMessage()}"
                ]
            ];
        }
    }
    
    /**
     * Handle the prompts/list request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handlePromptsList($request): array
    {
        $prompts = $this->promptManager->list();
        return ['prompts' => $prompts];
    }
    
    /**
     * Handle the prompts/get request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handlePromptGet($request): array
    {
        $name = $request->params['name'] ?? null;
        $params = $request->params['params'] ?? [];
        
        if ($name === null) {
            return [
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Missing required parameter: name'
                ]
            ];
        }
        
        if (!$this->promptManager->exists($name)) {
            return [
                'error' => [
                    'code' => 'PROMPT_NOT_FOUND',
                    'message' => "Prompt not found: {$name}"
                ]
            ];
        }
        
        try {
            $result = $this->promptManager->execute($name, $params);
            return $result;
        } catch (\ModelContextProtocol\Server\Tools\Schema\ValidationException $e) {
            return [
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Parameter validation failed',
                    'details' => $e->getErrors()
                ]
            ];
        } catch (\Exception $e) {
            return [
                'error' => [
                    'code' => 'PROMPT_ERROR',
                    'message' => "Error executing prompt: {$e->getMessage()}"
                ]
            ];
        }
    }
    
    /**
     * Register capabilities for resource support.
     *
     * @param bool $listChanged Whether the server supports notifying about resource list changes
     * @param bool $subscribe Whether the server supports clients subscribing to resource updates
     * @return void
     */
    public function registerResourceCapabilities(bool $listChanged = true, bool $subscribe = false): void
    {
        $this->server->registerCapabilities(new ServerCapabilities(resources: [
            'listChanged' => $listChanged,
            'subscribe' => $subscribe
        ]));
        
        $this->setResourceRequestHandlers();
    }
    
    /**
     * Register capabilities for tool support.
     *
     * @param bool $listChanged Whether the server supports notifying about tool list changes
     * @return void
     */
    public function registerToolCapabilities(bool $listChanged = true): void
    {
        $this->server->registerCapabilities(new ServerCapabilities(tools: [
            'listChanged' => $listChanged
        ]));
        
        $this->setToolRequestHandlers();
    }
    
    /**
     * Register capabilities for prompt support.
     *
     * @param bool $listChanged Whether the server supports notifying about prompt list changes
     * @return void
     */
    public function registerPromptCapabilities(bool $listChanged = true): void
    {
        $this->server->registerCapabilities(new ServerCapabilities(prompts: [
            'listChanged' => $listChanged
        ]));
        
        $this->setPromptRequestHandlers();
    }
    
    /**
     * Register capabilities for logging support.
     *
     * @return void
     */
    public function registerLoggingCapabilities(): void
    {
        $this->server->registerCapabilities(new ServerCapabilities(logging: []));
    }
    
    /**
     * Register a new tool.
     *
     * @param string $name The name of the tool
     * @param array|ToolSchema $schema The schema for the tool
     * @param callable $handler The handler function for the tool
     * @return Tool The registered tool
     */
    public function registerTool(string $name, array|ToolSchema $schema, callable $handler): Tool
    {
        // Ensure tool request handlers are set up
        $this->setToolRequestHandlers();
        
        // Register the tool with the tool manager
        $tool = $this->toolManager->register($name, $schema, $handler);
        
        // Notify clients if connected
        $this->sendToolListChanged();
        
        return $tool;
    }
    
    /**
     * Unregister a tool.
     *
     * @param string $name The name of the tool to unregister
     * @return bool True if the tool was unregistered
     */
    public function unregisterTool(string $name): bool
    {
        $result = $this->toolManager->remove($name);
        
        if ($result) {
            $this->sendToolListChanged();
        }
        
        return $result;
    }
    
    /**
     * Register a static resource.
     *
     * @param string $name The name of the resource
     * @param string $uri The URI of the resource
     * @param array<string, mixed> $content The resource content
     * @param array<string, mixed>|null $listOptions Options for listing this resource
     * @return StaticResource The registered resource
     */
    public function registerResource(
        string $name,
        string $uri,
        array $content,
        ?array $listOptions = null
    ): StaticResource {
        // Ensure resource request handlers are set up
        $this->setResourceRequestHandlers();
        
        // Register the resource with the resource manager
        $resource = $this->resourceManager->registerStatic($name, $uri, $content, $listOptions);
        
        // Notify clients if connected
        $this->sendResourceListChanged();
        
        return $resource;
    }
    
    /**
     * Register a dynamic resource with a template.
     *
     * @param string $name The name of the resource
     * @param string|ResourceTemplate $template The resource template
     * @param callable $handler The handler function for the resource
     * @return DynamicResource The registered resource
     */
    public function registerResourceTemplate(
        string $name,
        string|ResourceTemplate $template,
        callable $handler
    ): DynamicResource {
        // Ensure resource request handlers are set up
        $this->setResourceRequestHandlers();
        
        // Register the resource with the resource manager
        $resource = $this->resourceManager->registerDynamic($name, $template, $handler);
        
        // Notify clients if connected
        $this->sendResourceListChanged();
        
        return $resource;
    }
    
    /**
     * Unregister a resource.
     *
     * @param string $name The name of the resource to unregister
     * @return bool True if the resource was unregistered
     */
    public function unregisterResource(string $name): bool
    {
        $resource = $this->resourceManager->getResource($name);
        if ($resource === null) {
            return false;
        }
        
        $this->resourceManager->unregister($name);
        $this->sendResourceListChanged();
        
        return true;
    }
    
    /**
     * Register a new prompt.
     *
     * @param string $name The name of the prompt
     * @param array|PromptSchema $schema The schema for the prompt
     * @param callable $handler The handler function for the prompt
     * @return Prompt The registered prompt
     */
    public function registerPrompt(string $name, array|PromptSchema $schema, callable $handler): Prompt
    {
        // Ensure prompt request handlers are set up
        $this->setPromptRequestHandlers();
        
        // Register the prompt with the prompt manager
        $prompt = $this->promptManager->register($name, $schema, $handler);
        
        // Notify clients if connected
        $this->sendPromptListChanged();
        
        return $prompt;
    }
    
    /**
     * Unregister a prompt.
     *
     * @param string $name The name of the prompt to unregister
     * @return bool True if the prompt was unregistered
     */
    public function unregisterPrompt(string $name): bool
    {
        $result = $this->promptManager->remove($name);
        
        if ($result) {
            $this->sendPromptListChanged();
        }
        
        return $result;
    }
}