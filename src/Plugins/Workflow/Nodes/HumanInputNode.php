<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that pauses for human input.
 */
class HumanInputNode implements NodeContract
{
    public function __construct(
        protected string $nodeId,
        protected string $prompt,
        protected ?Schema $inputSchema = null,
        protected ?int $timeout = null,
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
        // Check if we have human input (resuming from pause)
        if (isset($input['human_input'])) {
            $humanInput = $input['human_input'];

            // Validate against schema if provided
            if ($this->inputSchema !== null) {
                $validation = $this->inputSchema->validate($humanInput);

                if (! $validation->isValid()) {
                    $errors = array_map(fn ($e) => $e->message, $validation->errors);

                    return NodeResult::failure('Invalid human input: ' . implode(', ', $errors));
                }
            }

            return NodeResult::success([
                'human_input' => $humanInput,
            ]);
        }

        // Pause for human input
        return NodeResult::pause($this->prompt, [
            'awaiting_input' => true,
            'prompt' => $this->prompt,
            'schema' => $this->inputSchema?->toJsonSchema(),
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Get the prompt text.
     */
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * Get the input schema.
     */
    public function getInputSchema(): ?Schema
    {
        return $this->inputSchema;
    }
}
