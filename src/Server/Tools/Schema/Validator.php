<?php

namespace ModelContextProtocol\Server\Tools\Schema;

use ModelContextProtocol\Server\Schema\SchemaInterface;

/**
 * Validates parameters against a schema
 */
class Validator
{
    /**
     * Validate parameters against a schema
     *
     * @param array $params The parameters to validate
     * @param SchemaInterface $schema The schema to validate against
     * @throws ValidationException If validation fails
     */
    public function validate(array $params, SchemaInterface $schema): void
    {
        $errors = [];

        // Validate required properties
        foreach ($schema->getRequired() as $required) {
            if (!isset($params[$required])) {
                $errors[] = "Missing required parameter: $required";
            }
        }

        // Validate properties types
        foreach ($params as $name => $value) {
            $properties = $schema->getProperties();

            if (!isset($properties[$name])) {
                $errors[] = "Unknown parameter: $name";
                continue;
            }

            $propertySchema = $properties[$name];
            $type = $propertySchema['type'] ?? 'any';

            if (!$this->validateType($value, $type)) {
                $errors[] = "Invalid type for parameter $name: expected $type";
            }

            // Validate enum if present
            if (isset($propertySchema['enum']) && !in_array($value, $propertySchema['enum'])) {
                $enum = implode(', ', $propertySchema['enum']);
                $errors[] = "Invalid value for parameter $name: must be one of [$enum]";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate a value against a type
     *
     * @param mixed $value The value to validate
     * @param string $type The expected type
     * @return mixed Always returns true/false for type validation
     */
    private function validateType(mixed $value, string $type): mixed
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'number':
            case 'integer':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_array($value) && array_keys($value) !== range(0, count($value) - 1);
            case 'null':
                return $value === null;
            case 'any':
                return true;
            default:
                // Unknown type, validation passes
                return true;
        }
    }
}
