<?php

namespace ModelContextProtocol\Tests\Unit\Server;

use ModelContextProtocol\Tests\TestCase;
use ModelContextProtocol\Server\Tools\ToolManager;
use ModelContextProtocol\Server\Tools\Tool;
use ModelContextProtocol\Server\Tools\Schema\ToolSchema;
use ModelContextProtocol\Protocol\Notifications\NotificationManager;

class ToolManagerTest extends TestCase
{
    private ToolManager $toolManager;
    private NotificationManager $notificationManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationManager = new NotificationManager();
        $this->toolManager = new ToolManager();
        $this->toolManager->setNotificationManager($this->notificationManager);
    }

    public function testRegisterTool(): void
    {
        $schema = [
            'properties' => [
                'name' => ['type' => 'string']
            ],
            'required' => ['name']
        ];
        
        $handler = function(array $params) {
            return ['content' => [['type' => 'text', 'text' => 'Hello ' . $params['name']]]];
        };

        $tool = $this->toolManager->register('greet', $schema, $handler);

        $this->assertInstanceOf(Tool::class, $tool);
        $this->assertEquals('greet', $tool->getName());
        $this->assertTrue($this->toolManager->has('greet'));
    }

    public function testRegisterToolWithDescription(): void
    {
        $schema = [
            'properties' => [
                'message' => ['type' => 'string']
            ],
            'description' => 'A test tool'
        ];
        
        $handler = function(array $params) {
            return ['content' => [['type' => 'text', 'text' => $params['message']]]];
        };

        $tool = $this->toolManager->register('echo', $schema, $handler);
        $toolSchema = $tool->getSchema();

        $this->assertEquals('A test tool', $toolSchema->getDescription());
    }

    public function testUnregisterTool(): void
    {
        $schema = ['properties' => ['test' => ['type' => 'string']]];
        $handler = function() { return ['content' => []]; };

        $this->toolManager->register('test-tool', $schema, $handler);
        $this->assertTrue($this->toolManager->has('test-tool'));

        $result = $this->toolManager->unregister('test-tool');
        $this->assertTrue($result);
        $this->assertFalse($this->toolManager->has('test-tool'));
    }

    public function testUnregisterNonExistentTool(): void
    {
        $result = $this->toolManager->unregister('non-existent');
        $this->assertFalse($result);
    }

    public function testGetTool(): void
    {
        $schema = ['properties' => ['test' => ['type' => 'string']]];
        $handler = function() { return ['content' => []]; };

        $registered = $this->toolManager->register('test-tool', $schema, $handler);
        $retrieved = $this->toolManager->get('test-tool');

        $this->assertSame($registered, $retrieved);
    }

    public function testGetNonExistentTool(): void
    {
        $tool = $this->toolManager->get('non-existent');
        $this->assertNull($tool);
    }

    public function testGetAllTools(): void
    {
        $schema = ['properties' => ['test' => ['type' => 'string']]];
        $handler = function() { return ['content' => []]; };

        $this->toolManager->register('tool1', $schema, $handler);
        $this->toolManager->register('tool2', $schema, $handler);

        $tools = $this->toolManager->getAll();

        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('tool1', $tools);
        $this->assertArrayHasKey('tool2', $tools);
    }

    public function testExecuteTool(): void
    {
        $schema = [
            'properties' => [
                'name' => ['type' => 'string']
            ],
            'required' => ['name']
        ];
        
        $handler = function(array $params) {
            return ['content' => [['type' => 'text', 'text' => 'Hello ' . $params['name']]]];
        };

        $this->toolManager->register('greet', $schema, $handler);
        $result = $this->toolManager->execute('greet', ['name' => 'World']);

        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('Hello World', $result['content'][0]['text']);
    }

    public function testExecuteNonExistentTool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tool not found: non-existent');

        $this->toolManager->execute('non-existent', []);
    }

    public function testExecuteToolWithValidation(): void
    {
        $schema = [
            'properties' => [
                'name' => ['type' => 'string']
            ],
            'required' => ['name']
        ];
        
        $handler = function(array $params) {
            return ['content' => [['type' => 'text', 'text' => 'Hello ' . $params['name']]]];
        };

        $this->toolManager->register('greet', $schema, $handler);

        // Test with missing required parameter
        $this->expectException(\InvalidArgumentException::class);
        $this->toolManager->execute('greet', []);
    }

    public function testListTools(): void
    {
        $schema1 = [
            'properties' => ['test' => ['type' => 'string']],
            'description' => 'Test tool 1'
        ];
        $schema2 = [
            'properties' => ['test' => ['type' => 'string']],
            'description' => 'Test tool 2'
        ];
        
        $handler = function() { return ['content' => []]; };

        $this->toolManager->register('tool1', $schema1, $handler);
        $this->toolManager->register('tool2', $schema2, $handler);

        $list = $this->toolManager->list();

        $this->assertArrayHasKey('tools', $list);
        $this->assertCount(2, $list['tools']);
        
        $toolNames = array_column($list['tools'], 'name');
        $this->assertContains('tool1', $toolNames);
        $this->assertContains('tool2', $toolNames);
    }

    public function testToolWithToolSchemaObject(): void
    {
        $schema = new ToolSchema(
            'test-tool',
            ['name' => ['type' => 'string']],
            ['name'],
            'A test tool'
        );
        
        $handler = function(array $params) {
            return ['content' => [['type' => 'text', 'text' => 'Hello ' . $params['name']]]];
        };

        $tool = $this->toolManager->register('test-tool', $schema, $handler);

        $this->assertEquals('test-tool', $tool->getName());
        $this->assertEquals('A test tool', $tool->getSchema()->getDescription());
    }

    public function testClearAllTools(): void
    {
        $schema = ['properties' => ['test' => ['type' => 'string']]];
        $handler = function() { return ['content' => []]; };

        $this->toolManager->register('tool1', $schema, $handler);
        $this->toolManager->register('tool2', $schema, $handler);

        $this->assertCount(2, $this->toolManager->getAll());

        // Clear all tools
        $this->setPropertyValue($this->toolManager, 'tools', []);

        $this->assertCount(0, $this->toolManager->getAll());
    }
} 