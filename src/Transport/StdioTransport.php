<?php

namespace ModelContextProtocol\Transport;

use ModelContextProtocol\Protocol\Messages\JsonRpcMessage;
use ModelContextProtocol\Transport\Exception\ConnectionException;
use ModelContextProtocol\Transport\Exception\MessageException;
use ModelContextProtocol\Utilities\Logging\LoggerInterface;
use ModelContextProtocol\Utilities\Logging\ConsoleLogger;
use ModelContextProtocol\Transport\Session\SessionManager;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Transport for stdio communication in the Model Context Protocol.
 * 
 * This transport communicates with a MCP client or server by reading from stdin and writing to stdout.
 */
class StdioTransport implements TransportInterface
{
    /**
     * @var resource The stdin stream
     */
    private $stdin;
    
    /**
     * @var resource The stdout stream
     */
    private $stdout;
    
    /**
     * @var MessageBuffer Message buffer for parsing incoming data
     */
    private MessageBuffer $messageBuffer;
    
    /**
     * @var bool Whether the transport has been started
     */
    private bool $started = false;
    
    /**
     * @var callable|null Callback for received messages
     */
    private $messageHandler = null;
    
    /**
     * @var callable|null Callback for errors
     */
    private $errorHandler = null;
    
    /**
     * @var callable|null Callback for when the connection is closed
     */
    private $closeHandler = null;
    
    /**
     * @var LoggerInterface The logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * @var int Number of consecutive read errors
     */
    private int $readErrorCount = 0;
    
    /**
     * @var int Maximum number of consecutive read errors before the connection is closed
     */
    private const MAX_READ_ERRORS = 5;
    
    /**
     * @var SessionManager Session manager for handling session data
     */
    private SessionManager $sessionManager;
    
    /**
     * Constructor.
     * 
     * @param resource|null $stdin The input stream (defaults to STDIN)
     * @param resource|null $stdout The output stream (defaults to STDOUT)
     * @param LoggerInterface|null $logger Optional logger instance
     * @param SessionManager|null $sessionManager Optional session manager
     * 
     * @throws InvalidArgumentException If the streams are not valid resources
     */
    public function __construct(
        $stdin = null, 
        $stdout = null, 
        ?LoggerInterface $logger = null,
        ?SessionManager $sessionManager = null
    ) {
        $this->stdin = $stdin ?? fopen('php://stdin', 'r');
        $this->stdout = $stdout ?? fopen('php://stdout', 'w');
        $this->logger = $logger ?? new ConsoleLogger();
        $this->sessionManager = $sessionManager ?? new SessionManager($this->logger);
        
        if (!is_resource($this->stdin) || !is_resource($this->stdout)) {
            throw new InvalidArgumentException('Invalid stream resources provided');
        }
        
        // Set stream to non-blocking mode
        stream_set_blocking($this->stdin, false);
        
        $this->messageBuffer = new MessageBuffer();
        
        $this->logger->debug('StdioTransport created');
    }
    
    /**
     * {@inheritdoc}
     */
    public function start(): void
    {
        if ($this->started) {
            throw new ConnectionException(
                'StdioTransport already started! If using Client or Server classes, note that connect() calls start() automatically.'
            );
        }
        
        $this->started = true;
        
        // Generate a session ID if not already set
        if ($this->sessionManager->getSessionId() === null) {
            $this->sessionManager->generateSessionId();
        }
        
        $this->logger->info('StdioTransport started', [
            'sessionId' => $this->sessionManager->getSessionId()
        ]);
    }
    
    /**
     * Process incoming data from stdin.
     * 
     * This method should be called periodically to check for new messages.
     * 
     * @return void
     * @throws ConnectionException If there are too many consecutive read errors
     */
    public function processInput(): void
    {
        if (!$this->started) {
            return;
        }
        
        try {
            // Read data from stdin if available
            $data = @fread($this->stdin, 8192);
            
            if ($data === false) {
                $error = error_get_last();
                $errorMessage = $error ? $error['message'] : 'Unknown error';
                
                $this->readErrorCount++;
                $this->logger->warning('Error reading from stdin: ' . $errorMessage, [
                    'errorCount' => $this->readErrorCount,
                    'sessionId' => $this->sessionManager->getSessionId()
                ]);
                
                if ($this->readErrorCount >= self::MAX_READ_ERRORS) {
                    $exception = new ConnectionException(
                        'Too many consecutive read errors from stdin, closing connection'
                    );
                    
                    $this->handleError($exception);
                    $this->close();
                    throw $exception;
                }
                
                return;
            }
            
            // Reset error count on successful read
            if ($this->readErrorCount > 0) {
                $this->readErrorCount = 0;
            }
            
            if (strlen($data) > 0) {
                $this->logger->debug('Read ' . strlen($data) . ' bytes from stdin', [
                    'sessionId' => $this->sessionManager->getSessionId()
                ]);
                $this->messageBuffer->append($data);
                
                // Process all available messages
                $this->processBuffer();
            }
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Process all complete messages in the buffer.
     * 
     * @return void
     */
    private function processBuffer(): void
    {
        while (true) {
            try {
                $message = $this->messageBuffer->readMessage();
                if ($message === null) {
                    break;
                }
                
                $this->logger->debug('Received message', [
                    'messageType' => get_class($message),
                    'sessionId' => $this->sessionManager->getSessionId()
                ]);
                
                // Store last received message timestamp
                $this->sessionManager->set('last_received', time());
                
                if ($this->messageHandler) {
                    try {
                        ($this->messageHandler)($message);
                    } catch (Throwable $e) {
                        $this->logger->error('Error in message handler: ' . $e->getMessage(), [
                            'exception' => get_class($e),
                            'sessionId' => $this->sessionManager->getSessionId()
                        ]);
                        $this->handleError($e);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error('Error processing message: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'sessionId' => $this->sessionManager->getSessionId()
                ]);
                $this->handleError(new MessageException(
                    'Error processing message: ' . $e->getMessage(), 
                    0, 
                    $e
                ));
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function send(JsonRpcMessage $message, array $options = []): void
    {
        if (!$this->started) {
            throw new ConnectionException('Cannot send message on a transport that has not been started');
        }
        
        try {
            $json = MessageBuffer::serializeMessage($message);
            $this->logger->debug('Sending message', [
                'messageType' => get_class($message),
                'length' => strlen($json),
                'sessionId' => $this->sessionManager->getSessionId()
            ]);
            
            // Store last sent message timestamp
            $this->sessionManager->set('last_sent', time());
            
            $bytesWritten = @fwrite($this->stdout, $json);
            
            if ($bytesWritten === false || $bytesWritten < strlen($json)) {
                $error = error_get_last();
                $errorMessage = $error ? $error['message'] : 'Unknown error';
                
                throw new ConnectionException(
                    'Failed to write message to stdout: ' . $errorMessage
                );
            }
            
            fflush($this->stdout);
        } catch (Throwable $e) {
            $this->logger->error('Error sending message: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'sessionId' => $this->sessionManager->getSessionId()
            ]);
            $this->handleError($e);
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (!$this->started) {
            return;
        }
        
        $this->logger->info('Closing StdioTransport', [
            'sessionId' => $this->sessionManager->getSessionId()
        ]);
        $this->started = false;
        
        // Clear buffer
        $this->messageBuffer->clear();
        
        // Close streams if not the default ones
        if (get_resource_type($this->stdin) !== 'Unknown' && 
            stream_get_meta_data($this->stdin)['uri'] !== 'php://stdin') {
            fclose($this->stdin);
        }
        
        if (get_resource_type($this->stdout) !== 'Unknown' && 
            stream_get_meta_data($this->stdout)['uri'] !== 'php://stdout') {
            fclose($this->stdout);
        }
        
        // Clear session
        $this->sessionManager->clear();
        
        // Notify of closure
        if ($this->closeHandler) {
            try {
                ($this->closeHandler)();
            } catch (Throwable $e) {
                $this->logger->error('Error in close handler: ' . $e->getMessage(), [
                    'exception' => get_class($e)
                ]);
            }
        }
    }
    
    /**
     * Handle an error by passing it to the error handler if one is registered.
     * 
     * @param Throwable $error The error to handle
     * @return void
     */
    private function handleError(Throwable $error): void
    {
        if ($this->errorHandler) {
            try {
                ($this->errorHandler)($error);
            } catch (Throwable $e) {
                $this->logger->error('Error in error handler: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'sessionId' => $this->sessionManager->getSessionId()
                ]);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function onMessage(callable $handler): void
    {
        $this->messageHandler = $handler;
    }
    
    /**
     * {@inheritdoc}
     */
    public function onError(callable $handler): void
    {
        $this->errorHandler = $handler;
    }
    
    /**
     * {@inheritdoc}
     */
    public function onClose(callable $handler): void
    {
        $this->closeHandler = $handler;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSessionId(): ?string
    {
        return $this->sessionManager->getSessionId();
    }
    
    /**
     * Get the session manager.
     *
     * @return SessionManager The session manager
     */
    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }
    
    /**
     * Get the logger instance.
     *
     * @return LoggerInterface The logger instance
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
    
    /**
     * Set the logger instance.
     *
     * @param LoggerInterface $logger The logger to use
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}