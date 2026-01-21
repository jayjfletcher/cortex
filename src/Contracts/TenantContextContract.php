<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

interface TenantContextContract
{
    public function id(): string|int|null;

    public function getProviderConfig(string $provider): array;

    public function getApiKey(string $provider): ?string;

    public function getSettings(): array;
}
