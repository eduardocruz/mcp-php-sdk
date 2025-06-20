<?php

namespace ModelContextProtocol\Transport;

use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;

/**
 * Describes the minimal contract for an MCP transport that a client or server can communicate over.
 */
interface TransportInterface
{
    /**
     * Starts processing messages on the transport, including any connection steps that might need to be taken.
     *
     * This method should only be called after callbacks are installed, or else messages may be lost.
     *
     * NOTE: This method should not be called explicitly when using Client or Server classes,
     * as they will implicitly call start().
     *
     * @return void
     */
    public function start(): void;

    /**
     * Sends a JSON-RPC message (request, response, or notification).
     *
     * @param JsonRpcMessage $message The message to send
     * @param array<string, mixed> $options Additional transport-specific options
     * @return void
     */
    public function send(JsonRpcMessage $message, array $options = []): void;

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close(): void;

    /**
     * Registers a callback to be called when a message is received.
     *
     * @param callable $handler Function to call with the received message and optional extra data
     * @return void
     */
    public function onMessage(callable $handler): void;

    /**
     * Registers a callback to be called when an error occurs.
     *
     * @param callable $handler Function to call with the error
     * @return void
     */
    public function onError(callable $handler): void;

    /**
     * Registers a callback to be called when the connection is closed.
     *
     * @param callable $handler Function to call when the connection closes
     * @return void
     */
    public function onClose(callable $handler): void;

    /**
     * Gets the session ID for this connection, if one exists.
     *
     * @return string|null The session ID, or null if no session is established
     */
    public function getSessionId(): ?string;
}
