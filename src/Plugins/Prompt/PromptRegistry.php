<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt;

use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptNotFoundException;

class PromptRegistry implements PromptRegistryContract
{
    /** @var array<string, array<string, PromptContract>> */
    protected array $prompts = [];

    public function register(PromptContract $prompt): void
    {
        $this->prompts[$prompt->id()][$prompt->version()] = $prompt;
    }

    public function get(string $id, ?string $version = null): PromptContract
    {
        if (! $this->has($id)) {
            throw PromptNotFoundException::forId($id);
        }

        if ($version === null) {
            return $this->latest($id);
        }

        if (! isset($this->prompts[$id][$version])) {
            throw PromptNotFoundException::forVersion($id, $version);
        }

        return $this->prompts[$id][$version];
    }

    public function has(string $id): bool
    {
        return isset($this->prompts[$id]) && ! empty($this->prompts[$id]);
    }

    public function versions(string $id): Collection
    {
        if (! $this->has($id)) {
            return collect();
        }

        return collect(array_keys($this->prompts[$id]))
            ->sort(SORT_NATURAL)
            ->values();
    }

    public function latest(string $id): PromptContract
    {
        if (! $this->has($id)) {
            throw PromptNotFoundException::forId($id);
        }

        $versions = $this->versions($id);
        $latestVersion = $versions->last();

        return $this->prompts[$id][$latestVersion];
    }

    public function all(): Collection
    {
        return collect($this->prompts)
            ->map(fn (array $versions) => collect($versions)->last());
    }

    /**
     * Get all registered prompt IDs.
     */
    public function ids(): Collection
    {
        return collect(array_keys($this->prompts));
    }

    /**
     * Get all versions of a specific prompt.
     */
    public function allVersions(string $id): Collection
    {
        if (! $this->has($id)) {
            return collect();
        }

        return collect($this->prompts[$id]);
    }
}
