<?php

namespace ModelContextProtocol\Protocol\Resources;

/**
 * Base class for all MCP resources
 */
abstract class Resource
{
    protected string $name;
    protected ResourceTemplate $template;

    /**
     * Create a new resource
     * 
     * @param string $name The resource name
     * @param ResourceTemplate $template The resource template
     */
    public function __construct(string $name, ResourceTemplate $template)
    {
        $this->name = $name;
        $this->template = $template;
    }

    /**
     * Get the resource name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the resource template
     */
    public function getTemplate(): ResourceTemplate
    {
        return $this->template;
    }

    /**
     * Check if a URI matches this resource
     */
    public function matches(string $uri): bool
    {
        return $this->template->matches($uri);
    }

    /**
     * Extract parameters from a URI
     */
    public function extract(string $uri): array
    {
        return $this->template->extract($uri);
    }

    /**
     * Handle a request for this resource
     * 
     * @param string $uri The requested URI
     * @param array $params The parameters extracted from the URI
     * @return array The resource content
     */
    abstract public function handle(string $uri, array $params): array;
}