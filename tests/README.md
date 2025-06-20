# MCP PHP SDK Test Suite

This directory contains the comprehensive test suite for the Model Context Protocol (MCP) PHP SDK. The test suite is designed to ensure code quality, protocol compliance, and production readiness.

## Test Structure

### Test Organization

```
tests/
├── TestCase.php                    # Base test case with common utilities
├── Unit/                          # Unit tests for individual components
│   ├── Protocol/                  # Protocol layer tests
│   ├── Server/                    # Server components tests
│   ├── Transport/                 # Transport layer tests
│   └── Utilities/                 # Utility classes tests
├── Integration/                   # End-to-end integration tests
└── Protocol/                      # MCP protocol compliance tests
```

### Test Categories

#### 1. Unit Tests (`tests/Unit/`)
- **Purpose**: Test individual classes and methods in isolation
- **Coverage**: All major classes in `src/` directory
- **Focus**: Logic correctness, edge cases, error handling

**Key Test Files:**
- `Protocol/JsonRpcMessageTest.php` - JSON-RPC message handling
- `Protocol/ErrorResponseBuilderTest.php` - Error response generation
- `Server/ToolManagerTest.php` - Tool management functionality
- `Server/ResourceManagerTest.php` - Resource management functionality
- `Server/PromptManagerTest.php` - Prompt management functionality

#### 2. Integration Tests (`tests/Integration/`)
- **Purpose**: Test complete workflows and component interactions
- **Coverage**: End-to-end scenarios
- **Focus**: Real-world usage patterns, component integration

**Key Test Files:**
- `McpServerIntegrationTest.php` - Complete server workflows

#### 3. Protocol Compliance Tests (`tests/Protocol/`)
- **Purpose**: Validate MCP protocol specification compliance
- **Coverage**: All MCP protocol requirements
- **Focus**: Specification adherence, message formats, error codes

**Key Test Files:**
- `McpProtocolComplianceTest.php` - MCP specification validation

## Running Tests

### Prerequisites

```bash
# Install dependencies
composer install
```

### Basic Test Execution

```bash
# Run all tests
composer test

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration
./vendor/bin/phpunit --testsuite=Protocol

# Run specific test file
./vendor/bin/phpunit tests/Unit/Protocol/JsonRpcMessageTest.php

# Run with verbose output
./vendor/bin/phpunit --testdox
```

### Coverage Reports

```bash
# Generate HTML coverage report
composer test-coverage

# View coverage report
open coverage-report/index.html
```

### Code Quality Checks

```bash
# Run all quality checks
composer quality

# Individual quality tools
composer phpstan      # Static analysis
composer cs-check     # Code style check
composer phpmd        # Mess detection
```

## Test Utilities

### Base Test Case (`TestCase.php`)

The base `TestCase` class provides common utilities for all tests:

#### Mock Creation
```php
// Create mock JSON-RPC request
$request = $this->createMockRequest('test/method', ['param' => 'value']);

// Create mock JSON-RPC response
$response = $this->createMockResponse(['result' => 'success']);
```

#### Validation Helpers
```php
// Validate JSON-RPC response structure
$this->assertValidJsonRpcResponse($response);

// Validate error response structure
$this->assertValidErrorResponse($errorArray);

// Validate MCP tool response
$this->assertValidToolResponse($toolResponse);

// Validate MCP resource response
$this->assertValidResourceResponse($resourceResponse);

// Validate MCP prompt response
$this->assertValidPromptResponse($promptResponse);
```

#### Reflection Utilities
```php
// Access private/protected properties
$value = $this->getPropertyValue($object, 'propertyName');
$this->setPropertyValue($object, 'propertyName', $value);

// Call private/protected methods
$result = $this->callMethod($object, 'methodName', [$arg1, $arg2]);
```

#### File Utilities
```php
// Create temporary test files
$tempFile = $this->createTempFile('content');
// Files are automatically cleaned up after test
```

## Writing Tests

### Test Naming Conventions

- Test classes: `{ClassName}Test.php`
- Test methods: `test{MethodName}()` or descriptive names
- Use descriptive test method names that explain what is being tested

### Test Structure

```php
<?php

namespace ModelContextProtocol\Tests\Unit\YourNamespace;

use ModelContextProtocol\Tests\TestCase;
use YourClassUnderTest;

class YourClassTest extends TestCase
{
    private YourClassUnderTest $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->instance = new YourClassUnderTest();
    }

    public function testMethodBehavior(): void
    {
        // Arrange
        $input = 'test input';
        
        // Act
        $result = $this->instance->method($input);
        
        // Assert
        $this->assertEquals('expected', $result);
    }

    public function testErrorHandling(): void
    {
        $this->expectException(SomeException::class);
        $this->expectExceptionMessage('Expected error message');
        
        $this->instance->methodThatThrows();
    }
}
```

### Testing Guidelines

1. **Arrange-Act-Assert Pattern**: Structure tests clearly
2. **One Concept Per Test**: Each test should verify one specific behavior
3. **Descriptive Names**: Test names should explain what is being tested
4. **Edge Cases**: Test boundary conditions and error scenarios
5. **Mock External Dependencies**: Use mocks for external services
6. **Clean Up**: Ensure tests don't affect each other

## Continuous Integration

### GitHub Actions

The CI pipeline (`.github/workflows/ci.yml`) runs:

1. **Test Matrix**: PHP 8.1, 8.2, 8.3 with different dependency versions
2. **Quality Checks**: PHPStan, PHP_CodeSniffer, PHPMD
3. **Security Scan**: Composer audit
4. **Integration Tests**: End-to-end workflow validation
5. **Protocol Compliance**: MCP specification validation
6. **Coverage Reports**: Code coverage analysis

### Quality Gates

- **Code Coverage**: Minimum 90% coverage required
- **Static Analysis**: PHPStan level 8 must pass
- **Code Style**: PSR-12 compliance required
- **No Security Issues**: Security audit must pass

## Test Data and Fixtures

### Mock Data

Tests use consistent mock data:

```php
// Standard test request
$request = $this->createMockRequest('test/method', [
    'param1' => 'value1',
    'param2' => 'value2'
]);

// Standard tool schema
$toolSchema = [
    'properties' => [
        'input' => ['type' => 'string', 'description' => 'Input parameter']
    ],
    'required' => ['input'],
    'description' => 'Test tool description'
];
```

### Test Environment

- **Isolation**: Each test runs in isolation
- **Temporary Files**: Automatically cleaned up
- **No External Dependencies**: Tests don't require external services
- **Deterministic**: Tests produce consistent results

## Debugging Tests

### Useful Commands

```bash
# Run specific test with debug output
./vendor/bin/phpunit --testdox --verbose tests/Unit/Protocol/JsonRpcMessageTest.php

# Run failed tests only
./vendor/bin/phpunit --testdox --stop-on-failure

# Debug with coverage
./vendor/bin/phpunit --testdox --coverage-text
```

### Common Issues

1. **Missing Dependencies**: Run `composer install`
2. **Permission Errors**: Check file permissions
3. **Memory Issues**: Increase PHP memory limit
4. **Timeout Issues**: Check for infinite loops

## Contributing

When adding new features:

1. **Write Tests First**: Follow TDD when possible
2. **Update Documentation**: Keep this README current
3. **Maintain Coverage**: Ensure >90% code coverage
4. **Follow Conventions**: Use established patterns
5. **Test Edge Cases**: Include error scenarios

For questions about testing, please refer to the main project documentation or open an issue. 