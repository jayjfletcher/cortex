<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class StringSchema extends Schema
{
    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected ?string $pattern = null;

    protected ?string $format = null;

    protected ?string $defaultValue = null;

    /**
     * @var array<int, string>
     */
    protected array $examples = [];

    /**
     * Set minimum length.
     */
    public function minLength(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /**
     * Set maximum length.
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Set pattern (regex).
     */
    public function pattern(string $regex): static
    {
        $this->pattern = $regex;

        return $this;
    }

    /**
     * Set format (email, uri, date-time, etc.).
     */
    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * Set default value.
     */
    public function default(string $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Add examples.
     */
    public function examples(string ...$examples): static
    {
        $this->examples = $examples;

        return $this;
    }

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => 'string'];

        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }

        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        if (count($this->examples) > 0) {
            $schema['examples'] = $this->examples;
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (! is_string($value)) {
            return ValidationResult::error('$', 'Value must be a string', $value);
        }

        $length = mb_strlen($value);

        if ($this->minLength !== null && $length < $this->minLength) {
            return ValidationResult::error(
                '$',
                "String must be at least {$this->minLength} characters",
                $value
            );
        }

        if ($this->maxLength !== null && $length > $this->maxLength) {
            return ValidationResult::error(
                '$',
                "String must be at most {$this->maxLength} characters",
                $value
            );
        }

        if ($this->pattern !== null && ! preg_match('/'.$this->pattern.'/', $value)) {
            return ValidationResult::error(
                '$',
                "String must match pattern: {$this->pattern}",
                $value
            );
        }

        if ($this->format !== null && ! $this->validateFormat($value)) {
            return ValidationResult::error(
                '$',
                "String must be a valid {$this->format}",
                $value
            );
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to a string.
     */
    public function cast(mixed $value): string
    {
        if ($value === null && $this->defaultValue !== null) {
            return $this->defaultValue;
        }

        return (string) $value;
    }

    /**
     * Validate string format.
     */
    protected function validateFormat(string $value): bool
    {
        return match ($this->format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uri', 'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'date' => $this->isValidDate($value, 'Y-m-d'),
            'date-time' => $this->isValidDate($value, \DateTimeInterface::ATOM) ||
                          $this->isValidDate($value, 'Y-m-d\TH:i:sP') ||
                          $this->isValidDate($value, 'Y-m-d\TH:i:s.uP'),
            'time' => $this->isValidDate($value, 'H:i:s'),
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            'ipv4' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'ipv6' => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            default => true,
        };
    }

    /**
     * Check if value is a valid date in the given format.
     */
    protected function isValidDate(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);

        return $date !== false && $date->format($format) === $value;
    }
}
