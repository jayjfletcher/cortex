<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class SchemaRequired
{
    /**
     * @param  array<int, string>  $properties
     */
    public function __construct(
        public array $properties = [],
    ) {}
}
