<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

interface TenantResolverContract
{
    public function resolve(): ?TenantContextContract;
}
