# Getting Started

This guide will help you get started with the PHP MCP SDK, covering basic concepts and providing simple examples.

## Introduction

The Model Context Protocol (MCP) is an open protocol that enables seamless integration between LLM applications and external data sources and tools. The PHP MCP SDK provides a PHP implementation of this protocol.

## Basic Architecture

MCP follows a client-host-server architecture:

- **Host**: Container and coordinator that creates/manages client instances
- **Clients**: Maintain isolated server connections (1:1 relationship with servers)
- **Servers**: Provide specialized context and capabilities

## Creating a Basic Server

Here's how to create a simple MCP server:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;
use MCP\Protocol\Resources\ResourceTemplate;
use MCP\Protocol\Resources\StaticResource;

// Create an MCP server
$server = new McpServer('Example Server', '1.0.0');

// Register resource capabilities
$server->registerResourceCapabilities();

// Register a static resource
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);

// Register a dynamic resource with a template
$greetingTemplate = new ResourceTemplate('greeting://{name}');
$server->registerResourceTemplate('personalized-greeting', $greetingTemplate, function($uri, $params) {
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => "Hello, {$params['name']}!"
            ]
        ]
    ];
});

// Register a simple tool
$server->registerTool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
], function($params) {
    $result = $params['a'] + $params['b'];
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => (string)$result
            ]
        ]
    ];
});

// Connect to stdio transport
$transport = new StdioTransport();
$server->connect($transport);

// The server is now running and will process messages from stdin
// This is a blocking call
```

## Creating a Basic Client

Here's how to create a simple MCP client:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Client\McpClient;
use ModelContextProtocol\Transport\HttpTransport;
use ModelContextProtocol\Protocol\Models\ClientCapabilities;

// Create a client
$client = new McpClient('Example Client', '1.0.0');

// Set up client capabilities
$capabilities = new ClientCapabilities(
    resources: ['list' => true],
    tools: ['list' => true]
);

// Connect to an MCP server
$transport = new HttpTransport('https://example.com/mcp');
$client->connect($transport, $capabilities);

// Initialize the connection
$result = $client->initialize();

// Get resources
$resources = $client->listResources();

// Call a tool
$toolResult = $client->callTool('add', ['a' => 5, 'b' => 3]);
echo "Result: " . $toolResult['content'][0]['text'] . "\n";

// Clean up
$client->shutdown();
```

## Key Concepts

### Resources

Resources are URI-addressable content that can be exposed to LLMs. They can be static (fixed content) or dynamic (content generated on demand).

```php
// Register a static resource
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);
```

### Tools

Tools are functions that can be registered and executed through the MCP protocol.

```php
// Register a tool
$server->registerTool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
], function($params) {
    return $params['a'] + $params['b'];
});
```

### Transports

Transports handle the communication between clients and servers. The SDK provides two main transport implementations:

- `StdioTransport`: For stdio-based communication
- `HttpTransport`: For HTTP-based communication with support for Server-Sent Events

## Next Steps

- Explore the [API Reference](../api-reference/README.md) for detailed documentation
- Check out the [Examples](../examples/README.md) for more complex examples
- Read the [Guides](README.md) for more detailed information on specific topics