<?php

namespace ModelContextProtocol\Protocol\Messages;

/**
 * Represents error data in a JSON-RPC 2.0 response.
 *
 * This class provides a structure for error information returned in error responses,
 * including an error code, message, and optional additional data.
 */
class ErrorData
{
    /**
     * Constructor.
     *
     * @param int $code The error code.
     * @param string $message The error message.
     * @param mixed|null $data Additional data about the error. Must be JSON serializable.
     */
    public function __construct(
        public int $code,
        public string $message,
        public mixed $data = null
    ) {
    }

    /**
     * Convert the error data to an associative array for serialization.
     *
     * @return array<string, mixed> The error data as an associative array.
     */
    public function toArray(): array
    {
        $result = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    /**
     * Create an ErrorData instance from an associative array.
     *
     * @param array<string, mixed> $data The error data as an associative array.
     * @return self The created ErrorData instance.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['code'],
            $data['message'],
            $data['data'] ?? null
        );
    }
}
