<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Contracts;

use JayI\Cortex\Plugins\Agent\AgentCollection;

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
     */
    public function all(): AgentCollection;

    /**
     * Get only the specified agents.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): AgentCollection;

    /**
     * Get all agents except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): AgentCollection;

    /**
     * Discover agents from directories.
     *
     * @param  array<int, string>  $paths
     */
    public function discover(array $paths): void;
}
