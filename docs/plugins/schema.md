# Schema Plugin

The Schema plugin provides JSON Schema generation, validation, and value casting. It's a core plugin that other plugins depend on for defining input/output schemas.

## Overview

- **Plugin ID:** `schema`
- **Dependencies:** None
- **Provides:** `schema`

## Basic Usage

### Creating Schemas

Use the `Schema` facade to create schemas with a fluent API:

```php
use JayI\Cortex\Plugins\Schema\Schema;

// String schema
$string = Schema::string()
    ->minLength(1)
    ->maxLength(100)
    ->pattern('^[a-z]+$')
    ->format('email')
    ->description('User email address');

// Number schema
$number = Schema::number()
    ->minimum(0.0)
    ->maximum(100.0)
    ->description('Percentage value');

// Integer schema
$integer = Schema::integer()
    ->minimum(0)
    ->maximum(150);

// Boolean schema
$boolean = Schema::boolean();

// Enum schema
$enum = Schema::enum(['red', 'green', 'blue']);

// Array schema
$array = Schema::array(Schema::string())
    ->minItems(1)
    ->maxItems(10);

// Object schema
$object = Schema::object()
    ->property('name', Schema::string()->minLength(1))
    ->property('age', Schema::integer()->minimum(0))
    ->required('name');
```

### Nested Objects

```php
$userSchema = Schema::object()
    ->property('user', Schema::object()
        ->property('name', Schema::string())
        ->property('email', Schema::string()->format('email'))
        ->required('name', 'email')
    )
    ->property('preferences', Schema::object()
        ->property('theme', Schema::enum(['light', 'dark']))
        ->property('notifications', Schema::boolean())
    )
    ->required('user');
```

### Nullable Types

```php
// String or null
$nullable = Schema::nullable(Schema::string());

// Validates null, "hello", etc.
// Fails on 123, [], etc.
```

### Union Types (anyOf)

```php
// String or integer
$union = Schema::anyOf(
    Schema::string(),
    Schema::integer()
);
```

## Validation

### Basic Validation

```php
$schema = Schema::string()->minLength(3)->maxLength(10);

$result = $schema->validate('hello');

if ($result->isValid()) {
    echo "Valid!";
} else {
    foreach ($result->errors as $error) {
        echo "{$error->path}: {$error->message}\n";
    }
}
```

### Validation Result

The `ValidationResult` object contains:

```php
class ValidationResult
{
    public bool $valid;
    public array $errors; // ValidationError[]

    public function isValid(): bool;
    public function isInvalid(): bool;
}

class ValidationError
{
    public string $path;    // JSON path to error (e.g., "user.email")
    public string $message; // Human-readable error message
    public mixed $value;    // The invalid value
}
```

### Validation Examples

```php
// String validation
$schema = Schema::string()->format('email');
$schema->validate('not-an-email')->isValid(); // false

// Number validation
$schema = Schema::number()->minimum(0)->maximum(100);
$schema->validate(150)->isValid(); // false

// Object validation
$schema = Schema::object()
    ->property('name', Schema::string())
    ->required('name');

$schema->validate([])->isValid(); // false - missing required
$schema->validate(['name' => 'John'])->isValid(); // true
```

## Type Casting

Schemas can cast values to the expected type:

```php
// Cast to string
$schema = Schema::string()->default('unknown');
$schema->cast(123);   // "123"
$schema->cast(null);  // "unknown"

// Cast to integer
$schema = Schema::integer();
$schema->cast('42');  // 42
$schema->cast(42.9);  // 42

// Cast to number
$schema = Schema::number();
$schema->cast('3.14'); // 3.14

// Cast to boolean
$schema = Schema::boolean();
$schema->cast(1);     // true
$schema->cast(0);     // false
```

## JSON Schema Generation

Convert schemas to JSON Schema format for LLM APIs:

```php
$schema = Schema::object()
    ->property('name', Schema::string()->description('User name'))
    ->property('age', Schema::integer()->minimum(0))
    ->required('name');

$jsonSchema = $schema->toJsonSchema();

// Result:
// [
//     'type' => 'object',
//     'properties' => [
//         'name' => ['type' => 'string', 'description' => 'User name'],
//         'age' => ['type' => 'integer', 'minimum' => 0],
//     ],
//     'required' => ['name'],
// ]
```

## Schema Factory

### From JSON Schema

Create schemas from existing JSON Schema definitions:

```php
use JayI\Cortex\Plugins\Schema\SchemaFactory;

$jsonSchema = [
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer', 'minimum' => 0],
    ],
    'required' => ['name'],
];

$schema = SchemaFactory::fromJsonSchema($jsonSchema);
```

### From Data Classes

Generate schemas from Spatie Laravel Data classes:

```php
use Spatie\LaravelData\Data;
use JayI\Cortex\Plugins\Schema\Attributes\SchemaProperty;

class UserData extends Data
{
    public function __construct(
        #[SchemaProperty(minLength: 1, description: 'User name')]
        public string $name,

        #[SchemaProperty(minimum: 0, maximum: 150)]
        public int $age,

        #[SchemaProperty(format: 'email')]
        public ?string $email = null,
    ) {}
}

$schema = SchemaFactory::fromDataClass(UserData::class);
```

## Schema Attributes

Use PHP attributes to define schema constraints on Data classes:

### SchemaProperty

```php
use JayI\Cortex\Plugins\Schema\Attributes\SchemaProperty;

#[SchemaProperty(
    description: 'Field description',
    minLength: 1,      // For strings
    maxLength: 100,    // For strings
    pattern: '^[a-z]+$', // Regex for strings
    format: 'email',   // String format
    minimum: 0,        // For numbers
    maximum: 100,      // For numbers
    minItems: 1,       // For arrays
    maxItems: 10,      // For arrays
)]
public string $field;
```

### SchemaRequired

```php
use JayI\Cortex\Plugins\Schema\Attributes\SchemaRequired;

#[SchemaRequired(['name', 'email'])]
class UserData extends Data
{
    public string $name;
    public string $email;
    public ?int $age = null; // Optional
}
```

## Schema Types Reference

### StringSchema

```php
Schema::string()
    ->minLength(int $length)
    ->maxLength(int $length)
    ->pattern(string $regex)
    ->format(string $format)  // 'email', 'uri', 'date', 'date-time', etc.
    ->default(string $value)
    ->description(string $description)
    ->examples(string ...$examples)
```

### NumberSchema

```php
Schema::number()
    ->minimum(float $value)
    ->maximum(float $value)
    ->exclusiveMinimum(float $value)
    ->exclusiveMaximum(float $value)
    ->multipleOf(float $value)
    ->default(float $value)
    ->description(string $description)
```

### IntegerSchema

```php
Schema::integer()
    ->minimum(int $value)
    ->maximum(int $value)
    ->exclusiveMinimum(int $value)
    ->exclusiveMaximum(int $value)
    ->multipleOf(int $value)
    ->default(int $value)
    ->description(string $description)
```

### BooleanSchema

```php
Schema::boolean()
    ->default(bool $value)
    ->description(string $description)
```

### EnumSchema

```php
Schema::enum(array $values)
    ->default(mixed $value)
    ->description(string $description)
```

### ArraySchema

```php
Schema::array(Schema $items)
    ->minItems(int $count)
    ->maxItems(int $count)
    ->uniqueItems(bool $unique)
    ->description(string $description)
```

### ObjectSchema

```php
Schema::object()
    ->property(string $name, Schema $schema)
    ->required(string ...$properties)
    ->additionalProperties(bool|Schema $value)
    ->description(string $description)
```

### NullableSchema

```php
Schema::nullable(Schema $schema)
```

### UnionSchema (anyOf)

```php
Schema::anyOf(Schema ...$schemas)
```

## Error Handling

Schema validation doesn't throw exceptions. Check the `ValidationResult`:

```php
$result = $schema->validate($data);

if ($result->isInvalid()) {
    foreach ($result->errors as $error) {
        logger()->warning("Validation error", [
            'path' => $error->path,
            'message' => $error->message,
            'value' => $error->value,
        ]);
    }
}
```

For cases where you want to throw on invalid data:

```php
use JayI\Cortex\Exceptions\SchemaValidationException;

$result = $schema->validate($data);

if ($result->isInvalid()) {
    throw SchemaValidationException::fromValidationResult($result);
}
```
