<?php

declare(strict_types=1);

namespace JayI\Cortex\Support;

use JayI\Cortex\Contracts\TenantContextContract;
use JayI\Cortex\Contracts\TenantResolverContract;

class NullTenantResolver implements TenantResolverContract
{
    public function resolve(): ?TenantContextContract
    {
        return null;
    }
}
