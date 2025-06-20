<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Protocol\Models\ServerCapabilities;
use ModelContextProtocol\Transport\StdioTransport;

// Create a demo server with health monitoring capabilities
$server = new McpServer(
    'health-demo-server',
    '1.0.0',
    new ServerCapabilities(
        logging: ['level' => 'info'],
        tools: ['listChanged' => true],
        resources: ['subscribe' => true, 'listChanged' => true],
        prompts: ['listChanged' => true]
    ),
    'Demo MCP server showcasing ping/pong and health monitoring capabilities'
);

// Configure health monitoring
$healthMonitor = $server->getHealthMonitor();
$healthMonitor->setPingInterval(10); // Ping every 10 seconds
$healthMonitor->setPingTimeout(5);   // 5 second timeout
$healthMonitor->setMaxFailedPings(3); // Consider unhealthy after 3 failed pings

// Set up health monitoring callbacks
$healthMonitor->onHealthy(function() {
    error_log("✅ Connection is healthy");
});

$healthMonitor->onUnhealthy(function() {
    error_log("❌ Connection is unhealthy");
});

$healthMonitor->onTimeout(function() {
    error_log("⏰ Connection timed out");
});

// Register a simple tool to demonstrate server functionality
$server->registerTool(
    'get_health_stats',
    [
        'type' => 'object',
        'properties' => [
            'include_details' => [
                'type' => 'boolean',
                'description' => 'Whether to include detailed statistics'
            ]
        ]
    ],
    function(array $params) use ($server) {
        $stats = $server->getConnectionStats();
        
        if ($params['include_details'] ?? false) {
            return [
                'health_stats' => $stats,
                'server_connected' => $server->isConnected(),
                'monitoring_active' => $server->getHealthMonitor()->isMonitoring(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            return [
                'is_healthy' => $stats['isHealthy'],
                'is_monitoring' => $stats['isMonitoring'],
                'failed_ping_count' => $stats['failedPingCount'],
                'total_pings' => $stats['totalPings']
            ];
        }
    }
);

// Register a resource that provides health information
$server->registerResource(
    'health-status',
    'health://status',
    [
        'mimeType' => 'application/json',
        'text' => json_encode([
            'description' => 'Current connection health status',
            'monitoring_enabled' => true,
            'ping_interval' => $healthMonitor->getStats()['pingInterval'] ?? 30,
            'ping_timeout' => $healthMonitor->getStats()['pingTimeout'] ?? 10
        ])
    ]
);

// Register a prompt for health monitoring commands
$server->registerPrompt(
    'health-check',
    [
        'name' => 'health-check',
        'description' => 'Perform a comprehensive health check of the MCP connection',
        'arguments' => [
            [
                'name' => 'verbose',
                'description' => 'Include detailed health statistics',
                'required' => false
            ]
        ]
    ],
    function(array $params) use ($server) {
        $verbose = $params['verbose'] ?? false;
        $stats = $server->getConnectionStats();
        
        $message = "# MCP Connection Health Check\n\n";
        $message .= "**Status**: " . ($stats['isHealthy'] ? "🟢 Healthy" : "🔴 Unhealthy") . "\n";
        $message .= "**Monitoring**: " . ($stats['isMonitoring'] ? "🟢 Active" : "🔴 Inactive") . "\n";
        
        if ($verbose) {
            $message .= "\n## Detailed Statistics\n";
            $message .= "- Failed ping count: {$stats['failedPingCount']}\n";
            $message .= "- Total pings sent: {$stats['totalPings']}\n";
            $message .= "- Ping interval: {$stats['pingInterval']} seconds\n";
            $message .= "- Ping timeout: {$stats['pingTimeout']} seconds\n";
            
            if ($stats['averageResponseTime'] !== null) {
                $message .= "- Average response time: {$stats['averageResponseTime']}ms\n";
            }
            
            if ($stats['lastPingSent'] !== null) {
                $message .= "- Last ping sent: " . date('Y-m-d H:i:s', (int)$stats['lastPingSent']) . "\n";
            }
            
            if ($stats['lastPongReceived'] !== null) {
                $message .= "- Last pong received: " . date('Y-m-d H:i:s', (int)$stats['lastPongReceived']) . "\n";
            }
        }
        
        $message .= "\n## Usage\n";
        $message .= "This server automatically monitors connection health using ping/pong messages.\n";
        $message .= "Use the `get_health_stats` tool for programmatic access to health data.\n";
        
        return [
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => [
                        'type' => 'text',
                        'text' => $message
                    ]
                ]
            ]
        ];
    }
);

// Set up a periodic health monitoring tick (in a real application, this would be in an event loop)
register_shutdown_function(function() use ($server) {
    error_log("Health monitoring demo server shutting down");
});

// Connect to stdio transport
$transport = new StdioTransport();
$server->connect($transport);

// Log server startup
error_log("Health monitoring demo server started");
error_log("Features available:");
error_log("- Automatic ping/pong health monitoring");
error_log("- Health statistics via get_health_stats tool");
error_log("- Health status resource at health://status");
error_log("- Health check prompt for detailed diagnostics");
error_log("- Connection timeout and failure detection");
error_log("");
error_log("Health monitoring configuration:");
error_log("- Ping interval: 10 seconds");
error_log("- Ping timeout: 5 seconds");
error_log("- Max failed pings before unhealthy: 3");
error_log("");
error_log("Try these commands:");
error_log("- tools/call with get_health_stats tool");
error_log("- resources/read with health://status URI");
error_log("- prompts/get with health-check prompt");

// In a real application with an event loop, you would call this periodically:
// $server->healthTick();

// Keep the server running (this would be handled by your event loop in a real application)
// For demo purposes, we'll just let stdio transport handle the event loop 