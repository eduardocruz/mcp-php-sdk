<?php

namespace ModelContextProtocol\Server;

use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Protocol\Models\Implementation;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;
use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use MCP\Server\Tools\Schema\ToolSchema;
use MCP\Server\Tools\Tool;
use MCP\Server\Tools\ToolManager;
use MCP\Server\Tools\ToolResponse;

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
     * @var array<string, mixed> Registered prompts
     */
    private array $registeredPrompts = [];
    
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
        
        $notification = new Notification('notifications/resources/list_changed');
        $this->server->sendNotification($notification);
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
        
        $notification = new Notification('notifications/tools/list_changed');
        $this->server->sendNotification($notification);
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
        
        $notification = new Notification('notifications/prompts/list_changed');
        $this->server->sendNotification($notification);
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
        } catch (\MCP\Server\Tools\Schema\ValidationException $e) {
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
        $resources = [];
        
        // In a real implementation, we would return registered resources
        // This is a placeholder implementation
        
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
        $templates = [];
        
        // In a real implementation, we would return registered resource templates
        // This is a placeholder implementation
        
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
        // In a real implementation, we would return the resource content
        // This is a placeholder implementation
        
        return [
            'contents' => [
                [
                    'uri' => $request->params['uri'] ?? 'unknown://uri',
                    'text' => 'Resource content would go here'
                ]
            ]
        ];
    }
    
    /**
     * Handle the prompts/list request.
     *
     * @param mixed $request The request object
     * @return array<string, mixed> The response data
     */
    public function handlePromptsList($request): array
    {
        $prompts = [];
        
        // In a real implementation, we would return registered prompts
        // This is a placeholder implementation
        
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
        // In a real implementation, we would return the prompt content
        // This is a placeholder implementation
        
        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Prompt content would go here'
                    ]
                ]
            ]
        ];
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
}