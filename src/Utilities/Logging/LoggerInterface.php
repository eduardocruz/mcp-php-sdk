<?php

namespace ModelContextProtocol\Utilities\Logging;

/**
 * Simple PSR-compatible logger interface for the MCP protocol.
 *
 * This is a simplified version of the PSR-3 LoggerInterface.
 */
interface LoggerInterface
{
    /**
     * Log an emergency message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Log an alert message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Log a critical message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Log an error message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log a notice message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Log an info message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log a message with an arbitrary level.
     *
     * @param string $level The logging level
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void;
}
