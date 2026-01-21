<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Attributes\SchemaProperty;
use JayI\Cortex\Plugins\Schema\Attributes\SchemaRequired;

describe('SchemaProperty', function () {
    test('creates with default values', function () {
        $attr = new SchemaProperty;

        expect($attr->minLength)->toBeNull();
        expect($attr->maxLength)->toBeNull();
        expect($attr->pattern)->toBeNull();
        expect($attr->format)->toBeNull();
        expect($attr->minimum)->toBeNull();
        expect($attr->maximum)->toBeNull();
        expect($attr->minItems)->toBeNull();
        expect($attr->maxItems)->toBeNull();
        expect($attr->description)->toBeNull();
    });

    test('creates with string constraints', function () {
        $attr = new SchemaProperty(
            minLength: 5,
            maxLength: 100,
            pattern: '^[a-z]+$',
            format: 'email',
            description: 'A string property',
        );

        expect($attr->minLength)->toBe(5);
        expect($attr->maxLength)->toBe(100);
        expect($attr->pattern)->toBe('^[a-z]+$');
        expect($attr->format)->toBe('email');
        expect($attr->description)->toBe('A string property');
    });

    test('creates with number constraints', function () {
        $attr = new SchemaProperty(
            minimum: 0.0,
            maximum: 100.0,
        );

        expect($attr->minimum)->toBe(0.0);
        expect($attr->maximum)->toBe(100.0);
    });

    test('creates with array constraints', function () {
        $attr = new SchemaProperty(
            minItems: 1,
            maxItems: 10,
        );

        expect($attr->minItems)->toBe(1);
        expect($attr->maxItems)->toBe(10);
    });
});

describe('SchemaRequired', function () {
    test('creates with empty properties', function () {
        $attr = new SchemaRequired;

        expect($attr->properties)->toBe([]);
    });

    test('creates with specified properties', function () {
        $attr = new SchemaRequired(
            properties: ['name', 'email', 'age'],
        );

        expect($attr->properties)->toBe(['name', 'email', 'age']);
    });
});
