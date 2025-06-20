<?php

namespace ModelContextProtocol\Protocol\Notifications;

use ModelContextProtocol\Protocol\Messages\Notification;
use ModelContextProtocol\Transport\TransportInterface;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use Throwable;

/**
 * Manages outbound notifications from server to client.
 *
 * Handles notification queuing, delivery, error handling, and subscription management.
 */
class NotificationManager
{
    /**
     * @var TransportInterface|null The transport for sending notifications
     */
    private ?TransportInterface $transport = null;

    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;

    /**
     * @var array<string, array<string, mixed>> Resource subscriptions by URI
     */
    private array $resourceSubscriptions = [];

    /**
     * @var array<Notification> Queued notifications waiting to be sent
     */
    private array $notificationQueue = [];

    /**
     * @var bool Whether notifications are currently being processed
     */
    private bool $processingQueue = false;

    /**
     * @var int Maximum number of delivery retries
     */
    private int $maxRetries = 3;

    /**
     * @var array<string, int> Failed notification retry counts
     */
    private array $retryCount = [];

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
     * Set the transport for sending notifications.
     *
     * @param TransportInterface|null $transport The transport instance
     * @return void
     */
    public function setTransport(?TransportInterface $transport): void
    {
        $this->transport = $transport;

        // Process any queued notifications
        if ($transport !== null) {
            $this->processNotificationQueue();
        }
    }

    /**
     * Send a general message notification to the client.
     *
     * @param string $level The log level (info, warning, error, etc.)
     * @param string $message The message content
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    public function sendMessage(string $level, string $message, array $data = []): void
    {
        $notification = new Notification('notifications/message', [
            'level' => $level,
            'message' => $message,
            'data' => $data
        ]);

        $this->queueNotification($notification);
    }

    /**
     * Send a resource list changed notification.
     *
     * @return void
     */
    public function sendResourceListChanged(): void
    {
        $notification = new Notification('notifications/resources/list_changed');
        $this->queueNotification($notification);
    }

    /**
     * Send a resource updated notification for a specific resource.
     *
     * @param string $uri The URI of the updated resource
     * @param array<string, mixed> $content The updated content (optional)
     * @return void
     */
    public function sendResourceUpdated(string $uri, array $content = []): void
    {
        // Only send if there are subscribers to this resource
        if (!isset($this->resourceSubscriptions[$uri])) {
            return;
        }

        $params = ['uri' => $uri];
        if (!empty($content)) {
            $params['content'] = $content;
        }

        $notification = new Notification('notifications/resources/updated', $params);
        $this->queueNotification($notification);
    }

    /**
     * Send a tools list changed notification.
     *
     * @return void
     */
    public function sendToolsListChanged(): void
    {
        $notification = new Notification('notifications/tools/list_changed');
        $this->queueNotification($notification);
    }

    /**
     * Send a prompts list changed notification.
     *
     * @return void
     */
    public function sendPromptsListChanged(): void
    {
        $notification = new Notification('notifications/prompts/list_changed');
        $this->queueNotification($notification);
    }

    /**
     * Subscribe to updates for a specific resource.
     *
     * @param string $uri The resource URI to subscribe to
     * @param array<string, mixed> $options Subscription options
     * @return void
     */
    public function subscribeToResource(string $uri, array $options = []): void
    {
        $this->resourceSubscriptions[$uri] = $options;

        $this->logger->debug('Subscribed to resource updates', [
            'uri' => $uri,
            'options' => $options
        ]);
    }

    /**
     * Unsubscribe from updates for a specific resource.
     *
     * @param string $uri The resource URI to unsubscribe from
     * @return void
     */
    public function unsubscribeFromResource(string $uri): void
    {
        if (isset($this->resourceSubscriptions[$uri])) {
            unset($this->resourceSubscriptions[$uri]);

            $this->logger->debug('Unsubscribed from resource updates', [
                'uri' => $uri
            ]);
        }
    }

    /**
     * Get all current resource subscriptions.
     *
     * @return array<string, array<string, mixed>> The resource subscriptions
     */
    public function getResourceSubscriptions(): array
    {
        return $this->resourceSubscriptions;
    }

    /**
     * Check if subscribed to a specific resource.
     *
     * @param string $uri The resource URI to check
     * @return bool True if subscribed
     */
    public function isSubscribedToResource(string $uri): bool
    {
        return isset($this->resourceSubscriptions[$uri]);
    }

    /**
     * Queue a notification for delivery.
     *
     * @param Notification $notification The notification to queue
     * @return void
     */
    private function queueNotification(Notification $notification): void
    {
        $this->notificationQueue[] = $notification;

        $this->logger->debug('Queued notification', [
            'method' => $notification->method,
            'queueSize' => count($this->notificationQueue)
        ]);

        // Try to process immediately if transport is available
        if ($this->transport !== null && !$this->processingQueue) {
            $this->processNotificationQueue();
        }
    }

    /**
     * Process all queued notifications.
     *
     * @return void
     */
    private function processNotificationQueue(): void
    {
        if ($this->processingQueue || $this->transport === null) {
            return;
        }

        $this->processingQueue = true;

        try {
            while (!empty($this->notificationQueue)) {
                $notification = array_shift($this->notificationQueue);
                $this->deliverNotification($notification);
            }
        } finally {
            $this->processingQueue = false;
        }
    }

    /**
     * Deliver a single notification with error handling and retries.
     *
     * @param Notification $notification The notification to deliver
     * @return void
     */
    private function deliverNotification(Notification $notification): void
    {
        if ($this->transport === null) {
            $this->logger->warning('Cannot deliver notification: no transport available', [
                'method' => $notification->method
            ]);
            return;
        }

        $notificationId = $this->getNotificationId($notification);

        try {
            $this->transport->send($notification);

            // Clear retry count on successful delivery
            if (isset($this->retryCount[$notificationId])) {
                unset($this->retryCount[$notificationId]);
            }

            $this->logger->debug('Notification delivered successfully', [
                'method' => $notification->method
            ]);
        } catch (Throwable $e) {
            $this->handleDeliveryFailure($notification, $e);
        }
    }

    /**
     * Handle notification delivery failure with retry logic.
     *
     * @param Notification $notification The failed notification
     * @param Throwable $error The delivery error
     * @return void
     */
    private function handleDeliveryFailure(Notification $notification, Throwable $error): void
    {
        $notificationId = $this->getNotificationId($notification);
        $retryCount = $this->retryCount[$notificationId] ?? 0;

        $this->logger->error('Notification delivery failed', [
            'method' => $notification->method,
            'error' => $error->getMessage(),
            'retryCount' => $retryCount,
            'maxRetries' => $this->maxRetries
        ]);

        if ($retryCount < $this->maxRetries) {
            // Increment retry count and re-queue
            $this->retryCount[$notificationId] = $retryCount + 1;
            $this->notificationQueue[] = $notification;

            $this->logger->debug('Notification queued for retry', [
                'method' => $notification->method,
                'retryCount' => $this->retryCount[$notificationId]
            ]);
        } else {
            // Max retries exceeded, give up
            unset($this->retryCount[$notificationId]);

            $this->logger->error('Notification delivery failed permanently', [
                'method' => $notification->method,
                'error' => $error->getMessage()
            ]);
        }
    }

    /**
     * Generate a unique identifier for a notification (for retry tracking).
     *
     * @param Notification $notification The notification
     * @return string The notification identifier
     */
    private function getNotificationId(Notification $notification): string
    {
        return md5($notification->method . serialize($notification->params));
    }

    /**
     * Clear all queued notifications.
     *
     * @return void
     */
    public function clearQueue(): void
    {
        $queueSize = count($this->notificationQueue);
        $this->notificationQueue = [];
        $this->retryCount = [];

        if ($queueSize > 0) {
            $this->logger->info('Cleared notification queue', [
                'clearedCount' => $queueSize
            ]);
        }
    }

    /**
     * Get the current queue size.
     *
     * @return int The number of queued notifications
     */
    public function getQueueSize(): int
    {
        return count($this->notificationQueue);
    }

    /**
     * Set the maximum number of delivery retries.
     *
     * @param int $maxRetries The maximum retry count
     * @return void
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = max(0, $maxRetries);
    }
}
