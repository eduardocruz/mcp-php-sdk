<?php

require_once __DIR__ . '/../vendor/autoload.php';

use ModelContextProtocol\Server\McpServer;
use ModelContextProtocol\Transport\StdioTransport;

/**
 * PHPStan MCP Server
 * 
 * This server provides PHP static analysis tools via MCP, powered by PHPStan.
 * It offers code analysis, quality checks, and security scanning for PHP code.
 */

// Create an MCP server instance
$server = new McpServer('PHPStan-MCP-Server', '1.0.0');

// Enable capabilities for tools, resources, and prompts
$server->registerToolCapabilities(true);
$server->registerResourceCapabilities(true, true);
$server->registerPromptCapabilities(true);

// Register a static resource for server information
$server->registerResource(
    'server-info',
    'phpstan://info',
    [
        [
            'type' => 'text',
            'text' => "# PHPStan MCP Server\n\nThis server provides PHP static analysis tools via MCP, powered by PHPStan.\nIt offers code analysis, quality checks, and security scanning for PHP code.\n\n## Available Tools:\n- phpstan-analyze: Analyze PHP code with PHPStan\n- code-quality-check: Check code quality issues"
        ]
    ],
    ['description' => 'Information about the PHPStan MCP Server']
);

// Register a dynamic resource for analysis results
use ModelContextProtocol\Protocol\Resources\ResourceTemplate;

$analysisTemplate = new ResourceTemplate('phpstan://analysis/{sessionId}', [
    'description' => 'Analysis results for a specific session',
    'examples' => ['session-123', 'session-456']
]);

$server->registerResourceTemplate(
    'analysis-results',
    $analysisTemplate,
    function(string $uri, array $params) {
        $sessionId = $params['sessionId'] ?? 'unknown';
        
        // In a real implementation, you would retrieve stored analysis results
        // For demo purposes, we'll return a sample result
        return [
            'content' => [
                [
                    'type' => 'application/json',
                    'data' => [
                        'sessionId' => $sessionId,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'status' => 'completed',
                        'results' => [
                            'errors' => 0,
                            'warnings' => 2,
                            'files_analyzed' => 5
                        ]
                    ]
                ]
            ]
        ];
    }
);

// PHPStan analysis tool
$server->registerTool(
    'phpstan-analyze',
    [
        'properties' => [
            'code' => [
                'type' => 'string', 
                'description' => 'PHP code to analyze with PHPStan'
            ],
            'projectPath' => [
                'type' => 'string', 
                'description' => 'Path to the project to analyze (optional)'
            ],
            'paths' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Specific paths to analyze (optional)'
            ],
            'level' => [
                'type' => 'integer',
                'description' => 'PHPStan analysis level (0-9)',
                'minimum' => 0,
                'maximum' => 9
            ],
            'showProgressBar' => [
                'type' => 'boolean',
                'description' => 'Whether to show progress bar'
            ]
        ],
        'required' => []
    ],
    function(array $params) {
        // Default values
        $level = $params['level'] ?? 5;
        $showProgressBar = $params['showProgressBar'] ?? false;
        $progressFlag = $showProgressBar ? '' : '--no-progress';
        
        try {
            if (isset($params['code'])) {
                // Analyze provided code snippet
                $tempFile = tempnam(sys_get_temp_dir(), 'phpstan_analysis_');
                file_put_contents($tempFile . '.php', $params['code']);
                
                $command = "phpx phpstan analyse {$tempFile}.php --level={$level} {$progressFlag} --error-format=json --no-ansi 2>&1";
                $output = shell_exec($command);
                
                // Clean up temporary file
                @unlink($tempFile);
                @unlink($tempFile . '.php');
                
            } elseif (isset($params['projectPath']) || isset($params['paths'])) {
                // Analyze project or specific paths
                $targetPath = $params['projectPath'] ?? getcwd();
                $paths = $params['paths'] ?? [$targetPath];
                $pathsStr = implode(' ', array_map('escapeshellarg', $paths));
                
                $command = "phpx phpstan analyse {$pathsStr} --level={$level} {$progressFlag} --error-format=json --no-ansi 2>&1";
                $output = shell_exec($command);
            } else {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "❌ Please provide either 'code' to analyze a snippet or 'projectPath'/'paths' to analyze files."
                        ]
                    ]
                ];
            }
            
            // Clean ANSI codes and extract JSON
            $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
            $analysisResults = null;
            if (preg_match('/(\{.*\})/s', $cleanOutput, $matches)) {
                $analysisResults = json_decode($matches[1], true);
            }
            
            // Format results
            $formattedResponse = "# PHPStan Analysis Results\n\n";
            
            if ($analysisResults && isset($analysisResults['totals']['file_errors'])) {
                $totalErrors = $analysisResults['totals']['file_errors'];
                
                if ($totalErrors === 0) {
                    $formattedResponse .= "✅ No errors found! Your code looks good.\n";
                } else {
                    $formattedResponse .= "❌ Found {$totalErrors} issues:\n\n";
                    
                    foreach ($analysisResults['files'] as $file => $fileData) {
                        $relativePath = basename($file);
                        $formattedResponse .= "## {$relativePath}\n\n";
                        
                        foreach ($fileData['messages'] as $error) {
                            $formattedResponse .= "- **Line {$error['line']}:** {$error['message']}\n";
                        }
                        $formattedResponse .= "\n";
                    }
                }
            } else {
                $formattedResponse .= "⚠️ PHPStan analysis completed but no structured output was generated.\n\n";
                $formattedResponse .= "Raw output:\n```\n{$cleanOutput}\n```\n";
            }
            
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $formattedResponse
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "❌ Error during analysis: " . $e->getMessage()
                    ]
                ]
            ];
        }
    }
);

// Code quality check tool
$server->registerTool(
    'code-quality-check',
    [
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'PHP code to check for quality issues'
            ]
        ],
        'required' => ['code']
    ],
    function(array $params) {
        $code = $params['code'];
        $issues = [];
        $lines = explode("\n", $code);
        
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
        preg_match_all('/function\s+\w+\s*\((.*?)\)/s', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $functionParams = trim($match[1]);
            $paramCount = $functionParams ? count(explode(',', $functionParams)) : 0;
            if ($paramCount > 5) {
                $pos = strpos($code, $match[0]);
                $lineNumber = count(explode("\n", substr($code, 0, $pos)));
                
                $issues[] = [
                    'line' => $lineNumber,
                    'type' => 'Function Complexity',
                    'message' => "Function has {$paramCount} parameters (recommended max: 5)"
                ];
            }
        }
        
        // Format response
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

// Security scan tool
$server->registerTool(
    'security-scan',
    [
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'PHP code to scan for security vulnerabilities'
            ]
        ],
        'required' => ['code']
    ],
    function(array $params) {
        $code = $params['code'];
        
        // Security patterns to check
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
            if (preg_match_all($details['pattern'], $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $matchedCode = $match[0];
                    $pos = $match[1];
                    $lineNumber = count(explode("\n", substr($code, 0, $pos)));
                    
                    $vulnerabilities[] = [
                        'line' => $lineNumber,
                        'type' => $type,
                        'code' => $matchedCode,
                        'recommendation' => $details['recommendation']
                    ];
                }
            }
        }
        
        // Format response
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

// Register prompts for common PHP analysis scenarios
$server->registerPrompt(
    'php-code-review',
    [
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'PHP code to review'
            ],
            'focus' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Areas to focus on (security, performance, maintainability, etc.)',
                'default' => ['security', 'performance', 'maintainability']
            ]
        ],
        'required' => ['code'],
        'description' => 'Generate a comprehensive PHP code review prompt'
    ],
    function(array $params) {
        $code = $params['code'];
        $focus = $params['focus'] ?? ['security', 'performance', 'maintainability'];
        $focusAreas = implode(', ', $focus);
        
        return [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => "You are an expert PHP developer and code reviewer. Focus your review on: {$focusAreas}."
                    ]
                ],
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "Please review this PHP code and provide detailed feedback:\n\n```php\n{$code}\n```\n\nFocus on: {$focusAreas}\n\nProvide specific recommendations for improvement."
                    ]
                ]
            ],
            'description' => "PHP code review focusing on {$focusAreas}"
        ];
    }
);

$server->registerPrompt(
    'php-refactoring-suggestions',
    [
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'PHP code to refactor'
            ],
            'goals' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Refactoring goals',
                'default' => ['readability', 'maintainability', 'performance']
            ]
        ],
        'required' => ['code'],
        'description' => 'Generate refactoring suggestions for PHP code'
    ],
    function(array $params) {
        $code = $params['code'];
        $goals = $params['goals'] ?? ['readability', 'maintainability', 'performance'];
        $goalsList = implode(', ', $goals);
        
        return [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => "You are an expert PHP developer specializing in code refactoring. Your goal is to improve: {$goalsList}."
                    ]
                ],
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "Please analyze this PHP code and suggest refactoring improvements:\n\n```php\n{$code}\n```\n\nGoals: {$goalsList}\n\nProvide specific refactoring suggestions with code examples."
                    ]
                ]
            ],
            'description' => "PHP refactoring suggestions for {$goalsList}"
        ];
    }
);

$server->registerPrompt(
    'php-documentation',
    [
        'properties' => [
            'code' => [
                'type' => 'string',
                'description' => 'PHP code to document'
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Documentation style',
                'enum' => ['phpdoc', 'inline', 'readme'],
                'default' => 'phpdoc'
            ]
        ],
        'required' => ['code'],
        'description' => 'Generate documentation for PHP code'
    ],
    function(array $params) {
        $code = $params['code'];
        $style = $params['style'] ?? 'phpdoc';
        
        return [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => "You are an expert PHP developer and technical writer. Generate {$style}-style documentation."
                    ]
                ],
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => "Please generate comprehensive {$style} documentation for this PHP code:\n\n```php\n{$code}\n```\n\nInclude parameter descriptions, return values, usage examples, and any important notes."
                    ]
                ]
            ],
            'description' => "PHP documentation in {$style} style"
        ];
    }
);

// Help tool
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
                             "- `security-scan`: Scan PHP code for security vulnerabilities\n" .
                             "- `help`: Show this help message\n\n" .
                             "## Available Prompts:\n\n" .
                             "- `php-code-review`: Generate comprehensive PHP code review prompts\n" .
                             "- `php-refactoring-suggestions`: Generate refactoring suggestions for PHP code\n" .
                             "- `php-documentation`: Generate documentation for PHP code\n\n" .
                             "## Usage Examples:\n\n" .
                             "### Analyze code snippet:\n" .
                             "```json\n" .
                             '{"name": "phpstan-analyze", "params": {"code": "<?php echo $undefined;"}}' . "\n" .
                             "```\n\n" .
                             "### Analyze project:\n" .
                             "```json\n" .
                             '{"name": "phpstan-analyze", "params": {"projectPath": "/path/to/project", "level": 5}}' . "\n" .
                             "```\n"
                ]
            ]
        ];
    }
);

// Show diagnostic information at startup
$projectPath = $argv[1] ?? getcwd();
error_log("PHPStan MCP Server initialized with project path: " . $projectPath);

// Connect to stdio transport and start the server
$transport = new StdioTransport();
$server->connect($transport);

// Process input continuously
while (true) {
    $transport->processInput();
    usleep(10000); // Sleep for 10ms to prevent busy waiting
}