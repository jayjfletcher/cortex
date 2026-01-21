<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

use Illuminate\Support\Collection;

interface AbstractRegistry
{
    public function all(): Collection;

    public function only(array $ids): Collection;

    public function except(array $ids): Collection;

    public function filter(callable $callback): Collection;

    public function ids(): array;

    public function get(string $id): mixed;

    public function has(string $id): bool;

    public function register(mixed $item): void;

    public function forget(string $id): void;

    public function clear(): void;

    public function discover(array $paths): void;
}
