<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Contracts;

use JayI\Cortex\Plugins\Agent\RetrievedContent;

interface RetrieverContract
{
    /**
     * Retrieve relevant content based on a query.
     *
     * @param  string  $query  The query to retrieve content for
     * @param  int  $limit  Maximum number of items to retrieve
     */
    public function retrieve(string $query, int $limit = 5): RetrievedContent;
}
