<?php

namespace ModelContextProtocol\Protocol\Messages;

/**
 * Represents a JSON-RPC 2.0 notification.
 *
 * A notification is a one-way message that does not expect a response.
 * It includes a method name and optional parameters, but no ID.
 */
class Notification extends JsonRpcMessage
{
    /**
     * Constructor.
     *
     * @param string $method The method to be invoked.
     * @param array<string, mixed>|null $params The parameters to pass to the method.
     */
    public function __construct(
        public string $method,
        public ?array $params = null
    ) {
    }

    /**
     * Convert the notification to an associative array for serialization.
     *
     * @return array<string, mixed> The notification as an associative array.
     */
    public function toArray(): array
    {
        $result = parent::toArray();
        $result['method'] = $this->method;

        if ($this->params !== null) {
            $result['params'] = $this->params;
        }

        return $result;
    }

    /**
     * Create a Notification instance from an associative array.
     *
     * @param array<string, mixed> $data The notification data as an associative array.
     * @return self The created Notification instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['method'],
            $data['params'] ?? null
        );
    }
}
