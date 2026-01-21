<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Closure;
use Spatie\LaravelData\Data;

class Edge extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Closure $condition = null,
        public int $priority = 0,
    ) {}

    /**
     * Create an edge builder.
     */
    public static function start(string $from): EdgeBuilder
    {
        return new EdgeBuilder($from);
    }
}

class EdgeBuilder
{
    private ?string $to = null;

    private ?Closure $condition = null;

    private int $priority = 0;

    public function __construct(
        private string $from,
    ) {}

    /**
     * Set the destination node.
     */
    public function to(string $to): static
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Add a condition.
     */
    public function when(?Closure $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Set priority.
     */
    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Build the edge.
     */
    public function build(): Edge
    {
        return new Edge(
            from: $this->from,
            to: $this->to ?? throw new \InvalidArgumentException('Edge destination not set'),
            condition: $this->condition,
            priority: $this->priority,
        );
    }
}
