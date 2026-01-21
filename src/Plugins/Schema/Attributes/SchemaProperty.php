<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class SchemaProperty
{
    public function __construct(
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $format = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?string $description = null,
    ) {}
}
