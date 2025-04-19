# MCP PHP SDK

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

A PHP implementation of the [Model Context Protocol (MCP)](https://modelcontextprotocol.io) specification. This SDK enables PHP applications to seamlessly integrate with LLM applications by providing contextual information, tools, and prompts through a standardized interface.

## Table of Contents

- [MCP PHP SDK](#mcp-php-sdk)
  - [Table of Contents](#table-of-contents)
  - [Overview](#overview)
  - [Installation](#installation)
  - [Quick Start](#quick-start)
  - [What is MCP?](#what-is-mcp)
  - [Core Concepts](#core-concepts)
    - [Server](#server)
    - [Resources](#resources)
    - [Tools](#tools)
    - [Prompts](#prompts)
  - [Running Your Server](#running-your-server)
    - [stdio](#stdio)
    - [HTTP with Streaming](#http-with-streaming)
    - [Testing and Debugging](#testing-and-debugging)
  - [Examples](#examples)
  - [Advanced Usage](#advanced-usage)
  - [Contributing](#contributing)
  - [Support](#support)
    - [Ways to Support](#ways-to-support)
  - [License](#license)

## Overview

The Model Context Protocol allows applications to provide context for LLMs in a standardized way, separating the concerns of providing context from the actual LLM interaction. This PHP SDK implements the full MCP specification (2025-03-26), making it easy to:

- Build MCP clients that can connect to any MCP server
- Create MCP servers that expose resources, prompts, and tools
- Use standard transports like stdio and HTTP with streaming
- Handle all MCP protocol messages and lifecycle events

## Installation

```bash
composer require modelcontextprotocol/sdk
```

> **Note:** Requires PHP 8.1 or higher.

## Quick Start

Let's create a simple MCP server that exposes a calculator tool and a dynamic resource:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;
use ModelContextProtocol\Server\Resources\ResourceTemplate;

// Create an MCP server
$server = new McpServer('Demo', '1.0.0');

// Add an addition tool
$server->tool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
], function(array $params) {
    $a = $params['a'];
    $b = $params['b'];
    
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => (string)($a + $b)
            ]
        ]
    ];
});

// Add a dynamic greeting resource
$server->resource('greeting', new ResourceTemplate(
    'greeting://{name}',
    ['list' => null]
), function(string $uri, array $params) {
    return [
        'contents' => [[
            'uri' => $uri,
            'text' => "Hello, {$params['name']}!"
        ]]
    ];
});

// Start the server using stdio transport
$transport = new StdioTransport();
$server->connect($transport);
```

## What is MCP?

The [Model Context Protocol (MCP)](https://modelcontextprotocol.io) lets you build servers that expose data and functionality to LLM applications in a secure, standardized way. Think of it like a web API, but specifically designed for LLM interactions. MCP servers can:

- Expose data through **Resources** (like GET endpoints; they provide information to the LLM's context)
- Provide functionality through **Tools** (like POST endpoints; they execute code or produce side effects)
- Define interaction patterns through **Prompts** (reusable templates for LLM interactions)
- And more!

## Core Concepts

### Server

The McpServer is your core interface to the MCP protocol. It handles connection management, protocol compliance, and message routing:

```php
$server = new McpServer('My App', '1.0.0');
```

### Resources

Resources are how you expose data to LLMs. They're similar to GET endpoints in a REST API - they provide data but shouldn't perform significant computation or have side effects:

```php
// Static resource
$server->resource(
    'config',
    'config://app',
    function(string $uri) {
        return [
            'contents' => [[
                'uri' => $uri,
                'text' => 'App configuration here'
            ]]
        ];
    }
);

// Dynamic resource with parameters
$server->resource(
    'user-profile',
    new ResourceTemplate('users://{userId}/profile', ['list' => null]),
    function(string $uri, array $params) {
        return [
            'contents' => [[
                'uri' => $uri,
                'text' => "Profile data for user {$params['userId']}"
            ]]
        ];
    }
);
```

### Tools

Tools let LLMs take actions through your server. Unlike resources, tools are expected to perform computation and have side effects:

```php
// Simple tool with parameters
$server->tool(
    'calculate-bmi',
    [
        'properties' => [
            'weightKg' => ['type' => 'number'],
            'heightM' => ['type' => 'number']
        ],
        'required' => ['weightKg', 'heightM']
    ],
    function(array $params) {
        $weightKg = $params['weightKg'];
        $heightM = $params['heightM'];
        $bmi = $weightKg / ($heightM * $heightM);
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => (string)$bmi
            ]]
        ];
    }
);

// Async tool with external API call
$server->tool(
    'fetch-weather',
    [
        'properties' => [
            'city' => ['type' => 'string']
        ],
        'required' => ['city']
    ],
    function(array $params) {
        $city = $params['city'];
        $url = "https://api.weather.com/" . urlencode($city);
        
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);
        $data = $response->getBody()->getContents();
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => $data
            ]]
        ];
    }
);
```

### Prompts

Prompts are reusable templates that help LLMs interact with your server effectively:

```php
$server->prompt(
    'review-code',
    [
        'properties' => [
            'code' => ['type' => 'string']
        ],
        'required' => ['code']
    ],
    function(array $params) {
        return [
            'messages' => [[
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "Please review this code:\n\n{$params['code']}"
                ]
            ]]
        ];
    }
);
```

## Running Your Server

MCP servers in PHP need to be connected to a transport to communicate with clients. How you start the server depends on the choice of transport:

### stdio

For command-line tools and direct integrations:

```php
use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;

$server = new McpServer('example-server', '1.0.0');

// ... set up server resources, tools, and prompts ...

$transport = new StdioTransport();
$server->connect($transport);
```

### HTTP with Streaming

For remote servers, set up an HTTP transport that handles both client requests and server-to-client notifications:

```php
use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\HttpTransport;
use ModelContextProtocol\Storage\InMemoryEventStore;

// Create a server with HTTP transport
$server = new McpServer('example-server', '1.0.0');

// ... set up server resources, tools, and prompts ...

// Set up HTTP transport with session management
$eventStore = new InMemoryEventStore();
$transport = new HttpTransport([
    'eventStore' => $eventStore,
    'sessionIdGenerator' => function() {
        return bin2hex(random_bytes(16));
    }
]);

// Start the server
$server->connect($transport);
```

### Testing and Debugging

For testing and debugging, we provide utilities to help you validate your implementation:

```php
use ModelContextProtocol\Debug\TestClient;

// Create a test client
$client = new TestClient();

// Connect to your server
$client->connect($server);

// Test a resource
$result = $client->fetchResource('greeting://world');
echo $result['contents'][0]['text']; // "Hello, world!"

// Test a tool
$result = $client->executeTool('add', ['a' => 5, 'b' => 3]);
echo $result['content'][0]['text']; // "8"
```

## Examples

Check out the `examples/` directory for complete examples of different MCP server use cases:

- [Echo Server](examples/echo-server.php): A simple server that echoes input
- [SQLite Explorer](examples/sqlite-explorer.php): A server that exposes a SQLite database
- [Web API Integration](examples/web-api.php): A server that wraps external web APIs

## Advanced Usage

For advanced usage scenarios, please refer to the [documentation](https://modelcontextprotocol.io).

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Support

If you find this SDK helpful or believe in its potential, please consider supporting its development. Your support helps maintain continuous improvements, new features, and ongoing maintenance.

### Ways to Support

1. **GitHub Sponsors**
   - Support the development team directly through [GitHub Sponsors](https://github.com/sponsors/modelcontextprotocol)

2. **Star the Repository**
   - Give us a star ⭐ on GitHub to increase visibility and show your appreciation

3. **Spread the Word**
   - Share your experience with the SDK on social media
   - Write blog posts or tutorials about your use cases
   - Recommend it to colleagues and friends

4. **Contribute**
   - Submit bug reports and feature requests
   - Contribute code improvements
   - Help improve documentation
   - Share your use cases and examples

Your support, in any form, helps make this project better for everyone. Thank you for being part of our community!

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.