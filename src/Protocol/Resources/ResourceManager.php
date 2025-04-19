<?php

namespace ModelContextProtocol\Protocol\Resources;

/**
 * Manages resources for the MCP server
 */
class ResourceManager
{
    /** @var Resource[] */
    private array $resources = [];
    private array $changeListeners = [];

    /**
     * Register a static resource
     */
    public function registerStatic(
        string $name,
        string $uri,
        array $content,
        ?array $listOptions = null
    ): StaticResource {
        $resource = new StaticResource($name, $uri, $content, $listOptions);
        $this->resources[$name] = $resource;
        $this->notifyListeners();
        return $resource;
    }

    /**
     * Register a dynamic resource with a template
     */
    public function registerDynamic(
        string $name,
        string|ResourceTemplate $template,
        callable $handler
    ): DynamicResource {
        if (is_string($template)) {
            $template = new ResourceTemplate($template);
        }
        
        $resource = new DynamicResource($name, $template, $handler);
        $this->resources[$name] = $resource;
        $this->notifyListeners();
        return $resource;
    }

    /**
     * Unregister a resource
     */
    public function unregister(string $name): void
    {
        if (isset($this->resources[$name])) {
            unset($this->resources[$name]);
            $this->notifyListeners();
        }
    }

    /**
     * Resolve a URI to a resource
     * 
     * @param string $uri The URI to resolve
     * @return array|null The resolved resource or null if not found
     */
    public function resolve(string $uri): ?array
    {
        foreach ($this->resources as $name => $resource) {
            if ($resource->matches($uri)) {
                return [
                    'name' => $name,
                    'resource' => $resource,
                    'params' => $resource->extract($uri)
                ];
            }
        }
        
        return null;
    }

    /**
     * Get a resource by name
     */
    public function getResource(string $name): ?Resource
    {
        return $this->resources[$name] ?? null;
    }

    /**
     * List all listable resources
     */
    public function list(): array
    {
        $result = [];
        
        foreach ($this->resources as $name => $resource) {
            $template = $resource->getTemplate();
            $listOptions = $template->getListOptions();
            
            if ($listOptions !== null) {
                $result[] = [
                    'name' => $name,
                    'template' => (string)$template,
                    'listOptions' => $listOptions
                ];
            }
        }
        
        return $result;
    }

    /**
     * Register a listener for resource changes
     */
    public function onListChanged(callable $listener): void
    {
        $this->changeListeners[] = $listener;
    }

    /**
     * Notify all listeners of a change
     */
    private function notifyListeners(): void
    {
        $resources = $this->list();
        foreach ($this->changeListeners as $listener) {
            $listener($resources);
        }
    }
}