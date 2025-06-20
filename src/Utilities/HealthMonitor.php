<?php

namespace ModelContextProtocol\Utilities;

use ModelContextProtocol\Protocol\Messages\Request;
use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;

/**
 * Health monitoring utility for MCP connections.
 *
 * Provides connection health monitoring, automatic ping/pong functionality,
 * and timeout handling for unresponsive connections.
 */
class HealthMonitor
{
    /**
     * @var TransportInterface|null The transport to monitor
     */
    private ?TransportInterface $transport = null;

    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var bool Whether monitoring is active
     */
    private bool $monitoring = false;

    /**
     * @var int Ping interval in seconds
     */
    private int $pingInterval = 30;

    /**
     * @var int Timeout for ping responses in seconds
     */
    private int $pingTimeout = 10;

    /**
     * @var float|null Timestamp of last ping sent
     */
    private ?float $lastPingSent = null;

    /**
     * @var float|null Timestamp of last pong received
     */
    private ?float $lastPongReceived = null;

    /**
     * @var string|null ID of the last ping request
     */
    private ?string $lastPingId = null;

    /**
     * @var array<string, float> Ping response times (requestId => responseTime)
     */
    private array $pingResponseTimes = [];

    /**
     * @var int Maximum number of consecutive failed pings before considering connection dead
     */
    private int $maxFailedPings = 3;

    /**
     * @var int Current count of consecutive failed pings
     */
    private int $failedPingCount = 0;

    /**
     * @var callable|null Callback for when connection is considered healthy
     */
    private $onHealthy = null;

    /**
     * @var callable|null Callback for when connection is considered unhealthy
     */
    private $onUnhealthy = null;

    /**
     * @var callable|null Callback for when connection times out
     */
    private $onTimeout = null;

    /**
     * @var bool Current health status
     */
    private bool $isHealthy = true;

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
     * Set the transport to monitor.
     *
     * @param TransportInterface|null $transport The transport to monitor
     * @return void
     */
    public function setTransport(?TransportInterface $transport): void
    {
        $this->transport = $transport;

        if ($transport === null) {
            $this->stopMonitoring();
        }
    }

    /**
     * Start connection health monitoring.
     *
     * @return void
     */
    public function startMonitoring(): void
    {
        if ($this->monitoring) {
            return;
        }

        if ($this->transport === null) {
            $this->logger->warning('Cannot start health monitoring: no transport set');
            return;
        }

        $this->monitoring = true;
        $this->isHealthy = true;
        $this->failedPingCount = 0;
        $this->lastPingSent = null;
        $this->lastPongReceived = null;
        $this->lastPingId = null;

        $this->logger->info('Health monitoring started', [
            'pingInterval' => $this->pingInterval,
            'pingTimeout' => $this->pingTimeout,
            'maxFailedPings' => $this->maxFailedPings
        ]);
    }

    /**
     * Stop connection health monitoring.
     *
     * @return void
     */
    public function stopMonitoring(): void
    {
        if (!$this->monitoring) {
            return;
        }

        $this->monitoring = false;
        $this->logger->info('Health monitoring stopped');
    }

    /**
     * Check if monitoring is active.
     *
     * @return bool True if monitoring is active
     */
    public function isMonitoring(): bool
    {
        return $this->monitoring;
    }

    /**
     * Check if the connection is considered healthy.
     *
     * @return bool True if the connection is healthy
     */
    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    /**
     * Set the ping interval.
     *
     * @param int $seconds Ping interval in seconds
     * @return void
     */
    public function setPingInterval(int $seconds): void
    {
        $this->pingInterval = max(1, $seconds);
    }

    /**
     * Set the ping timeout.
     *
     * @param int $seconds Ping timeout in seconds
     * @return void
     */
    public function setPingTimeout(int $seconds): void
    {
        $this->pingTimeout = max(1, $seconds);
    }

    /**
     * Set the maximum number of failed pings.
     *
     * @param int $count Maximum failed ping count
     * @return void
     */
    public function setMaxFailedPings(int $count): void
    {
        $this->maxFailedPings = max(1, $count);
    }

    /**
     * Set callback for healthy connection events.
     *
     * @param callable $callback Callback function
     * @return void
     */
    public function onHealthy(callable $callback): void
    {
        $this->onHealthy = $callback;
    }

    /**
     * Set callback for unhealthy connection events.
     *
     * @param callable $callback Callback function
     * @return void
     */
    public function onUnhealthy(callable $callback): void
    {
        $this->onUnhealthy = $callback;
    }

    /**
     * Set callback for connection timeout events.
     *
     * @param callable $callback Callback function
     * @return void
     */
    public function onTimeout(callable $callback): void
    {
        $this->onTimeout = $callback;
    }

    /**
     * Perform health check cycle.
     * This should be called periodically by the application.
     *
     * @return void
     */
    public function tick(): void
    {
        if (!$this->monitoring || $this->transport === null) {
            return;
        }

        $now = microtime(true);

        // Check if we need to send a ping
        if ($this->shouldSendPing($now)) {
            $this->sendPing();
        }

        // Check for ping timeout
        if ($this->hasPingTimedOut($now)) {
            $this->handlePingTimeout();
        }
    }

    /**
     * Handle a ping response.
     *
     * @param string $requestId The request ID of the ping
     * @return void
     */
    public function handlePingResponse(string $requestId): void
    {
        if ($requestId === $this->lastPingId) {
            $now = microtime(true);
            $responseTime = $now - $this->lastPingSent;

            $this->pingResponseTimes[$requestId] = $responseTime;
            $this->lastPongReceived = $now;
            $this->lastPingId = null;
            $this->failedPingCount = 0;

            $this->logger->debug('Ping response received', [
                'requestId' => $requestId,
                'responseTime' => round($responseTime * 1000, 2) . 'ms'
            ]);

            // Update health status to healthy if it wasn't
            if (!$this->isHealthy) {
                $this->setHealthy(true);
            }
        }
    }

    /**
     * Get connection statistics.
     *
     * @return array<string, mixed> Connection statistics
     */
    public function getStats(): array
    {
        $recentPings = array_slice($this->pingResponseTimes, -10, 10, true);
        $avgResponseTime = empty($recentPings) ? null : array_sum($recentPings) / count($recentPings);

        return [
            'isHealthy' => $this->isHealthy,
            'isMonitoring' => $this->monitoring,
            'failedPingCount' => $this->failedPingCount,
            'maxFailedPings' => $this->maxFailedPings,
            'lastPingSent' => $this->lastPingSent,
            'lastPongReceived' => $this->lastPongReceived,
            'pingInterval' => $this->pingInterval,
            'pingTimeout' => $this->pingTimeout,
            'averageResponseTime' => $avgResponseTime ? round($avgResponseTime * 1000, 2) : null,
            'totalPings' => count($this->pingResponseTimes)
        ];
    }

    /**
     * Reset health monitoring state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->failedPingCount = 0;
        $this->lastPingSent = null;
        $this->lastPongReceived = null;
        $this->lastPingId = null;
        $this->pingResponseTimes = [];
        $this->setHealthy(true);
    }

    /**
     * Check if we should send a ping.
     *
     * @param float $now Current timestamp
     * @return bool True if we should send a ping
     */
    private function shouldSendPing(float $now): bool
    {
        // Don't send if we have a ping pending
        if ($this->lastPingId !== null) {
            return false;
        }

        // Send initial ping if we haven't sent one yet
        if ($this->lastPingSent === null) {
            return true;
        }

        // Send ping if interval has elapsed
        return ($now - $this->lastPingSent) >= $this->pingInterval;
    }

    /**
     * Check if the current ping has timed out.
     *
     * @param float $now Current timestamp
     * @return bool True if ping has timed out
     */
    private function hasPingTimedOut(float $now): bool
    {
        if ($this->lastPingId === null || $this->lastPingSent === null) {
            return false;
        }

        return ($now - $this->lastPingSent) >= $this->pingTimeout;
    }

    /**
     * Send a ping request.
     *
     * @return void
     */
    private function sendPing(): void
    {
        if ($this->transport === null) {
            return;
        }

        $this->lastPingId = 'health-ping-' . uniqid();
        $this->lastPingSent = microtime(true);

        $ping = new Request($this->lastPingId, 'ping', []);

        try {
            $this->transport->send($ping);

            $this->logger->debug('Health ping sent', [
                'requestId' => $this->lastPingId
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send health ping', [
                'error' => $e->getMessage(),
                'requestId' => $this->lastPingId
            ]);

            $this->lastPingId = null;
            $this->handlePingTimeout();
        }
    }

    /**
     * Handle ping timeout.
     *
     * @return void
     */
    private function handlePingTimeout(): void
    {
        $this->failedPingCount++;
        $this->lastPingId = null;

        $this->logger->warning('Ping timeout', [
            'failedPingCount' => $this->failedPingCount,
            'maxFailedPings' => $this->maxFailedPings
        ]);

        if ($this->failedPingCount >= $this->maxFailedPings) {
            $this->setHealthy(false);

            if ($this->onTimeout !== null) {
                try {
                    ($this->onTimeout)();
                } catch (\Throwable $e) {
                    $this->logger->error('Error in timeout callback', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Set the health status.
     *
     * @param bool $healthy True if healthy
     * @return void
     */
    private function setHealthy(bool $healthy): void
    {
        if ($this->isHealthy === $healthy) {
            return;
        }

        $this->isHealthy = $healthy;

        $this->logger->info('Connection health status changed', [
            'isHealthy' => $healthy
        ]);

        $callback = $healthy ? $this->onHealthy : $this->onUnhealthy;

        if ($callback !== null) {
            try {
                $callback();
            } catch (\Throwable $e) {
                $this->logger->error('Error in health status callback', [
                    'error' => $e->getMessage(),
                    'isHealthy' => $healthy
                ]);
            }
        }
    }
}
