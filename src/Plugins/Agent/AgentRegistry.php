<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use JayI\Cortex\Exceptions\AgentException;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;

class AgentRegistry implements AgentRegistryContract
{
    protected AgentCollection $agents;

    public function __construct()
    {
        $this->agents = AgentCollection::make([]);
    }

    /**
     * {@inheritdoc}
     */
    public function register(AgentContract $agent): void
    {
        $this->agents = $this->agents->add($agent);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): AgentContract
    {
        if (! $this->has($id)) {
            throw AgentException::notFound($id);
        }

        return $this->agents->get($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->agents->has($id);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): AgentCollection
    {
        return $this->agents;
    }

    /**
     * {@inheritdoc}
     */
    public function only(array $ids): AgentCollection
    {
        return $this->agents->only($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function except(array $ids): AgentCollection
    {
        return $this->agents->except($ids);
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
