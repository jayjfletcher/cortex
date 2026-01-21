<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt\Exceptions;

use Exception;

class PromptNotFoundException extends Exception
{
    public static function forId(string $id): self
    {
        return new self("Prompt '{$id}' not found.");
    }

    public static function forVersion(string $id, string $version): self
    {
        return new self("Prompt '{$id}' version '{$version}' not found.");
    }
}
