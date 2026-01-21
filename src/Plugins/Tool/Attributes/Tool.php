<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Tool
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?int $timeout = null,
        public array $metadata = [],
    ) {}
}
