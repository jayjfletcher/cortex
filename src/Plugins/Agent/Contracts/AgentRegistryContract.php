<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Contracts;

use Illuminate\Support\Collection;

interface AgentRegistryContract
{
    /**
     * Register an agent.
     */
    public function register(AgentContract $agent): void;

    /**
     * Get an agent by ID.
     */
    public function get(string $id): AgentContract;

    /**
     * Check if an agent exists.
     */
    public function has(string $id): bool;

    /**
     * Get all registered agents.
     *
     * @return Collection<string, AgentContract>
     */
    public function all(): Collection;

    /**
     * Discover agents from directories.
     *
     * @param  array<int, string>  $paths
     */
    public function discover(array $paths): void;
}
