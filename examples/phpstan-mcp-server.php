<?php

/**
 * PHPStan MCP Server Example for Cursor
 * 
 * This example follows the same message format as the TypeScript SDK to ensure compatibility with Cursor.
 */

// Disable all warnings and notices that might print to stdout
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Redirect error_log to STDERR so it doesn't interfere with JSON-RPC messages
ini_set('error_log', 'php://stderr');

// Simple MCP server for static analysis
class SimpleMcpServer {
    private $tools = [];
    private $initialized = false;
    private $clientCapabilities = null;
    
    public function __construct(private $name, private $version) {
    }
    
    /**
     * Register a tool with the server
     */
    public function registerTool($name, $schema, $handler, $description = '') {
        $this->tools[$name] = [
            'name' => $name,
            'schema' => $schema,
            'handler' => $handler,
            'description' => $description ?: "Tool: {$name}"
        ];
        return $this;
    }
    
    /**
     * Process input from STDIN
     */
    public function processInput() {
        // Set a timeout to prevent hanging indefinitely but allow long operations
        stream_set_timeout(STDIN, 3600); // 1 hour timeout
        
        // Set to non-blocking mode to avoid hanging
        stream_set_blocking(STDIN, false);
        
        // Read a line from STDIN
        $line = fgets(STDIN);
        
        // If no data is available yet in non-blocking mode, return true to continue
        if ($line === false && !feof(STDIN)) {
            usleep(100000); // Sleep for 100ms to avoid busy waiting
            return true;
        }
        
        // Check for EOF
        if ($line === false && feof(STDIN)) {
            error_log("End of input stream reached, reconnecting...");
            // Try to reopen STDIN
            // This is a workaround to handle Cursor's connection management
            return true;
        }
        
        // Skip empty lines
        if ($line !== false && trim($line) === '') {
            return true;
        }
        
        // If we have data to process
        if ($line !== false) {
            try {
                // Parse the JSON message
                $message = json_decode($line, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("Error decoding JSON message: " . json_last_error_msg());
                    error_log("Raw message: " . $line);
                    return true;
                }
                
                // Handle the message
                $this->handleMessage($message);
            } catch (Exception $e) {
                error_log("Error processing message: " . $e->getMessage());
                error_log("Exception trace: " . $e->getTraceAsString());
            }
        }
        
        // Always return true to keep the server running
        return true;
    }
    
    /**
     * Handle a JSON-RPC message
     */
    private function handleMessage($message) {
        error_log("Handling message: " . json_encode($message['method'] ?? 'unknown'));
        
        // Check if it's a request (has an ID)
        if (isset($message['id'])) {
            // Handle requests
            $method = $message['method'] ?? '';
            $params = $message['params'] ?? [];
            
            switch ($method) {
                case 'initialize':
                    $this->handleInitialize($message);
                    break;
                    
                case 'shutdown':
                    $this->sendResponse($message['id'], null);
                    break;
                    
                case 'tools/list':
                    $this->handleToolsList($message);
                    break;
                    
                case 'tools/call':
                    $this->handleToolsCall($message);
                    break;
                    
                case 'analyzers/analyze':
                    $this->handleAnalyze($message);
                    break;
                    
                default:
                    $this->sendErrorResponse($message['id'], -32601, "Method not found: {$method}");
                    break;
            }
        } else {
            // Handle notifications
            $method = $message['method'] ?? '';
            
            switch ($method) {
                case 'notifications/initialized':
                    $this->initialized = true;
                    error_log("Server initialized");
                    break;
                    
                case '$/cancelRequest':
                    // Handle cancellation - no implementation needed for this example
                    break;
                    
                default:
                    // Ignore other notifications
                    break;
            }
        }
    }
    
    /**
     * Handle initialize request
     */
    private function handleInitialize($request) {
        error_log("Handling initialize request");
        
        $this->clientCapabilities = $request['params']['capabilities'] ?? [];
        
        $result = [
            'protocolVersion' => $request['params']['protocolVersion'] ?? '2025-03-26',
            'serverInfo' => [
                'name' => $this->name,
                'version' => $this->version
            ],
            'capabilities' => [
                'tools' => [
                    'list' => true, 
                    'listChanged' => false,
                    'call' => true
                ],
                'analyzers' => [
                    'supportedLanguages' => ['php'],
                    'analyze' => true
                ]
            ]
        ];
        
        $this->sendResponse($request['id'], $result);
    }
    
    /**
     * Handle tools/list request
     */
    private function handleToolsList($request) {
        error_log("Handling tools/list request");
        
        // Hard-coded tools for Cursor compatibility
        $tools = [
            [
                'name' => 'phpstan-analyze',
                'description' => 'Run PHPStan analysis on PHP code',
                'inputSchema' => json_decode('{
                    "type": "object",
                    "properties": {
                        "code": {"type": "string", "description": "PHP code to analyze"},
                        "level": {"type": "integer", "description": "Analysis level (0-9)", "default": 5},
                        "showProgressBar": {"type": "boolean", "description": "Show progress bar", "default": false}
                    },
                    "required": ["code"]
                }')
            ],
            [
                'name' => 'code-quality-check',
                'description' => 'Check PHP code for quality issues',
                'inputSchema' => json_decode('{
                    "type": "object",
                    "properties": {
                        "code": {"type": "string", "description": "PHP code to check"}
                    },
                    "required": ["code"]
                }')
            ],
            [
                'name' => 'security-scan',
                'description' => 'Scan PHP code for security vulnerabilities',
                'inputSchema' => json_decode('{
                    "type": "object",
                    "properties": {
                        "code": {"type": "string", "description": "PHP code to scan for security issues"}
                    },
                    "required": ["code"]
                }')
            ],
            [
                'name' => 'help',
                'description' => 'Get help on the available tools',
                'inputSchema' => json_decode('{
                    "type": "object",
                    "properties": {}
                }')
            ]
        ];
        
        $this->sendResponse($request['id'], ['tools' => $tools]);
    }
    
    /**
     * Handle tools/call request
     */
    private function handleToolsCall($request) {
        $params = $request['params'] ?? [];
        $toolName = $params['name'] ?? '';
        $toolParams = $params['params'] ?? [];
        
        error_log("Handling tools/call for tool: {$toolName}");
        
        if (!isset($this->tools[$toolName])) {
            $this->sendErrorResponse($request['id'], -32602, "Tool not found: {$toolName}");
            return;
        }
        
        $tool = $this->tools[$toolName];
        
        try {
            $result = ($tool['handler'])($toolParams);
            $this->sendResponse($request['id'], $result);
        } catch (Exception $e) {
            $this->sendErrorResponse(
                $request['id'],
                -32000,
                "Error executing tool: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Handle analyzers/analyze request
     * This is used by Cursor to analyze code
     */
    private function handleAnalyze($request) {
        error_log("Handling analyzers/analyze request");
        
        $params = $request['params'] ?? [];
        $language = $params['language'] ?? '';
        $content = $params['content'] ?? '';
        $path = $params['path'] ?? '';
        
        error_log("Analyzing {$language} content for path: {$path}");
        
        if (empty($content)) {
            $this->sendErrorResponse($request['id'], -32602, "No content provided for analysis");
            return;
        }
        
        if ($language !== 'php') {
            $this->sendResponse($request['id'], [
                'diagnostics' => [],
                'metadata' => [
                    'message' => "Language {$language} is not supported. Only PHP is supported."
                ]
            ]);
            return;
        }
        
        try {
            // Create a temporary file with the code
            $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_analysis_');
            file_put_contents($tempFile . '.php', $content);
            
            // Build the phpstan command
            $level = 5; // Default level
            $progressFlag = '--no-progress';
            
            // Execute phpstan with no colors
            $command = "phpx phpstan analyse {$tempFile}.php --level={$level} {$progressFlag} --error-format=json --no-ansi 2>&1";
            $output = shell_exec($command);
            
            // Clean up temporary file
            @unlink($tempFile);
            if (file_exists($tempFile . '.php')) {
                @unlink($tempFile . '.php');
            }
            
            // Remove any ANSI codes that might break JSON parsing
            $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
            
            // Extract the JSON part from the output
            $analysisResults = null;
            if (preg_match('/(\{.*\})/s', $cleanOutput, $matches)) {
                $jsonStr = $matches[1];
                $analysisResults = json_decode($jsonStr, true);
            }
            
            // Convert PHPStan diagnostics to LSP format
            $diagnostics = [];
            
            if ($analysisResults && isset($analysisResults['files'])) {
                $startLine = 1;
                $startChar = 0;
                
                foreach ($analysisResults['files'] as $file => $fileErrors) {
                    foreach ($fileErrors['messages'] as $error) {
                        $diagnostics[] = [
                            'range' => [
                                'start' => ['line' => (int)$error['line'] - 1, 'character' => $startChar],
                                'end' => ['line' => (int)$error['line'] - 1, 'character' => 1000]
                            ],
                            'severity' => 1, // Error severity in LSP
                            'message' => $error['message'],
                            'source' => 'PHPStan'
                        ];
                    }
                }
            }
            
            // Also run code quality checks
            $lines = explode("\n", $content);
            
            // Check line length
            foreach ($lines as $i => $line) {
                if (strlen($line) > 120) {
                    $diagnostics[] = [
                        'range' => [
                            'start' => ['line' => $i, 'character' => 0],
                            'end' => ['line' => $i, 'character' => strlen($line)]
                        ],
                        'severity' => 2, // Warning severity in LSP
                        'message' => 'Line exceeds 120 characters (' . strlen($line) . ')',
                        'source' => 'CodeQuality'
                    ];
                }
            }
            
            // Return diagnostics in LSP format
            $this->sendResponse($request['id'], [
                'diagnostics' => $diagnostics,
                'metadata' => [
                    'message' => count($diagnostics) > 0 
                        ? "Found " . count($diagnostics) . " issues" 
                        : "No issues found"
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error analyzing: " . $e->getMessage());
            $this->sendErrorResponse(
                $request['id'],
                -32000,
                "Error analyzing code: " . $e->getMessage()
            );
        }
    }
    
    /**
     * Send a JSON-RPC response
     */
    private function sendResponse($id, $result, $error = null) {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $id
        ];
        
        if ($error !== null) {
            $response['error'] = $error;
        } else {
            $response['result'] = $result;
        }
        
        $this->sendMessage($response);
    }
    
    /**
     * Send a JSON-RPC error response
     */
    private function sendErrorResponse($id, $code, $message, $data = null) {
        $error = [
            'code' => $code,
            'message' => $message
        ];
        
        if ($data !== null) {
            $error['data'] = $data;
        }
        
        $this->sendResponse($id, null, $error);
    }
    
    /**
     * Send a JSON-RPC message
     */
    private function sendMessage($message) {
        // Format message with proper JSON-RPC envelope
        $json = json_encode($message);
        
        // Log what we're sending for debugging
        error_log("Sending response: " . (isset($message['method']) ? $message['method'] : 'response'));
        
        try {
            // Send as a single line followed by a newline
            $result = fwrite(STDOUT, $json . "\n");
            if ($result === false) {
                error_log("Error writing to STDOUT");
            }
            
            // Make sure the output is flushed immediately
            $flushResult = fflush(STDOUT);
            if (!$flushResult) {
                error_log("Error flushing STDOUT");
            }
        } catch (Exception $e) {
            error_log("Exception when sending message: " . $e->getMessage());
        }
    }
    
    /**
     * Run the server
     */
    public function run() {
        error_log("PHPStan MCP Server running...");
        
        // Register signal handlers to gracefully handle termination
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function() {
                error_log("Received SIGTERM, shutting down gracefully");
                exit(0);
            });
            pcntl_signal(SIGINT, function() {
                error_log("Received SIGINT, shutting down gracefully");
                exit(0);
            });
        }
        
        // Keep the server running indefinitely
        while (true) {
            // Process input and ignore return value - we never want to exit
            $this->processInput();
            
            // Signal handling if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }
}

// Create a simple MCP server
$server = new SimpleMcpServer('PHPStan-MCP-Server', '1.0.0');

// Register PHPStan analysis tool
$server->registerTool(
    'phpstan-analyze',
    [
        'properties' => [
            'code' => ['type' => 'string', 'description' => 'PHP code to analyze'],
            'level' => ['type' => 'integer', 'description' => 'Analysis level (0-9)', 'default' => 5],
            'showProgressBar' => ['type' => 'boolean', 'description' => 'Show progress bar', 'default' => false],
        ],
        'required' => ['code']
    ],
    function(array $params) {
        // Create a temporary file with the code
        $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_analysis_');
        file_put_contents($tempFile . '.php', $params['code']);
        
        // Build the phpx command
        $level = isset($params['level']) ? (int)$params['level'] : 5;
        $level = max(0, min(9, $level)); // Ensure level is between 0-9
        
        $progressFlag = isset($params['showProgressBar']) && $params['showProgressBar'] ? '' : '--no-progress';
        
        // Execute phpstan via phpx with no colors
        $command = "phpx phpstan analyse {$tempFile}.php --level={$level} {$progressFlag} --error-format=json --no-ansi 2>&1";
        $output = shell_exec($command);
        
        // Clean up temporary file
        @unlink($tempFile);
        if (file_exists($tempFile . '.php')) {
            @unlink($tempFile . '.php');
        }
        
        // Remove any ANSI codes that might break JSON parsing
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
        
        // Extract the JSON part from the output
        $analysisResults = null;
        if (preg_match('/(\{.*\})/s', $cleanOutput, $matches)) {
            $jsonStr = $matches[1];
            $analysisResults = json_decode($jsonStr, true);
        }
        
        // If we couldn't extract or parse the JSON, return the raw output
        if ($analysisResults === null || !is_array($analysisResults)) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "# PHPStan Analysis Results\n\n" .
                                "⚠️ Could not parse PHPStan output.\n\n" .
                                "## Raw Output\n\n```\n{$cleanOutput}\n```\n\n" .
                                "This might indicate an issue with the code format or PHPStan configuration."
                    ]
                ]
            ];
        }
        
        $formattedResponse = "# PHPStan Analysis Results\n\n";
        
        if (isset($analysisResults['totals']['errors']) && $analysisResults['totals']['errors'] === 0) {
            $formattedResponse .= "✅ No errors found at level {$level}.\n";
        } else {
            $errors = $analysisResults['files'] ?? [];
            $formattedResponse .= "❌ Found " . ($analysisResults['totals']['errors'] ?? 'unknown number of') . " errors at level {$level}.\n\n";
            
            foreach ($errors as $file => $fileErrors) {
                $formattedResponse .= "## File: " . basename($file) . "\n\n";
                foreach ($fileErrors['messages'] as $error) {
                    $formattedResponse .= "- **Line {$error['line']}:** {$error['message']}\n";
                }
                $formattedResponse .= "\n";
            }
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $formattedResponse
                ]
            ]
        ];
    }
);

// Register code quality check tool
$server->registerTool(
    'code-quality-check',
    [
        'properties' => [
            'code' => ['type' => 'string', 'description' => 'PHP code to check'],
        ],
        'required' => ['code']
    ],
    function(array $params) {
        // Create a temporary file with the code
        $tempFile = tempnam(sys_get_temp_dir(), 'code_quality_');
        file_put_contents($tempFile . '.php', $params['code']);
        
        // Build a simple quality check (line length, function complexity)
        $issues = [];
        $lines = explode("\n", $params['code']);
        
        // Check line length
        foreach ($lines as $i => $line) {
            $lineNumber = $i + 1;
            if (strlen($line) > 120) {
                $issues[] = [
                    'line' => $lineNumber,
                    'type' => 'Line Length',
                    'message' => 'Line exceeds 120 characters (' . strlen($line) . ')'
                ];
            }
        }
        
        // Count function parameters
        preg_match_all('/function\s+\w+\s*\((.*?)\)/s', $params['code'], $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $functionParams = $match[1];
            $paramCount = $functionParams ? count(explode(',', $functionParams)) : 0;
            if ($paramCount > 5) {
                // Find the line number
                $pos = strpos($params['code'], $match[0]);
                $lineNumber = count(explode("\n", substr($params['code'], 0, $pos)));
                
                $issues[] = [
                    'line' => $lineNumber,
                    'type' => 'Function Complexity',
                    'message' => "Function has {$paramCount} parameters (recommended max: 5)"
                ];
            }
        }
        
        // Clean up temporary file
        @unlink($tempFile);
        if (file_exists($tempFile . '.php')) {
            @unlink($tempFile . '.php');
        }
        
        // Format the response
        $formattedResponse = "# Code Quality Analysis\n\n";
        
        if (empty($issues)) {
            $formattedResponse .= "✅ No quality issues detected.\n";
        } else {
            $formattedResponse .= "❌ Found " . count($issues) . " quality issues:\n\n";
            
            foreach ($issues as $issue) {
                $formattedResponse .= "- **Line {$issue['line']} - {$issue['type']}:** {$issue['message']}\n";
            }
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $formattedResponse
                ]
            ]
        ];
    }
);

// Register security scan tool
$server->registerTool(
    'security-scan',
    [
        'properties' => [
            'code' => ['type' => 'string', 'description' => 'PHP code to scan for security issues'],
        ],
        'required' => ['code']
    ],
    function(array $params) {
        // Create a temporary file with the code
        $tempFile = tempnam(sys_get_temp_dir(), 'security_scan_');
        file_put_contents($tempFile . '.php', $params['code']);
        
        // Basic security patterns to check
        $securityPatterns = [
            'SQL Injection' => [
                'pattern' => '/\$(?:sql|query).*?\$_(?:GET|POST|REQUEST|COOKIE)/i',
                'recommendation' => 'Use prepared statements or parameterized queries'
            ],
            'XSS Vulnerability' => [
                'pattern' => '/echo\s+\$_(?:GET|POST|REQUEST|COOKIE)/i',
                'recommendation' => 'Use htmlspecialchars() to escape output'
            ],
            'Command Injection' => [
                'pattern' => '/(?:system|exec|shell_exec|passthru|proc_open)\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i',
                'recommendation' => 'Avoid using exec functions with user input'
            ],
            'File Inclusion' => [
                'pattern' => '/(?:include|require|include_once|require_once)\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)/i',
                'recommendation' => 'Validate file paths before inclusion'
            ],
            'Sensitive Data Exposure' => [
                'pattern' => '/(?:password|secret|credential|key|token)\s*=\s*[\'"][^\'"]+[\'"]/i',
                'recommendation' => 'Avoid hardcoding sensitive data'
            ]
        ];
        
        // Scan for vulnerabilities
        $vulnerabilities = [];
        foreach ($securityPatterns as $type => $details) {
            if (preg_match_all($details['pattern'], $params['code'], $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $code = $match[0];
                    $pos = $match[1];
                    
                    // Find the line number
                    $lineNumber = count(explode("\n", substr($params['code'], 0, $pos)));
                    
                    $vulnerabilities[] = [
                        'line' => $lineNumber,
                        'type' => $type,
                        'code' => $code,
                        'recommendation' => $details['recommendation']
                    ];
                }
            }
        }
        
        // Clean up temporary file
        @unlink($tempFile);
        if (file_exists($tempFile . '.php')) {
            @unlink($tempFile . '.php');
        }
        
        // Format the response
        $formattedResponse = "# Security Scan Results\n\n";
        
        if (empty($vulnerabilities)) {
            $formattedResponse .= "✅ No security vulnerabilities detected.\n";
        } else {
            $formattedResponse .= "⚠️ Found " . count($vulnerabilities) . " potential security issues:\n\n";
            
            foreach ($vulnerabilities as $vuln) {
                $formattedResponse .= "## Line {$vuln['line']}: {$vuln['type']}\n\n";
                $formattedResponse .= "```php\n{$vuln['code']}\n```\n\n";
                $formattedResponse .= "**Recommendation:** {$vuln['recommendation']}\n\n";
            }
        }
        
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $formattedResponse
                ]
            ]
        ];
    }
);

// Register help tool
$server->registerTool(
    'help',
    [
        'properties' => [],
        'required' => []
    ],
    function() {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "# PHPStan MCP Server\n\n" .
                             "This server provides PHP static analysis tools via MCP, powered by PHPStan.\n\n" .
                             "## Available Tools:\n\n" .
                             "- `phpstan-analyze`: Run PHPStan analysis on PHP code\n" .
                             "- `code-quality-check`: Check PHP code for quality issues\n" .
                             "- `security-scan`: Scan PHP code for security vulnerabilities\n"
                ]
            ]
        ];
    }
);

// Run the server
$server->run();