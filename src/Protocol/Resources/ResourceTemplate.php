<?php

namespace ModelContextProtocol\Protocol\Resources;

/**
 * Resource template for dynamic URIs
 */
class ResourceTemplate
{
    private string $template;
    private ?array $listOptions;
    private UriTemplate $uriTemplate;

    /**
     * Create a new resource template
     *
     * @param string $template The URI template
     * @param array|null $listOptions Options for listing this resource
     */
    public function __construct(string $template, ?array $listOptions = null)
    {
        $this->template = $template;
        $this->listOptions = $listOptions;
        $this->uriTemplate = new UriTemplate($template);
    }

    /**
     * Check if a URI matches this template
     */
    public function matches(string $uri): bool
    {
        return $this->uriTemplate->match($uri) !== null;
    }

    /**
     * Extract parameters from a URI
     */
    public function extract(string $uri): array
    {
        $params = $this->uriTemplate->match($uri);
        return $params ?? [];
    }

    /**
     * Expand this template with the given variables
     */
    public function expand(array $variables): string
    {
        return $this->uriTemplate->expand($variables);
    }

    /**
     * Get the list options for this template
     */
    public function getListOptions(): ?array
    {
        return $this->listOptions;
    }

    /**
     * Get the template string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Get the variable names in this template
     */
    public function getVariableNames(): array
    {
        return $this->uriTemplate->getVariableNames();
    }

    /**
     * Convert to string
     */
    public function __toString(): string
    {
        return $this->template;
    }
}
