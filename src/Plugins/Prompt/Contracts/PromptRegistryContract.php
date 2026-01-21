<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt\Contracts;

use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Prompt\PromptCollection;

interface PromptRegistryContract
{
    public function register(PromptContract $prompt): void;

    public function get(string $id, ?string $version = null): PromptContract;

    public function has(string $id): bool;

    /**
     * Get all versions for a prompt ID.
     *
     * @return Collection<int, string>
     */
    public function versions(string $id): Collection;

    public function latest(string $id): PromptContract;

    /**
     * Get all registered prompts (latest versions).
     */
    public function all(): PromptCollection;

    /**
     * Get only the specified prompts.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): PromptCollection;

    /**
     * Get all prompts except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): PromptCollection;
}
