<?php

namespace ModelContextProtocol\Protocol\Messages;

use InvalidArgumentException;

/**
 * Represents a JSON-RPC 2.0 request.
 *
 * A request message includes an ID, a method name, and optional parameters.
 * The response to a request will include the same ID to allow for correlation.
 */
class Request extends JsonRpcMessage
{
    /**
     * Constructor.
     *
     * @param string|int $id The request identifier. Must not be null.
     * @param string $method The method to be invoked.
     * @param array<string, mixed>|null $params The parameters to pass to the method.
     *
     * @throws InvalidArgumentException If the request ID is null.
     */
    public function __construct(
        public string|int $requestId,
        public string $method,
        public ?array $params = null
    ) {
        // No validation needed - PHP type system ensures $id is string|int
    }

    /**
     * Convert the request to an associative array for serialization.
     *
     * @return array<string, mixed> The request as an associative array.
     */
    public function toArray(): array
    {
        $result = parent::toArray();
        $result['id'] = $this->requestId;
        $result['method'] = $this->method;

        if ($this->params !== null) {
            $result['params'] = $this->params;
        }

        return $result;
    }

    /**
     * Create a Request instance from an associative array.
     *
     * @param array<string, mixed> $data The request data as an associative array.
     * @return self The created Request instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['method'],
            $data['params'] ?? null
        );
    }
}
