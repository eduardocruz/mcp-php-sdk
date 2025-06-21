<?php

namespace ModelContextProtocol\Utilities\Cancellation;

use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;

/**
 * Manages cancellation tokens for active requests and handles cancellation notifications.
 */
class CancellationManager
{
    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var array<string, CancellationToken> Active requests by request ID
     */
    private array $activeRequests = [];

    /**
     * @var array<string, array> Request metadata by request ID
     */
    private array $requestMetadata = [];

    /**
     * @var callable|null Global cancellation callback
     */
    private $globalCallback = null;

    /**
     * Constructor.
     *
     * @param LoggerInterface|null $logger Optional logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new ConsoleLogger();
    }

    /**
     * Register a new request with a cancellation token.
     *
     * @param string $requestId The request ID
     * @param array $metadata Optional metadata about the request
     * @return CancellationToken The cancellation token for this request
     */
    public function registerRequest(string $requestId, array $metadata = []): CancellationToken
    {
        $token = new CancellationToken();

        $this->activeRequests[$requestId] = $token;
        $this->requestMetadata[$requestId] = array_merge($metadata, [
            'registeredAt' => microtime(true),
            'requestId' => $requestId
        ]);

        $this->logger->debug('Request registered for cancellation tracking', [
            'requestId' => $requestId,
            'metadata' => $metadata
        ]);

        return $token;
    }

    /**
     * Unregister a request (call when request completes normally).
     *
     * @param string $requestId The request ID
     * @return bool True if the request was found and removed
     */
    public function unregisterRequest(string $requestId): bool
    {
        if (!isset($this->activeRequests[$requestId])) {
            return false;
        }

        unset($this->activeRequests[$requestId]);
        unset($this->requestMetadata[$requestId]);

        $this->logger->debug('Request unregistered from cancellation tracking', [
            'requestId' => $requestId
        ]);

        return true;
    }

    /**
     * Cancel a specific request.
     *
     * @param string $requestId The request ID to cancel
     * @param string|null $reason Optional reason for cancellation
     * @return bool True if the request was found and cancelled
     */
    public function cancelRequest(string $requestId, ?string $reason = null): bool
    {
        if (!isset($this->activeRequests[$requestId])) {
            $this->logger->debug('Cancellation requested for unknown request', [
                'requestId' => $requestId,
                'reason' => $reason
            ]);
            return false;
        }

        $token = $this->activeRequests[$requestId];
        $metadata = $this->requestMetadata[$requestId] ?? [];

        $this->logger->info('Request cancelled', [
            'requestId' => $requestId,
            'reason' => $reason,
            'metadata' => $metadata
        ]);

        // Cancel the token
        $token->cancel($reason);

        // Execute global cancellation callback if set
        if ($this->globalCallback !== null) {
            try {
                ($this->globalCallback)($requestId, $token, $metadata);
            } catch (\Throwable $e) {
                $this->logger->error('Error in global cancellation callback', [
                    'error' => $e->getMessage(),
                    'requestId' => $requestId
                ]);
            }
        }

        // Remove from active requests
        unset($this->activeRequests[$requestId]);
        unset($this->requestMetadata[$requestId]);

        return true;
    }

    /**
     * Get the cancellation token for a request.
     *
     * @param string $requestId The request ID
     * @return CancellationToken|null The cancellation token, or null if not found
     */
    public function getToken(string $requestId): ?CancellationToken
    {
        return $this->activeRequests[$requestId] ?? null;
    }

    /**
     * Check if a request is being tracked.
     *
     * @param string $requestId The request ID
     * @return bool True if the request is being tracked
     */
    public function hasRequest(string $requestId): bool
    {
        return isset($this->activeRequests[$requestId]);
    }

    /**
     * Get all active request IDs.
     *
     * @return array<string> Array of active request IDs
     */
    public function getActiveRequestIds(): array
    {
        return array_keys($this->activeRequests);
    }

    /**
     * Get the number of active requests.
     *
     * @return int Number of active requests
     */
    public function getActiveRequestCount(): int
    {
        return count($this->activeRequests);
    }

    /**
     * Get metadata for a request.
     *
     * @param string $requestId The request ID
     * @return array|null Request metadata, or null if not found
     */
    public function getRequestMetadata(string $requestId): ?array
    {
        return $this->requestMetadata[$requestId] ?? null;
    }

    /**
     * Cancel all active requests.
     *
     * @param string|null $reason Optional reason for cancellation
     * @return int Number of requests cancelled
     */
    public function cancelAll(?string $reason = null): int
    {
        $requestIds = $this->getActiveRequestIds();
        $cancelledCount = 0;

        foreach ($requestIds as $requestId) {
            if ($this->cancelRequest($requestId, $reason)) {
                $cancelledCount++;
            }
        }

        $this->logger->info('All active requests cancelled', [
            'cancelledCount' => $cancelledCount,
            'reason' => $reason
        ]);

        return $cancelledCount;
    }

    /**
     * Set a global callback to be executed when any request is cancelled.
     *
     * @param callable|null $callback Callback function (requestId, token, metadata) => void
     * @return void
     */
    public function setGlobalCancellationCallback(?callable $callback): void
    {
        $this->globalCallback = $callback;
    }

    /**
     * Clean up old completed requests (for memory management).
     * This is useful if requests are not properly unregistered.
     *
     * @param float $maxAge Maximum age in seconds for completed requests
     * @return int Number of requests cleaned up
     */
    public function cleanupOldRequests(float $maxAge = 3600): int
    {
        $now = microtime(true);
        $cleanedUp = 0;

        foreach ($this->requestMetadata as $requestId => $metadata) {
            $age = $now - ($metadata['registeredAt'] ?? $now);

            if ($age > $maxAge) {
                $this->unregisterRequest($requestId);
                $cleanedUp++;
            }
        }

        if ($cleanedUp > 0) {
            $this->logger->info('Cleaned up old requests', [
                'cleanedUpCount' => $cleanedUp,
                'maxAge' => $maxAge
            ]);
        }

        return $cleanedUp;
    }

    /**
     * Get statistics about the cancellation manager.
     *
     * @return array<string, mixed> Statistics
     */
    public function getStats(): array
    {
        $now = microtime(true);
        $ages = [];

        foreach ($this->requestMetadata as $metadata) {
            $ages[] = $now - ($metadata['registeredAt'] ?? $now);
        }

        return [
            'activeRequestCount' => count($this->activeRequests),
            'oldestRequestAge' => empty($ages) ? null : max($ages),
            'averageRequestAge' => empty($ages) ? null : array_sum($ages) / count($ages),
            'requestIds' => array_keys($this->activeRequests)
        ];
    }
}
