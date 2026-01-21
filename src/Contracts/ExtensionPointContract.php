<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

use Illuminate\Support\Collection;

interface ExtensionPointContract
{
    /**
     * Get the name of this extension point.
     */
    public function name(): string;

    /**
     * Get the interface/class that extensions must implement.
     */
    public function accepts(): string;

    /**
     * Register an extension with this extension point.
     */
    public function register(mixed $extension): void;

    /**
     * Get all registered extensions.
     *
     * @return Collection<int, mixed>
     */
    public function all(): Collection;
}
