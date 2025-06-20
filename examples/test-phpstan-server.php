<?php

/**
 * Test script for sending requests to the PHPStan MCP server
 */

// We'll simulate an MCP client by sending JSON-RPC requests to the server's STDIN

// 1. First, let's send an initialization request
$initRequest = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'initialize',
    'params' => [
        'protocolVersion' => '2025-03-26',
        'capabilities' => [
            'tools' => [
                'list' => true
            ]
        ],
        'client' => [
            'name' => 'test-client',
            'version' => '1.0.0'
        ]
    ]
];

// 2. Then, let's send a tools/list request to see available tools
$listToolsRequest = [
    'jsonrpc' => '2.0',
    'id' => 2,
    'method' => 'tools/list',
    'params' => []
];

// 3. Let's execute the phpstan-analyze tool on the project files
$analyzeRequest = [
    'jsonrpc' => '2.0',
    'id' => 3,
    'method' => 'tools/call',
    'params' => [
        'name' => 'phpstan-analyze',
        'params' => [
            // Use current working directory as project path
            'projectPath' => getcwd(),
            // Analyze the src directory
            'paths' => ['src'],
            // Set analysis level
            'level' => 5,
            'showProgressBar' => false
        ]
    ]
];

// 3b. Let's also execute the phpstan-analyze tool with a code snippet that has errors
$analyzeCodeRequest = [
    'jsonrpc' => '2.0',
    'id' => 3.5,
    'method' => 'tools/call',
    'params' => [
        'name' => 'phpstan-analyze',
        'params' => [
            'code' => '<?php
function add($a, $b) {
    return $a + $c; // Undefined variable $c
}

$result = add("5", 10); // Type mismatch, string + int
echo $result;
',
            'level' => 2,
            'showProgressBar' => false
        ]
    ]
];

// 4. Let's execute the code-quality-check tool with a badly formatted PHP code
$qualityRequest = [
    'jsonrpc' => '2.0',
    'id' => 4,
    'method' => 'tools/call',
    'params' => [
        'name' => 'code-quality-check',
        'params' => [
            'code' => '<?php
function processData($data, $option1, $option2, $option3, $option4, $option5, $option6) { // Too many parameters
    // This is a very long line that exceeds the recommended 120 character limit and should be detected by our quality check tool as an issue to fix
    return $data;
}
'
        ]
    ]
];

// 5. Let's execute the security-scan tool with code containing security vulnerabilities
$securityRequest = [
    'jsonrpc' => '2.0',
    'id' => 5,
    'method' => 'tools/call',
    'params' => [
        'name' => 'security-scan',
        'params' => [
            'code' => '<?php
$username = $_GET["username"];
$password = $_GET["password"];
$query = "SELECT * FROM users WHERE username = \'" . $username . "\' AND password = \'" . $password . "\'";
echo $query;

// Execute user-provided command directly
$command = $_POST["command"];
system($command);

// Include user-provided file
$file = $_GET["file"];
include($file);

// Database connection with hardcoded credentials
$dbPassword = "supersecretpassword123";
'
        ]
    ]
];

// Function to send a request and receive a response
function sendRequest($request) {
    // Start the server process
    $descriptorspec = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];
    
    $process = proc_open('php ' . __DIR__ . '/phpstan-mcp-server.php', $descriptorspec, $pipes);
    
    if (!is_resource($process)) {
        die("Failed to start the server process");
    }
    
    // Prepare the request with Content-Length header
    $jsonRequest = json_encode($request);
    $message = "Content-Length: " . strlen($jsonRequest) . "\r\n\r\n" . $jsonRequest;
    
    // Send the request
    fwrite($pipes[0], $message);
    
    // Read the response
    $response = '';
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        $response .= $line;
        
        // Check if we've reached the end of headers
        if ($line === "\r\n") {
            // Parse Content-Length
            if (preg_match('/Content-Length: (\d+)/', $response, $matches)) {
                $contentLength = (int) $matches[1];
                $content = fread($pipes[1], $contentLength);
                $response .= $content;
                break;
            }
        }
    }
    
    // Close pipes and process
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    
    // Parse and return the JSON-RPC response
    if (preg_match('/\{.*\}/s', $response, $matches)) {
        return json_decode($matches[0], true);
    }
    
    return null;
}

// Execute the test

echo "Running tests for PHPStan MCP Server...\n\n";

// Test 1: Initialize
echo "Test 1: Initialize request\n";
$initResponse = sendRequest($initRequest);
echo "Response: " . json_encode($initResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: List tools
echo "Test 2: List tools request\n";
$listResponse = sendRequest($listToolsRequest);
echo "Response: " . json_encode($listResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Run PHPStan analysis on project
echo "Test 3: PHPStan project analysis\n";
$analyzeResponse = sendRequest($analyzeRequest);
echo "Response: " . json_encode($analyzeResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test 3b: Run PHPStan analysis on code snippet
echo "Test 3b: PHPStan code snippet analysis\n";
$analyzeCodeResponse = sendRequest($analyzeCodeRequest);
echo "Response: " . json_encode($analyzeCodeResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Check code quality
echo "Test 4: Code quality check\n";
$qualityResponse = sendRequest($qualityRequest);
echo "Response: " . json_encode($qualityResponse, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Security scan
echo "Test 5: Security scan\n";
$securityResponse = sendRequest($securityRequest);
echo "Response: " . json_encode($securityResponse, JSON_PRETTY_PRINT) . "\n\n";

echo "Tests completed.\n";