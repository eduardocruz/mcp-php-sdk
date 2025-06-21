<?php

namespace ModelContextProtocol\Protocol\Errors;

use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Response;
use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use ModelContextProtocol\Protocol\Errors\ErrorResponseBuilder;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Error recovery mechanisms for different failure types.
 *
 * This class provides strategies for recovering from various error conditions
 * that may occur during MCP operations, including transport failures,
 * request timeouts, and connection issues.
 */
class ErrorRecovery
{
    /**
     * Recovery strategies
     */
    public const STRATEGY_RETRY = 'retry';
    public const STRATEGY_FALLBACK = 'fallback';
    public const STRATEGY_CIRCUIT_BREAKER = 'circuit_breaker';
    public const STRATEGY_GRACEFUL_DEGRADATION = 'graceful_degradation';

    /**
     * Circuit breaker states
     */
    public const CIRCUIT_CLOSED = 'closed';
    public const CIRCUIT_OPEN = 'open';
    public const CIRCUIT_HALF_OPEN = 'half_open';

    private LoggerInterface $logger;
    private array $retryConfig;
    private array $circuitBreakerState = [];
    private array $fallbackHandlers = [];

    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger Logger instance
     * @param array $config Recovery configuration
     */
    public function __construct(?LoggerInterface $logger = null, array $config = [])
    {
        $this->logger = $logger ?? new ConsoleLogger();
        $this->retryConfig = array_merge([
            'max_retries' => 3,
            'initial_delay' => 100, // milliseconds
            'max_delay' => 5000,    // milliseconds
            'backoff_multiplier' => 2.0,
            'jitter' => true,
        ], $config['retry'] ?? []);
    }

    /**
     * Execute a request with error recovery.
     *
     * @param callable $operation The operation to execute
     * @param string $strategy The recovery strategy to use
     * @param array $options Strategy-specific options
     * @return mixed The operation result
     * @throws Throwable If all recovery attempts fail
     */
    public function executeWithRecovery(
        callable $operation,
        string $strategy = self::STRATEGY_RETRY,
        array $options = []
    ): mixed {
        return match ($strategy) {
            self::STRATEGY_RETRY => $this->executeWithRetry($operation, $options),
            self::STRATEGY_FALLBACK => $this->executeWithFallback($operation, $options),
            self::STRATEGY_CIRCUIT_BREAKER => $this->executeWithCircuitBreaker($operation, $options),
            self::STRATEGY_GRACEFUL_DEGRADATION => $this->executeWithGracefulDegradation($operation, $options),
            default => throw new InvalidArgumentException("Unknown recovery strategy: {$strategy}"),
        };
    }

    /**
     * Execute operation with retry strategy.
     *
     * @param callable $operation The operation to execute
     * @param array $options Retry options
     * @return mixed The operation result
     * @throws Throwable If all retries fail
     */
    private function executeWithRetry(callable $operation, array $options = []): mixed
    {
        $maxRetries = $options['max_retries'] ?? $this->retryConfig['max_retries'];
        $initialDelay = $options['initial_delay'] ?? $this->retryConfig['initial_delay'];
        $maxDelay = $options['max_delay'] ?? $this->retryConfig['max_delay'];
        $backoffMultiplier = $options['backoff_multiplier'] ?? $this->retryConfig['backoff_multiplier'];
        $jitter = $options['jitter'] ?? $this->retryConfig['jitter'];

        $attempt = 0;
        $delay = $initialDelay;
        /** @var \Throwable|null $lastException */
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                $result = $operation();

                // Log successful recovery if this wasn't the first attempt
                if ($attempt > 0) {
                    $this->logger->info('Operation succeeded after retry', [
                        'attempt' => $attempt,
                        'total_attempts' => $attempt + 1,
                    ]);
                }

                return $result;
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                // Don't retry if we've reached the max attempts
                if ($attempt > $maxRetries) {
                    break;
                }

                // Check if this exception should be retried
                if (!$this->shouldRetry($e)) {
                    $this->logger->warning('Exception not suitable for retry', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $this->logger->warning('Operation failed, retrying', [
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'delay_ms' => $delay,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);

                // Sleep before retry
                if ($delay > 0) {
                    $actualDelay = $jitter ? $this->addJitter($delay) : $delay;
                    usleep($actualDelay * 1000); // Convert to microseconds
                }

                // Calculate next delay with exponential backoff
                $delay = min($delay * $backoffMultiplier, $maxDelay);
            }
        }

        // All retries failed
        if ($lastException !== null) {
            $this->logger->error('All retry attempts failed', [
                'total_attempts' => $attempt,
                'last_exception' => get_class($lastException),
                'message' => $lastException->getMessage(),
            ]);

            throw $lastException;
        }

        // This should never happen, but we need it for PHPStan
        throw new RuntimeException('All retry attempts failed with no exception captured');
    }

    /**
     * Execute operation with fallback strategy.
     *
     * @param callable $operation The primary operation
     * @param array $options Fallback options
     * @return mixed The operation result
     * @throws Throwable If both primary and fallback operations fail
     */
    private function executeWithFallback(callable $operation, array $options = []): mixed
    {
        $fallbackOperation = $options['fallback'] ?? null;

        if (!$fallbackOperation || !is_callable($fallbackOperation)) {
            throw new InvalidArgumentException('Fallback strategy requires a callable fallback operation');
        }

        try {
            return $operation();
        } catch (Throwable $e) {
            $this->logger->warning('Primary operation failed, trying fallback', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            try {
                $result = $fallbackOperation($e);

                $this->logger->info('Fallback operation succeeded');

                return $result;
            } catch (Throwable $fallbackException) {
                $this->logger->error('Fallback operation also failed', [
                    'primary_exception' => get_class($e),
                    'fallback_exception' => get_class($fallbackException),
                    'primary_message' => $e->getMessage(),
                    'fallback_message' => $fallbackException->getMessage(),
                ]);

                // Throw the original exception unless specified otherwise
                throw $options['throw_fallback_exception'] ?? false ? $fallbackException : $e;
            }
        }
    }

    /**
     * Execute operation with circuit breaker pattern.
     *
     * @param callable $operation The operation to execute
     * @param array $options Circuit breaker options
     * @return mixed The operation result
     * @throws Throwable If circuit is open or operation fails
     */
    private function executeWithCircuitBreaker(callable $operation, array $options = []): mixed
    {
        $circuitName = $options['circuit_name'] ?? 'default';
        $failureThreshold = $options['failure_threshold'] ?? 5;
        $recoveryTimeout = $options['recovery_timeout'] ?? 60; // seconds
        $successThreshold = $options['success_threshold'] ?? 3;

        $circuit = $this->getCircuitState($circuitName);

        // Check circuit state
        if ($circuit['state'] === self::CIRCUIT_OPEN) {
            // Check if recovery timeout has passed
            if (time() - $circuit['last_failure_time'] >= $recoveryTimeout) {
                $this->setCircuitState($circuitName, self::CIRCUIT_HALF_OPEN);
                $this->logger->info('Circuit breaker transitioning to half-open', [
                    'circuit' => $circuitName,
                ]);
            } else {
                $this->logger->warning('Circuit breaker is open, rejecting request', [
                    'circuit' => $circuitName,
                    'time_until_recovery' => $recoveryTimeout - (time() - $circuit['last_failure_time']),
                ]);
                throw new RuntimeException('Circuit breaker is open');
            }
        }

        try {
            $result = $operation();

            // Operation succeeded
            if ($circuit['state'] === self::CIRCUIT_HALF_OPEN) {
                $circuit['consecutive_successes']++;

                if ($circuit['consecutive_successes'] >= $successThreshold) {
                    $this->setCircuitState($circuitName, self::CIRCUIT_CLOSED);
                    $this->logger->info('Circuit breaker closed after successful recovery', [
                        'circuit' => $circuitName,
                    ]);
                }
            } elseif ($circuit['state'] === self::CIRCUIT_CLOSED) {
                // Reset failure count on success
                $circuit['consecutive_failures'] = 0;
                $this->circuitBreakerState[$circuitName] = $circuit;
            }

            return $result;
        } catch (Throwable $e) {
            // Operation failed
            $circuit['consecutive_failures']++;
            $circuit['consecutive_successes'] = 0;
            $circuit['last_failure_time'] = time();

            if ($circuit['consecutive_failures'] >= $failureThreshold) {
                $this->setCircuitState($circuitName, self::CIRCUIT_OPEN);
                $this->logger->error('Circuit breaker opened due to consecutive failures', [
                    'circuit' => $circuitName,
                    'failures' => $circuit['consecutive_failures'],
                    'threshold' => $failureThreshold,
                ]);
            }

            $this->circuitBreakerState[$circuitName] = $circuit;

            throw $e;
        }
    }

    /**
     * Execute operation with graceful degradation.
     *
     * @param callable $operation The operation to execute
     * @param array $options Degradation options
     * @return mixed The operation result or degraded response
     */
    private function executeWithGracefulDegradation(callable $operation, array $options = []): mixed
    {
        $degradedResponse = $options['degraded_response'] ?? null;
        $degradationHandler = $options['degradation_handler'] ?? null;

        try {
            return $operation();
        } catch (Throwable $e) {
            $this->logger->warning('Operation failed, providing degraded response', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            if ($degradationHandler && is_callable($degradationHandler)) {
                return $degradationHandler($e);
            }

            if ($degradedResponse !== null) {
                return $degradedResponse;
            }

            // Default degraded response
            return [
                'error' => [
                    'code' => ErrorResponseBuilder::ERROR_CODE_INTERNAL_ERROR,
                    'message' => 'Service temporarily unavailable',
                    'degraded' => true,
                ]
            ];
        }
    }

    /**
     * Register a fallback handler for a specific operation.
     *
     * @param string $operation Operation identifier
     * @param callable $handler Fallback handler
     * @return void
     */
    public function registerFallbackHandler(string $operation, callable $handler): void
    {
        $this->fallbackHandlers[$operation] = $handler;
    }

    /**
     * Get circuit breaker state.
     *
     * @param string $circuitName Circuit name
     * @return array Circuit state
     */
    private function getCircuitState(string $circuitName): array
    {
        return $this->circuitBreakerState[$circuitName] ?? [
            'state' => self::CIRCUIT_CLOSED,
            'consecutive_failures' => 0,
            'consecutive_successes' => 0,
            'last_failure_time' => 0,
        ];
    }

    /**
     * Set circuit breaker state.
     *
     * @param string $circuitName Circuit name
     * @param string $state New state
     * @return void
     */
    private function setCircuitState(string $circuitName, string $state): void
    {
        $circuit = $this->getCircuitState($circuitName);
        $circuit['state'] = $state;

        if ($state === self::CIRCUIT_CLOSED) {
            $circuit['consecutive_failures'] = 0;
            $circuit['consecutive_successes'] = 0;
        } elseif ($state === self::CIRCUIT_HALF_OPEN) {
            $circuit['consecutive_successes'] = 0;
        }

        $this->circuitBreakerState[$circuitName] = $circuit;
    }

    /**
     * Determine if an exception should be retried.
     *
     * @param Throwable $exception The exception
     * @return bool True if the exception should be retried
     */
    private function shouldRetry(Throwable $exception): bool
    {
        // Don't retry validation errors or invalid arguments
        if ($exception instanceof InvalidArgumentException) {
            return false;
        }

        // Don't retry validation exceptions
        if (get_class($exception) === 'ModelContextProtocol\Server\Tools\Schema\ValidationException') {
            return false;
        }

        // Retry transport-related errors
        if (str_contains(get_class($exception), 'Transport')) {
            return true;
        }

        // Retry connection-related errors
        if (str_contains(get_class($exception), 'Connection')) {
            return true;
        }

        // Default: retry for most exceptions
        return true;
    }

    /**
     * Add jitter to delay to avoid thundering herd.
     *
     * @param int $delay Base delay in milliseconds
     * @return int Delay with jitter applied
     */
    private function addJitter(int $delay): int
    {
        // Add ±25% jitter
        $jitterRange = (int)($delay * 0.25);
        $jitter = mt_rand(-$jitterRange, $jitterRange);

        return max(0, $delay + $jitter);
    }

    /**
     * Get recovery statistics.
     *
     * @return array Recovery statistics
     */
    public function getStatistics(): array
    {
        return [
            'circuit_breakers' => $this->circuitBreakerState,
            'fallback_handlers' => array_keys($this->fallbackHandlers),
        ];
    }

    /**
     * Reset all circuit breakers.
     *
     * @return void
     */
    public function resetCircuitBreakers(): void
    {
        $this->circuitBreakerState = [];
        $this->logger->info('All circuit breakers reset');
    }
}
