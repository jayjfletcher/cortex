<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

abstract class Content
{
    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * Get the content type.
     */
    abstract public function type(): string;
}
