<?php

namespace ModelContextProtocol\Protocol;

/**
 * Constants used throughout the MCP protocol.
 */
class Constants
{
    /**
     * The latest supported MCP protocol version.
     */
    public const LATEST_PROTOCOL_VERSION = '2024-11-05';
    
    /**
     * All supported MCP protocol versions.
     * 
     * @var array<string>
     */
    public const SUPPORTED_PROTOCOL_VERSIONS = [
        self::LATEST_PROTOCOL_VERSION,
        '2024-10-07',
    ];
    
    /**
     * Predefined error codes for the MCP protocol.
     */
    public const ERROR_CODE_CONNECTION_CLOSED = -32000;
    public const ERROR_CODE_REQUEST_TIMEOUT = -32001;
    public const ERROR_CODE_PARSE_ERROR = -32700;
    public const ERROR_CODE_INVALID_REQUEST = -32600;
    public const ERROR_CODE_METHOD_NOT_FOUND = -32601;
    public const ERROR_CODE_INVALID_PARAMS = -32602;
    public const ERROR_CODE_INTERNAL_ERROR = -32603;
}