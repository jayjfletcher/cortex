<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Illuminate\Support\Collection;
use JayI\Cortex\Exceptions\AgentException;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;

class AgentRegistry implements AgentRegistryContract
{
    /**
     * @var array<string, AgentContract>
     */
    protected array $agents = [];

    /**
     * {@inheritdoc}
     */
    public function register(AgentContract $agent): void
    {
        $this->agents[$agent->id()] = $agent;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): AgentContract
    {
        if (! $this->has($id)) {
            throw AgentException::notFound($id);
        }

        return $this->agents[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->agents[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        return collect($this->agents);
    }

    /**
     * {@inheritdoc}
     */
    public function discover(array $paths): void
    {
        // TODO: Implement agent discovery from directories
        // This would scan for classes with #[Agent] attribute
    }
}
