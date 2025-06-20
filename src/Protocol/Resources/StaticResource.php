<?php

namespace ModelContextProtocol\Protocol\Resources;

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
     * @param array<mixed> $content The resource content
     * @param array<mixed>|null $listOptions Options for listing this resource
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

    /**
     * Update the content of this resource
     *
     * @param array<mixed> $content The new content
     * @return void
     */
    public function updateContent(array $content): void
    {
        $this->content = $content;
    }

    /**
     * Get the current content of this resource
     *
     * @return array<mixed> The current content
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * Get the URI of this resource
     *
     * @return string The resource URI
     */
    public function getUri(): string
    {
        return $this->template->getTemplate();
    }
}
