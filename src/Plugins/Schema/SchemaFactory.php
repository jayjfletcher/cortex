<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema;

use InvalidArgumentException;
use JayI\Cortex\Plugins\Schema\Attributes\SchemaProperty;
use JayI\Cortex\Plugins\Schema\Attributes\SchemaRequired;
use JayI\Cortex\Plugins\Schema\Types\ArraySchema;
use JayI\Cortex\Plugins\Schema\Types\BooleanSchema;
use JayI\Cortex\Plugins\Schema\Types\EnumSchema;
use JayI\Cortex\Plugins\Schema\Types\IntegerSchema;
use JayI\Cortex\Plugins\Schema\Types\NullableSchema;
use JayI\Cortex\Plugins\Schema\Types\NumberSchema;
use JayI\Cortex\Plugins\Schema\Types\ObjectSchema;
use JayI\Cortex\Plugins\Schema\Types\StringSchema;
use JayI\Cortex\Plugins\Schema\Types\UnionSchema;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class SchemaFactory
{
    /**
     * Create a schema from a JSON Schema array.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    public static function fromJsonSchema(array $jsonSchema): Schema
    {
        // Handle anyOf/oneOf
        if (isset($jsonSchema['anyOf'])) {
            $schemas = array_map(
                fn ($s) => self::fromJsonSchema($s),
                $jsonSchema['anyOf']
            );

            return new UnionSchema($schemas, 'anyOf');
        }

        if (isset($jsonSchema['oneOf'])) {
            $schemas = array_map(
                fn ($s) => self::fromJsonSchema($s),
                $jsonSchema['oneOf']
            );

            return new UnionSchema($schemas, 'oneOf');
        }

        // Handle enum
        if (isset($jsonSchema['enum'])) {
            $schema = new EnumSchema($jsonSchema['enum']);
            if (isset($jsonSchema['default'])) {
                $schema->default($jsonSchema['default']);
            }
            if (isset($jsonSchema['description'])) {
                $schema->description($jsonSchema['description']);
            }

            return $schema;
        }

        // Handle type-based schemas
        $type = $jsonSchema['type'] ?? null;

        // Handle nullable types
        if (is_array($type) && count($type) === 2 && in_array('null', $type, true)) {
            $nonNullType = array_values(array_filter($type, fn ($t) => $t !== 'null'))[0];
            $innerSchema = self::fromJsonSchema(array_merge($jsonSchema, ['type' => $nonNullType]));

            return new NullableSchema($innerSchema);
        }

        return match ($type) {
            'string' => self::createStringSchema($jsonSchema),
            'number' => self::createNumberSchema($jsonSchema),
            'integer' => self::createIntegerSchema($jsonSchema),
            'boolean' => self::createBooleanSchema($jsonSchema),
            'array' => self::createArraySchema($jsonSchema),
            'object' => self::createObjectSchema($jsonSchema),
            default => throw new InvalidArgumentException("Unknown schema type: {$type}"),
        };
    }

    /**
     * Create a schema from a Data class.
     *
     * @param  class-string  $class
     */
    public static function fromDataClass(string $class): ObjectSchema
    {
        $reflection = new ReflectionClass($class);
        $schema = new ObjectSchema();

        // Get required properties from class attribute
        $requiredAttribute = $reflection->getAttributes(SchemaRequired::class, ReflectionAttribute::IS_INSTANCEOF);
        $requiredFromAttribute = [];
        if (count($requiredAttribute) > 0) {
            $requiredFromAttribute = $requiredAttribute[0]->newInstance()->properties;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertySchema = self::createPropertySchema($property);
            $schema->property($property->getName(), $propertySchema);

            // Determine if property is required
            $isRequired = in_array($property->getName(), $requiredFromAttribute, true);

            // Properties without defaults and not nullable are required
            if (! $isRequired && ! $property->hasDefaultValue()) {
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && ! $type->allowsNull()) {
                    $isRequired = true;
                }
            }

            if ($isRequired) {
                $schema->required($property->getName());
            }
        }

        return $schema;
    }

    /**
     * Create a schema for a property based on its type and attributes.
     */
    protected static function createPropertySchema(ReflectionProperty $property): Schema
    {
        $type = $property->getType();
        $attribute = $property->getAttributes(SchemaProperty::class, ReflectionAttribute::IS_INSTANCEOF);
        $attrInstance = count($attribute) > 0 ? $attribute[0]->newInstance() : null;

        // Get doc comment for array type hints
        $docComment = $property->getDocComment();

        $schema = self::createSchemaFromType($type, $docComment);

        // Apply attribute constraints
        if ($attrInstance !== null) {
            $schema = self::applyAttributeConstraints($schema, $attrInstance);
        }

        return $schema;
    }

    /**
     * Create a schema from a reflection type.
     */
    protected static function createSchemaFromType(
        ?\ReflectionType $type,
        string|false $docComment = false
    ): Schema {
        if ($type === null) {
            // No type hint, default to string
            return new StringSchema();
        }

        if ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();

            // Check for nullable union (Type|null)
            $nullableIndex = null;
            foreach ($types as $index => $t) {
                if ($t instanceof ReflectionNamedType && $t->getName() === 'null') {
                    $nullableIndex = $index;
                    break;
                }
            }

            if ($nullableIndex !== null && count($types) === 2) {
                $nonNullType = $types[$nullableIndex === 0 ? 1 : 0];

                return new NullableSchema(self::createSchemaFromType($nonNullType, $docComment));
            }

            // Multiple non-null types
            $schemas = array_map(
                fn ($t) => self::createSchemaFromType($t, $docComment),
                $types
            );

            return new UnionSchema($schemas, 'anyOf');
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $schema = match ($typeName) {
                'string' => new StringSchema(),
                'int' => new IntegerSchema(),
                'float' => new NumberSchema(),
                'bool' => new BooleanSchema(),
                'array' => self::createArraySchemaFromDocComment($docComment),
                default => new StringSchema(), // Default to string for unknown types
            };

            if ($type->allowsNull()) {
                return new NullableSchema($schema);
            }

            return $schema;
        }

        return new StringSchema();
    }

    /**
     * Try to create an array schema from doc comment.
     */
    protected static function createArraySchemaFromDocComment(string|false $docComment): ArraySchema
    {
        $itemSchema = new StringSchema(); // Default

        if ($docComment !== false) {
            // Match @var string[], @var array<string>, @var array<int, string>, etc.
            if (preg_match('/@var\s+(?:array<(?:\w+,\s*)?(\w+)>|(\w+)\[\])/', $docComment, $matches)) {
                $itemType = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? 'string');
                $itemSchema = match (strtolower($itemType)) {
                    'string' => new StringSchema(),
                    'int', 'integer' => new IntegerSchema(),
                    'float', 'double' => new NumberSchema(),
                    'bool', 'boolean' => new BooleanSchema(),
                    default => new StringSchema(),
                };
            }
        }

        return new ArraySchema($itemSchema);
    }

    /**
     * Apply SchemaProperty attribute constraints to a schema.
     */
    protected static function applyAttributeConstraints(Schema $schema, SchemaProperty $attr): Schema
    {
        if ($schema instanceof StringSchema) {
            if ($attr->minLength !== null) {
                $schema->minLength($attr->minLength);
            }
            if ($attr->maxLength !== null) {
                $schema->maxLength($attr->maxLength);
            }
            if ($attr->pattern !== null) {
                $schema->pattern($attr->pattern);
            }
            if ($attr->format !== null) {
                $schema->format($attr->format);
            }
        }

        if ($schema instanceof NumberSchema || $schema instanceof IntegerSchema) {
            if ($attr->minimum !== null) {
                $schema->minimum((int) $attr->minimum);
            }
            if ($attr->maximum !== null) {
                $schema->maximum((int) $attr->maximum);
            }
        }

        if ($schema instanceof ArraySchema) {
            if ($attr->minItems !== null) {
                $schema->minItems($attr->minItems);
            }
            if ($attr->maxItems !== null) {
                $schema->maxItems($attr->maxItems);
            }
        }

        if ($attr->description !== null) {
            $schema->description($attr->description);
        }

        // Handle nullable wrapping
        if ($schema instanceof NullableSchema && $attr !== null) {
            $innerSchema = $schema->getWrappedSchema();
            $innerSchema = self::applyAttributeConstraints($innerSchema, $attr);

            return new NullableSchema($innerSchema);
        }

        return $schema;
    }

    /**
     * Create a StringSchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createStringSchema(array $jsonSchema): StringSchema
    {
        $schema = new StringSchema();

        if (isset($jsonSchema['minLength'])) {
            $schema->minLength($jsonSchema['minLength']);
        }
        if (isset($jsonSchema['maxLength'])) {
            $schema->maxLength($jsonSchema['maxLength']);
        }
        if (isset($jsonSchema['pattern'])) {
            $schema->pattern($jsonSchema['pattern']);
        }
        if (isset($jsonSchema['format'])) {
            $schema->format($jsonSchema['format']);
        }
        if (isset($jsonSchema['default'])) {
            $schema->default($jsonSchema['default']);
        }
        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }
        if (isset($jsonSchema['examples'])) {
            $schema->examples(...$jsonSchema['examples']);
        }

        return $schema;
    }

    /**
     * Create a NumberSchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createNumberSchema(array $jsonSchema): NumberSchema
    {
        $schema = new NumberSchema();

        if (isset($jsonSchema['minimum'])) {
            $schema->minimum($jsonSchema['minimum']);
        }
        if (isset($jsonSchema['maximum'])) {
            $schema->maximum($jsonSchema['maximum']);
        }
        if (isset($jsonSchema['exclusiveMinimum'])) {
            $schema->exclusiveMinimum($jsonSchema['exclusiveMinimum']);
        }
        if (isset($jsonSchema['exclusiveMaximum'])) {
            $schema->exclusiveMaximum($jsonSchema['exclusiveMaximum']);
        }
        if (isset($jsonSchema['multipleOf'])) {
            $schema->multipleOf($jsonSchema['multipleOf']);
        }
        if (isset($jsonSchema['default'])) {
            $schema->default($jsonSchema['default']);
        }
        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }

        return $schema;
    }

    /**
     * Create an IntegerSchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createIntegerSchema(array $jsonSchema): IntegerSchema
    {
        $schema = new IntegerSchema();

        if (isset($jsonSchema['minimum'])) {
            $schema->minimum((int) $jsonSchema['minimum']);
        }
        if (isset($jsonSchema['maximum'])) {
            $schema->maximum((int) $jsonSchema['maximum']);
        }
        if (isset($jsonSchema['exclusiveMinimum'])) {
            $schema->exclusiveMinimum((int) $jsonSchema['exclusiveMinimum']);
        }
        if (isset($jsonSchema['exclusiveMaximum'])) {
            $schema->exclusiveMaximum((int) $jsonSchema['exclusiveMaximum']);
        }
        if (isset($jsonSchema['multipleOf'])) {
            $schema->multipleOf((int) $jsonSchema['multipleOf']);
        }
        if (isset($jsonSchema['default'])) {
            $schema->default((int) $jsonSchema['default']);
        }
        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }

        return $schema;
    }

    /**
     * Create a BooleanSchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createBooleanSchema(array $jsonSchema): BooleanSchema
    {
        $schema = new BooleanSchema();

        if (isset($jsonSchema['default'])) {
            $schema->default($jsonSchema['default']);
        }
        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }

        return $schema;
    }

    /**
     * Create an ArraySchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createArraySchema(array $jsonSchema): ArraySchema
    {
        $itemsSchema = isset($jsonSchema['items'])
            ? self::fromJsonSchema($jsonSchema['items'])
            : new StringSchema();

        $schema = new ArraySchema($itemsSchema);

        if (isset($jsonSchema['minItems'])) {
            $schema->minItems($jsonSchema['minItems']);
        }
        if (isset($jsonSchema['maxItems'])) {
            $schema->maxItems($jsonSchema['maxItems']);
        }
        if (isset($jsonSchema['uniqueItems'])) {
            $schema->uniqueItems($jsonSchema['uniqueItems']);
        }
        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }

        return $schema;
    }

    /**
     * Create an ObjectSchema from JSON Schema.
     *
     * @param  array<string, mixed>  $jsonSchema
     */
    protected static function createObjectSchema(array $jsonSchema): ObjectSchema
    {
        $schema = new ObjectSchema();

        if (isset($jsonSchema['properties'])) {
            foreach ($jsonSchema['properties'] as $name => $propertySchema) {
                $schema->property($name, self::fromJsonSchema($propertySchema));
            }
        }

        if (isset($jsonSchema['required'])) {
            $schema->required(...$jsonSchema['required']);
        }

        if (isset($jsonSchema['additionalProperties'])) {
            if ($jsonSchema['additionalProperties'] === false) {
                $schema->additionalProperties(false);
            } elseif (is_array($jsonSchema['additionalProperties'])) {
                $schema->additionalProperties(self::fromJsonSchema($jsonSchema['additionalProperties']));
            }
        }

        if (isset($jsonSchema['description'])) {
            $schema->description($jsonSchema['description']);
        }

        return $schema;
    }
}
