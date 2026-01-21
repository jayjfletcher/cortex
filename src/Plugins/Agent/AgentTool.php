<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;

/**
 * Wraps an agent as a tool, allowing agents to be used by other agents.
 */
class AgentTool implements ToolContract
{
    protected ?string $toolName = null;

    protected ?string $toolDescription = null;

    protected ?Schema $toolInputSchema = null;

    protected ?int $toolTimeout = null;

    public function __construct(
        protected AgentContract $agent,
    ) {}

    /**
     * Create a new agent tool.
     */
    public static function make(AgentContract $agent): static
    {
        return new static($agent);
    }

    /**
     * Set a custom tool name (defaults to agent ID).
     */
    public function withName(string $name): static
    {
        $this->toolName = $name;

        return $this;
    }

    /**
     * Set a custom tool description (defaults to agent description).
     */
    public function withDescription(string $description): static
    {
        $this->toolDescription = $description;

        return $this;
    }

    /**
     * Set a custom input schema.
     */
    public function withInputSchema(Schema $schema): static
    {
        $this->toolInputSchema = $schema;

        return $this;
    }

    /**
     * Set the timeout in seconds.
     */
    public function withTimeout(?int $seconds): static
    {
        $this->toolTimeout = $seconds;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->toolName ?? $this->agent->id();
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        if ($this->toolDescription !== null) {
            return $this->toolDescription;
        }

        $description = $this->agent->description();

        if (empty($description)) {
            return "Delegate task to the {$this->agent->name()} agent.";
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    public function inputSchema(): Schema
    {
        if ($this->toolInputSchema !== null) {
            return $this->toolInputSchema;
        }

        return Schema::object()
            ->property('task', Schema::string()->description('The task or query to send to the agent'))
            ->required('task');
    }

    /**
     * {@inheritdoc}
     */
    public function outputSchema(): ?Schema
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $input, ToolContext $context): ToolResult
    {
        try {
            $task = $input['task'] ?? '';

            $agentContext = new AgentContext(
                conversationId: $context->conversationId,
                tenantId: $context->tenantId,
                metadata: array_merge($context->metadata ?? [], [
                    'parent_agent' => $context->agentId,
                    'delegated_from_tool' => true,
                ]),
            );

            $response = $this->agent->run($task, $agentContext);

            return ToolResult::success([
                'response' => $response->content,
                'iterations' => $response->iterationCount,
                'stop_reason' => $response->stopReason->value,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error("Agent execution failed: {$e->getMessage()}");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function timeout(): ?int
    {
        return $this->toolTimeout;
    }

    /**
     * {@inheritdoc}
     */
    public function toDefinition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => $this->inputSchema()->toJsonSchema(),
        ];
    }

    /**
     * Get the wrapped agent.
     */
    public function agent(): AgentContract
    {
        return $this->agent;
    }
}
