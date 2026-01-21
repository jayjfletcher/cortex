<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use Spatie\LaravelData\Data;

class WorkflowDefinition extends Data
{
    /**
     * @param  array<int, NodeContract>  $nodes
     * @param  array<int, Edge>  $edges
     * @param  array<int, string>  $exitPoints
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $nodes,
        public array $edges,
        public ?string $entryNode = null,
        public array $exitPoints = [],
        public array $metadata = [],
    ) {}

    /**
     * Get a node by ID.
     */
    public function getNode(string $id): ?NodeContract
    {
        foreach ($this->nodes as $node) {
            if ($node->id() === $id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Check if a node exists.
     */
    public function hasNode(string $id): bool
    {
        return $this->getNode($id) !== null;
    }

    /**
     * Get edges from a node.
     *
     * @return array<int, Edge>
     */
    public function getEdgesFrom(string $nodeId): array
    {
        $edges = array_filter($this->edges, fn (Edge $e) => $e->from === $nodeId);

        // Sort by priority (higher first)
        usort($edges, fn (Edge $a, Edge $b) => $b->priority <=> $a->priority);

        return array_values($edges);
    }

    /**
     * Check if a node is an exit point.
     */
    public function isExitPoint(string $nodeId): bool
    {
        if (empty($this->exitPoints)) {
            // If no exit points defined, check if node has no outgoing edges
            return empty($this->getEdgesFrom($nodeId));
        }

        return in_array($nodeId, $this->exitPoints, true);
    }

    /**
     * Get the next node from an edge.
     *
     * @param  array<string, mixed>  $context
     */
    public function getNextNode(string $fromNodeId, array $context = []): ?string
    {
        $edges = $this->getEdgesFrom($fromNodeId);

        foreach ($edges as $edge) {
            if ($edge->condition === null || ($edge->condition)($context)) {
                return $edge->to;
            }
        }

        return null;
    }
}
