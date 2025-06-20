<?php

namespace ModelContextProtocol\Tests\Integration;

use ModelContextProtocol\Tests\TestCase;
use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Protocol\Resources\ResourceTemplate;

class McpServerIntegrationTest extends TestCase
{
    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new McpServer('test-server', '1.0.0');
    }

    public function testCompleteToolWorkflow(): void
    {
        // Register a tool
        $tool = $this->server->registerTool(
            'calculator',
            [
                'properties' => [
                    'operation' => ['type' => 'string', 'enum' => ['add', 'subtract']],
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number']
                ],
                'required' => ['operation', 'a', 'b'],
                'description' => 'A simple calculator'
            ],
            function(array $params) {
                $result = match($params['operation']) {
                    'add' => $params['a'] + $params['b'],
                    'subtract' => $params['a'] - $params['b'],
                    default => throw new \InvalidArgumentException('Invalid operation')
                };
                
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => (string)$result
                        ]
                    ]
                ];
            }
        );

        // Test tool listing
        $listRequest = (object)['method' => 'tools/list', 'params' => []];
        $listResponse = $this->server->handleToolsList($listRequest);
        
        $this->assertArrayHasKey('tools', $listResponse);
        $this->assertCount(1, $listResponse['tools']);
        $this->assertEquals('calculator', $listResponse['tools'][0]['name']);

        // Test tool execution
        $callRequest = (object)[
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculator',
                'params' => ['operation' => 'add', 'a' => 5, 'b' => 3]
            ]
        ];
        
        $callResponse = $this->server->handleToolsCall($callRequest);
        $this->assertValidToolResponse($callResponse);
        $this->assertEquals('8', $callResponse['content'][0]['text']);
    }

    public function testCompleteResourceWorkflow(): void
    {
        // Register static resource
        $staticResource = $this->server->registerResource(
            'greeting',
            'greeting://hello',
            [['type' => 'text', 'text' => 'Hello, World!']],
            ['description' => 'A greeting resource']
        );

        // Register dynamic resource
        $template = new ResourceTemplate('user://{id}', [
            'description' => 'User profile resource'
        ]);
        
        $dynamicResource = $this->server->registerResourceTemplate(
            'user-profile',
            $template,
            function(string $uri, array $params) {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "User profile for ID: {$params['id']}"
                        ]
                    ]
                ];
            }
        );

        // Test resource listing
        $listRequest = (object)['method' => 'resources/list', 'params' => []];
        $listResponse = $this->server->handleResourcesList($listRequest);
        
        $this->assertArrayHasKey('resources', $listResponse);
        $this->assertCount(1, $listResponse['resources']); // Only static resources

        // Test resource templates listing
        $templatesRequest = (object)['method' => 'resources/templates/list', 'params' => []];
        $templatesResponse = $this->server->handleResourceTemplatesList($templatesRequest);
        
        $this->assertArrayHasKey('resourceTemplates', $templatesResponse);
        $this->assertCount(1, $templatesResponse['resourceTemplates']);

        // Test static resource reading
        $readStaticRequest = (object)[
            'method' => 'resources/read',
            'params' => ['uri' => 'greeting://hello']
        ];
        
        $readStaticResponse = $this->server->handleResourceRead($readStaticRequest);
        $this->assertValidResourceResponse($readStaticResponse);
        $this->assertEquals('Hello, World!', $readStaticResponse['content'][0]['text']);

        // Test dynamic resource reading
        $readDynamicRequest = (object)[
            'method' => 'resources/read',
            'params' => ['uri' => 'user://123']
        ];
        
        $readDynamicResponse = $this->server->handleResourceRead($readDynamicRequest);
        $this->assertValidResourceResponse($readDynamicResponse);
        $this->assertStringContainsString('123', $readDynamicResponse['content'][0]['text']);
    }

    public function testCompletePromptWorkflow(): void
    {
        // Register a prompt
        $prompt = $this->server->registerPrompt(
            'code-review',
            [
                'properties' => [
                    'code' => ['type' => 'string'],
                    'language' => ['type' => 'string']
                ],
                'required' => ['code', 'language'],
                'description' => 'Generate code review prompt'
            ],
            function(array $params) {
                return [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "You are a {$params['language']} code reviewer."
                                ]
                            ]
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Please review this code:\n\n{$params['code']}"
                                ]
                            ]
                        ]
                    ]
                ];
            }
        );

        // Test prompt listing
        $listRequest = (object)['method' => 'prompts/list', 'params' => []];
        $listResponse = $this->server->handlePromptsList($listRequest);
        
        $this->assertArrayHasKey('prompts', $listResponse);
        $this->assertCount(1, $listResponse['prompts']);
        $this->assertEquals('code-review', $listResponse['prompts'][0]['name']);

        // Test prompt execution
        $getRequest = (object)[
            'method' => 'prompts/get',
            'params' => [
                'name' => 'code-review',
                'params' => [
                    'code' => 'function hello() { return "world"; }',
                    'language' => 'JavaScript'
                ]
            ]
        ];
        
        $getResponse = $this->server->handlePromptGet($getRequest);
        $this->assertValidPromptResponse($getResponse);
        $this->assertCount(2, $getResponse['messages']);
        $this->assertEquals('system', $getResponse['messages'][0]['role']);
        $this->assertEquals('user', $getResponse['messages'][1]['role']);
    }

    public function testErrorHandlingIntegration(): void
    {
        // Test tool not found
        $callRequest = (object)[
            'method' => 'tools/call',
            'params' => ['name' => 'nonexistent', 'params' => []]
        ];
        
        $callResponse = $this->server->handleToolsCall($callRequest);
        $this->assertValidErrorResponse($callResponse);

        // Test resource not found
        $readRequest = (object)[
            'method' => 'resources/read',
            'params' => ['uri' => 'nonexistent://resource']
        ];
        
        $readResponse = $this->server->handleResourceRead($readRequest);
        $this->assertValidErrorResponse($readResponse);

        // Test prompt not found
        $getRequest = (object)[
            'method' => 'prompts/get',
            'params' => ['name' => 'nonexistent', 'params' => []]
        ];
        
        $getResponse = $this->server->handlePromptGet($getRequest);
        $this->assertValidErrorResponse($getResponse);
    }

    public function testCapabilitiesIntegration(): void
    {
        // Enable all capabilities
        $this->server->registerToolCapabilities(true);
        $this->server->registerResourceCapabilities(true, true);
        $this->server->registerPromptCapabilities(true);

        // Test initialize
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

        $initResponse = $this->server->handleInitialize($initRequest);
        
        $this->assertArrayHasKey('capabilities', $initResponse);
        $this->assertArrayHasKey('tools', $initResponse['capabilities']);
        $this->assertArrayHasKey('resources', $initResponse['capabilities']);
        $this->assertArrayHasKey('prompts', $initResponse['capabilities']);
        $this->assertArrayHasKey('serverInfo', $initResponse);
    }

    public function testNotificationIntegration(): void
    {
        // Register tool and verify notification manager integration
        $notificationManager = $this->server->getNotificationManager();
        
        // Verify notification manager is accessible
        $this->assertInstanceOf(\ModelContextProtocol\Protocol\Notifications\NotificationManager::class, $notificationManager);

        $this->server->registerTool(
            'test-tool',
            ['properties' => ['test' => ['type' => 'string']]],
            function() { return ['content' => []]; }
        );

        // In a real scenario, notifications would be sent through the transport
        // Here we just verify the notification manager is properly integrated
        // The queue should have 1 notification from the tool registration
        $this->assertEquals(1, $notificationManager->getQueueSize());
    }
} 