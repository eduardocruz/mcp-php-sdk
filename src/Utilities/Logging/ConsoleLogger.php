<?php

namespace ModelContextProtocol\Utilities\Logging;

/**
 * A simple console logger implementation that writes to stderr.
 */
class ConsoleLogger implements LoggerInterface
{
    /**
     * Logging levels from syslog protocol.
     */
    private const LOG_LEVEL_EMERGENCY = 'emergency';
    private const LOG_LEVEL_ALERT = 'alert';
    private const LOG_LEVEL_CRITICAL = 'critical';
    private const LOG_LEVEL_ERROR = 'error';
    private const LOG_LEVEL_WARNING = 'warning';
    private const LOG_LEVEL_NOTICE = 'notice';
    private const LOG_LEVEL_INFO = 'info';
    private const LOG_LEVEL_DEBUG = 'debug';
    
    /**
     * @var array<string, int> Mapping of log level names to numeric priorities
     */
    private const LOG_LEVELS = [
        self::LOG_LEVEL_EMERGENCY => 0,
        self::LOG_LEVEL_ALERT => 1,
        self::LOG_LEVEL_CRITICAL => 2,
        self::LOG_LEVEL_ERROR => 3,
        self::LOG_LEVEL_WARNING => 4,
        self::LOG_LEVEL_NOTICE => 5,
        self::LOG_LEVEL_INFO => 6,
        self::LOG_LEVEL_DEBUG => 7,
    ];
    
    /**
     * @var string The minimum log level to output
     */
    private string $minLevel;
    
    /**
     * @var resource The error output stream
     */
    private $errorOutput;
    
    /**
     * Constructor.
     *
     * @param string $minLevel The minimum log level to output
     * @param resource|null $errorOutput The error output stream (defaults to STDERR)
     */
    public function __construct(string $minLevel = self::LOG_LEVEL_INFO, $errorOutput = null)
    {
        $this->minLevel = strtolower($minLevel);
        $this->errorOutput = $errorOutput ?? fopen('php://stderr', 'w');
    }
    
    /**
     * {@inheritdoc}
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_EMERGENCY, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ALERT, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_NOTICE, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        
        // Skip if level is below minimum level
        if (!isset(self::LOG_LEVELS[$level]) || !isset(self::LOG_LEVELS[$this->minLevel])) {
            return;
        }
        
        if (self::LOG_LEVELS[$level] > self::LOG_LEVELS[$this->minLevel]) {
            return;
        }
        
        // Format the message
        $formatted = $this->formatMessage($level, $message, $context);
        
        // Write to error output
        fwrite($this->errorOutput, $formatted . PHP_EOL);
    }
    
    /**
     * Format a log message.
     *
     * @param string $level The log level
     * @param string $message The message to log
     * @param array<string, mixed> $context Additional data to include in the log
     * @return string The formatted message
     */
    private function formatMessage(string $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Replace placeholders in the message with context values
        $message = $this->interpolate($message, $context);
        
        // Format: [TIMESTAMP] [LEVEL] MESSAGE
        $formatted = "[$timestamp] [$levelUpper] $message";
        
        // Add any context data not used in interpolation
        if (!empty($context)) {
            $json = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $formatted .= " $json";
        }
        
        return $formatted;
    }
    
    /**
     * Interpolate placeholders in the message with context values.
     *
     * @param string $message The message with placeholders
     * @param array<string, mixed> $context The context data
     * @return string The interpolated message
     */
    private function interpolate(string $message, array &$context = []): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        
        foreach ($context as $key => $val) {
            // Check if the value can be cast to string
            if ($val === null || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
                // Remove from context since it's been used
                unset($context[$key]);
            }
        }
        
        // Replace placeholders with values
        return strtr($message, $replace);
    }
}