# Server Implementation Example

This example demonstrates how to create a full-featured MCP server using the PHP MCP SDK.

## Basic Server Setup

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;

// Create a logger
$logger = new ConsoleLogger();

// Create server capabilities
$capabilities = new ServerCapabilities(
    resources: [
        'list' => true,
        'listChanged' => true
    ],
    tools: [
        'list' => true,
        'listChanged' => true
    ],
    prompts: [
        'list' => true
    ]
);

// Create an MCP server with capabilities
$server = new McpServer(
    name: 'Example Server',
    version: '1.0.0',
    capabilities: $capabilities,
    instructions: 'This server provides example resources and tools.',
    logger: $logger
);

// Connect to stdio transport
$transport = new StdioTransport();
$server->connect($transport);

// The server is now running and will process messages from stdin
```

## Adding Resources

```php
// Add a static resource
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);

// Add a dynamic resource with a template
$greetingTemplate = new MCP\Protocol\Resources\ResourceTemplate('greeting://{name}');
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

// Add a file resource
$server->registerResource('readme', 'file://readme.md', [
    [
        'type' => 'text',
        'text' => file_get_contents('README.md')
    ]
]);
```

## Adding Tools

```php
// Add a simple addition tool
$server->registerTool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b'],
    'description' => 'Adds two numbers together'
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

// Add a more complex tool
$server->registerTool('fetchWeather', [
    'properties' => [
        'location' => ['type' => 'string'],
        'units' => [
            'type' => 'string',
            'enum' => ['metric', 'imperial'],
            'default' => 'metric'
        ]
    ],
    'required' => ['location'],
    'description' => 'Fetches weather information for a location'
], function($params) {
    $location = $params['location'];
    $units = $params['units'] ?? 'metric';
    
    // In a real implementation, this would call a weather API
    $weatherData = [
        'location' => $location,
        'temperature' => ($units === 'metric') ? 22 : 72,
        'conditions' => 'Sunny',
        'units' => $units
    ];
    
    return [
        'content' => [
            [
                'type' => 'application/json',
                'data' => $weatherData
            ]
        ]
    ];
});
```

## Adding Prompts

```php
// Add a prompt template
$server->registerPrompt('introduction', [
    'properties' => [
        'name' => ['type' => 'string'],
        'profession' => ['type' => 'string']
    ],
    'required' => ['name', 'profession'],
    'description' => 'Creates an introduction message'
], function($params) {
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
```

## Complete Server Example

```php
<?php

require_once 'vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;
use MCP\Protocol\Resources\ResourceTemplate;

// Create a logger
$logger = new ConsoleLogger();

// Create server capabilities
$capabilities = new ServerCapabilities(
    resources: [
        'list' => true,
        'listChanged' => true
    ],
    tools: [
        'list' => true,
        'listChanged' => true
    ],
    prompts: [
        'list' => true
    ]
);

// Create an MCP server with capabilities
$server = new McpServer(
    name: 'Example Server',
    version: '1.0.0',
    capabilities: $capabilities,
    instructions: 'This server provides example resources and tools.',
    logger: $logger
);

// Add resources
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);

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

// Add tools
$server->registerTool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b'],
    'description' => 'Adds two numbers together'
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

// Add prompts
$server->registerPrompt('introduction', [
    'properties' => [
        'name' => ['type' => 'string'],
        'profession' => ['type' => 'string']
    ],
    'required' => ['name', 'profession'],
    'description' => 'Creates an introduction message'
], function($params) {
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

// Connect to stdio transport
$transport = new StdioTransport();
$server->connect($transport);

// The server is now running and will process messages from stdin
```

## Running the Server

Save the code above to a file named `server.php` and run it with:

```bash
php server.php
```

The server will now listen for MCP messages on stdin/stdout. You can use an MCP client to communicate with it.

## Next Steps

- See the [Client Implementation](client-implementation.md) example for how to connect to this server
- Learn more about [Resource Handling](resource-handling.md)
- Explore [Tool Registration](tool-registration.md) in depth