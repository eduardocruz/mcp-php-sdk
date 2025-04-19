# PHP MCP SDK Documentation

Welcome to the PHP Model Context Protocol (MCP) SDK documentation. This SDK provides a PHP implementation of the MCP protocol, enabling PHP applications to integrate with the Model Context Protocol ecosystem.

## Documentation Sections

- [API Reference](api-reference/README.md): Comprehensive documentation of all classes, methods, and interfaces
- [Examples](examples/README.md): Code examples for common use cases and features
- [Guides](guides/README.md): Installation and getting started guides
- [Troubleshooting](troubleshooting/README.md): Common issues and their solutions

## Quick Links

- [Installation Guide](guides/installation.md)
- [Getting Started](guides/getting-started.md)
- [API Reference Index](api-reference/README.md)
- [Server Tutorial](examples/server-tutorial.md)
- [Client Tutorial](examples/client-tutorial.md)

## About MCP

The Model Context Protocol (MCP) is an open protocol that enables seamless integration between LLM applications and external data sources and tools. It provides a standardized way to connect LLMs with the context they need. The protocol uses JSON-RPC 2.0 messages to establish communication between hosts (LLM applications), clients (connectors), and servers (services providing context and capabilities).

This PHP SDK follows the official MCP Specification.

## MCP Architecture

MCP follows a client-host-server architecture:

- **Host**: Container and coordinator that creates/manages client instances
- **Clients**: Maintain isolated server connections (1:1 relationship with servers)
- **Servers**: Provide specialized context and capabilities

The MCP protocol defines several key features:

1. **Resources**: URI-addressable content that can be exposed to LLMs
2. **Tools**: Functions that can be registered and executed
3. **Prompts**: Reusable templates for LLM interactions
4. **Lifecycle Management**: Connection and session handling

## Contributing

Contributions to the PHP MCP SDK are welcome! Please see the [contributing guidelines](../CONTRIBUTING.md) for more information.

## License

This project is licensed under the [MIT License](../LICENSE).