<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that executes multiple nodes in parallel.
 */
class ParallelNode implements NodeContract
{
    /**
     * @param  array<int, NodeContract>  $nodes
     */
    public function __construct(
        protected string $nodeId,
        protected array $nodes,
        protected string $mergeStrategy = 'all',
        protected ?Closure $merger = null,
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
        $results = [];
        $errors = [];

        // Execute all nodes (sequentially for now, could be made parallel with async)
        foreach ($this->nodes as $node) {
            try {
                $result = $node->execute($input, $state);
                $results[$node->id()] = $result;

                if (! $result->success) {
                    $errors[$node->id()] = $result->error;
                }

                // Handle pause
                if ($result->shouldPause) {
                    return NodeResult::pause(
                        "Parallel node {$node->id()} requested pause: {$result->pauseReason}",
                        ['partial_results' => $results]
                    );
                }
            } catch (\Throwable $e) {
                $errors[$node->id()] = $e->getMessage();
            }
        }

        // Check merge strategy
        return match ($this->mergeStrategy) {
            'all' => $this->mergeAll($results, $errors),
            'any' => $this->mergeAny($results, $errors),
            'custom' => $this->mergeCustom($results, $errors),
            default => $this->mergeAll($results, $errors),
        };
    }

    /**
     * Merge strategy: all nodes must succeed.
     *
     * @param  array<string, NodeResult>  $results
     * @param  array<string, string>  $errors
     */
    protected function mergeAll(array $results, array $errors): NodeResult
    {
        if (! empty($errors)) {
            return NodeResult::failure('Not all parallel nodes succeeded: '.json_encode($errors));
        }

        $merged = [];
        foreach ($results as $nodeId => $result) {
            $merged[$nodeId] = $result->output;
        }

        return NodeResult::success($merged);
    }

    /**
     * Merge strategy: at least one node must succeed.
     *
     * @param  array<string, NodeResult>  $results
     * @param  array<string, string>  $errors
     */
    protected function mergeAny(array $results, array $errors): NodeResult
    {
        $successful = array_filter($results, fn ($r) => $r->success);

        if (empty($successful)) {
            return NodeResult::failure('No parallel nodes succeeded: '.json_encode($errors));
        }

        $merged = [];
        foreach ($successful as $nodeId => $result) {
            $merged[$nodeId] = $result->output;
        }

        return NodeResult::success($merged);
    }

    /**
     * Merge strategy: custom merger function.
     *
     * @param  array<string, NodeResult>  $results
     * @param  array<string, string>  $errors
     */
    protected function mergeCustom(array $results, array $errors): NodeResult
    {
        if ($this->merger === null) {
            return $this->mergeAll($results, $errors);
        }

        $output = ($this->merger)($results, $errors);

        if ($output instanceof NodeResult) {
            return $output;
        }

        return NodeResult::success(is_array($output) ? $output : ['result' => $output]);
    }
}
