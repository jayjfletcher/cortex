<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache\Strategies;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use JayI\Cortex\Plugins\Cache\Contracts\CacheStrategyContract;
use JayI\Cortex\Plugins\Cache\Data\CacheEntry;

/**
 * Cache strategy using semantic similarity matching.
 *
 * This strategy caches responses based on semantic similarity of prompts,
 * allowing cache hits for semantically equivalent but textually different queries.
 */
class SemanticCacheStrategy implements CacheStrategyContract
{
    protected string $prefix = 'cortex_semantic_';

    /** @var array<string, CacheEntry> */
    protected array $index = [];

    public function __construct(
        protected CacheRepository $cache,
        protected int $defaultTtl = 3600,
        protected float $similarityThreshold = 0.95,
    ) {
        $this->loadIndex();
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'semantic';
    }

    /**
     * {@inheritdoc}
     */
    public function generateKey(array $request): string
    {
        $content = $this->extractContent($request);

        return $this->prefix.hash('sha256', $content);
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(CacheEntry $entry, array $request): bool
    {
        return ! $entry->isExpired();
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $request): ?CacheEntry
    {
        $content = $this->extractContent($request);

        // First, try exact match
        $exactKey = $this->generateKey($request);
        $exactEntry = $this->cache->get($exactKey);

        if ($exactEntry instanceof CacheEntry && $this->isValid($exactEntry, $request)) {
            return $exactEntry->recordHit();
        }

        // Then, try semantic match
        $match = $this->findSemanticMatch($content);

        if ($match !== null && $this->isValid($match, $request)) {
            return $match->recordHit();
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function put(array $request, mixed $response, ?int $ttlSeconds = null): CacheEntry
    {
        $key = $this->generateKey($request);
        $ttl = $ttlSeconds ?? $this->defaultTtl;
        $content = $this->extractContent($request);

        $entry = CacheEntry::create(
            key: $key,
            response: $response,
            ttlSeconds: $ttl,
            metadata: [
                'strategy' => $this->id(),
                'content' => $content,
                'content_hash' => hash('sha256', $content),
            ],
        );

        $this->cache->put($key, $entry, $ttl);
        $this->addToIndex($content, $entry);

        return $entry;
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $key): void
    {
        $entry = $this->cache->get($key);

        if ($entry instanceof CacheEntry) {
            $this->removeFromIndex($entry);
        }

        $this->cache->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->index = [];
        $this->saveIndex();
        $this->cache->flush();
    }

    /**
     * Extract the content from a request for comparison.
     *
     * @param  array<string, mixed>  $request
     */
    protected function extractContent(array $request): string
    {
        // Extract the main message content
        if (isset($request['messages'])) {
            $contents = [];
            foreach ($request['messages'] as $message) {
                if (isset($message['content'])) {
                    $contents[] = is_string($message['content'])
                        ? $message['content']
                        : json_encode($message['content']);
                }
            }

            return implode("\n", $contents);
        }

        if (isset($request['prompt'])) {
            return $request['prompt'];
        }

        return json_encode($request);
    }

    /**
     * Find a semantically similar cached entry.
     */
    protected function findSemanticMatch(string $content): ?CacheEntry
    {
        $contentWords = $this->tokenize($content);

        foreach ($this->index as $indexedContent => $entry) {
            $indexedWords = $this->tokenize($indexedContent);
            $similarity = $this->calculateSimilarity($contentWords, $indexedWords);

            if ($similarity >= $this->similarityThreshold) {
                // Verify entry still exists in cache
                $cachedEntry = $this->cache->get($entry->key);

                if ($cachedEntry instanceof CacheEntry) {
                    return $cachedEntry;
                }
            }
        }

        return null;
    }

    /**
     * Calculate similarity between two sets of tokens using Jaccard similarity.
     *
     * @param  array<int, string>  $tokens1
     * @param  array<int, string>  $tokens2
     */
    protected function calculateSimilarity(array $tokens1, array $tokens2): float
    {
        if (empty($tokens1) && empty($tokens2)) {
            return 1.0;
        }

        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));

        return $intersection / $union;
    }

    /**
     * Tokenize text for comparison.
     *
     * @return array<int, string>
     */
    protected function tokenize(string $text): array
    {
        // Normalize text
        $text = strtolower($text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove common stop words
        $stopWords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'to',
            'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through',
            'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again',
            'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how',
            'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor',
            'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 'but',
            'if', 'or', 'because', 'until', 'while', 'although', 'though', 'after', 'before',
        ];

        return array_values(array_diff($words, $stopWords));
    }

    /**
     * Add an entry to the semantic index.
     */
    protected function addToIndex(string $content, CacheEntry $entry): void
    {
        $this->index[$content] = $entry;
        $this->saveIndex();
    }

    /**
     * Remove an entry from the semantic index.
     */
    protected function removeFromIndex(CacheEntry $entry): void
    {
        $content = $entry->metadata['content'] ?? null;

        if ($content !== null) {
            unset($this->index[$content]);
            $this->saveIndex();
        }
    }

    /**
     * Load the index from cache.
     */
    protected function loadIndex(): void
    {
        $this->index = $this->cache->get($this->prefix.'index', []);
    }

    /**
     * Save the index to cache.
     */
    protected function saveIndex(): void
    {
        $this->cache->put($this->prefix.'index', $this->index, $this->defaultTtl * 2);
    }

    /**
     * Set the similarity threshold.
     */
    public function setThreshold(float $threshold): self
    {
        $this->similarityThreshold = max(0.0, min(1.0, $threshold));

        return $this;
    }
}
