<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt\Contracts;

use Illuminate\Support\Collection;

interface PromptRegistryContract
{
    public function register(PromptContract $prompt): void;

    public function get(string $id, ?string $version = null): PromptContract;

    public function has(string $id): bool;

    public function versions(string $id): Collection;

    public function latest(string $id): PromptContract;

    public function all(): Collection;
}
