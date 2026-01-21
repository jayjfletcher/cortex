<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

use JayI\Cortex\Plugins\Schema\ValidationError;

class SchemaValidationException extends CortexException
{
    /**
     * @var array<int, ValidationError>
     */
    protected array $validationErrors = [];

    /**
     * Create an exception from validation errors.
     *
     * @param  array<int, ValidationError>  $errors
     */
    public static function withErrors(array $errors): static
    {
        $messages = array_map(
            fn (ValidationError $error) => $error->toString(),
            $errors
        );

        $exception = static::make('Schema validation failed: '.implode(', ', $messages));
        $exception->validationErrors = $errors;

        return $exception;
    }

    /**
     * Get the validation errors.
     *
     * @return array<int, ValidationError>
     */
    public function errors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Get error messages as an array.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->validationErrors as $error) {
            $messages[$error->path] = $error->message;
        }

        return $messages;
    }
}
