<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class IntegerSchema extends Schema
{
    protected ?int $minimum = null;

    protected ?int $maximum = null;

    protected ?int $exclusiveMinimum = null;

    protected ?int $exclusiveMaximum = null;

    protected ?int $multipleOf = null;

    protected ?int $defaultValue = null;

    /**
     * Set minimum value (inclusive).
     */
    public function minimum(int $value): static
    {
        $this->minimum = $value;

        return $this;
    }

    /**
     * Set maximum value (inclusive).
     */
    public function maximum(int $value): static
    {
        $this->maximum = $value;

        return $this;
    }

    /**
     * Set exclusive minimum value.
     */
    public function exclusiveMinimum(int $value): static
    {
        $this->exclusiveMinimum = $value;

        return $this;
    }

    /**
     * Set exclusive maximum value.
     */
    public function exclusiveMaximum(int $value): static
    {
        $this->exclusiveMaximum = $value;

        return $this;
    }

    /**
     * Set multipleOf constraint.
     */
    public function multipleOf(int $value): static
    {
        $this->multipleOf = $value;

        return $this;
    }

    /**
     * Set default value.
     */
    public function default(int $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => 'integer'];

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->exclusiveMinimum !== null) {
            $schema['exclusiveMinimum'] = $this->exclusiveMinimum;
        }

        if ($this->exclusiveMaximum !== null) {
            $schema['exclusiveMaximum'] = $this->exclusiveMaximum;
        }

        if ($this->multipleOf !== null) {
            $schema['multipleOf'] = $this->multipleOf;
        }

        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (! is_int($value) && ! (is_numeric($value) && (int) $value == $value)) {
            return ValidationResult::error('$', 'Value must be an integer', $value);
        }

        $intValue = (int) $value;

        if ($this->minimum !== null && $intValue < $this->minimum) {
            return ValidationResult::error(
                '$',
                "Integer must be at least {$this->minimum}",
                $value
            );
        }

        if ($this->maximum !== null && $intValue > $this->maximum) {
            return ValidationResult::error(
                '$',
                "Integer must be at most {$this->maximum}",
                $value
            );
        }

        if ($this->exclusiveMinimum !== null && $intValue <= $this->exclusiveMinimum) {
            return ValidationResult::error(
                '$',
                "Integer must be greater than {$this->exclusiveMinimum}",
                $value
            );
        }

        if ($this->exclusiveMaximum !== null && $intValue >= $this->exclusiveMaximum) {
            return ValidationResult::error(
                '$',
                "Integer must be less than {$this->exclusiveMaximum}",
                $value
            );
        }

        if ($this->multipleOf !== null && $intValue % $this->multipleOf !== 0) {
            return ValidationResult::error(
                '$',
                "Integer must be a multiple of {$this->multipleOf}",
                $value
            );
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to an integer.
     */
    public function cast(mixed $value): int
    {
        if ($value === null && $this->defaultValue !== null) {
            return $this->defaultValue;
        }

        return (int) $value;
    }
}
