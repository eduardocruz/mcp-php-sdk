<?php

namespace ModelContextProtocol\Protocol\Resources;

use ModelContextProtocol\Protocol\Notifications\NotificationManager;

/**
 * Manages resources for the MCP server
 */
class ResourceManager
{
    /** @var Resource[] */
    private array $resources = [];
    private array $changeListeners = [];

    /**
     * @var NotificationManager|null The notification manager for sending notifications
     */
    private ?NotificationManager $notificationManager = null;

    /**
     * Set the notification manager for sending automatic notifications.
     *
     * @param NotificationManager|null $notificationManager The notification manager
     * @return void
     */
    public function setNotificationManager(?NotificationManager $notificationManager): void
    {
        $this->notificationManager = $notificationManager;
    }

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
     * Get all registered resources
     */
    public function getAll(): array
    {
        return $this->resources;
    }

    /**
     * List all listable static resources (not templates)
     */
    public function list(): array
    {
        $result = [];

        foreach ($this->resources as $name => $resource) {
            // Only include static resources in the main list
            if ($resource instanceof StaticResource) {
                $template = $resource->getTemplate();
                $listOptions = $template->getListOptions();

                if ($listOptions !== null) {
                    $result[] = [
                        'name' => $name,
                        'uri' => (string)$template,
                        'description' => $listOptions['description'] ?? ''
                    ];
                }
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
     * Update the content of a resource and notify subscribers.
     *
     * @param string $name The resource name
     * @param array<string, mixed> $content The new content
     * @return void
     */
    public function updateResource(string $name, array $content): void
    {
        $resource = $this->getResource($name);
        if ($resource instanceof StaticResource) {
            $resource->updateContent($content);

            // Send resource updated notification if notification manager is available
            if ($this->notificationManager !== null) {
                $this->notificationManager->sendResourceUpdated($resource->getUri(), $content);
            }
        }
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

        // Send resource list changed notification if notification manager is available
        if ($this->notificationManager !== null) {
            $this->notificationManager->sendResourceListChanged();
        }
    }
}
