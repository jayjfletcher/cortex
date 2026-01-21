<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class UnionSchema extends Schema
{
    /**
     * @param  array<int, Schema>  $schemas
     * @param  'anyOf'|'oneOf'  $type
     */
    public function __construct(
        protected array $schemas,
        protected string $type = 'anyOf',
    ) {}

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = [
            $this->type => array_map(
                fn (Schema $s) => $s->toJsonSchema(),
                $this->schemas
            ),
        ];

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        $validCount = 0;
        $allErrors = [];

        foreach ($this->schemas as $schema) {
            $result = $schema->validate($value);
            if ($result->isValid()) {
                $validCount++;
                if ($this->type === 'anyOf') {
                    // For anyOf, one valid match is sufficient
                    return ValidationResult::valid();
                }
            } else {
                $allErrors = array_merge($allErrors, $result->errors);
            }
        }

        // For oneOf, exactly one schema must match
        if ($this->type === 'oneOf') {
            if ($validCount === 1) {
                return ValidationResult::valid();
            }

            if ($validCount > 1) {
                return ValidationResult::error(
                    '$',
                    'Value matches more than one schema in oneOf',
                    $value
                );
            }
        }

        // If no schema matched, return all errors
        return ValidationResult::error(
            '$',
            'Value does not match any schema in '.$this->type,
            $value
        );
    }

    /**
     * Cast a value using the first matching schema.
     */
    public function cast(mixed $value): mixed
    {
        foreach ($this->schemas as $schema) {
            $result = $schema->validate($value);
            if ($result->isValid()) {
                return $schema->cast($value);
            }
        }

        // If no schema matches, return the first schema's cast (best effort)
        return $this->schemas[0]->cast($value);
    }

    /**
     * Get the schemas in this union.
     *
     * @return array<int, Schema>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Get the union type.
     *
     * @return 'anyOf'|'oneOf'
     */
    public function getType(): string
    {
        return $this->type;
    }
}
