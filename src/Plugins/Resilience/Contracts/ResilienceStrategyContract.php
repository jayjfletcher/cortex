<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Contracts;

use Closure;

interface ResilienceStrategyContract
{
    /**
     * Execute an operation with resilience protection.
     *
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function execute(Closure $operation): mixed;
}
