<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Retrievers;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use JayI\Cortex\Plugins\Agent\Contracts\RetrieverContract;
use JayI\Cortex\Plugins\Agent\RetrievedContent;
use JayI\Cortex\Plugins\Agent\RetrievedItem;

/**
 * Retriever that searches Eloquent models.
 */
class EloquentRetriever implements RetrieverContract
{
    protected ?Closure $queryModifier = null;

    protected string $contentColumn = 'content';

    protected ?string $scoreColumn = null;

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     */
    public function __construct(
        protected string $model,
        protected array $searchColumns,
    ) {}

    /**
     * Set the content column to use.
     */
    public function contentColumn(string $column): static
    {
        $this->contentColumn = $column;

        return $this;
    }

    /**
     * Set the score column (for semantic search results).
     */
    public function scoreColumn(string $column): static
    {
        $this->scoreColumn = $column;

        return $this;
    }

    /**
     * Add a custom query modifier.
     *
     * @param  Closure(Builder, string): void  $modifier
     */
    public function modifyQuery(Closure $modifier): static
    {
        $this->queryModifier = $modifier;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        /** @var Model $model */
        $model = new $this->model;

        $builder = $model->newQuery();

        // Build search query using LIKE on specified columns
        $builder->where(function (Builder $q) use ($query) {
            foreach ($this->searchColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$query}%");
            }
        });

        // Apply custom modifications
        if ($this->queryModifier) {
            ($this->queryModifier)($builder, $query);
        }

        $results = $builder->limit($limit)->get();

        $items = $results->map(function ($row) {
            return new RetrievedItem(
                content: $row->{$this->contentColumn} ?? '',
                score: $this->scoreColumn ? (float) ($row->{$this->scoreColumn} ?? 1.0) : 1.0,
                metadata: $row->toArray(),
            );
        })->all();

        return new RetrievedContent(items: $items);
    }

    /**
     * Create a retriever for a model.
     *
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchColumns
     */
    public static function make(string $model, array $searchColumns): static
    {
        return new static($model, $searchColumns);
    }
}
