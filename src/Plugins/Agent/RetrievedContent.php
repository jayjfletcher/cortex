<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Spatie\LaravelData\Data;

class RetrievedContent extends Data
{
    /**
     * @param  array<int, RetrievedItem>  $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    /**
     * Create from an array of items.
     *
     * @param  array<int, RetrievedItem|array<string, mixed>>  $items
     */
    public static function fromItems(array $items): static
    {
        $retrievedItems = array_map(
            fn ($item) => $item instanceof RetrievedItem ? $item : RetrievedItem::fromArray($item),
            $items
        );

        return new static(items: $retrievedItems);
    }

    /**
     * Check if the content is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }

    /**
     * Check if the content is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the count of items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Get items sorted by score (highest first).
     *
     * @return array<int, RetrievedItem>
     */
    public function sortedByScore(): array
    {
        $items = $this->items;
        usort($items, fn ($a, $b) => $b->score <=> $a->score);

        return $items;
    }

    /**
     * Get the first N items.
     *
     * @return array<int, RetrievedItem>
     */
    public function take(int $limit): array
    {
        return array_slice($this->items, 0, $limit);
    }

    /**
     * Filter items by minimum score.
     */
    public function filterByScore(float $minScore): static
    {
        $filtered = array_filter(
            $this->items,
            fn ($item) => $item->score >= $minScore
        );

        return new static(items: array_values($filtered));
    }

    /**
     * Format the content for inclusion in a prompt.
     */
    public function toContext(string $separator = "\n\n---\n\n"): string
    {
        return implode(
            $separator,
            array_map(fn ($item) => $item->toContext(), $this->items)
        );
    }

    /**
     * Get all content as a simple string array.
     *
     * @return array<int, string>
     */
    public function toStringArray(): array
    {
        return array_map(fn ($item) => $item->content, $this->items);
    }
}
