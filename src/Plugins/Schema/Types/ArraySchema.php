<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationError;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class ArraySchema extends Schema
{
    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected bool $uniqueItems = false;

    public function __construct(
        protected Schema $itemsSchema,
    ) {}

    /**
     * Set the items schema.
     */
    public function items(Schema $schema): static
    {
        $this->itemsSchema = $schema;

        return $this;
    }

    /**
     * Set minimum number of items.
     */
    public function minItems(int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    /**
     * Set maximum number of items.
     */
    public function maxItems(int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    /**
     * Require unique items.
     */
    public function uniqueItems(bool $unique = true): static
    {
        $this->uniqueItems = $unique;

        return $this;
    }

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = [
            'type' => 'array',
            'items' => $this->itemsSchema->toJsonSchema(),
        ];

        if ($this->minItems !== null) {
            $schema['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        if ($this->uniqueItems) {
            $schema['uniqueItems'] = true;
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (! is_array($value)) {
            return ValidationResult::error('$', 'Value must be an array', $value);
        }

        $errors = [];
        $count = count($value);

        if ($this->minItems !== null && $count < $this->minItems) {
            $errors[] = new ValidationError(
                '$',
                "Array must have at least {$this->minItems} items",
                $value
            );
        }

        if ($this->maxItems !== null && $count > $this->maxItems) {
            $errors[] = new ValidationError(
                '$',
                "Array must have at most {$this->maxItems} items",
                $value
            );
        }

        if ($this->uniqueItems && count($value) !== count(array_unique($value, SORT_REGULAR))) {
            $errors[] = new ValidationError(
                '$',
                'Array items must be unique',
                $value
            );
        }

        // Validate each item against the items schema
        foreach ($value as $index => $item) {
            $result = $this->itemsSchema->validate($item);
            if (! $result->isValid()) {
                foreach ($result->errors as $error) {
                    $path = $error->path === '$' ? "\$[{$index}]" : "\$[{$index}].".substr($error->path, 2);
                    $errors[] = new ValidationError($path, $error->message, $error->value);
                }
            }
        }

        if (count($errors) > 0) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to an array with cast items.
     *
     * @return array<int, mixed>
     */
    public function cast(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(
            fn ($item) => $this->itemsSchema->cast($item),
            $value
        );
    }
}
