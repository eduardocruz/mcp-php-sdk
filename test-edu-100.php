<?php

require_once __DIR__ . '/vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Protocol\Resources\ResourceTemplate;

echo "=== EDU-100: Resource System Integration Test ===\n\n";

// Create an MCP server instance
$server = new McpServer('Test-Resource-Server', '1.0.0');

// Enable resource capabilities
$server->registerResourceCapabilities(true, true);

echo "1. Testing static resource registration...\n";

// Register a static resource
$staticResource = $server->registerResource(
    'greeting',
    'greeting://hello',
    [
        [
            'type' => 'text',
            'text' => 'Hello, world! This is a static resource.'
        ]
    ],
    ['description' => 'A simple greeting resource']
);

echo "   ✓ Static resource registered: {$staticResource->getName()}\n";
echo "   ✓ Static resource URI: {$staticResource->getUri()}\n";

echo "\n2. Testing dynamic resource registration...\n";

// Register a dynamic resource with template
$template = new ResourceTemplate('greeting://{name}', [
    'description' => 'Personalized greeting resource',
    'examples' => ['John', 'Jane', 'Bob']
]);

$dynamicResource = $server->registerResourceTemplate(
    'personalized-greeting',
    $template,
    function(string $uri, array $params) {
        $name = $params['name'] ?? 'Unknown';
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Hello, {$name}! This is a dynamic resource."
                ]
            ]
        ];
    }
);

echo "   ✓ Dynamic resource registered: {$dynamicResource->getName()}\n";
echo "   ✓ Dynamic resource template: {$dynamicResource->getTemplate()}\n";

echo "\n3. Testing resource listing (resources/list)...\n";

// Mock request for resources/list
$listRequest = (object)[
    'method' => 'resources/list',
    'params' => []
];

$listResponse = $server->handleResourcesList($listRequest);
echo "   ✓ Resources list response:\n";
echo "     " . json_encode($listResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n4. Testing resource templates listing (resources/templates/list)...\n";

// Mock request for resources/templates/list
$templatesRequest = (object)[
    'method' => 'resources/templates/list',
    'params' => []
];

$templatesResponse = $server->handleResourceTemplatesList($templatesRequest);
echo "   ✓ Resource templates response:\n";
echo "     " . json_encode($templatesResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n5. Testing static resource reading (resources/read)...\n";

// Mock request for resources/read with static resource
$readStaticRequest = (object)[
    'method' => 'resources/read',
    'params' => ['uri' => 'greeting://hello']
];

$readStaticResponse = $server->handleResourceRead($readStaticRequest);
echo "   ✓ Static resource read response:\n";
echo "     " . json_encode($readStaticResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n6. Testing dynamic resource reading (resources/read)...\n";

// Mock request for resources/read with dynamic resource
$readDynamicRequest = (object)[
    'method' => 'resources/read',
    'params' => ['uri' => 'greeting://Alice']
];

$readDynamicResponse = $server->handleResourceRead($readDynamicRequest);
echo "   ✓ Dynamic resource read response:\n";
echo "     " . json_encode($readDynamicResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n7. Testing error handling for invalid URI...\n";

// Mock request for resources/read with invalid URI
$readInvalidRequest = (object)[
    'method' => 'resources/read',
    'params' => ['uri' => 'invalid://nonexistent']
];

$readInvalidResponse = $server->handleResourceRead($readInvalidRequest);
echo "   ✓ Invalid URI error response:\n";
echo "     " . json_encode($readInvalidResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n8. Testing error handling for missing URI parameter...\n";

// Mock request for resources/read without URI
$readNoUriRequest = (object)[
    'method' => 'resources/read',
    'params' => []
];

$readNoUriResponse = $server->handleResourceRead($readNoUriRequest);
echo "   ✓ Missing URI parameter error response:\n";
echo "     " . json_encode($readNoUriResponse, JSON_PRETTY_PRINT) . "\n";

echo "\n9. Testing resource unregistration...\n";

$unregisterResult = $server->unregisterResource('greeting');
echo "   ✓ Static resource unregistered: " . ($unregisterResult ? 'Yes' : 'No') . "\n";

// Verify resource is gone
$listAfterUnregister = $server->handleResourcesList($listRequest);
echo "   ✓ Resources list after unregistering:\n";
echo "     " . json_encode($listAfterUnregister, JSON_PRETTY_PRINT) . "\n";

echo "\n10. Testing ResourceManager direct access...\n";

$resourceManager = $server->getResourceManager();
echo "   ✓ ResourceManager retrieved from server\n";

// Test direct resource resolution
$resolved = $resourceManager->resolve('greeting://Bob');
if ($resolved) {
    echo "   ✓ Direct resource resolution successful:\n";
    echo "     Name: {$resolved['name']}\n";
    echo "     Params: " . json_encode($resolved['params']) . "\n";
    
    // Test resource handling
    $result = $resolved['resource']->handle('greeting://Bob', $resolved['params']);
    echo "   ✓ Direct resource handling result:\n";
    echo "     " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "   ❌ Direct resource resolution failed\n";
}

echo "\n=== All tests completed! ===\n"; 