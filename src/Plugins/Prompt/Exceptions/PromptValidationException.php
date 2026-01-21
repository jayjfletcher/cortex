<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt\Exceptions;

use Exception;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class PromptValidationException extends Exception
{
    public function __construct(
        public readonly ValidationResult $result,
        string $message = 'Prompt validation failed',
    ) {
        parent::__construct($message . ': ' . implode(', ', $result->messages()));
    }

    public static function fromResult(ValidationResult $result): self
    {
        return new self($result);
    }
}
