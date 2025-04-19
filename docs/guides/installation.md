# Installation Guide

This guide will help you install the PHP MCP SDK in your project.

## Requirements

- PHP 8.1 or higher
- Composer (for package management)
- ext-json (PHP JSON extension)
- ext-pcntl (for cancellation support)
- ext-curl (for HTTP transport)

## Installation via Composer

The recommended way to install the PHP MCP SDK is via Composer:

```bash
composer require eduardocruz/mcp-php-sdk
```

This will install the SDK and all its dependencies.

## Manual Installation

If you prefer to install the SDK manually:

1. Clone the repository:

```bash
git clone https://github.com/eduardocruz/mcp-php-sdk.git
```

2. Include the SDK in your project:

```php
require_once 'path/to/mcp-php-sdk/autoload.php';
```

## Verifying Installation

You can verify that the SDK is properly installed by creating a simple test script:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;

// Create a server instance
$server = new McpServer(
    'Test Server',
    '1.0.0',
    new ServerCapabilities()
);

echo "PHP MCP SDK installed successfully!\n";
```

Save this file as `test-mcp.php` and run it:

```bash
php test-mcp.php
```

If the script runs without errors, the SDK is properly installed.

## Configuration

The SDK doesn't require any specific configuration files. Configuration is done programmatically when initializing components like servers and clients.

See the [Configuration Guide](configuration.md) for more details on available configuration options.

## Next Steps

Now that you have installed the PHP MCP SDK, you can:

- Read the [Getting Started Guide](getting-started.md) to learn the basics
- Explore the [API Reference](../api-reference/README.md) for detailed documentation
- Try out the [Examples](../examples/README.md) to see the SDK in action