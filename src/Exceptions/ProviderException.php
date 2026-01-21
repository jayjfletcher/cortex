<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

use Throwable;

class ProviderException extends CortexException
{
    /**
     * Provider not found.
     */
    public static function notFound(string $providerId): static
    {
        return static::make("Provider [{$providerId}] is not registered.")
            ->withContext(['provider_id' => $providerId]);
    }

    /**
     * No default provider set.
     */
    public static function noDefault(): static
    {
        return static::make('No default provider has been set.');
    }

    /**
     * Model not found.
     */
    public static function modelNotFound(string $providerId, string $modelId): static
    {
        return static::make("Model [{$modelId}] not found for provider [{$providerId}].")
            ->withContext([
                'provider_id' => $providerId,
                'model_id' => $modelId,
            ]);
    }

    /**
     * Feature not supported.
     */
    public static function featureNotSupported(string $providerId, string $feature): static
    {
        return static::make("Provider [{$providerId}] does not support [{$feature}].")
            ->withContext([
                'provider_id' => $providerId,
                'feature' => $feature,
            ]);
    }

    /**
     * API error.
     */
    public static function apiError(
        string $providerId,
        string $message,
        int $statusCode = 0,
        ?Throwable $previous = null
    ): static {
        return static::make("Provider [{$providerId}] API error: {$message}", $statusCode, $previous)
            ->withContext([
                'provider_id' => $providerId,
                'status_code' => $statusCode,
            ]);
    }

    /**
     * Rate limited.
     */
    public static function rateLimited(string $providerId, ?int $retryAfter = null): static
    {
        $message = "Provider [{$providerId}] rate limited.";
        if ($retryAfter !== null) {
            $message .= " Retry after {$retryAfter} seconds.";
        }

        return static::make($message)
            ->withContext([
                'provider_id' => $providerId,
                'retry_after' => $retryAfter,
            ]);
    }

    /**
     * Authentication failed.
     */
    public static function authenticationFailed(string $providerId): static
    {
        return static::make("Provider [{$providerId}] authentication failed. Check your credentials.")
            ->withContext(['provider_id' => $providerId]);
    }

    /**
     * Invalid configuration.
     */
    public static function invalidConfiguration(string $providerId, string $message): static
    {
        return static::make("Provider [{$providerId}] configuration error: {$message}")
            ->withContext(['provider_id' => $providerId]);
    }
}
