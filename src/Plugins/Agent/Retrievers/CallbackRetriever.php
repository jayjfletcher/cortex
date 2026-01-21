<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Retrievers;

use Closure;
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;
use JayI\Cortex\Plugins\Agent\RetrievedContent;

/**
 * Retriever that uses a callback for custom retrieval logic.
 */
class CallbackRetriever implements RetrieverContract
{
    /**
     * @param  Closure(string, int): RetrievedContent  $callback
     */
    public function __construct(
        protected Closure $callback,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        return ($this->callback)($query, $limit);
    }

    /**
     * Create a retriever from a callback.
     *
     * @param  Closure(string, int): RetrievedContent  $callback
     */
    public static function make(Closure $callback): static
    {
        return new static($callback);
    }
}
