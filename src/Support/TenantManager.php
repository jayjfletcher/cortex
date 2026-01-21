<?php

declare(strict_types=1);

namespace JayI\Cortex\Support;

use Closure;
use JayI\Cortex\Contracts\TenantContextContract;
use JayI\Cortex\Contracts\TenantResolverContract;

class TenantManager
{
    protected ?TenantContextContract $currentTenant = null;

    public function __construct(
        protected TenantResolverContract $resolver,
    ) {}

    /**
     * Get the current tenant context.
     */
    public function current(): ?TenantContextContract
    {
        return $this->currentTenant ?? $this->resolver->resolve();
    }

    /**
     * Set the current tenant context.
     */
    public function set(TenantContextContract $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    /**
     * Clear the current tenant context.
     */
    public function clear(): void
    {
        $this->currentTenant = null;
    }

    /**
     * Execute a callback within a tenant context.
     */
    public function withTenant(TenantContextContract $tenant, Closure $callback): mixed
    {
        $previous = $this->currentTenant;
        $this->currentTenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->currentTenant = $previous;
        }
    }

    /**
     * Check if a tenant context is currently active.
     */
    public function hasTenant(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Get the resolver instance.
     */
    public function resolver(): TenantResolverContract
    {
        return $this->resolver;
    }
}
