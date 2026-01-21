<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema;

use JayI\Cortex\Plugins\Schema\Types\ArraySchema;
use JayI\Cortex\Plugins\Schema\Types\BooleanSchema;
use JayI\Cortex\Plugins\Schema\Types\EnumSchema;
use JayI\Cortex\Plugins\Schema\Types\IntegerSchema;
use JayI\Cortex\Plugins\Schema\Types\NullableSchema;
use JayI\Cortex\Plugins\Schema\Types\NumberSchema;
use JayI\Cortex\Plugins\Schema\Types\ObjectSchema;
use JayI\Cortex\Plugins\Schema\Types\StringSchema;
use JayI\Cortex\Plugins\Schema\Types\UnionSchema;

abstract class Schema
{
    protected ?string $schemaDescription = null;

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    abstract public function toJsonSchema(): array;

    /**
     * Validate a value against this schema.
     */
    abstract public function validate(mixed $value): ValidationResult;

    /**
     * Cast a value to match this schema's type.
     */
    abstract public function cast(mixed $value): mixed;

    /**
     * Create a string schema.
     */
    public static function string(): StringSchema
    {
        return new StringSchema;
    }

    /**
     * Create a number schema.
     */
    public static function number(): NumberSchema
    {
        return new NumberSchema;
    }

    /**
     * Create an integer schema.
     */
    public static function integer(): IntegerSchema
    {
        return new IntegerSchema;
    }

    /**
     * Create a boolean schema.
     */
    public static function boolean(): BooleanSchema
    {
        return new BooleanSchema;
    }

    /**
     * Create an array schema.
     */
    public static function array(Schema $items): ArraySchema
    {
        return new ArraySchema($items);
    }

    /**
     * Create an object schema.
     */
    public static function object(): ObjectSchema
    {
        return new ObjectSchema;
    }

    /**
     * Create an enum schema.
     *
     * @param  array<int, string|int|float|bool>  $values
     */
    public static function enum(array $values): EnumSchema
    {
        return new EnumSchema($values);
    }

    /**
     * Create a union schema (anyOf).
     */
    public static function anyOf(Schema ...$schemas): UnionSchema
    {
        return new UnionSchema($schemas, 'anyOf');
    }

    /**
     * Create a union schema (oneOf).
     */
    public static function oneOf(Schema ...$schemas): UnionSchema
    {
        return new UnionSchema($schemas, 'oneOf');
    }

    /**
     * Create a nullable schema wrapper.
     */
    public static function nullable(Schema $schema): NullableSchema
    {
        return new NullableSchema($schema);
    }

    /**
     * Create a schema from a JSON Schema array.
     *
     * @param  array<string, mixed>  $schema
     */
    public static function fromJsonSchema(array $schema): Schema
    {
        return SchemaFactory::fromJsonSchema($schema);
    }

    /**
     * Create a schema from a Data class.
     *
     * @param  class-string  $class
     */
    public static function fromDataClass(string $class): ObjectSchema
    {
        return SchemaFactory::fromDataClass($class);
    }

    /**
     * Set the description for this schema.
     */
    public function description(string $description): static
    {
        $this->schemaDescription = $description;

        return $this;
    }

    /**
     * Get the description for this schema.
     */
    public function getDescription(): ?string
    {
        return $this->schemaDescription;
    }

    /**
     * Add description to JSON Schema array if set.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function addDescriptionToSchema(array $schema): array
    {
        if ($this->schemaDescription !== null) {
            $schema['description'] = $this->schemaDescription;
        }

        return $schema;
    }
}
