<?php

namespace ModelContextProtocol\Server;

use ModelContextProtocol\Protocol\Constants;
use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Protocol\Messages\ErrorData;
use ModelContextProtocol\Protocol\Errors\ErrorResponseBuilder;
use ModelContextProtocol\Utilities\HealthMonitor;
use ModelContextProtocol\Utilities\Cancellation\CancellationManager;
use ModelContextProtocol\Utilities\Cancellation\CancellationToken;
use ModelContextProtocol\Protocol\Models\ClientCapabilities;
use ModelContextProtocol\Protocol\Models\Implementation;
use ModelContextProtocol\Protocol\Models\InitializeParams;
use ModelContextProtocol\Protocol\Models\InitializeResult;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;
use ModelContextProtocol\Protocol\Notifications\NotificationManager;
use ModelContextProtocol\Transport\Exception\ConnectionException;
use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use Throwable;

/**
 * An MCP server on top of a pluggable transport.
 * 
 * This server will automatically respond to the initialization flow as initiated from the client.
 */
class Server
{
    /**
     * @var TransportInterface|null The transport interface
     */
    private ?TransportInterface $transport = null;
    
    /**
     * @var ClientCapabilities|null The client's capabilities, available after initialization
     */
    private ?ClientCapabilities $clientCapabilities = null;
    
    /**
     * @var Implementation|null The client's version information, available after initialization
     */
    private ?Implementation $clientVersion = null;
    
    /**
     * @var ServerCapabilities The server's capabilities
     */
    private ServerCapabilities $capabilities;
    
    /**
     * @var array<string, callable> Request handlers registered by method name
     */
    private array $requestHandlers = [];
    
    /**
     * @var array<string, callable> Notification handlers registered by method name
     */
    private array $notificationHandlers = [];
    
    /**
     * @var bool Whether the server has been initialized
     */
    private bool $initialized = false;
    
    /**
     * @var callable|null Callback for when initialization has fully completed (i.e., the client has sent an `initialized` notification)
     */
    private $initializedHandler = null;
    
    /**
     * @var HealthMonitor|null Optional health monitor for connection monitoring
     */
    private ?HealthMonitor $healthMonitor = null;
    
    /**
     * @var CancellationManager The cancellation manager for request cancellation
     */
    private CancellationManager $cancellationManager;
    
    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * @var NotificationManager The notification manager
     */
    private NotificationManager $notificationManager;
    
    /**
     * Constructor.
     *
     * @param Implementation $serverInfo Information about the server implementation
     * @param ServerCapabilities|null $capabilities The server's capabilities (optional)
     * @param string|null $instructions Optional instructions describing how to use the server and its features
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(
        private Implementation $serverInfo,
        ?ServerCapabilities $capabilities = null,
        private ?string $instructions = null,
        ?LoggerInterface $logger = null
    ) {
        $this->capabilities = $capabilities ?? new ServerCapabilities();
        $this->logger = $logger ?? new ConsoleLogger();
        $this->notificationManager = new NotificationManager($this->logger);
        $this->cancellationManager = new CancellationManager($this->logger);
        
        // Register built-in handlers
        $this->setRequestHandler('initialize', [$this, 'handleInitialize']);
        $this->setRequestHandler('ping', [$this, 'handlePing']);
        $this->setRequestHandler('resources/subscribe', [$this, 'handleResourceSubscribe']);
        $this->setRequestHandler('resources/unsubscribe', [$this, 'handleResourceUnsubscribe']);
        $this->setNotificationHandler('notifications/initialized', [$this, 'handleInitialized']);
        $this->setNotificationHandler('notifications/cancelled', [$this, 'handleCancelled']);
    }
    
    /**
     * Get the transport instance.
     *
     * @return TransportInterface|null The transport instance, or null if not connected
     */
    public function getTransport(): ?TransportInterface
    {
        return $this->transport;
    }
    
    /**
     * Check if the server is connected to a transport.
     *
     * @return bool True if the server is connected to a transport, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->transport !== null;
    }
    
    /**
     * Attaches to the given transport, starts it, and starts listening for messages.
     *
     * The Server object assumes ownership of the Transport, replacing any callbacks
     * that have already been set, and expects that it is the only user of the
     * Transport instance going forward.
     *
     * @param TransportInterface $transport The transport to attach to
     * @return void
     * @throws ConnectionException If the transport is already in use or cannot be started
     */
    public function connect(TransportInterface $transport): void
    {
        if ($this->transport !== null) {
            throw new ConnectionException('Server is already connected to a transport');
        }
        
        $this->transport = $transport;
        
        // Set up transport event handlers
        $transport->onMessage([$this, 'handleMessage']);
        $transport->onError([$this, 'handleError']);
        $transport->onClose([$this, 'handleClose']);
        
        // Connect notification manager to transport
        $this->notificationManager->setTransport($transport);
        
        // Start the transport
        $transport->start();
        
        $this->logger->info('Server connected to transport', [
            'serverName' => $this->serverInfo->name,
            'serverVersion' => $this->serverInfo->version,
            'sessionId' => $transport->getSessionId()
        ]);
    }
    
    /**
     * Close the connection to the transport.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->transport !== null) {
            $this->logger->info('Closing server connection');
            
            // Disconnect notification manager
            $this->notificationManager->setTransport(null);
            $this->notificationManager->clearQueue();
            
            $this->transport->close();
            $this->transport = null;
        }
    }
    
    /**
     * Handle an incoming JSON-RPC message.
     *
     * @param JsonRpcMessage $message The message to handle
     * @return void
     */
    public function handleMessage(JsonRpcMessage $message): void
    {
        try {
            if ($message instanceof Request) {
                $this->handleRequest($message);
            } elseif ($message instanceof Notification) {
                $this->handleNotification($message);
            } else {
                $this->logger->warning('Received unsupported message type', [
                    'messageType' => get_class($message)
                ]);
            }
        } catch (Throwable $e) {
            $this->logger->error('Error handling message', [
                'error' => $e->getMessage(),
                'messageType' => get_class($message)
            ]);
        }
    }
    
    /**
     * Handle a transport error.
     *
     * @param Throwable $error The error that occurred
     * @return void
     */
    public function handleError(Throwable $error): void
    {
        $this->logger->error('Transport error', [
            'error' => $error->getMessage(),
            'exception' => get_class($error)
        ]);
    }
    
    /**
     * Handle transport connection close.
     *
     * @return void
     */
    public function handleClose(): void
    {
        $this->logger->info('Transport connection closed');
        $this->transport = null;
    }
    
    /**
     * Handle a request message.
     *
     * @param Request $request The request to handle
     * @return void
     */
    private function handleRequest(Request $request): void
    {
        $method = $request->method;
        
        $this->logger->debug('Handling request', [
            'method' => $method,
            'id' => $request->id
        ]);
        
        if (!$this->initialized && $method !== 'initialize') {
            $response = ErrorResponseBuilder::createErrorResponse(
                $request,
                Constants::ERROR_CODE_INVALID_REQUEST,
                'Server not initialized'
            );
            $this->sendRawResponse($response);
            return;
        }
        
        if (isset($this->requestHandlers[$method])) {
            // Register request for cancellation tracking if it has an ID
            $cancellationToken = null;
            if (is_string($request->id)) {
                $cancellationToken = $this->cancellationManager->registerRequest($request->id, [
                    'method' => $method,
                    'startedAt' => microtime(true)
                ]);
            }
            
            try {
                $handler = $this->requestHandlers[$method];
                
                // Call handler with cancellation token if supported
                $result = $this->callHandlerWithCancellation($handler, $request, $cancellationToken);
                
                if (is_array($result)) {
                    $this->sendResponse($request, $result);
                } elseif ($result instanceof Response) {
                    $this->sendRawResponse($result);
                } else {
                    $response = ErrorResponseBuilder::createErrorResponse(
                        $request,
                        Constants::ERROR_CODE_INTERNAL_ERROR,
                        'Handler returned invalid result'
                    );
                    $this->sendRawResponse($response);
                }
            } catch (Throwable $e) {
                $this->logger->error('Error in request handler', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e)
                ]);
                
                $response = ErrorResponseBuilder::fromException($request, $e);
                $this->sendRawResponse($response);
            } finally {
                // Unregister the request when done
                if (is_string($request->id)) {
                    $this->cancellationManager->unregisterRequest($request->id);
                }
            }
        } else {
            $this->logger->warning('Method not found', ['method' => $method]);
            $response = ErrorResponseBuilder::createErrorResponse(
                $request,
                Constants::ERROR_CODE_METHOD_NOT_FOUND,
                'Method not found'
            );
            $this->sendRawResponse($response);
        }
    }
    
    /**
     * Handle a notification message.
     *
     * @param Notification $notification The notification to handle
     * @return void
     */
    private function handleNotification(Notification $notification): void
    {
        $method = $notification->method;
        
        $this->logger->debug('Handling notification', [
            'method' => $method
        ]);
        
        if (!$this->initialized && $method !== 'notifications/cancelled') {
            $this->logger->warning('Received notification before initialization', [
                'method' => $method
            ]);
            return;
        }
        
        if (isset($this->notificationHandlers[$method])) {
            try {
                $handler = $this->notificationHandlers[$method];
                $handler($notification);
            } catch (Throwable $e) {
                $this->logger->error('Error in notification handler', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e)
                ]);
            }
        } else {
            $this->logger->debug('No handler for notification', ['method' => $method]);
        }
    }
    
    /**
     * Handler for the initialize request.
     *
     * @param Request $request The initialize request
     * @return array<string, mixed> The initialization result
     */
    public function handleInitialize(Request $request): array
    {
        if ($this->initialized) {
            throw new \RuntimeException('Server already initialized');
        }
        
        $params = InitializeParams::fromArray($request->params ?? []);
        $requestedVersion = $params->protocolVersion;
        
        $this->clientCapabilities = $params->capabilities;
        $this->clientVersion = $params->clientInfo;
        
        $this->logger->info('Server initialized', [
            'clientName' => $params->clientInfo->name,
            'clientVersion' => $params->clientInfo->version,
            'requestedVersion' => $requestedVersion
        ]);
        
        // Choose protocol version
        $protocolVersion = in_array($requestedVersion, Constants::SUPPORTED_PROTOCOL_VERSIONS)
            ? $requestedVersion
            : Constants::LATEST_PROTOCOL_VERSION;
        
        $this->initialized = true;
        
        // Prepare result
        $result = new InitializeResult(
            $protocolVersion,
            $this->capabilities,
            $this->serverInfo,
            $this->instructions
        );
        
        return $result->toArray();
    }
    
    /**
     * Handler for the 'initialized' notification.
     *
     * @param Notification $notification The initialized notification
     * @return void
     */
    public function handleInitialized(Notification $notification): void
    {
        $this->logger->info('Client sent initialized notification');
        
        if ($this->initializedHandler !== null) {
            ($this->initializedHandler)();
        }
    }
    
    /**
     * Handler for the ping request.
     *
     * @param Request $request The ping request
     * @return array<string, mixed> Empty result
     */
    public function handlePing(Request $request): array
    {
        // Notify health monitor if available
        if ($this->healthMonitor !== null && is_string($request->id)) {
            $this->healthMonitor->handlePingResponse($request->id);
        }
        
        return [];
    }
    
    /**
     * Handler for the 'cancelled' notification.
     *
     * @param Notification $notification The cancelled notification
     * @return void
     */
    public function handleCancelled(Notification $notification): void
    {
        $requestId = $notification->params['requestId'] ?? null;
        
        if ($requestId === null) {
            $this->logger->warning('Received cancelled notification without requestId');
            return;
        }
        
        $reason = $notification->params['reason'] ?? null;
        
        $this->logger->debug('Request cancelled by client', [
            'requestId' => $requestId,
            'reason' => $reason
        ]);
        
        // Cancel the request using the cancellation manager
        $cancelled = $this->cancellationManager->cancelRequest($requestId, $reason);
        
        if (!$cancelled) {
            $this->logger->debug('Cancellation requested for unknown or already completed request', [
                'requestId' => $requestId
            ]);
        }
    }
    
    /**
     * Handler for the 'resources/subscribe' request.
     *
     * @param Request $request The subscribe request
     * @return array<string, mixed> Empty result
     */
    public function handleResourceSubscribe(Request $request): array
    {
        $uri = $request->params['uri'] ?? null;
        
        if ($uri === null) {
            throw new \InvalidArgumentException('Missing required parameter: uri');
        }
        
        $options = $request->params['options'] ?? [];
        $this->notificationManager->subscribeToResource($uri, $options);
        
        $this->logger->info('Resource subscription added', [
            'uri' => $uri,
            'options' => $options
        ]);
        
        return [];
    }
    
    /**
     * Handler for the 'resources/unsubscribe' request.
     *
     * @param Request $request The unsubscribe request
     * @return array<string, mixed> Empty result
     */
    public function handleResourceUnsubscribe(Request $request): array
    {
        $uri = $request->params['uri'] ?? null;
        
        if ($uri === null) {
            throw new \InvalidArgumentException('Missing required parameter: uri');
        }
        
        $this->notificationManager->unsubscribeFromResource($uri);
        
        $this->logger->info('Resource subscription removed', [
            'uri' => $uri
        ]);
        
        return [];
    }
    
    /**
     * Send a response to a request.
     *
     * @param Request $request The request to respond to
     * @param array<string, mixed> $result The result data
     * @return void
     */
    private function sendResponse(Request $request, array $result): void
    {
        if ($this->transport === null) {
            $this->logger->warning('Cannot send response: not connected to transport');
            return;
        }
        
        $response = new Response($request->id, $result);
        $this->sendRawResponse($response);
    }
    
    /**
     * Send a raw response.
     *
     * @param Response $response The response to send
     * @return void
     */
    private function sendRawResponse(Response $response): void
    {
        if ($this->transport === null) {
            $this->logger->warning('Cannot send response: not connected to transport');
            return;
        }
        
        try {
            $this->transport->send($response);
        } catch (Throwable $e) {
            $this->logger->error('Error sending response', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
        }
    }
    
    /**
     * Send an error response to a request.
     *
     * @param Request $request The request to respond to
     * @param int $code The error code
     * @param string $message The error message
     * @param mixed $data Additional error data
     * @return void
     */
    private function sendErrorResponse(Request $request, int $code, string $message, mixed $data = null): void
    {
        if ($this->transport === null) {
            $this->logger->warning('Cannot send error response: not connected to transport');
            return;
        }
        
        $error = new ErrorData($code, $message, $data);
        $response = new Response($request->id, null, $error);
        
        try {
            $this->transport->send($response);
        } catch (Throwable $e) {
            $this->logger->error('Error sending error response', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
        }
    }
    
    /**
     * Send a notification to the client.
     *
     * @param Notification $notification The notification to send
     * @return void
     */
    public function sendNotification(Notification $notification): void
    {
        if ($this->transport === null) {
            $this->logger->warning('Cannot send notification: not connected to transport');
            return;
        }
        
        try {
            $this->transport->send($notification);
        } catch (Throwable $e) {
            $this->logger->error('Error sending notification', [
                'error' => $e->getMessage(),
                'exception' => get_class($e)
            ]);
        }
    }
    
    /**
     * Get the notification manager.
     *
     * @return NotificationManager The notification manager
     */
    public function getNotificationManager(): NotificationManager
    {
        return $this->notificationManager;
    }
    
    /**
     * Set a handler for a specific request method.
     *
     * @param string $method The method name
     * @param callable $handler The handler function
     * @return void
     */
    public function setRequestHandler(string $method, callable $handler): void
    {
        $this->requestHandlers[$method] = $handler;
    }
    
    /**
     * Remove a request handler.
     *
     * @param string $method The method name
     * @return void
     */
    public function removeRequestHandler(string $method): void
    {
        unset($this->requestHandlers[$method]);
    }
    
    /**
     * Check if a request handler exists for a method.
     *
     * @param string $method The method name
     * @return bool True if a handler exists
     */
    public function hasRequestHandler(string $method): bool
    {
        return isset($this->requestHandlers[$method]);
    }
    
    /**
     * Set a handler for a specific notification method.
     *
     * @param string $method The method name
     * @param callable $handler The handler function
     * @return void
     */
    public function setNotificationHandler(string $method, callable $handler): void
    {
        $this->notificationHandlers[$method] = $handler;
    }
    
    /**
     * Remove a notification handler.
     *
     * @param string $method The method name
     * @return void
     */
    public function removeNotificationHandler(string $method): void
    {
        unset($this->notificationHandlers[$method]);
    }
    
    /**
     * Check if a notification handler exists for a method.
     *
     * @param string $method The method name
     * @return bool True if a handler exists
     */
    public function hasNotificationHandler(string $method): bool
    {
        return isset($this->notificationHandlers[$method]);
    }
    
    /**
     * Set the callback for when initialization is fully complete.
     *
     * @param callable $handler The handler function
     * @return void
     */
    public function onInitialized(callable $handler): void
    {
        $this->initializedHandler = $handler;
    }
    
    /**
     * Register additional server capabilities.
     *
     * @param ServerCapabilities $capabilities The capabilities to register
     * @return void
     * @throws \RuntimeException If the server is already initialized or connected to a transport
     */
    public function registerCapabilities(ServerCapabilities $capabilities): void
    {
        if ($this->initialized) {
            throw new \RuntimeException('Cannot register capabilities after initialization');
        }
        
        if ($this->transport !== null) {
            throw new \RuntimeException('Cannot register capabilities after connecting to transport');
        }
        
        $this->capabilities = $this->capabilities->merge($capabilities);
    }
    
    /**
     * Get the client's capabilities, available after initialization.
     *
     * @return ClientCapabilities|null The client's capabilities, or null if not initialized
     */
    public function getClientCapabilities(): ?ClientCapabilities
    {
        return $this->clientCapabilities;
    }
    
    /**
     * Get the client's version information, available after initialization.
     *
     * @return Implementation|null The client's version information, or null if not initialized
     */
    public function getClientVersion(): ?Implementation
    {
        return $this->clientVersion;
    }
    
    /**
     * Set the health monitor for connection monitoring.
     *
     * @param HealthMonitor|null $healthMonitor The health monitor instance
     * @return void
     */
    public function setHealthMonitor(?HealthMonitor $healthMonitor): void
    {
        $this->healthMonitor = $healthMonitor;
    }
    
    /**
     * Get the cancellation manager.
     *
     * @return CancellationManager The cancellation manager instance
     */
    public function getCancellationManager(): CancellationManager
    {
        return $this->cancellationManager;
    }
    
    /**
     * Call a handler with optional cancellation token support.
     *
     * @param callable $handler The handler to call
     * @param Request $request The request
     * @param CancellationToken|null $cancellationToken Optional cancellation token
     * @return mixed The handler result
     */
    private function callHandlerWithCancellation(callable $handler, Request $request, ?CancellationToken $cancellationToken): mixed
    {
        // Use reflection to check if the handler accepts a cancellation token
        try {
            $reflection = new \ReflectionFunction($handler);
            $parameters = $reflection->getParameters();
            
            // If handler has 2+ parameters and the second one is CancellationToken, pass it
            if (count($parameters) >= 2) {
                $secondParam = $parameters[1];
                $paramType = $secondParam->getType();
                
                if ($paramType instanceof \ReflectionNamedType && 
                    $paramType->getName() === CancellationToken::class) {
                    return $handler($request, $cancellationToken);
                }
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, fall back to single parameter call
            $this->logger->debug('Could not reflect handler, using single parameter', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Default: call handler with just the request
        return $handler($request);
    }
}