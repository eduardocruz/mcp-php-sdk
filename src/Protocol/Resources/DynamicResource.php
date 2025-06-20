<?php

namespace ModelContextProtocol\Protocol\Resources;

/**
 * A dynamic resource with content determined by a handler function
 */
class DynamicResource extends Resource
{
    private \Closure $handler;

    /**
     * Create a new dynamic resource
     *
     * @param string $name The resource name
     * @param ResourceTemplate $template The resource template
     * @param callable $handler The handler function
     */
    public function __construct(
        string $name,
        ResourceTemplate $template,
        callable $handler
    ) {
        parent::__construct($name, $template);
        $this->handler = \Closure::fromCallable($handler);
    }

    /**
     * Handle a request for this resource
     */
    public function handle(string $uri, array $params): array
    {
        return ($this->handler)($uri, $params);
    }
}
