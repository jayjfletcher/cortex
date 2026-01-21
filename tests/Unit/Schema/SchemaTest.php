<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\Types\ArraySchema;
use JayI\Cortex\Plugins\Schema\Types\BooleanSchema;
use JayI\Cortex\Plugins\Schema\Types\EnumSchema;
use JayI\Cortex\Plugins\Schema\Types\IntegerSchema;
use JayI\Cortex\Plugins\Schema\Types\NumberSchema;
use JayI\Cortex\Plugins\Schema\Types\ObjectSchema;
use JayI\Cortex\Plugins\Schema\Types\StringSchema;

describe('Schema', function () {
    it('creates a string schema', function () {
        $schema = Schema::string();

        expect($schema)->toBeInstanceOf(StringSchema::class);
        expect($schema->toJsonSchema())->toBe(['type' => 'string']);
    });

    it('creates a string schema with constraints', function () {
        $schema = Schema::string()
            ->minLength(5)
            ->maxLength(100)
            ->pattern('^[a-z]+$')
            ->format('email')
            ->description('A test string');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('string');
        expect($jsonSchema['minLength'])->toBe(5);
        expect($jsonSchema['maxLength'])->toBe(100);
        expect($jsonSchema['pattern'])->toBe('^[a-z]+$');
        expect($jsonSchema['format'])->toBe('email');
        expect($jsonSchema['description'])->toBe('A test string');
    });

    it('creates a number schema', function () {
        $schema = Schema::number()
            ->minimum(0.0)
            ->maximum(100.0)
            ->description('A percentage');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('number');
        expect($jsonSchema['minimum'])->toBe(0.0);
        expect($jsonSchema['maximum'])->toBe(100.0);
    });

    it('creates an integer schema', function () {
        $schema = Schema::integer()
            ->minimum(0)
            ->maximum(150);

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('integer');
        expect($jsonSchema['minimum'])->toBe(0);
        expect($jsonSchema['maximum'])->toBe(150);
    });

    it('creates a boolean schema', function () {
        $schema = Schema::boolean();

        expect($schema)->toBeInstanceOf(BooleanSchema::class);
        expect($schema->toJsonSchema())->toBe(['type' => 'boolean']);
    });

    it('creates an enum schema', function () {
        $schema = Schema::enum(['red', 'green', 'blue']);

        expect($schema)->toBeInstanceOf(EnumSchema::class);
        expect($schema->toJsonSchema())->toBe(['enum' => ['red', 'green', 'blue']]);
    });

    it('creates an array schema', function () {
        $schema = Schema::array(Schema::string())
            ->minItems(1)
            ->maxItems(10);

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('array');
        expect($jsonSchema['items'])->toBe(['type' => 'string']);
        expect($jsonSchema['minItems'])->toBe(1);
        expect($jsonSchema['maxItems'])->toBe(10);
    });

    it('creates an object schema', function () {
        $schema = Schema::object()
            ->property('name', Schema::string()->minLength(1))
            ->property('age', Schema::integer()->minimum(0))
            ->required('name');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toBe('object');
        expect($jsonSchema['properties']['name']['type'])->toBe('string');
        expect($jsonSchema['properties']['age']['type'])->toBe('integer');
        expect($jsonSchema['required'])->toBe(['name']);
    });

    it('creates a nested object schema', function () {
        $schema = Schema::object()
            ->property('user', Schema::object()
                ->property('name', Schema::string())
                ->property('email', Schema::string()->format('email'))
                ->required('name', 'email')
            )
            ->required('user');

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['properties']['user']['type'])->toBe('object');
        expect($jsonSchema['properties']['user']['properties']['name']['type'])->toBe('string');
        expect($jsonSchema['properties']['user']['properties']['email']['format'])->toBe('email');
    });

    it('creates a nullable schema', function () {
        $schema = Schema::nullable(Schema::string());

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema['type'])->toContain('string');
        expect($jsonSchema['type'])->toContain('null');
    });

    it('creates an anyOf schema', function () {
        $schema = Schema::anyOf(
            Schema::string(),
            Schema::integer()
        );

        $jsonSchema = $schema->toJsonSchema();

        expect($jsonSchema)->toHaveKey('anyOf');
        expect($jsonSchema['anyOf'])->toHaveCount(2);
    });
});

describe('Schema Validation', function () {
    it('validates strings', function () {
        $schema = Schema::string()->minLength(3)->maxLength(10);

        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate('hi')->isValid())->toBeFalse();
        expect($schema->validate('this is too long')->isValid())->toBeFalse();
        expect($schema->validate(123)->isValid())->toBeFalse();
    });

    it('validates email format', function () {
        $schema = Schema::string()->format('email');

        expect($schema->validate('test@example.com')->isValid())->toBeTrue();
        expect($schema->validate('not-an-email')->isValid())->toBeFalse();
    });

    it('validates numbers', function () {
        $schema = Schema::number()->minimum(0.0)->maximum(100.0);

        expect($schema->validate(50.0)->isValid())->toBeTrue();
        expect($schema->validate(-1.0)->isValid())->toBeFalse();
        expect($schema->validate(101.0)->isValid())->toBeFalse();
    });

    it('validates integers', function () {
        $schema = Schema::integer()->minimum(0);

        expect($schema->validate(10)->isValid())->toBeTrue();
        expect($schema->validate(-5)->isValid())->toBeFalse();
        expect($schema->validate(10.5)->isValid())->toBeFalse();
    });

    it('validates booleans', function () {
        $schema = Schema::boolean();

        expect($schema->validate(true)->isValid())->toBeTrue();
        expect($schema->validate(false)->isValid())->toBeTrue();
        expect($schema->validate('true')->isValid())->toBeFalse();
    });

    it('validates enums', function () {
        $schema = Schema::enum(['red', 'green', 'blue']);

        expect($schema->validate('red')->isValid())->toBeTrue();
        expect($schema->validate('yellow')->isValid())->toBeFalse();
    });

    it('validates arrays', function () {
        $schema = Schema::array(Schema::integer())->minItems(1)->maxItems(5);

        expect($schema->validate([1, 2, 3])->isValid())->toBeTrue();
        expect($schema->validate([])->isValid())->toBeFalse();
        expect($schema->validate([1, 2, 3, 4, 5, 6])->isValid())->toBeFalse();
        expect($schema->validate(['a', 'b'])->isValid())->toBeFalse();
    });

    it('validates objects', function () {
        $schema = Schema::object()
            ->property('name', Schema::string())
            ->property('age', Schema::integer())
            ->required('name');

        expect($schema->validate(['name' => 'John', 'age' => 30])->isValid())->toBeTrue();
        expect($schema->validate(['name' => 'John'])->isValid())->toBeTrue();
        expect($schema->validate(['age' => 30])->isValid())->toBeFalse();
    });

    it('validates nullable values', function () {
        $schema = Schema::nullable(Schema::string());

        expect($schema->validate(null)->isValid())->toBeTrue();
        expect($schema->validate('hello')->isValid())->toBeTrue();
        expect($schema->validate(123)->isValid())->toBeFalse();
    });
});

describe('Schema Casting', function () {
    it('casts values to strings', function () {
        $schema = Schema::string()->default('default');

        expect($schema->cast(123))->toBe('123');
        expect($schema->cast(null))->toBe('default');
    });

    it('casts values to integers', function () {
        $schema = Schema::integer();

        expect($schema->cast('42'))->toBe(42);
        expect($schema->cast(42.9))->toBe(42);
    });

    it('casts values to numbers', function () {
        $schema = Schema::number();

        expect($schema->cast('3.14'))->toBe(3.14);
    });

    it('casts values to booleans', function () {
        $schema = Schema::boolean();

        expect($schema->cast(1))->toBeTrue();
        expect($schema->cast(0))->toBeFalse();
    });
});
