<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\Types\ArraySchema;
use JayI\Cortex\Plugins\Schema\Types\BooleanSchema;
use JayI\Cortex\Plugins\Schema\Types\EnumSchema;
use JayI\Cortex\Plugins\Schema\Types\IntegerSchema;
use JayI\Cortex\Plugins\Schema\Types\NullableSchema;
use JayI\Cortex\Plugins\Schema\Types\NumberSchema;
use JayI\Cortex\Plugins\Schema\Types\ObjectSchema;
use JayI\Cortex\Plugins\Schema\Types\StringSchema;
use JayI\Cortex\Plugins\Schema\Types\UnionSchema;
use JayI\Cortex\Plugins\Schema\ValidationError;
use JayI\Cortex\Plugins\Schema\ValidationResult;

describe('Schema static methods', function () {
    test('creates string schema', function () {
        $schema = Schema::string();

        expect($schema)->toBeInstanceOf(StringSchema::class);
    });

    test('creates number schema', function () {
        $schema = Schema::number();

        expect($schema)->toBeInstanceOf(NumberSchema::class);
    });

    test('creates integer schema', function () {
        $schema = Schema::integer();

        expect($schema)->toBeInstanceOf(IntegerSchema::class);
    });

    test('creates boolean schema', function () {
        $schema = Schema::boolean();

        expect($schema)->toBeInstanceOf(BooleanSchema::class);
    });

    test('creates array schema', function () {
        $schema = Schema::array(Schema::string());

        expect($schema)->toBeInstanceOf(ArraySchema::class);
    });

    test('creates object schema', function () {
        $schema = Schema::object();

        expect($schema)->toBeInstanceOf(ObjectSchema::class);
    });

    test('creates enum schema', function () {
        $schema = Schema::enum(['red', 'green', 'blue']);

        expect($schema)->toBeInstanceOf(EnumSchema::class);
    });

    test('creates anyOf union schema', function () {
        $schema = Schema::anyOf(Schema::string(), Schema::integer());

        expect($schema)->toBeInstanceOf(UnionSchema::class);
        expect($schema->getType())->toBe('anyOf');
    });

    test('creates oneOf union schema', function () {
        $schema = Schema::oneOf(Schema::string(), Schema::integer());

        expect($schema)->toBeInstanceOf(UnionSchema::class);
        expect($schema->getType())->toBe('oneOf');
    });

    test('creates nullable schema', function () {
        $schema = Schema::nullable(Schema::string());

        expect($schema)->toBeInstanceOf(NullableSchema::class);
    });

    test('sets and gets description', function () {
        $schema = Schema::string()->description('A test description');

        expect($schema->getDescription())->toBe('A test description');
    });
});

describe('StringSchema', function () {
    test('validates string values', function () {
        $schema = Schema::string();

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate(123)->isValid())->toBeFalse();
    });

    test('validates minLength', function () {
        $schema = Schema::string()->minLength(5);

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate('hi')->isValid())->toBeFalse();
    });

    test('validates maxLength', function () {
        $schema = Schema::string()->maxLength(5);

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate('hello world')->isValid())->toBeFalse();
    });

    test('validates pattern', function () {
        $schema = Schema::string()->pattern('^[A-Z]+$');

        expect($schema->validate('HELLO')->isValid())->toBeTrue();
        expect($schema->validate('hello')->isValid())->toBeFalse();
    });

    test('validates email format', function () {
        $schema = Schema::string()->format('email');

        expect($schema->validate('test@example.com')->isValid())->toBeTrue();
        expect($schema->validate('invalid-email')->isValid())->toBeFalse();
    });

    test('validates uri format', function () {
        $schema = Schema::string()->format('uri');

        expect($schema->validate('https://example.com')->isValid())->toBeTrue();
        expect($schema->validate('not-a-url')->isValid())->toBeFalse();
    });

    test('validates uuid format', function () {
        $schema = Schema::string()->format('uuid');

        expect($schema->validate('550e8400-e29b-41d4-a716-446655440000')->isValid())->toBeTrue();
        expect($schema->validate('not-a-uuid')->isValid())->toBeFalse();
    });

    test('validates ipv4 format', function () {
        $schema = Schema::string()->format('ipv4');

        expect($schema->validate('192.168.1.1')->isValid())->toBeTrue();
        expect($schema->validate('not-an-ip')->isValid())->toBeFalse();
    });

    test('validates ipv6 format', function () {
        $schema = Schema::string()->format('ipv6');

        expect($schema->validate('::1')->isValid())->toBeTrue();
        expect($schema->validate('192.168.1.1')->isValid())->toBeFalse();
    });

    test('validates date format', function () {
        $schema = Schema::string()->format('date');

        expect($schema->validate('2024-01-15')->isValid())->toBeTrue();
        expect($schema->validate('15-01-2024')->isValid())->toBeFalse();
    });

    test('validates time format', function () {
        $schema = Schema::string()->format('time');

        expect($schema->validate('12:30:45')->isValid())->toBeTrue();
        expect($schema->validate('25:00:00')->isValid())->toBeFalse();
    });

    test('casts values to string', function () {
        $schema = Schema::string();

        expect($schema->cast(123))->toBe('123');
        expect($schema->cast(true))->toBe('1');
    });

    test('uses default value when casting null', function () {
        $schema = Schema::string()->default('default');

        expect($schema->cast(null))->toBe('default');
    });

    test('generates JSON schema', function () {
        $schema = Schema::string()
            ->minLength(1)
            ->maxLength(100)
            ->pattern('^[A-Z]+$')
            ->format('email')
            ->default('test@example.com')
            ->examples('example1@test.com', 'example2@test.com')
            ->description('An email address');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('string');
        expect($jsonSchema['minLength'])->toBe(1);
        expect($jsonSchema['maxLength'])->toBe(100);
        expect($jsonSchema['pattern'])->toBe('^[A-Z]+$');
        expect($jsonSchema['format'])->toBe('email');
        expect($jsonSchema['default'])->toBe('test@example.com');
        expect($jsonSchema['examples'])->toBe(['example1@test.com', 'example2@test.com']);
        expect($jsonSchema['description'])->toBe('An email address');
    });
});

describe('IntegerSchema', function () {
    test('validates integer values', function () {
        $schema = Schema::integer();

        expect($schema->validate(42)->isValid())->toBeTrue();
        expect($schema->validate('42')->isValid())->toBeTrue();
        expect($schema->validate(42.5)->isValid())->toBeFalse();
        expect($schema->validate('hello')->isValid())->toBeFalse();
    });

    test('validates minimum', function () {
        $schema = Schema::integer()->minimum(10);

        expect($schema->validate(15)->isValid())->toBeTrue();
        expect($schema->validate(5)->isValid())->toBeFalse();
    });

    test('validates maximum', function () {
        $schema = Schema::integer()->maximum(100);

        expect($schema->validate(50)->isValid())->toBeTrue();
        expect($schema->validate(150)->isValid())->toBeFalse();
    });

    test('validates exclusiveMinimum', function () {
        $schema = Schema::integer()->exclusiveMinimum(10);

        expect($schema->validate(11)->isValid())->toBeTrue();
        expect($schema->validate(10)->isValid())->toBeFalse();
    });

    test('validates exclusiveMaximum', function () {
        $schema = Schema::integer()->exclusiveMaximum(100);

        expect($schema->validate(99)->isValid())->toBeTrue();
        expect($schema->validate(100)->isValid())->toBeFalse();
    });

    test('validates multipleOf', function () {
        $schema = Schema::integer()->multipleOf(5);

        expect($schema->validate(15)->isValid())->toBeTrue();
        expect($schema->validate(17)->isValid())->toBeFalse();
    });

    test('casts values to integer', function () {
        $schema = Schema::integer();

        expect($schema->cast('42'))->toBe(42);
        expect($schema->cast(42.9))->toBe(42);
    });

    test('uses default value when casting null', function () {
        $schema = Schema::integer()->default(10);

        expect($schema->cast(null))->toBe(10);
    });

    test('generates JSON schema', function () {
        $schema = Schema::integer()
            ->minimum(1)
            ->maximum(100)
            ->exclusiveMinimum(0)
            ->exclusiveMaximum(101)
            ->multipleOf(5)
            ->default(50)
            ->description('A number between 1 and 100');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('integer');
        expect($jsonSchema['minimum'])->toBe(1);
        expect($jsonSchema['maximum'])->toBe(100);
        expect($jsonSchema['exclusiveMinimum'])->toBe(0);
        expect($jsonSchema['exclusiveMaximum'])->toBe(101);
        expect($jsonSchema['multipleOf'])->toBe(5);
        expect($jsonSchema['default'])->toBe(50);
        expect($jsonSchema['description'])->toBe('A number between 1 and 100');
    });
});

describe('NumberSchema', function () {
    test('validates number values', function () {
        $schema = Schema::number();

        expect($schema->validate(42)->isValid())->toBeTrue();
        expect($schema->validate(42.5)->isValid())->toBeTrue();
        expect($schema->validate('42.5')->isValid())->toBeTrue();
        expect($schema->validate('hello')->isValid())->toBeFalse();
    });

    test('validates minimum', function () {
        $schema = Schema::number()->minimum(10.5);

        expect($schema->validate(15.0)->isValid())->toBeTrue();
        expect($schema->validate(5.0)->isValid())->toBeFalse();
    });

    test('validates maximum', function () {
        $schema = Schema::number()->maximum(100.5);

        expect($schema->validate(50.0)->isValid())->toBeTrue();
        expect($schema->validate(150.0)->isValid())->toBeFalse();
    });

    test('validates multipleOf', function () {
        $schema = Schema::number()->multipleOf(0.5);

        expect($schema->validate(2.5)->isValid())->toBeTrue();
        expect($schema->validate(2.3)->isValid())->toBeFalse();
    });

    test('casts values to float', function () {
        $schema = Schema::number();

        expect($schema->cast('42.5'))->toBe(42.5);
        expect($schema->cast(42))->toBe(42.0);
    });

    test('uses default value when casting null', function () {
        $schema = Schema::number()->default(3.14);

        expect($schema->cast(null))->toBe(3.14);
    });
});

describe('BooleanSchema', function () {
    test('validates boolean values', function () {
        $schema = Schema::boolean();

        expect($schema->validate(true)->isValid())->toBeTrue();
        expect($schema->validate(false)->isValid())->toBeTrue();
        expect($schema->validate('true')->isValid())->toBeFalse();
        expect($schema->validate(1)->isValid())->toBeFalse();
    });

    test('casts values to boolean', function () {
        $schema = Schema::boolean();

        expect($schema->cast('true'))->toBe(true);
        expect($schema->cast(1))->toBe(true);
        expect($schema->cast(0))->toBe(false);
        expect($schema->cast(''))->toBe(false);
    });

    test('uses default value when casting null', function () {
        $schema = Schema::boolean()->default(true);

        expect($schema->cast(null))->toBe(true);
    });

    test('generates JSON schema', function () {
        $schema = Schema::boolean()
            ->default(false)
            ->description('A boolean flag');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('boolean');
        expect($jsonSchema['default'])->toBe(false);
        expect($jsonSchema['description'])->toBe('A boolean flag');
    });
});

describe('ArraySchema', function () {
    test('validates array values', function () {
        $schema = Schema::array(Schema::string());

        expect($schema->validate(['a', 'b', 'c'])->isValid())->toBeTrue();
        expect($schema->validate('not an array')->isValid())->toBeFalse();
    });

    test('validates item types', function () {
        $schema = Schema::array(Schema::integer());

        expect($schema->validate([1, 2, 3])->isValid())->toBeTrue();
        expect($schema->validate([1, 'two', 3])->isValid())->toBeFalse();
    });

    test('validates minItems', function () {
        $schema = Schema::array(Schema::string())->minItems(2);

        expect($schema->validate(['a', 'b'])->isValid())->toBeTrue();
        expect($schema->validate(['a'])->isValid())->toBeFalse();
    });

    test('validates maxItems', function () {
        $schema = Schema::array(Schema::string())->maxItems(3);

        expect($schema->validate(['a', 'b'])->isValid())->toBeTrue();
        expect($schema->validate(['a', 'b', 'c', 'd'])->isValid())->toBeFalse();
    });

    test('validates uniqueItems', function () {
        $schema = Schema::array(Schema::string())->uniqueItems();

        expect($schema->validate(['a', 'b', 'c'])->isValid())->toBeTrue();
        expect($schema->validate(['a', 'b', 'a'])->isValid())->toBeFalse();
    });

    test('casts array items', function () {
        $schema = Schema::array(Schema::integer());

        expect($schema->cast(['1', '2', '3']))->toBe([1, 2, 3]);
    });

    test('casts non-array to empty array', function () {
        $schema = Schema::array(Schema::string());

        expect($schema->cast('not an array'))->toBe([]);
    });

    test('can change items schema', function () {
        $schema = Schema::array(Schema::string())->items(Schema::integer());

        expect($schema->validate([1, 2, 3])->isValid())->toBeTrue();
    });

    test('generates JSON schema', function () {
        $schema = Schema::array(Schema::string())
            ->minItems(1)
            ->maxItems(10)
            ->uniqueItems()
            ->description('A list of strings');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('array');
        expect($jsonSchema['items']['type'])->toBe('string');
        expect($jsonSchema['minItems'])->toBe(1);
        expect($jsonSchema['maxItems'])->toBe(10);
        expect($jsonSchema['uniqueItems'])->toBe(true);
        expect($jsonSchema['description'])->toBe('A list of strings');
    });
});

describe('ObjectSchema', function () {
    test('validates object values', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->property('age', Schema::integer());

        expect($schema->validate(['name' => 'John', 'age' => 30])->isValid())->toBeTrue();
        expect($schema->validate('not an object')->isValid())->toBeFalse();
    });

    test('validates required properties', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->required('name');

        expect($schema->validate(['name' => 'John'])->isValid())->toBeTrue();
        expect($schema->validate([])->isValid())->toBeFalse();
    });

    test('validates property types', function () {
        $schema = Schema::object()
            ->property('age', Schema::integer());

        expect($schema->validate(['age' => 30])->isValid())->toBeTrue();
        expect($schema->validate(['age' => 'thirty'])->isValid())->toBeFalse();
    });

    test('validates additionalProperties false', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->additionalProperties(false);

        expect($schema->validate(['name' => 'John'])->isValid())->toBeTrue();
        expect($schema->validate(['name' => 'John', 'extra' => 'value'])->isValid())->toBeFalse();
    });

    test('validates additionalProperties schema', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->additionalProperties(Schema::integer());

        expect($schema->validate(['name' => 'John', 'count' => 5])->isValid())->toBeTrue();
        expect($schema->validate(['name' => 'John', 'extra' => 'string'])->isValid())->toBeFalse();
    });

    test('adds multiple properties at once', function () {
        $schema = Schema::object()
            ->properties([
                'name' => Schema::string(),
                'age' => Schema::integer(),
            ]);

        expect($schema->validate(['name' => 'John', 'age' => 30])->isValid())->toBeTrue();
    });

    test('creates nested objects with callback', function () {
        $schema = Schema::object()
            ->nested('address', function (ObjectSchema $obj) {
                $obj->property('city', Schema::string())
                    ->property('zip', Schema::string());
            });

        $valid = ['address' => ['city' => 'NYC', 'zip' => '10001']];
        expect($schema->validate($valid)->isValid())->toBeTrue();
    });

    test('casts object properties', function () {
        $schema = Schema::object()
            ->property('age', Schema::integer())
            ->property('active', Schema::boolean());

        $result = $schema->cast(['age' => '30', 'active' => '1']);

        expect($result['age'])->toBe(30);
        expect($result['active'])->toBe(true);
    });

    test('casts additional properties', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->additionalProperties(Schema::integer());

        $result = $schema->cast(['name' => 'John', 'count' => '5']);

        expect($result['name'])->toBe('John');
        expect($result['count'])->toBe(5);
    });

    test('casts with additionalProperties true', function () {
        $schema = Schema::object()
            ->property('name', Schema::string());

        $result = $schema->cast(['name' => 'John', 'extra' => 'value']);

        expect($result['extra'])->toBe('value');
    });

    test('casts non-object to empty array', function () {
        $schema = Schema::object();

        expect($schema->cast('not an object'))->toBe([]);
    });

    test('gets properties', function () {
        $schema = Schema::object()
            ->property('name', Schema::string());

        expect($schema->getProperties())->toHaveKey('name');
    });

    test('gets required properties', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->required('name');

        expect($schema->getRequired())->toBe(['name']);
    });

    test('generates JSON schema', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->property('age', Schema::integer())
            ->required('name')
            ->additionalProperties(false)
            ->description('A person object');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('object');
        expect($jsonSchema['properties']['name']['type'])->toBe('string');
        expect($jsonSchema['properties']['age']['type'])->toBe('integer');
        expect($jsonSchema['required'])->toBe(['name']);
        expect($jsonSchema['additionalProperties'])->toBe(false);
        expect($jsonSchema['description'])->toBe('A person object');
    });
});

describe('EnumSchema', function () {
    test('validates enum values', function () {
        $schema = Schema::enum(['red', 'green', 'blue']);

        expect($schema->validate('red')->isValid())->toBeTrue();
        expect($schema->validate('yellow')->isValid())->toBeFalse();
    });

    test('validates numeric enum values', function () {
        $schema = Schema::enum([1, 2, 3]);

        expect($schema->validate(2)->isValid())->toBeTrue();
        expect($schema->validate(4)->isValid())->toBeFalse();
    });

    test('casts to first value when invalid', function () {
        $schema = Schema::enum(['red', 'green', 'blue']);

        // When value is valid, it's returned as is
        expect($schema->cast('red'))->toBe('red');
        // When value is invalid, it returns first value
        expect($schema->cast('yellow'))->toBe('red');
    });

    test('uses default value when casting null', function () {
        $schema = Schema::enum(['red', 'green', 'blue'])->default('red');

        expect($schema->cast(null))->toBe('red');
    });

    test('generates JSON schema', function () {
        $schema = Schema::enum(['red', 'green', 'blue'])
            ->default('red')
            ->description('A color');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['enum'])->toBe(['red', 'green', 'blue']);
        expect($jsonSchema['default'])->toBe('red');
        expect($jsonSchema['description'])->toBe('A color');
    });
});

describe('NullableSchema', function () {
    test('allows null values', function () {
        $schema = Schema::nullable(Schema::string());

        expect($schema->validate(null)->isValid())->toBeTrue();
        expect($schema->validate('hello')->isValid())->toBeTrue();
    });

    test('validates non-null values against wrapped schema', function () {
        $schema = Schema::nullable(Schema::string()->minLength(3));

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate('hi')->isValid())->toBeFalse();
    });

    test('casts null to null', function () {
        $schema = Schema::nullable(Schema::string());

        expect($schema->cast(null))->toBeNull();
    });

    test('casts non-null values using wrapped schema', function () {
        $schema = Schema::nullable(Schema::integer());

        expect($schema->cast('42'))->toBe(42);
    });

    test('gets wrapped schema', function () {
        $wrapped = Schema::string();
        $schema = Schema::nullable($wrapped);

        expect($schema->getWrappedSchema())->toBe($wrapped);
    });

    test('generates JSON schema with type array', function () {
        $schema = Schema::nullable(Schema::string());

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe(['string', 'null']);
    });

    test('generates anyOf for enum types', function () {
        $schema = Schema::nullable(Schema::enum(['a', 'b']));

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema)->toHaveKey('anyOf');
    });
});

describe('UnionSchema', function () {
    test('validates anyOf schema', function () {
        $schema = Schema::anyOf(Schema::string(), Schema::integer());

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate(42)->isValid())->toBeTrue();
        expect($schema->validate(true)->isValid())->toBeFalse();
    });

    test('validates oneOf schema - exactly one match', function () {
        $schema = Schema::oneOf(
            Schema::string()->minLength(5),
            Schema::integer()
        );

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate(42)->isValid())->toBeTrue();
    });

    test('oneOf fails when multiple schemas match', function () {
        // Both schemas would match a numeric string
        $schema = Schema::oneOf(
            Schema::string(),
            Schema::string()
        );

        // String matches both schemas
        expect($schema->validate('hello')->isValid())->toBeFalse();
    });

    test('casts using first matching schema', function () {
        $schema = Schema::anyOf(
            Schema::integer(),
            Schema::string()
        );

        expect($schema->cast(42))->toBe(42);
        expect($schema->cast('hello'))->toBe('hello');
    });

    test('casts using first schema when none match', function () {
        $schema = Schema::anyOf(
            Schema::integer()->minimum(10),
            Schema::string()->minLength(5)
        );

        // Neither matches, uses first schema's cast
        expect($schema->cast(true))->toBe(1);
    });

    test('gets schemas', function () {
        $string = Schema::string();
        $integer = Schema::integer();
        $schema = Schema::anyOf($string, $integer);

        expect($schema->getSchemas())->toBe([$string, $integer]);
    });

    test('generates JSON schema', function () {
        $schema = Schema::anyOf(Schema::string(), Schema::integer())
            ->description('A string or integer');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema)->toHaveKey('anyOf');
        expect($jsonSchema['anyOf'])->toHaveCount(2);
        expect($jsonSchema['description'])->toBe('A string or integer');
    });
});

describe('ValidationResult', function () {
    test('creates valid result', function () {
        $result = ValidationResult::valid();

        expect($result->isValid())->toBeTrue();
        expect($result->hasErrors())->toBeFalse();
    });

    test('creates invalid result with errors', function () {
        $errors = [new ValidationError('$.name', 'Required field')];
        $result = ValidationResult::invalid($errors);

        expect($result->isValid())->toBeFalse();
        expect($result->hasErrors())->toBeTrue();
    });

    test('creates error with single error', function () {
        $result = ValidationResult::error('$.name', 'Field is required', null);

        expect($result->isValid())->toBeFalse();
        expect($result->errors)->toHaveCount(1);
    });

    test('merges two valid results', function () {
        $result = ValidationResult::valid()->merge(ValidationResult::valid());

        expect($result->isValid())->toBeTrue();
    });

    test('merges valid with invalid result', function () {
        $valid = ValidationResult::valid();
        $invalid = ValidationResult::error('$.name', 'Error');

        $result = $valid->merge($invalid);

        expect($result->isValid())->toBeFalse();
        expect($result->errors)->toHaveCount(1);
    });

    test('merges two invalid results', function () {
        $error1 = ValidationResult::error('$.name', 'Name error');
        $error2 = ValidationResult::error('$.age', 'Age error');

        $result = $error1->merge($error2);

        expect($result->isValid())->toBeFalse();
        expect($result->errors)->toHaveCount(2);
    });

    test('gets error messages', function () {
        $result = ValidationResult::invalid([
            new ValidationError('$.name', 'Name is required'),
            new ValidationError('$.age', 'Age must be positive'),
        ]);

        $messages = $result->messages();

        expect($messages['$.name'])->toBe('Name is required');
        expect($messages['$.age'])->toBe('Age must be positive');
    });

    test('throw does nothing when valid', function () {
        $result = ValidationResult::valid();

        $result->throw(); // Should not throw
        expect(true)->toBeTrue(); // If we get here, no exception was thrown
    });

    test('throw throws exception when invalid', function () {
        $result = ValidationResult::error('$.name', 'Required');

        expect(fn () => $result->throw())
            ->toThrow(\JayI\Cortex\Exceptions\SchemaValidationException::class);
    });
});

describe('ValidationError', function () {
    test('creates validation error', function () {
        $error = new ValidationError('$.name', 'Field is required', null);

        expect($error->path)->toBe('$.name');
        expect($error->message)->toBe('Field is required');
        expect($error->value)->toBeNull();
    });

    test('creates error with make factory', function () {
        $error = ValidationError::make('$.age', 'Must be positive', -5);

        expect($error->path)->toBe('$.age');
        expect($error->message)->toBe('Must be positive');
        expect($error->value)->toBe(-5);
    });

    test('converts to string', function () {
        $error = new ValidationError('$.name', 'Field is required');

        expect($error->toString())->toBe('[$.name]: Field is required');
    });
});

describe('SchemaFactory', function () {
    test('creates string schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 100,
            'pattern' => '^[A-Z]+$',
            'format' => 'email',
            'default' => 'test@example.com',
            'description' => 'An email',
            'examples' => ['a@b.com', 'c@d.com'],
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(StringSchema::class);
    });

    test('creates number schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'number',
            'minimum' => 0,
            'maximum' => 100,
            'exclusiveMinimum' => -1,
            'exclusiveMaximum' => 101,
            'multipleOf' => 0.5,
            'default' => 50.0,
            'description' => 'A percentage',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(NumberSchema::class);
    });

    test('creates integer schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'integer',
            'minimum' => 0,
            'maximum' => 100,
            'default' => 50,
            'description' => 'An age',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(IntegerSchema::class);
    });

    test('creates boolean schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Is active',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(BooleanSchema::class);
    });

    test('creates array schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'minItems' => 1,
            'maxItems' => 10,
            'uniqueItems' => true,
            'description' => 'A list',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(ArraySchema::class);
    });

    test('creates object schema from JSON schema', function () {
        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
            'additionalProperties' => false,
            'description' => 'A person',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(ObjectSchema::class);
    });

    test('creates object schema with additionalProperties schema', function () {
        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'additionalProperties' => ['type' => 'integer'],
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(ObjectSchema::class);
    });

    test('creates enum schema from JSON schema', function () {
        $jsonSchema = [
            'enum' => ['red', 'green', 'blue'],
            'default' => 'red',
            'description' => 'A color',
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(EnumSchema::class);
    });

    test('creates anyOf union schema from JSON schema', function () {
        $jsonSchema = [
            'anyOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(UnionSchema::class);
        expect($schema->getType())->toBe('anyOf');
    });

    test('creates oneOf union schema from JSON schema', function () {
        $jsonSchema = [
            'oneOf' => [
                ['type' => 'string'],
                ['type' => 'integer'],
            ],
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(UnionSchema::class);
        expect($schema->getType())->toBe('oneOf');
    });

    test('creates nullable schema from JSON schema', function () {
        $jsonSchema = [
            'type' => ['string', 'null'],
        ];

        $schema = Schema::fromJsonSchema($jsonSchema);

        expect($schema)->toBeInstanceOf(NullableSchema::class);
    });

    test('throws for unknown type', function () {
        $jsonSchema = ['type' => 'unknown'];

        expect(fn () => Schema::fromJsonSchema($jsonSchema))
            ->toThrow(InvalidArgumentException::class);
    });
});
