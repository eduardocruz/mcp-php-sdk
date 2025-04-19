# API Reference

This section provides comprehensive documentation for all public classes, interfaces, and methods in the PHP MCP SDK.

## Core Components

- [Protocol](protocol.md): JSON-RPC 2.0 message types and protocol models
- [Transport](transport.md): Transport implementations for communication
- [Server](server.md): Server implementation and lifecycle handling
- [Client](client.md): Client implementation

## Feature Components

- [Resources](resources.md): URI-addressable content handling
- [Tools](tools.md): Tool registration and execution
- [Prompts](prompts.md): Template-based LLM interactions

## Utility Components

- [Logging](utilities/logging.md): Logging utilities
- [Cancellation](utilities/cancellation.md): Request cancellation handling
- [Progress](utilities/progress.md): Progress reporting

## Class Reference

### Protocol

- `JsonRpcMessage`: Base class for all JSON-RPC 2.0 messages
- `Request`: Represents a JSON-RPC request
- `Response`: Represents a JSON-RPC response
- `Notification`: Represents a JSON-RPC notification
- `ErrorData`: Represents error data in a JSON-RPC response

### Models

- `Implementation`: Describes server/client name and version
- `ClientCapabilities`: Represents client capabilities
- `ServerCapabilities`: Represents server capabilities
- `InitializeParams`: Parameters for initialization requests
- `InitializeResult`: Results for initialization responses

### Transport

- `TransportInterface`: Interface for transport implementations
- `StdioTransport`: Transport for stdio communication
- `HttpTransport`: Transport for HTTP communication with SSE support
- `MessageBuffer`: Helper for buffering and parsing messages

### Server

- `Server`: Core server implementation with lifecycle management
- `McpServer`: High-level server with capability advertisement

### Resources

- `ResourceTemplate`: Template for dynamic URIs
- `ResourceManager`: Manages resource registration and resolution
- `Resource`: Base class for resources
- `StaticResource`: Represents a static resource
- `DynamicResource`: Represents a dynamic resource
- `UriTemplate`: Implements RFC 6570 URI Templates

### Tools

- `ToolManager`: Manages tool registration and execution
- `Tool`: Represents a tool
- `ToolSchema`: Schema definition for tools
- `ToolResponse`: Standardized response format for tools
- `Validator`: Parameter validation for tools
- `ValidationException`: Exception for validation errors

### Utilities

- `LoggerInterface`: Interface for logging
- `ConsoleLogger`: Basic logger implementation
- `CancellationManager`: Handles request cancellation
- `ProgressReporter`: Reports progress for long-running operations