<?php

namespace MCP\Protocol\Resources;

/**
 * A static resource with fixed content
 */
class StaticResource extends Resource
{
    private array $content;

    /**
     * Create a new static resource
     * 
     * @param string $name The resource name
     * @param string $uri The resource URI
     * @param array $content The resource content
     * @param array|null $listOptions Options for listing this resource
     */
    public function __construct(
        string $name,
        string $uri,
        array $content,
        ?array $listOptions = null
    ) {
        $template = new ResourceTemplate($uri, $listOptions);
        parent::__construct($name, $template);
        $this->content = $content;
    }

    /**
     * Handle a request for this resource
     */
    public function handle(string $uri, array $params): array
    {
        return [
            'content' => $this->content
        ];
    }
}