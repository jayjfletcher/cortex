<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class NullableSchema extends Schema
{
    public function __construct(
        protected Schema $wrappedSchema,
    ) {}

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = $this->wrappedSchema->toJsonSchema();

        // Handle the type field to make it nullable
        if (isset($schema['type'])) {
            if (is_array($schema['type'])) {
                if (! in_array('null', $schema['type'], true)) {
                    $schema['type'][] = 'null';
                }
            } else {
                $schema['type'] = [$schema['type'], 'null'];
            }
        } else {
            // If there's no type (like for enum), use anyOf
            $schema = [
                'anyOf' => [
                    $schema,
                    ['type' => 'null'],
                ],
            ];
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        // Null is always valid for nullable schemas
        if ($value === null) {
            return ValidationResult::valid();
        }

        // Otherwise, validate against the wrapped schema
        return $this->wrappedSchema->validate($value);
    }

    /**
     * Cast a value, allowing null.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return $this->wrappedSchema->cast($value);
    }

    /**
     * Get the wrapped schema.
     */
    public function getWrappedSchema(): Schema
    {
        return $this->wrappedSchema;
    }
}
