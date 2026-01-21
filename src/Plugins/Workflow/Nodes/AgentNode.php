<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that executes an agent.
 */
class AgentNode implements NodeContract
{
    public function __construct(
        protected string $nodeId,
        protected AgentContract|string $agent,
        protected ?string $inputKey = null,
        protected ?string $outputKey = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->nodeId;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $input, WorkflowState $state): NodeResult
    {
        $agent = $this->resolveAgent();

        // Get input from state if key specified
        $agentInput = $this->inputKey !== null
            ? $state->get($this->inputKey, '')
            : ($input['message'] ?? json_encode($input));

        // Create agent context from workflow state
        $context = new AgentContext(
            conversationId: $state->runId,
            metadata: [
                'workflow_id' => $state->workflowId,
                'node_id' => $this->nodeId,
            ],
        );

        try {
            $response = $agent->run($agentInput, $context);

            $output = [
                'content' => $response->content,
                'iterations' => $response->iterationCount,
                'stop_reason' => $response->stopReason->value,
                'usage' => [
                    'input_tokens' => $response->totalUsage->inputTokens,
                    'output_tokens' => $response->totalUsage->outputTokens,
                ],
            ];

            // Store in specific key if configured
            if ($this->outputKey !== null) {
                $output = [$this->outputKey => $response->content];
            }

            return NodeResult::success($output);
        } catch (\Throwable $e) {
            return NodeResult::failure("Agent execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Resolve the agent instance.
     */
    protected function resolveAgent(): AgentContract
    {
        if ($this->agent instanceof AgentContract) {
            return $this->agent;
        }

        /** @var AgentRegistryContract $registry */
        $registry = app(AgentRegistryContract::class);

        return $registry->get($this->agent);
    }
}
