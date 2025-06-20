<?php

namespace ModelContextProtocol\Utilities\Cancellation;

/**
 * Represents a cancellation token that can be used to signal cancellation of an operation.
 */
class CancellationToken
{
    /**
     * @var bool Whether cancellation has been requested
     */
    private bool $cancelled = false;
    
    /**
     * @var string|null The reason for cancellation
     */
    private ?string $reason = null;
    
    /**
     * @var array<callable> Callbacks to execute when cancellation is requested
     */
    private array $callbacks = [];
    
    /**
     * @var float|null Timestamp when cancellation was requested
     */
    private ?float $cancelledAt = null;
    
    /**
     * Check if cancellation has been requested.
     *
     * @return bool True if cancellation has been requested
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
    
    /**
     * Get the reason for cancellation.
     *
     * @return string|null The cancellation reason, or null if not cancelled
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
    
    /**
     * Get the timestamp when cancellation was requested.
     *
     * @return float|null The cancellation timestamp, or null if not cancelled
     */
    public function getCancelledAt(): ?float
    {
        return $this->cancelledAt;
    }
    
    /**
     * Request cancellation of the operation.
     *
     * @param string|null $reason Optional reason for cancellation
     * @return void
     */
    public function cancel(?string $reason = null): void
    {
        if ($this->cancelled) {
            return; // Already cancelled
        }
        
        $this->cancelled = true;
        $this->reason = $reason;
        $this->cancelledAt = microtime(true);
        
        // Execute all registered callbacks
        foreach ($this->callbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable $e) {
                // Ignore callback errors to prevent cascading failures
                error_log("Error in cancellation callback: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Register a callback to be executed when cancellation is requested.
     *
     * @param callable $callback Callback function that receives the cancellation token
     * @return void
     */
    public function onCancelled(callable $callback): void
    {
        if ($this->cancelled) {
            // If already cancelled, execute immediately
            try {
                $callback($this);
            } catch (\Throwable $e) {
                error_log("Error in cancellation callback: " . $e->getMessage());
            }
        } else {
            $this->callbacks[] = $callback;
        }
    }
    
    /**
     * Throw an exception if cancellation has been requested.
     *
     * @throws CancellationException If cancellation has been requested
     * @return void
     */
    public function throwIfCancelled(): void
    {
        if ($this->cancelled) {
            throw new CancellationException($this->reason ?? 'Operation was cancelled', $this);
        }
    }
    
    /**
     * Create a new cancellation token that is already cancelled.
     *
     * @param string|null $reason Optional reason for cancellation
     * @return static A cancelled token
     */
    public static function cancelled(?string $reason = null): static
    {
        $token = new static();
        $token->cancel($reason);
        return $token;
    }
    
    /**
     * Create a new cancellation token that is not cancelled.
     *
     * @return static A new token
     */
    public static function none(): static
    {
        return new static();
    }
    
    /**
     * Create a cancellation token that will be cancelled after a timeout.
     *
     * @param float $timeoutSeconds Timeout in seconds
     * @param string|null $reason Optional reason for cancellation
     * @return static A token that will be cancelled after the timeout
     */
    public static function timeout(float $timeoutSeconds, ?string $reason = null): static
    {
        $token = new static();
        
        // Note: In a real implementation, you would use an event loop or timer
        // For now, this is a placeholder that would need to be integrated with
        // your application's timer/event system
        
        return $token;
    }
} 