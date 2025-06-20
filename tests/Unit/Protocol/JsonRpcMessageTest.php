<?php

namespace ModelContextProtocol\Tests\Unit\Protocol;

use ModelContextProtocol\Tests\TestCase;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Protocol\Messages\ErrorData;

class JsonRpcMessageTest extends TestCase
{
    public function testRequestCreation(): void
    {
        $request = new Request('test-123', 'test/method', ['param' => 'value']);
        
        $this->assertEquals('2.0', $request->jsonrpc);
        $this->assertEquals('test-123', $request->id);
        $this->assertEquals('test/method', $request->method);
        $this->assertEquals(['param' => 'value'], $request->params);
    }

    public function testRequestSerialization(): void
    {
        $request = new Request('test-123', 'test/method', ['param' => 'value']);
        $json = json_encode($request);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals('test-123', $decoded['id']);
        $this->assertEquals('test/method', $decoded['method']);
        $this->assertEquals(['param' => 'value'], $decoded['params']);
    }

    public function testResponseCreation(): void
    {
        $response = new Response('test-123', ['result' => 'success']);
        
        $this->assertEquals('2.0', $response->jsonrpc);
        $this->assertEquals('test-123', $response->id);
        $this->assertEquals(['result' => 'success'], $response->result);
        $this->assertNull($response->error);
    }

    public function testErrorResponseCreation(): void
    {
        $errorData = new ErrorData(-32603, 'Internal error');
        
        $response = new Response('test-123', null, $errorData);
        
        $this->assertEquals('2.0', $response->jsonrpc);
        $this->assertEquals('test-123', $response->id);
        $this->assertNull($response->result);
        $this->assertEquals($errorData, $response->error);
    }

    public function testNotificationCreation(): void
    {
        $notification = new Notification('test/notification', ['data' => 'value']);
        
        $this->assertEquals('2.0', $notification->jsonrpc);
        $this->assertEquals('test/notification', $notification->method);
        $this->assertEquals(['data' => 'value'], $notification->params);
        $this->assertFalse(property_exists($notification, 'id'));
    }

    public function testNotificationSerialization(): void
    {
        $notification = new Notification('test/notification', ['data' => 'value']);
        $json = json_encode($notification);
        $decoded = json_decode($json, true);
        
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals('test/notification', $decoded['method']);
        $this->assertEquals(['data' => 'value'], $decoded['params']);
        $this->assertArrayNotHasKey('id', $decoded);
    }

    public function testRequestWithoutParams(): void
    {
        $request = new Request('test-123', 'test/method');
        
        $this->assertEquals('test/method', $request->method);
        $this->assertNull($request->params);
    }

    public function testNotificationWithoutParams(): void
    {
        $notification = new Notification('test/notification');
        
        $this->assertEquals('test/notification', $notification->method);
        $this->assertNull($notification->params);
    }
} 