<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Retrievers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;
use JayI\Cortex\Plugins\Agent\RetrievedContent;
use JayI\Cortex\Plugins\Agent\RetrievedItem;

/**
 * Retriever that searches an in-memory collection using simple text matching.
 */
class CollectionRetriever implements RetrieverContract
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function __construct(
        protected Collection $items,
        protected string $contentKey = 'content',
    ) {}

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        $query = Str::lower($query);
        $queryWords = array_filter(explode(' ', $query), fn ($word) => strlen($word) > 2);

        if (empty($queryWords)) {
            $queryWords = [$query];
        }

        $scored = $this->items->map(function ($item) use ($queryWords) {
            $content = is_array($item) ? ($item[$this->contentKey] ?? '') : (string) $item;
            $contentLower = Str::lower($content);

            // Calculate score based on matching words
            $score = 0;
            $totalWords = count($queryWords);

            foreach ($queryWords as $word) {
                if (Str::contains($contentLower, $word)) {
                    // Boost score for exact word matches
                    $wordCount = substr_count($contentLower, $word);
                    $score += min($wordCount * 0.5, 1.0);
                }
            }

            // Normalize score
            $normalizedScore = $totalWords > 0 ? $score / $totalWords : 0;

            return [
                'content' => $content,
                'score' => $normalizedScore,
                'metadata' => is_array($item) ? $item : ['content' => $content],
            ];
        })
            ->filter(fn ($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        $items = $scored->map(fn ($item) => new RetrievedItem(
            content: $item['content'],
            score: $item['score'],
            metadata: $item['metadata'],
        ))->all();

        return new RetrievedContent(items: $items);
    }

    /**
     * Create a retriever from an array or collection.
     *
     * @param  iterable<int, mixed>  $items
     */
    public static function make(iterable $items, string $contentKey = 'content'): static
    {
        return new static(
            items: Collection::make($items),
            contentKey: $contentKey,
        );
    }

    /**
     * Create a retriever from simple strings.
     *
     * @param  array<int, string>  $strings
     */
    public static function fromStrings(array $strings): static
    {
        $items = Collection::make($strings)->map(fn ($s) => ['content' => $s]);

        return new static($items);
    }
}
