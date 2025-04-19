<?php

namespace ModelContextProtocol\Transport\Session;

use ModelContextProtocol\Utilities\Logging\LoggerInterface;

/**
 * Manages session information for transports.
 */
class SessionManager
{
    /**
     * @var string|null The current session ID
     */
    private ?string $sessionId = null;
    
    /**
     * @var array<string, mixed> Session data
     */
    private array $sessionData = [];
    
    /**
     * @var LoggerInterface|null The logger instance
     */
    private ?LoggerInterface $logger;
    
    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }
    
    /**
     * Generate a new session ID.
     *
     * @return string The generated session ID
     */
    public function generateSessionId(): string
    {
        $this->sessionId = bin2hex(random_bytes(16));
        
        if ($this->logger) {
            $this->logger->debug('Generated new session ID: ' . $this->sessionId);
        }
        
        return $this->sessionId;
    }
    
    /**
     * Get the current session ID.
     *
     * @return string|null The current session ID, or null if no session is active
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }
    
    /**
     * Set the session ID.
     *
     * @param string $sessionId The session ID to set
     * @return void
     */
    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
        
        if ($this->logger) {
            $this->logger->debug('Set session ID: ' . $this->sessionId);
        }
    }
    
    /**
     * Clear the session.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->sessionId = null;
        $this->sessionData = [];
        
        if ($this->logger) {
            $this->logger->debug('Session cleared');
        }
    }
    
    /**
     * Store data in the session.
     *
     * @param string $key The key to store the data under
     * @param mixed $value The value to store
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->sessionData[$key] = $value;
    }
    
    /**
     * Get data from the session.
     *
     * @param string $key The key to retrieve
     * @param mixed $default A default value to return if the key doesn't exist
     * @return mixed The value, or the default if the key doesn't exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->sessionData[$key] ?? $default;
    }
    
    /**
     * Check if a key exists in the session.
     *
     * @param string $key The key to check for
     * @return bool True if the key exists, false otherwise
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->sessionData);
    }
    
    /**
     * Remove data from the session.
     *
     * @param string $key The key to remove
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->sessionData[$key]);
    }
    
    /**
     * Get all session data.
     *
     * @return array<string, mixed> All session data
     */
    public function all(): array
    {
        return $this->sessionData;
    }
}