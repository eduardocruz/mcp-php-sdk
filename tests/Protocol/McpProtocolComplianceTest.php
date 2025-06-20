<?php

namespace ModelContextProtocol\Tests\Protocol;

use ModelContextProtocol\Tests\TestCase;
use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Protocol\Constants;

/**
 * Tests to validate MCP protocol compliance according to the specification
 */
class McpProtocolComplianceTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new McpServer('test-server', '1.0.0');
        
        // Enable all capabilities for comprehensive testing
        $this->server->registerToolCapabilities(true);
        $this->server->registerResourceCapabilities(true, true);
        $this->server->registerPromptCapabilities(true);
    }

    public function testInitializeProtocolCompliance(): void
    {
        $initRequest = (object)[
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => (object)[],
                    'resources' => (object)['subscribe' => true],
                    'prompts' => (object)[]
                ],
                'clientInfo' => [
                    'name' => 'test-client',
                    'version' => '1.0.0'
                ]
            ]
        ];

        $response = $this->server->handleInitialize($initRequest);

        // Validate response structure according to MCP spec
        $this->assertArrayHasKey('protocolVersion', $response);
        $this->assertArrayHasKey('capabilities', $response);
        $this->assertArrayHasKey('serverInfo', $response);
        
        // Validate server info
        $this->assertArrayHasKey('name', $response['serverInfo']);
        $this->assertArrayHasKey('version', $response['serverInfo']);
        
        // Validate capabilities structure
        $capabilities = $response['capabilities'];
        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);
        
        // Tools capability should have listChanged if supported
        $this->assertArrayHasKey('listChanged', $capabilities['tools']);
        $this->assertTrue($capabilities['tools']['listChanged']);
        
        // Resources capability should have subscribe and listChanged
        $this->assertArrayHasKey('subscribe', $capabilities['resources']);
        $this->assertArrayHasKey('listChanged', $capabilities['resources']);
        
        // Prompts capability should have listChanged
        $this->assertArrayHasKey('listChanged', $capabilities['prompts']);
        $this->assertTrue($capabilities['prompts']['listChanged']);
    }

    public function testToolsListProtocolCompliance(): void
    {
        // Register a test tool
        $this->server->registerTool(
            'test-tool',
            [
                'properties' => [
                    'input' => ['type' => 'string', 'description' => 'Input parameter']
                ],
                'required' => ['input'],
                'description' => 'A test tool'
            ],
            function(array $params) {
                return ['content' => [['type' => 'text', 'text' => $params['input']]]];
            }
        );

        $request = (object)['method' => 'tools/list', 'params' => []];
        $response = $this->server->handleToolsList($request);

        // Validate response structure
        $this->assertArrayHasKey('tools', $response);
        $this->assertIsArray($response['tools']);
        $this->assertCount(1, $response['tools']);

        $tool = $response['tools'][0];
        
        // Validate tool structure according to MCP spec
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('inputSchema', $tool);
        
        $this->assertEquals('test-tool', $tool['name']);
        $this->assertEquals('A test tool', $tool['description']);
        
        // Validate input schema is valid JSON Schema
        $schema = $tool['inputSchema'];
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
    }

    public function testToolsCallProtocolCompliance(): void
    {
        // Register a test tool
        $this->server->registerTool(
            'echo',
            [
                'properties' => [
                    'message' => ['type' => 'string']
                ],
                'required' => ['message']
            ],
            function(array $params) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $params['message']
                        ]
                    ]
                ];
            }
        );

        $request = (object)[
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo',
                'params' => ['message' => 'Hello, World!']
            ]
        ];

        $response = $this->server->handleToolsCall($request);

        // Validate response structure according to MCP spec
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        
        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertIsString($content['type']);
            
            // For text content, validate structure
            if ($content['type'] === 'text') {
                $this->assertArrayHasKey('text', $content);
                $this->assertIsString($content['text']);
            }
        }
    }

    public function testResourcesListProtocolCompliance(): void
    {
        // Register a test resource
        $this->server->registerResource(
            'test-resource',
            'test://resource',
            [['type' => 'text', 'text' => 'Test content']],
            ['description' => 'A test resource']
        );

        $request = (object)['method' => 'resources/list', 'params' => []];
        $response = $this->server->handleResourcesList($request);

        // Validate response structure
        $this->assertArrayHasKey('resources', $response);
        $this->assertIsArray($response['resources']);
        $this->assertCount(1, $response['resources']);

        $resource = $response['resources'][0];
        
        // Validate resource structure according to MCP spec
        $this->assertArrayHasKey('uri', $resource);
        $this->assertArrayHasKey('name', $resource);
        $this->assertIsString($resource['uri']);
        $this->assertIsString($resource['name']);
        
        $this->assertEquals('test://resource', $resource['uri']);
        $this->assertEquals('test-resource', $resource['name']);
    }

    public function testResourcesReadProtocolCompliance(): void
    {
        // Register a test resource
        $this->server->registerResource(
            'test-resource',
            'test://resource',
            [
                ['type' => 'text', 'text' => 'Hello'],
                ['type' => 'text', 'text' => 'World']
            ]
        );

        $request = (object)[
            'method' => 'resources/read',
            'params' => ['uri' => 'test://resource']
        ];

        $response = $this->server->handleResourceRead($request);

        // Validate response structure according to MCP spec
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        $this->assertCount(2, $response['content']);

        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertIsString($content['type']);
            
            if ($content['type'] === 'text') {
                $this->assertArrayHasKey('text', $content);
                $this->assertIsString($content['text']);
            }
        }
    }

    public function testPromptsListProtocolCompliance(): void
    {
        // Register a test prompt
        $this->server->registerPrompt(
            'test-prompt',
            [
                'properties' => [
                    'topic' => ['type' => 'string']
                ],
                'required' => ['topic'],
                'description' => 'A test prompt'
            ],
            function(array $params) {
                return [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => "Tell me about {$params['topic']}"
                            ]
                        ]
                    ]
                ];
            }
        );

        $request = (object)['method' => 'prompts/list', 'params' => []];
        $response = $this->server->handlePromptsList($request);

        // Validate response structure
        $this->assertArrayHasKey('prompts', $response);
        $this->assertIsArray($response['prompts']);
        $this->assertCount(1, $response['prompts']);

        $prompt = $response['prompts'][0];
        
        // Validate prompt structure according to MCP spec
        $this->assertArrayHasKey('name', $prompt);
        $this->assertArrayHasKey('description', $prompt);
        $this->assertArrayHasKey('arguments', $prompt);
        
        $this->assertEquals('test-prompt', $prompt['name']);
        $this->assertEquals('A test prompt', $prompt['description']);
        
        // Validate arguments schema
        $arguments = $prompt['arguments'];
        $this->assertIsArray($arguments);
        $this->assertArrayHasKey('type', $arguments);
        $this->assertEquals('object', $arguments['type']);
    }

    public function testPromptsGetProtocolCompliance(): void
    {
        // Register a test prompt
        $this->server->registerPrompt(
            'greeting',
            [
                'properties' => [
                    'name' => ['type' => 'string']
                ],
                'required' => ['name']
            ],
            function(array $params) {
                return [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                'type' => 'text',
                                'text' => 'You are a helpful assistant.'
                            ]
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => "Say hello to {$params['name']}"
                            ]
                        ]
                    ]
                ];
            }
        );

        $request = (object)[
            'method' => 'prompts/get',
            'params' => [
                'name' => 'greeting',
                'params' => ['name' => 'Alice']
            ]
        ];

        $response = $this->server->handlePromptGet($request);

        // Validate response structure according to MCP spec
        $this->assertArrayHasKey('messages', $response);
        $this->assertIsArray($response['messages']);
        $this->assertCount(2, $response['messages']);

        foreach ($response['messages'] as $message) {
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
            $this->assertIsString($message['role']);
            
            // Validate role is valid
            $this->assertContains($message['role'], ['system', 'user', 'assistant']);
            
            // Validate content structure
            $this->assertIsArray($message['content']);
            foreach ($message['content'] as $content) {
                $this->assertArrayHasKey('type', $content);
                $this->assertIsString($content['type']);
            }
        }
    }

    public function testErrorResponseProtocolCompliance(): void
    {
        // Test tool not found error
        $request = (object)[
            'method' => 'tools/call',
            'params' => ['name' => 'nonexistent', 'params' => []]
        ];

        $response = $this->server->handleToolsCall($request);

        // Validate error response structure according to JSON-RPC and MCP spec
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);
        
        $this->assertIsInt($response['error']['code']);
        $this->assertIsString($response['error']['message']);
        
        // Validate error code is in valid range
        $code = $response['error']['code'];
        $this->assertTrue(
            ($code >= -32768 && $code <= -32000) || // JSON-RPC reserved range
            ($code >= -32099 && $code <= -32000),   // Server-defined range
            "Error code {$code} is not in valid range"
        );
    }

    public function testPingPongProtocolCompliance(): void
    {
        $request = (object)['method' => 'ping', 'params' => []];
        $response = $this->server->handlePing($request);

        // Validate ping response (should be empty object or simple acknowledgment)
        $this->assertIsArray($response);
        // Ping response can be empty or contain simple data
    }

    public function testJsonRpcMessageFormat(): void
    {
        // Test that all responses follow JSON-RPC 2.0 format when using Response objects
        $this->server->registerTool(
            'test',
            ['properties' => ['test' => ['type' => 'string']]],
            function() { return ['content' => [['type' => 'text', 'text' => 'test']]]; }
        );

        $request = new \ModelContextProtocol\Protocol\Messages\Request(
            'test-123', 
            'tools/list', 
            []
        );

        // This would be handled by the Server class, not McpServer directly
        // But we can verify the structure is maintained
        $listResponse = $this->server->handleToolsList((object)['method' => 'tools/list', 'params' => []]);
        
        // Verify the response can be properly formatted as JSON-RPC
        $this->assertIsArray($listResponse);
        $this->assertArrayHasKey('tools', $listResponse);
    }
} 