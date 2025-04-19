# Tools API Reference

This page documents the classes and interfaces related to tools in the PHP MCP SDK.

## ToolManager

`ToolManager` is responsible for managing tool registration, discovery, and execution.

### Class Definition

```php
namespace MCP\Server\Tools;

use MCP\Server\Tools\Schema\ToolSchema;
use MCP\Server\Tools\Schema\Validator;
use MCP\Server\Tools\Schema\ValidationException;

class ToolManager
{
    // Methods
    public function __construct();
    public function register(string $name, array|ToolSchema $schema, callable $handler): Tool;
    public function getTool(string $name): ?Tool;
    public function getSchema(string $name): ?ToolSchema;
    public function execute(string $name, array $params): mixed;
    public function list(): array;
    public function exists(string $name): bool;
    public function count(): int;
    public function remove(string $name): bool;
}
```

### Methods

#### `__construct()`

Creates a new ToolManager instance.

#### `register(string $name, array|ToolSchema $schema, callable $handler): Tool`

Registers a new tool with the given name, schema, and handler function.

**Parameters:**
- `$name`: The name of the tool
- `$schema`: The schema for the tool, either as an array or a ToolSchema object
- `$handler`: The handler function that will be called when the tool is executed

**Returns:**
- The registered Tool object

**Example:**
```php
$toolManager->register('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
], function($params) {
    return $params['a'] + $params['b'];
});
```

#### `getTool(string $name): ?Tool`

Gets a tool by name.

**Parameters:**
- `$name`: The name of the tool

**Returns:**
- The Tool object, or null if not found

#### `getSchema(string $name): ?ToolSchema`

Gets a tool's schema by name.

**Parameters:**
- `$name`: The name of the tool

**Returns:**
- The ToolSchema object, or null if not found

#### `execute(string $name, array $params): mixed`

Executes a tool with the given parameters.

**Parameters:**
- `$name`: The name of the tool to execute
- `$params`: The parameters to pass to the tool

**Returns:**
- The result of the tool execution

**Throws:**
- `\Exception`: If the tool is not found
- `ValidationException`: If the parameters are invalid

#### `list(): array`

Lists all registered tools.

**Returns:**
- An array of tool metadata

#### `exists(string $name): bool`

Checks if a tool exists.

**Parameters:**
- `$name`: The name of the tool

**Returns:**
- True if the tool exists, false otherwise

#### `count(): int`

Gets the number of registered tools.

**Returns:**
- The number of registered tools

#### `remove(string $name): bool`

Removes a tool.

**Parameters:**
- `$name`: The name of the tool to remove

**Returns:**
- True if the tool was removed, false if it wasn't found

## Tool

`Tool` represents a tool that can be executed.

### Class Definition

```php
namespace MCP\Server\Tools;

use MCP\Server\Tools\Schema\ToolSchema;

class Tool
{
    // Methods
    public function __construct(string $name, ToolSchema $schema, callable $handler);
    public function getName(): string;
    public function getSchema(): ToolSchema;
    public function execute(array $params);
    public function getMetadata(): array;
}
```

### Methods

#### `__construct(string $name, ToolSchema $schema, callable $handler)`

Creates a new Tool instance.

**Parameters:**
- `$name`: The name of the tool
- `$schema`: The schema for the tool
- `$handler`: The handler function that will be called when the tool is executed

#### `getName(): string`

Gets the tool name.

**Returns:**
- The name of the tool

#### `getSchema(): ToolSchema`

Gets the tool schema.

**Returns:**
- The ToolSchema object

#### `execute(array $params)`

Executes the tool with the given parameters.

**Parameters:**
- `$params`: The parameters to pass to the tool

**Returns:**
- The result of the tool execution

#### `getMetadata(): array`

Gets the tool metadata for discovery.

**Returns:**
- An array containing the tool's metadata

## ToolSchema

`ToolSchema` represents the schema for a tool.

### Class Definition

```php
namespace MCP\Server\Tools\Schema;

class ToolSchema
{
    // Methods
    public function __construct(string $name, array $properties, array $required = [], ?string $description = null);
    public function getName(): string;
    public function getProperties(): array;
    public function getRequired(): array;
    public function getDescription(): ?string;
    public function toArray(): array;
    public static function fromArray(string $name, array $schema): self;
}
```

### Methods

#### `__construct(string $name, array $properties, array $required = [], ?string $description = null)`

Creates a new ToolSchema instance.

**Parameters:**
- `$name`: The name of the tool
- `$properties`: The properties of the tool schema
- `$required`: The required properties
- `$description`: The tool description

#### `getName(): string`

Gets the tool name.

**Returns:**
- The name of the tool

#### `getProperties(): array`

Gets the tool properties.

**Returns:**
- The properties array

#### `getRequired(): array`

Gets the required properties.

**Returns:**
- The required properties array

#### `getDescription(): ?string`

Gets the tool description.

**Returns:**
- The description, or null if not set

#### `toArray(): array`

Converts the schema to an array representation.

**Returns:**
- The schema as an array

#### `fromArray(string $name, array $schema): self`

Creates a ToolSchema from an array representation.

**Parameters:**
- `$name`: The name of the tool
- `$schema`: The schema as an array

**Returns:**
- A new ToolSchema instance

## ToolResponse

`ToolResponse` represents a response from a tool execution.

### Class Definition

```php
namespace MCP\Server\Tools;

class ToolResponse
{
    // Methods
    public function __construct(array $content);
    public static function text(string $text): self;
    public static function json(mixed $data): self;
    public static function error(string $message, ?string $code = null): self;
    public function getContent(): array;
    public function toArray(): array;
}
```

### Methods

#### `__construct(array $content)`

Creates a new ToolResponse instance.

**Parameters:**
- `$content`: The response content

#### `text(string $text): self`

Creates a text response.

**Parameters:**
- `$text`: The text content

**Returns:**
- A new ToolResponse instance

#### `json(mixed $data): self`

Creates a JSON response.

**Parameters:**
- `$data`: The data to include in the response

**Returns:**
- A new ToolResponse instance

#### `error(string $message, ?string $code = null): self`

Creates an error response.

**Parameters:**
- `$message`: The error message
- `$code`: The error code

**Returns:**
- A new ToolResponse instance

#### `getContent(): array`

Gets the response content.

**Returns:**
- The content array

#### `toArray(): array`

Converts the response to an array.

**Returns:**
- The response as an array

## Validator

`Validator` validates parameters against a schema.

### Class Definition

```php
namespace MCP\Server\Tools\Schema;

class Validator
{
    // Methods
    public function validate(array $params, ToolSchema $schema): void;
}
```

### Methods

#### `validate(array $params, ToolSchema $schema): void`

Validates parameters against a schema.

**Parameters:**
- `$params`: The parameters to validate
- `$schema`: The schema to validate against

**Throws:**
- `ValidationException`: If validation fails

## ValidationException

`ValidationException` is thrown when schema validation fails.

### Class Definition

```php
namespace MCP\Server\Tools\Schema;

class ValidationException extends \Exception
{
    // Methods
    public function __construct(array $errors, string $message = "Schema validation failed");
    public function getErrors(): array;
}
```

### Methods

#### `__construct(array $errors, string $message = "Schema validation failed")`

Creates a new ValidationException.

**Parameters:**
- `$errors`: The validation errors
- `$message`: The exception message

#### `getErrors(): array`

Gets the validation errors.

**Returns:**
- The errors array

## Using Tools in the McpServer

The `McpServer` class provides methods for working with tools:

```php
// Register a tool
$server->registerTool('add', [
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
], function($params) {
    return $params['a'] + $params['b'];
});

// Unregister a tool
$server->unregisterTool('add');

// Access the tool manager
$toolManager = $server->getToolManager();
```