# Debugging Techniques

This guide provides techniques for debugging applications using the PHP MCP SDK.

## Enabling Detailed Logging

The PHP MCP SDK uses a logging system that can provide detailed information about what's happening during execution. You can enable detailed logging by using a custom logger:

```php
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;

// Create a logger with debug level
$logger = new ConsoleLogger(ConsoleLogger::LEVEL_DEBUG);

// Use the logger when creating a server or client
$server = new McpServer(
    name: 'Example Server',
    version: '1.0.0',
    logger: $logger
);
```

## Common Debugging Techniques

### 1. Inspect JSON-RPC Messages

You can inspect the raw JSON-RPC messages being sent and received by implementing a custom transport wrapper:

```php
class DebugTransportWrapper implements TransportInterface
{
    private TransportInterface $transport;
    private ConsoleLogger $logger;
    
    public function __construct(TransportInterface $transport, ConsoleLogger $logger)
    {
        $this->transport = $transport;
        $this->logger = $logger;
    }
    
    public function send(string $message): void
    {
        $this->logger->debug('>>> SEND: ' . $message);
        $this->transport->send($message);
    }
    
    public function receive(): ?string
    {
        $message = $this->transport->receive();
        if ($message !== null) {
            $this->logger->debug('<<< RECV: ' . $message);
        }
        return $message;
    }
    
    public function close(): void
    {
        $this->transport->close();
    }
    
    public function onMessage(callable $handler): void
    {
        $this->transport->onMessage(function($message) use ($handler) {
            $this->logger->debug('<<< RECV (async): ' . $message);
            $handler($message);
        });
    }
    
    public function startListening(): void
    {
        $this->transport->startListening();
    }
}

// Usage
$baseTransport = new StdioTransport();
$debugTransport = new DebugTransportWrapper($baseTransport, $logger);
$server->connect($debugTransport);
```

### 2. Trace Method Calls

Add trace points in your code to track method calls:

```php
private function executeHandler($requestId, $methodName, $params)
{
    $this->logger->debug("Executing handler for method: $methodName");
    $this->logger->debug("Parameters: " . json_encode($params));
    
    try {
        $result = $this->handlers[$methodName]($params);
        $this->logger->debug("Handler result: " . json_encode($result));
        return $result;
    } catch (Exception $e) {
        $this->logger->error("Handler exception: " . $e->getMessage());
        throw $e;
    }
}
```

### 3. Use PHP's Built-in Debugging Functions

PHP has several built-in functions useful for debugging:

```php
// Print a variable's structure
var_dump($variable);

// Print a human-readable representation of a variable
print_r($variable);

// Get information about a variable
var_export($variable);

// Track execution time
$startTime = microtime(true);
// ... code to time ...
$endTime = microtime(true);
echo "Execution time: " . ($endTime - $startTime) . " seconds";

// Get memory usage
$memoryBefore = memory_get_usage();
// ... code to measure ...
$memoryAfter = memory_get_usage();
echo "Memory used: " . ($memoryAfter - $memoryBefore) . " bytes";
```

### 4. Set Up Exception Handlers

Catch and log all exceptions:

```php
set_exception_handler(function($exception) use ($logger) {
    $logger->error("Uncaught exception: " . $exception->getMessage());
    $logger->error("Stack trace: " . $exception->getTraceAsString());
});
```

## Debugging Specific Components

### Transport Debugging

For transport issues:

```php
// Debug transport connection
try {
    $transport = new StdioTransport();
    $logger->debug("Transport created successfully");
    
    // Test sending a message
    $transport->send('{"test": true}');
    $logger->debug("Message sent successfully");
    
    // Test receiving a message
    $message = $transport->receive();
    $logger->debug("Received message: " . ($message ?? "null"));
} catch (Exception $e) {
    $logger->error("Transport error: " . $e->getMessage());
}
```

### Resource Resolution Debugging

For resource resolution issues:

```php
// Debug resource resolution
try {
    $uri = 'resource://example';
    $logger->debug("Resolving URI: $uri");
    
    $result = $resourceManager->resolve($uri);
    if ($result !== null) {
        $logger->debug("Resource resolved: " . $result['name']);
        $logger->debug("Parameters: " . json_encode($result['params']));
    } else {
        $logger->warning("Resource not found for URI: $uri");
    }
} catch (Exception $e) {
    $logger->error("Resource resolution error: " . $e->getMessage());
}
```

### Tool Execution Debugging

For tool execution issues:

```php
// Debug tool execution
try {
    $toolName = 'example';
    $params = ['param1' => 'value1', 'param2' => 'value2'];
    $logger->debug("Executing tool: $toolName");
    $logger->debug("Parameters: " . json_encode($params));
    
    $result = $toolManager->execute($toolName, $params);
    $logger->debug("Tool execution result: " . json_encode($result));
} catch (ValidationException $e) {
    $logger->error("Tool validation error: " . $e->getMessage());
    $logger->error("Validation errors: " . json_encode($e->getErrors()));
} catch (Exception $e) {
    $logger->error("Tool execution error: " . $e->getMessage());
}
```

## Using Xdebug

[Xdebug](https://xdebug.org/) is a powerful PHP extension for debugging that provides:

- Step debugging
- Stack traces
- Function traces
- Code coverage analysis
- Profiling

To install Xdebug:

```bash
pecl install xdebug
```

Then add the following to your `php.ini`:

```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.start_with_request=yes
```

You can then use an IDE like PHPStorm or VS Code with the PHP Debug extension to step through your code.

## Remote Debugging

For remote debugging:

1. Configure Xdebug on the remote server:

```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.client_host=your-local-ip
xdebug.client_port=9003
xdebug.start_with_request=yes
xdebug.discover_client_host=true
```

2. Set up an SSH tunnel if needed:

```bash
ssh -R 9003:localhost:9003 user@remote-server
```

3. Configure your IDE to listen for debug connections.

## Common Debugging Scenarios

### 1. Connection Issues

If you're experiencing connection issues:

- Check if the transport is properly initialized
- Verify that the server is running and accessible
- Check for firewall or network issues
- Ensure that the protocol version is compatible

### 2. Message Parsing Issues

If you're having issues with JSON-RPC message parsing:

- Validate the JSON structure using a JSON validator
- Check for encoding issues (especially with special characters)
- Verify that the message follows the JSON-RPC 2.0 specification

### 3. Resource Resolution Issues

If resources aren't being resolved correctly:

- Check that the URI is correctly formatted
- Verify that the resource is registered with the correct name and template
- Ensure that parameters are being correctly extracted from the URI

### 4. Tool Execution Issues

If tools aren't executing correctly:

- Verify that the tool is registered with the correct name and schema
- Check the parameter validation logic
- Ensure that the handler function is correctly implemented

## Additional Debugging Tools

- **PHPUnit**: For unit testing your code
- **PHP-VCR**: For recording and replaying HTTP interactions
- **Monolog**: For advanced logging capabilities
- **Symfony VarDumper**: For enhanced variable inspection