<?php

declare(strict_types=1);

namespace JayI\Cortex\Support;

use JayI\Cortex\Contracts\TenantContextContract;
use Spatie\LaravelData\Data;

class TenantContext extends Data implements TenantContextContract
{
    public function __construct(
        protected string|int|null $tenantId = null,
        protected array $providerConfigs = [],
        protected array $apiKeys = [],
        protected array $settings = [],
    ) {}

    public function id(): string|int|null
    {
        return $this->tenantId;
    }

    public function getProviderConfig(string $provider): array
    {
        return $this->providerConfigs[$provider] ?? [];
    }

    public function getApiKey(string $provider): ?string
    {
        return $this->apiKeys[$provider] ?? null;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Create a tenant context with provider configuration.
     */
    public static function withProvider(
        string|int $tenantId,
        string $provider,
        array $config,
        ?string $apiKey = null,
    ): static {
        return new static(
            tenantId: $tenantId,
            providerConfigs: [$provider => $config],
            apiKeys: $apiKey ? [$provider => $apiKey] : [],
        );
    }

    /**
     * Add additional provider configuration.
     */
    public function addProviderConfig(string $provider, array $config, ?string $apiKey = null): static
    {
        return new static(
            tenantId: $this->tenantId,
            providerConfigs: array_merge($this->providerConfigs, [$provider => $config]),
            apiKeys: array_merge($this->apiKeys, $apiKey ? [$provider => $apiKey] : []),
            settings: $this->settings,
        );
    }

    /**
     * Set tenant settings.
     */
    public function withSettings(array $settings): static
    {
        return new static(
            tenantId: $this->tenantId,
            providerConfigs: $this->providerConfigs,
            apiKeys: $this->apiKeys,
            settings: $settings,
        );
    }
}
