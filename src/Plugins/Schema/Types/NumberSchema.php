<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class NumberSchema extends Schema
{
    protected ?float $minimum = null;

    protected ?float $maximum = null;

    protected ?float $exclusiveMinimum = null;

    protected ?float $exclusiveMaximum = null;

    protected ?float $multipleOf = null;

    protected ?float $defaultValue = null;

    /**
     * Set minimum value (inclusive).
     */
    public function minimum(float $value): static
    {
        $this->minimum = $value;

        return $this;
    }

    /**
     * Set maximum value (inclusive).
     */
    public function maximum(float $value): static
    {
        $this->maximum = $value;

        return $this;
    }

    /**
     * Set exclusive minimum value.
     */
    public function exclusiveMinimum(float $value): static
    {
        $this->exclusiveMinimum = $value;

        return $this;
    }

    /**
     * Set exclusive maximum value.
     */
    public function exclusiveMaximum(float $value): static
    {
        $this->exclusiveMaximum = $value;

        return $this;
    }

    /**
     * Set multipleOf constraint.
     */
    public function multipleOf(float $value): static
    {
        $this->multipleOf = $value;

        return $this;
    }

    /**
     * Set default value.
     */
    public function default(float $value): static
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
        $schema = ['type' => 'number'];

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
        if (! is_numeric($value)) {
            return ValidationResult::error('$', 'Value must be a number', $value);
        }

        $numericValue = (float) $value;

        if ($this->minimum !== null && $numericValue < $this->minimum) {
            return ValidationResult::error(
                '$',
                "Number must be at least {$this->minimum}",
                $value
            );
        }

        if ($this->maximum !== null && $numericValue > $this->maximum) {
            return ValidationResult::error(
                '$',
                "Number must be at most {$this->maximum}",
                $value
            );
        }

        if ($this->exclusiveMinimum !== null && $numericValue <= $this->exclusiveMinimum) {
            return ValidationResult::error(
                '$',
                "Number must be greater than {$this->exclusiveMinimum}",
                $value
            );
        }

        if ($this->exclusiveMaximum !== null && $numericValue >= $this->exclusiveMaximum) {
            return ValidationResult::error(
                '$',
                "Number must be less than {$this->exclusiveMaximum}",
                $value
            );
        }

        if ($this->multipleOf !== null && fmod($numericValue, $this->multipleOf) !== 0.0) {
            return ValidationResult::error(
                '$',
                "Number must be a multiple of {$this->multipleOf}",
                $value
            );
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to a float.
     */
    public function cast(mixed $value): float
    {
        if ($value === null && $this->defaultValue !== null) {
            return $this->defaultValue;
        }

        return (float) $value;
    }
}
