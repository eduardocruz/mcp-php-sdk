<?php

namespace ModelContextProtocol\Utilities\Cancellation;

/**
 * Exception thrown when an operation is cancelled.
 */
class CancellationException extends \Exception
{
    /**
     * @var CancellationToken The cancellation token that was cancelled
     */
    private CancellationToken $token;

    /**
     * Constructor.
     *
     * @param string $message The exception message
     * @param CancellationToken $token The cancellation token
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $message, CancellationToken $token, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->token = $token;
    }

    /**
     * Get the cancellation token.
     *
     * @return CancellationToken The cancellation token
     */
    public function getToken(): CancellationToken
    {
        return $this->token;
    }

    /**
     * Get the cancellation reason.
     *
     * @return string|null The cancellation reason
     */
    public function getReason(): ?string
    {
        return $this->token->getReason();
    }

    /**
     * Get the timestamp when cancellation was requested.
     *
     * @return float|null The cancellation timestamp
     */
    public function getCancelledAt(): ?float
    {
        return $this->token->getCancelledAt();
    }
}
