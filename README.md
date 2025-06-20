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
    - [stdio Transport](#stdio-transport)
  - [Documentation](#documentation)
  - [Support](#-support)
  - [Contributing](#contributing)
  - [License](#license)

## Overview

The Model Context Protocol allows applications to provide context for LLMs in a standardized way, separating the concerns of providing context from the actual LLM interaction. This PHP SDK implements the full MCP specification (2025-03-26), making it easy to:

![MCP PHP SDK Architecture](diagram.png)

*Diagram created with [GitDiagram](https://gitdiagram.com/eduardocruz/mcp-php-sdk)*

- **Build MCP clients** that can connect to any MCP server
- **Create MCP servers** that expose resources, prompts, and tools
- **Use standard transports** like stdio with proper message framing
- **Handle all MCP protocol messages** and lifecycle events
- **Robust error handling** with standardized JSON-RPC error responses
- **Advanced resource management** with URI templates and dynamic content
- **Complete prompts system** for reusable LLM interaction patterns
- **Production-ready features** including error recovery, logging, and validation

## Installation

```bash
composer require eduardocruz/mcp-php-sdk
```

> **Note:** Requires PHP 8.1 or higher.

## Quick Start

Let's create a simple MCP server that exposes a calculator tool and a dynamic resource:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;
use ModelContextProtocol\Protocol\Resources\ResourceTemplate;

// Create an MCP server
$server = new McpServer('Demo', '1.0.0');

// Add an addition tool
$server->registerTool('add', [
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
$template = new ResourceTemplate('greeting://{name}');
$server->registerResourceTemplate('personalized-greeting', $template, function($uri, $params) {
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => "Hello, {$params['name']}!"
            ]
        ]
    ];
});

// Add a prompt for generating introductions
$server->registerPrompt('introduction', [
    'properties' => [
        'name' => ['type' => 'string'],
        'profession' => ['type' => 'string']
    ],
    'required' => ['name', 'profession']
], function(array $params) {
    return [
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "Please introduce {$params['name']}, who works as a {$params['profession']}."
                ]
            ]
        ]
    ];
});

// Start the server using stdio transport
$transport = new StdioTransport();
$server->connect($transport);
```

## What is MCP?

The Model Context Protocol (MCP) lets you build servers that expose data and functionality to LLM applications in a secure, standardized way. Think of it like a web API, but specifically designed for LLM interactions. MCP servers can:

- Expose data through **Resources** (like GET endpoints; they provide information to the LLM's context)
- Provide functionality through **Tools** (like POST endpoints; they execute code or produce side effects)
- Define interaction patterns through **Prompts** (reusable templates for LLM interactions)

## Core Concepts

### Server

The McpServer is your core interface to the MCP protocol. It handles connection management, protocol compliance, and message routing:

```php
$server = new ModelContextProtocol\Server\McpServer('My App', '1.0.0');
```

### Resources

Resources are how you expose data to LLMs. They're similar to GET endpoints in a REST API - they provide data but shouldn't perform significant computation or have side effects:

```php
// Static resource
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);

// Dynamic resource with parameters
$template = new ModelContextProtocol\Protocol\Resources\ResourceTemplate('greeting://{name}');
$server->registerResourceTemplate('personalized-greeting', $template, function(string $uri, array $params) {
    return [
        'content' => [
            [
                'type' => 'text',
                'text' => "Hello, {$params['name']}!"
            ]
        ]
    ];
});
```

### Tools

Tools let LLMs take actions through your server. Unlike resources, tools are expected to perform computation and have side effects:

```php
// Simple tool with parameters
$server->registerTool(
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
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string)$bmi
                ]
            ]
        ];
    }
);
```

### Prompts

Prompts are reusable templates that help LLMs interact with your server effectively:

```php
$server->registerPrompt(
    'introduction',
    [
        'properties' => [
            'name' => ['type' => 'string'],
            'profession' => ['type' => 'string']
        ],
        'required' => ['name', 'profession']
    ],
    function(array $params) {
        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "Please introduce {$params['name']}, who works as a {$params['profession']}."
                    ]
                ]
            ]
        ];
    }
);
```

## Running Your Server

MCP servers in PHP need to be connected to a transport to communicate with clients.

### stdio Transport

For command-line tools and direct integrations:

```php
use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;

$server = new McpServer('example-server', '1.0.0');

// ... set up server resources, tools, and prompts ...

$transport = new StdioTransport();
$server->connect($transport);
```

## Recent Improvements

This SDK has been significantly enhanced with production-ready features:

### ✅ **Standardized Error Handling** (EDU-102)
- **ErrorResponseBuilder**: Centralized error response creation with JSON-RPC 2.0 compliance
- **Error Recovery**: Automatic retry mechanisms with exponential backoff and circuit breaker patterns
- **Enhanced Debugging**: Structured error logging with context and stack traces
- **MCP-Specific Error Codes**: Extended error codes (-32001 to -32010) for MCP-specific scenarios

### ✅ **Complete Prompts System** (EDU-101)
- **Full Implementation**: Complete prompts registration, listing, and execution
- **Schema Validation**: Parameter validation with detailed error reporting
- **Integration**: Seamlessly integrated with McpServer for production use

### ✅ **Advanced Resource Management** (EDU-100)
- **Resource Templates**: Dynamic URI pattern matching with parameter extraction
- **Static & Dynamic Resources**: Support for both static content and computed resources
- **Full Integration**: Complete resource listing, reading, and template support

### ✅ **Enhanced Infrastructure** (EDU-98, EDU-99)
- **Improved Message Handling**: Better JSON-RPC message processing and validation
- **Transport Reliability**: Enhanced stdio transport with proper error handling
- **Session Management**: Robust connection and session lifecycle management

## Documentation

For more detailed documentation, please refer to the [docs folder](/docs), which includes:

- [API Reference](/docs/api-reference/README.md): Comprehensive documentation of all classes and methods
- [Examples](/docs/examples/README.md): Code examples for common use cases
- [Guides](/docs/guides/README.md): Installation and getting started guides
- [Troubleshooting](/docs/troubleshooting/README.md): Common issues and their solutions

## Cursor Integration

This SDK includes a [PHPStan MCP Server](/examples/phpstan-mcp-server.php) example that you can connect to Cursor for PHP code analysis. When connected, you can ask Claude to:

- Analyze PHP code for errors using PHPStan
- Check code quality issues like complexity
- Scan for potential security vulnerabilities

[Learn more about using the PHPStan MCP Server with Cursor](/examples/README.md)

## 🚀 Support

If you found **MCP PHP SDK** helpful, believe in its potential, or simply want to support meaningful open-source contributions, please consider becoming a sponsor. Your support helps sustain continuous improvements, new features, and ongoing maintenance.

Whether you're actively using **MCP PHP SDK**, exploring its possibilities, or just excited by its mission—your contribution makes a significant difference.

👉 [Become a Sponsor](https://github.com/sponsors/eduardocruz)

Thank you for empowering open source!

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.