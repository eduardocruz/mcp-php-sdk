<?php

namespace ModelContextProtocol\Server\Schema;

/**
 * Interface for schema objects that can be validated
 */
interface SchemaInterface
{
    /**
     * Get the schema properties
     */
    public function getProperties(): array;
    
    /**
     * Get the required properties
     */
    public function getRequired(): array;
    
    /**
     * Get the schema description
     */
    public function getDescription(): ?string;
}