# MCP PHP SDK Examples

This directory contains example MCP servers implemented using the PHP SDK.

## PHPStan MCP Server

The PHPStan MCP server (`phpstan-mcp-server.php`) demonstrates how to create an MCP server that provides PHP static analysis tools, primarily leveraging [PHPStan](https://phpstan.org/) - a popular static analysis tool for PHP. This server can analyze PHP code for errors, quality issues, and security vulnerabilities.

### Features

- **PHPStan Analysis**: Run PHPStan static analysis on PHP code
- **Code Quality Check**: Check code for quality issues like long lines and complex functions
- **Security Scanning**: Detect potential security vulnerabilities in PHP code

### Running the Server with Cursor

To run the server with Cursor:

1. **Use the simplified MCP server**: We've created a more robust version that works better with Cursor:
   ```bash
   php /path/to/mcp-php-sdk/examples/fixed-phpstan-mcp-server.php
   ```

2. **Configure in Cursor**:
   - The server is automatically configured in the `.cursor/mcp.json` file with the name `php-analyzer`
   - In Cursor, select the "php-analyzer" server from the dropdown
   - Or press Cmd+Shift+L (or Ctrl+Shift+L on Windows) and select it from the list

The server communicates via STDIN/STDOUT using the MCP protocol.

### Example Usage

When connected to an LLM via the MCP protocol, the PHP Analyzer server allows the LLM to:

1. Run PHPStan analysis on PHP code:
   ```
   Use the phpstan-analyze tool to analyze this code: 
   <?php
   function add($a, $b) {
     return $a + $b;
   }
   ```

2. Check code quality:
   ```
   Use the code-quality-check tool to check the quality of this PHP function:
   <?php
   function processData($data, $option1, $option2, $option3, $option4, $option5, $option6) {
     // Complex function with too many parameters
     return $data;
   }
   ```

3. Scan for security vulnerabilities:
   ```
   Use the security-scan tool to check this code for security issues:
   <?php
   $username = $_GET['username'];
   $password = $_GET['password'];
   $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
   echo $query;
   ```

## Using with Cursor

When you connect to the PHPStan MCP server in Cursor, you'll be able to:

1. Analyze PHP code for errors using PHPStan:
   - You can ask Claude to "analyze this PHP code" or "find errors in this PHP function"
   - Claude will use the PHPStan tool to provide detailed static analysis

2. Check code quality:
   - Ask "check the quality of this PHP code" or "find style issues in this function"
   - Claude will identify issues like excessive parameter counts or line lengths

3. Scan for security vulnerabilities:
   - Request "scan this code for security issues" or "find security vulnerabilities in this PHP file"
   - Claude will identify common security issues like SQL injection risks

### Example Commands to Try

Once connected to the PHPStan MCP server, try asking Claude:

- "Analyze this PHP function for errors:
  ```php
  function add($a, $b) {
      return $a + $c; // Undefined variable $c
  }
  ```"

- "Check this PHP code for quality issues:
  ```php
  function processData($data, $option1, $option2, $option3, $option4, $option5, $option6) {
      // Function with too many parameters
      return $data;
  }
  ```"

- "Scan this PHP code for security vulnerabilities:
  ```php
  $username = $_GET['username'];
  $query = "SELECT * FROM users WHERE username = '$username'";
  echo $query;
  ```"

## Additional Examples

More examples demonstrating other aspects of the MCP protocol will be added in the future.