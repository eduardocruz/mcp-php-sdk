<?php

/**
 * Direct test of the PHPStan analysis functionality
 */

// Find the phpstan tool using phpx with no ANSI colors
$output = shell_exec("phpx phpstan analyse " . __DIR__ . "/test-code.php --level=5 --error-format=json --no-ansi 2>&1");

// Clean any ANSI colors that might remain
$cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

echo "Raw output from PHPStan:\n";
echo $output . "\n\n";

// Try to extract the JSON part from the output
if (preg_match('/(\{.*\})/s', $cleanOutput, $matches)) {
    echo "Extracted JSON:\n";
    echo $matches[1] . "\n\n";
    
    // Try to parse the extracted JSON
    $analysisResults = json_decode($matches[1], true);
    
    echo "Parsed JSON result:\n";
    var_dump($analysisResults);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON parsing error: " . json_last_error_msg() . "\n";
    }
} else {
    echo "Could not extract JSON from output.\n";
}