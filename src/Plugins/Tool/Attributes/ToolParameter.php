<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class ToolParameter
{
    /**
     * @param  array<string>|null  $enum
     */
    public function __construct(
        public ?string $description = null,
        public ?string $type = null,
        public bool $required = true,
        public mixed $default = null,
        public ?array $enum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?string $pattern = null,
        public ?string $format = null,
    ) {}
}
