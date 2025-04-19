<?php

namespace MCP\Protocol\Resources;

/**
 * Implementation of RFC 6570 URI Templates
 */
class UriTemplate
{
    private const MAX_TEMPLATE_LENGTH = 1000000; // 1MB
    private const MAX_VARIABLE_LENGTH = 1000000; // 1MB
    private const MAX_TEMPLATE_EXPRESSIONS = 10000;
    private const MAX_REGEX_LENGTH = 1000000; // 1MB

    private string $template;
    private array $parts;

    /**
     * Create a new URI template
     */
    public function __construct(string $template)
    {
        $this->validateLength($template, self::MAX_TEMPLATE_LENGTH, "Template");
        $this->template = $template;
        $this->parts = $this->parse($template);
    }

    /**
     * Returns true if the given string contains any URI template expressions.
     * A template expression is a sequence of characters enclosed in curly braces,
     * like {foo} or {?bar}.
     */
    public static function isTemplate(string $str): bool
    {
        // Look for any sequence of characters between curly braces
        // that isn't just whitespace
        return (bool) preg_match('/\{[^}\s]+\}/', $str);
    }

    /**
     * Get all variable names in this template
     */
    public function getVariableNames(): array
    {
        $names = [];
        foreach ($this->parts as $part) {
            if (is_array($part)) {
                $names = array_merge($names, $part['names']);
            }
        }
        return $names;
    }

    /**
     * Convert template to string
     */
    public function __toString(): string
    {
        return $this->template;
    }

    /**
     * Parse a URI template into parts
     */
    private function parse(string $template): array
    {
        $parts = [];
        $currentText = "";
        $i = 0;
        $expressionCount = 0;

        while ($i < strlen($template)) {
            if ($template[$i] === "{") {
                if ($currentText) {
                    $parts[] = $currentText;
                    $currentText = "";
                }
                
                $end = strpos($template, "}", $i);
                if ($end === false) {
                    throw new \InvalidArgumentException("Unclosed template expression");
                }

                $expressionCount++;
                if ($expressionCount > self::MAX_TEMPLATE_EXPRESSIONS) {
                    throw new \InvalidArgumentException(
                        "Template contains too many expressions (max " . self::MAX_TEMPLATE_EXPRESSIONS . ")"
                    );
                }

                $expr = substr($template, $i + 1, $end - $i - 1);
                $operator = $this->getOperator($expr);
                $exploded = str_contains($expr, "*");
                $names = $this->getNames($expr);
                $name = $names[0] ?? '';

                // Validate variable name length
                foreach ($names as $varName) {
                    $this->validateLength($varName, self::MAX_VARIABLE_LENGTH, "Variable name");
                }

                $parts[] = [
                    'name' => $name,
                    'operator' => $operator,
                    'names' => $names,
                    'exploded' => $exploded
                ];
                
                $i = $end + 1;
            } else {
                $currentText .= $template[$i];
                $i++;
            }
        }

        if ($currentText) {
            $parts[] = $currentText;
        }

        return $parts;
    }

    /**
     * Get the operator from an expression
     */
    private function getOperator(string $expr): string
    {
        $operators = ['+', '#', '.', '/', '?', '&'];
        foreach ($operators as $op) {
            if (str_starts_with($expr, $op)) {
                return $op;
            }
        }
        return "";
    }

    /**
     * Get variable names from an expression
     */
    private function getNames(string $expr): array
    {
        $operator = $this->getOperator($expr);
        $names = explode(',', substr($expr, strlen($operator)));
        
        return array_filter(array_map(function ($name) {
            return trim(str_replace('*', '', $name));
        }, $names), function ($name) {
            return strlen($name) > 0;
        });
    }

    /**
     * Encode a value according to the operator
     */
    private function encodeValue(string $value, string $operator): string
    {
        $this->validateLength($value, self::MAX_VARIABLE_LENGTH, "Variable value");
        
        if ($operator === '+' || $operator === '#') {
            return rawurlencode($value);
        }
        
        return urlencode($value);
    }

    /**
     * Expand a part of the template
     * 
     * @param array $part The template part
     * @param array $variables The variables to use
     * @return string The expanded part
     */
    private function expandPart(array $part, array $variables): string
    {
        if ($part['operator'] === '?' || $part['operator'] === '&') {
            $pairs = [];
            foreach ($part['names'] as $name) {
                if (!isset($variables[$name])) {
                    continue;
                }
                
                $value = $variables[$name];
                if (is_array($value)) {
                    $encoded = implode(',', array_map(fn($v) => $this->encodeValue($v, $part['operator']), $value));
                } else {
                    $encoded = $this->encodeValue((string)$value, $part['operator']);
                }
                
                $pairs[] = "$name=$encoded";
            }

            if (empty($pairs)) {
                return "";
            }
            
            $separator = $part['operator'] === '?' ? '?' : '&';
            return $separator . implode('&', $pairs);
        }

        if (count($part['names']) > 1) {
            $values = [];
            foreach ($part['names'] as $name) {
                if (isset($variables[$name])) {
                    $val = $variables[$name];
                    if (is_array($val)) {
                        $values[] = $val[0];
                    } else {
                        $values[] = $val;
                    }
                }
            }
            
            if (empty($values)) {
                return "";
            }
            
            return implode(',', $values);
        }

        $name = $part['name'];
        if (!isset($variables[$name])) {
            return "";
        }

        $value = $variables[$name];
        $values = is_array($value) ? $value : [$value];
        $encoded = array_map(fn($v) => $this->encodeValue((string)$v, $part['operator']), $values);

        switch ($part['operator']) {
            case '':
                return implode(',', $encoded);
            case '+':
                return implode(',', $encoded);
            case '#':
                return '#' . implode(',', $encoded);
            case '.':
                return '.' . implode('.', $encoded);
            case '/':
                return '/' . implode('/', $encoded);
            default:
                return implode(',', $encoded);
        }
    }

    /**
     * Expand the template with the given variables
     * 
     * @param array $variables The variables to use for expansion
     * @return string The expanded URI
     */
    public function expand(array $variables): string
    {
        $result = "";
        $hasQueryParam = false;

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $result .= $part;
                continue;
            }

            $expanded = $this->expandPart($part, $variables);
            if (!$expanded) {
                continue;
            }

            // Convert ? to & if we already have a query parameter
            if (($part['operator'] === '?' || $part['operator'] === '&') && $hasQueryParam) {
                $result .= str_replace('?', '&', $expanded);
            } else {
                $result .= $expanded;
            }

            if ($part['operator'] === '?' || $part['operator'] === '&') {
                $hasQueryParam = true;
            }
        }

        return $result;
    }

    /**
     * Escape a string for use in a regular expression
     */
    private function escapeRegExp(string $str): string
    {
        return preg_quote($str, '/');
    }

    /**
     * Convert a template part to a regular expression
     */
    private function partToRegExp(array $part): array
    {
        $patterns = [];

        // Validate variable name length for matching
        foreach ($part['names'] as $name) {
            $this->validateLength($name, self::MAX_VARIABLE_LENGTH, "Variable name");
        }

        if ($part['operator'] === '?' || $part['operator'] === '&') {
            for ($i = 0; $i < count($part['names']); $i++) {
                $name = $part['names'][$i];
                $prefix = $i === 0 ? '\\' . $part['operator'] : '&';
                $patterns[] = [
                    'pattern' => $prefix . $this->escapeRegExp($name) . '=([^&]+)',
                    'name' => $name
                ];
            }
            return $patterns;
        }

        $pattern = '';
        $name = $part['name'];

        switch ($part['operator']) {
            case '':
                $pattern = $part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)';
                break;
            case '+':
            case '#':
                $pattern = '(.+)';
                break;
            case '.':
                $pattern = '\\.([^/,]+)';
                break;
            case '/':
                $pattern = '/' . ($part['exploded'] ? '([^/]+(?:,[^/]+)*)' : '([^/,]+)');
                break;
            default:
                $pattern = '([^/]+)';
        }

        $patterns[] = ['pattern' => $pattern, 'name' => $name];
        return $patterns;
    }

    /**
     * Match a URI against this template
     * 
     * @param string $uri The URI to match
     * @return array|null The extracted variables or null if no match
     */
    public function match(string $uri): ?array
    {
        $this->validateLength($uri, self::MAX_TEMPLATE_LENGTH, "URI");
        $pattern = '^';
        $names = [];

        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $pattern .= $this->escapeRegExp($part);
            } else {
                $patterns = $this->partToRegExp($part);
                foreach ($patterns as $patternData) {
                    $pattern .= $patternData['pattern'];
                    $names[] = [
                        'name' => $patternData['name'],
                        'exploded' => $part['exploded']
                    ];
                }
            }
        }

        $pattern .= '$';
        $this->validateLength($pattern, self::MAX_REGEX_LENGTH, "Generated regex pattern");
        
        if (!preg_match('/' . $pattern . '/', $uri, $matches)) {
            return null;
        }

        $result = [];
        for ($i = 0; $i < count($names); $i++) {
            $name = $names[$i]['name'];
            $exploded = $names[$i]['exploded'];
            $value = $matches[$i + 1];
            $cleanName = str_replace('*', '', $name);

            if ($exploded && str_contains($value, ',')) {
                $result[$cleanName] = explode(',', $value);
            } else {
                $result[$cleanName] = $value;
            }
        }

        return $result;
    }

    /**
     * Validate the length of a string
     */
    private function validateLength(string $str, int $max, string $context): void
    {
        if (strlen($str) > $max) {
            throw new \InvalidArgumentException(
                "$context exceeds maximum length of $max characters (got " . strlen($str) . ")"
            );
        }
    }
}