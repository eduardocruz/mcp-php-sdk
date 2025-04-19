# Resource Development Guide

This guide explains how to work with resources in the PHP MCP SDK.

## Introduction to Resources

Resources are URI-addressable content that can be exposed to LLMs through the MCP protocol. They can be:

- **Static resources**: Fixed content that doesn't change
- **Dynamic resources**: Content generated on demand based on parameters

Resources are identified by URIs, which can be:

- **Simple URIs**: Like `resource://example`
- **Template URIs**: Like `resource://{parameter}` which can be expanded with values

## Resource Components

The PHP MCP SDK provides several classes for working with resources:

- `ResourceTemplate`: Represents a URI template for dynamic resources
- `UriTemplate`: Implements RFC 6570 URI Templates
- `Resource`: Base class for all resources
- `StaticResource`: Implementation for static resources
- `DynamicResource`: Implementation for dynamic resources
- `ResourceManager`: Manages resource registration and resolution

## Creating a Static Resource

A static resource has fixed content that doesn't change:

```php
use MCP\Protocol\Resources\StaticResource;

// Create a static resource
$resource = new StaticResource(
    'greeting',                // Resource name
    'greeting://hello',        // Resource URI
    [                          // Resource content
        [
            'type' => 'text',
            'text' => 'Hello, world!'
        ]
    ]
);
```

With the McpServer, you can register static resources directly:

```php
// Register a static resource with McpServer
$server->registerResource('greeting', 'greeting://hello', [
    [
        'type' => 'text',
        'text' => 'Hello, world!'
    ]
]);
```

## Creating a Dynamic Resource

A dynamic resource generates content based on parameters in the URI:

```php
use MCP\Protocol\Resources\ResourceTemplate;
use MCP\Protocol\Resources\DynamicResource;

// Create a resource template
$template = new ResourceTemplate('greeting://{name}');

// Create a dynamic resource
$resource = new DynamicResource(
    'personalized-greeting',   // Resource name
    $template,                 // Resource template
    function($uri, $params) {  // Handler function
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Hello, {$params['name']}!"
                ]
            ]
        ];
    }
);
```

With the McpServer, you can register dynamic resources using templates:

```php
// Create a resource template
$template = new ResourceTemplate('greeting://{name}');

// Register a dynamic resource with McpServer
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
```

## Working with URI Templates

URI Templates follow RFC 6570 and allow for dynamic parts in URIs:

```php
use MCP\Protocol\Resources\UriTemplate;

// Create a URI template
$template = new UriTemplate('greeting://{name}');

// Check if a string is a template
$isTemplate = UriTemplate::isTemplate('greeting://{name}'); // true

// Get variable names from a template
$variables = $template->getVariableNames(); // ['name']

// Expand a template with values
$uri = $template->expand(['name' => 'John']); // 'greeting://John'

// Match a URI against a template
$values = $template->match('greeting://John'); // ['name' => 'John']
```

## ResourceTemplate

`ResourceTemplate` is a wrapper around `UriTemplate` that adds additional functionality for resources:

```php
use MCP\Protocol\Resources\ResourceTemplate;

// Create a resource template
$template = new ResourceTemplate(
    'greeting://{name}',    // URI template
    [                        // List options (optional)
        'list' => [
            'examples' => ['John', 'Jane', 'Bob']
        ]
    ]
);

// Check if a URI matches this template
$matches = $template->matches('greeting://John'); // true

// Extract parameters from a URI
$params = $template->extract('greeting://John'); // ['name' => 'John']

// Expand template with parameters
$uri = $template->expand(['name' => 'John']); // 'greeting://John'

// Get list options
$options = $template->getListOptions();
```

## Resource Manager

The `ResourceManager` handles resource registration, discovery, and resolution:

```php
use MCP\Protocol\Resources\ResourceManager;
use MCP\Protocol\Resources\StaticResource;
use MCP\Protocol\Resources\ResourceTemplate;

// Create a resource manager
$manager = new ResourceManager();

// Register a static resource
$staticResource = $manager->registerStatic(
    'greeting',             // Resource name
    'greeting://hello',     // Resource URI
    [                       // Resource content
        [
            'type' => 'text',
            'text' => 'Hello, world!'
        ]
    ]
);

// Create a template and register a dynamic resource
$template = new ResourceTemplate('greeting://{name}');
$dynamicResource = $manager->registerDynamic(
    'personalized-greeting', // Resource name
    $template,              // Resource template
    function($uri, $params) {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Hello, {$params['name']}!"
                ]
            ]
        ];
    }
);

// Resolve a URI to a resource
$result = $manager->resolve('greeting://John');
if ($result !== null) {
    $name = $result['name'];          // Resource name
    $resource = $result['resource'];  // Resource object
    $params = $result['params'];      // Extracted parameters
    
    // Handle the resource
    $content = $resource->handle($uri, $params);
}

// List all listable resources
$resources = $manager->list();
```

## Working with Resource Content

Resource content is returned as an array with a specific structure:

```php
// Example resource content
$content = [
    'content' => [
        [
            'type' => 'text',
            'text' => 'Hello, world!'
        ]
    ]
];
```

Content can be of different types:

```php
// Text content
$textContent = [
    'content' => [
        [
            'type' => 'text',
            'text' => 'Hello, world!'
        ]
    ]
];

// JSON content
$jsonContent = [
    'content' => [
        [
            'type' => 'application/json',
            'data' => [
                'greeting' => 'Hello',
                'target' => 'world'
            ]
        ]
    ]
];

// Image content
$imageContent = [
    'content' => [
        [
            'type' => 'image/png',
            'data' => 'base64,iVBORw0KGgoAAAANSUhEUgA...'
        ]
    ]
];

// Mixed content
$mixedContent = [
    'content' => [
        [
            'type' => 'text',
            'text' => 'Hello, world!'
        ],
        [
            'type' => 'application/json',
            'data' => ['greeting' => 'Hello']
        ]
    ]
];
```

## Resource Registration in McpServer

The `McpServer` class provides convenient methods for working with resources:

```php
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

// Notify clients of resource list changes
$server->sendResourceListChanged();
```

## Best Practices for Resources

1. **Use descriptive URIs**: Make URIs self-descriptive and follow a consistent pattern
2. **Keep resources focused**: Each resource should have a single purpose
3. **Validate parameters**: Always validate parameters in dynamic resources
4. **Handle errors gracefully**: Return meaningful error messages
5. **Document resources**: Include descriptions and examples
6. **Consider caching**: Cache resource content when appropriate
7. **Use appropriate content types**: Choose the right content type for the data
8. **Keep URI templates simple**: Don't overcomplicate URI templates