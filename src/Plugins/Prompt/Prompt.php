<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt;

use Illuminate\Support\Facades\Blade;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptValidationException;
use JayI\Cortex\Plugins\Schema\ValidationError;
use JayI\Cortex\Plugins\Schema\ValidationResult;
use Spatie\LaravelData\Data;

class Prompt extends Data implements PromptContract
{
    public function __construct(
        public string $id,
        public string $template,
        public array $requiredVariables = [],
        public array $defaults = [],
        public ?string $version = '1.0.0',
        public ?string $name = null,
        public array $metadata = [],
    ) {
        $this->name ??= $this->id;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name ?? $this->id;
    }

    public function version(): string
    {
        return $this->version ?? '1.0.0';
    }

    public function template(): string
    {
        return $this->template;
    }

    public function variables(): array
    {
        return $this->requiredVariables;
    }

    public function render(array $variables = []): string
    {
        $validation = $this->validateVariables($variables);

        if (! $validation->isValid()) {
            throw PromptValidationException::fromResult($validation);
        }

        $merged = array_merge($this->defaults, $variables);

        return Blade::render($this->template, $merged);
    }

    public function validateVariables(array $variables): ValidationResult
    {
        $errors = [];

        foreach ($this->requiredVariables as $required) {
            if (! array_key_exists($required, $variables) && ! array_key_exists($required, $this->defaults)) {
                $errors[] = new ValidationError(
                    path: $required,
                    message: "Missing required variable: {$required}",
                    value: null,
                );
            }
        }

        if (! empty($errors)) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }

    /**
     * Create a new prompt with a specific version.
     */
    public function withVersion(string $version): static
    {
        return new static(
            id: $this->id,
            template: $this->template,
            requiredVariables: $this->requiredVariables,
            defaults: $this->defaults,
            version: $version,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new prompt with defaults.
     */
    public function withDefaults(array $defaults): static
    {
        return new static(
            id: $this->id,
            template: $this->template,
            requiredVariables: $this->requiredVariables,
            defaults: array_merge($this->defaults, $defaults),
            version: $this->version,
            name: $this->name,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a simple prompt from a template string.
     */
    public static function fromTemplate(string $id, string $template, array $variables = []): static
    {
        return new static(
            id: $id,
            template: $template,
            requiredVariables: $variables,
        );
    }
}
