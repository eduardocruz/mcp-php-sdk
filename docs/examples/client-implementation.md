# Client Implementation Example

This example demonstrates how to create an MCP client using the PHP MCP SDK.

## Basic Client Setup

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Client\McpClient;
use ModelContextProtocol\Transport\HttpTransport;
use ModelContextProtocol\Protocol\Models\ClientCapabilities;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;

// Create a logger
$logger = new ConsoleLogger();

// Create client capabilities
$capabilities = new ClientCapabilities(
    resources: [
        'list' => true
    ],
    tools: [
        'list' => true
    ],
    prompts: [
        'list' => true
    ]
);

// Create a client
$client = new McpClient(
    name: 'Example Client',
    version: '1.0.0',
    logger: $logger
);

// Connect to an MCP server
$transport = new HttpTransport('https://example.com/mcp');
$client->connect($transport);

// Initialize the connection
$result = $client->initialize($capabilities);

// Check if initialization was successful
if ($result->success) {
    echo "Connected to server: {$result->server->name} v{$result->server->version}\n";
    echo "Protocol version: {$result->protocolVersion}\n";
    
    // Access server capabilities
    $serverCapabilities = $result->capabilities;
    echo "Server supports resources: " . json_encode($serverCapabilities->resources) . "\n";
    echo "Server supports tools: " . json_encode($serverCapabilities->tools) . "\n";
    echo "Server supports prompts: " . json_encode($serverCapabilities->prompts) . "\n";
} else {
    echo "Failed to initialize connection\n";
}

// Clean up when done
$client->shutdown();
```

## Working with Resources

```php
// List available resources
$resources = $client->listResources();
echo "Available resources:\n";
foreach ($resources as $resource) {
    echo "- {$resource->name}: {$resource->uri}\n";
}

// Read a resource
$resourceUri = 'greeting://hello';
$content = $client->getResource($resourceUri);
echo "Resource content: {$content->text}\n";

// Read a dynamic resource
$dynamicUri = 'greeting://John';
$content = $client->getResource($dynamicUri);
echo "Dynamic resource content: {$content->text}\n";
```

## Working with Tools

```php
// List available tools
$tools = $client->listTools();
echo "Available tools:\n";
foreach ($tools as $tool) {
    echo "- {$tool->name}: {$tool->description}\n";
}

// Call a tool
$params = [
    'a' => 5,
    'b' => 3
];
$result = $client->callTool('add', $params);
echo "Tool result: {$result->content[0]->text}\n";

// Call a more complex tool
$weatherParams = [
    'location' => 'New York',
    'units' => 'metric'
];
$weatherResult = $client->callTool('fetchWeather', $weatherParams);
echo "Weather data: " . json_encode($weatherResult->content[0]->data) . "\n";
```

## Working with Prompts

```php
// List available prompts
$prompts = $client->listPrompts();
echo "Available prompts:\n";
foreach ($prompts as $prompt) {
    echo "- {$prompt->name}: {$prompt->description}\n";
}

// Get a prompt
$promptParams = [
    'name' => 'John Doe',
    'profession' => 'software engineer'
];
$promptResult = $client->getPrompt('introduction', $promptParams);
echo "Prompt message: {$promptResult->messages[0]->content[0]->text}\n";
```

## Complete Client Example

Here's a complete client example combining all the features:

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Client\McpClient;
use ModelContextProtocol\Transport\HttpTransport;
use ModelContextProtocol\Protocol\Models\ClientCapabilities;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;

// Create a logger
$logger = new ConsoleLogger();

// Create client capabilities
$capabilities = new ClientCapabilities(
    resources: [
        'list' => true
    ],
    tools: [
        'list' => true
    ],
    prompts: [
        'list' => true
    ]
);

// Create a client
$client = new McpClient(
    name: 'Example Client',
    version: '1.0.0',
    logger: $logger
);

try {
    // Connect to an MCP server
    $transport = new HttpTransport('https://example.com/mcp');
    $client->connect($transport);

    // Initialize the connection
    $result = $client->initialize($capabilities);

    // Check server capabilities
    echo "Connected to server: {$result->server->name} v{$result->server->version}\n";
    echo "Protocol version: {$result->protocolVersion}\n";
    
    // Work with resources
    $resources = $client->listResources();
    echo "Available resources: " . count($resources) . "\n";
    
    $content = $client->getResource('greeting://hello');
    echo "Greeting: {$content->text}\n";
    
    // Work with tools
    $tools = $client->listTools();
    echo "Available tools: " . count($tools) . "\n";
    
    $addResult = $client->callTool('add', ['a' => 10, 'b' => 5]);
    echo "10 + 5 = {$addResult->content[0]->text}\n";
    
    // Work with prompts
    $prompts = $client->listPrompts();
    echo "Available prompts: " . count($prompts) . "\n";
    
    $promptResult = $client->getPrompt('introduction', [
        'name' => 'Jane Smith',
        'profession' => 'data scientist'
    ]);
    echo "Introduction prompt: {$promptResult->messages[0]->content[0]->text}\n";
    
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
} finally {
    // Clean up
    if (isset($client)) {
        $client->shutdown();
    }
}
```

## Running the Client

Save the code above to a file named `client.php` and run it with:

```bash
php client.php
```

The client will connect to the specified MCP server, initialize the connection, and communicate with it.

## Next Steps

- See the [Server Implementation](server-implementation.md) example for how to create a server
- Learn more about [Authentication](authentication.md)
- Explore [Server-Sent Events](sse-streaming.md) for streaming capabilities