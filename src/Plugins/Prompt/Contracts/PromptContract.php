<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt\Contracts;

use JayI\Cortex\Plugins\Schema\ValidationResult;

interface PromptContract
{
    public function id(): string;

    public function name(): string;

    public function version(): string;

    public function template(): string;

    public function variables(): array;

    public function render(array $variables = []): string;

    public function validateVariables(array $variables): ValidationResult;
}
