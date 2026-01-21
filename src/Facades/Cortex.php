<?php

declare(strict_types=1);

namespace JayI\Cortex\Facades;

use Illuminate\Support\Facades\Facade;
use JayI\Cortex\CortexManager;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Chat\StreamedResponse;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;

/**
 * @method static ChatClientContract chat()
 * @method static ProviderContract provider(?string $id = null)
 * @method static ProviderRegistryContract providers()
 * @method static \JayI\Cortex\Contracts\PluginManagerContract plugins()
 * @method static ChatResponse send(ChatRequest $request)
 * @method static StreamedResponse stream(ChatRequest $request)
 * @method static ChatClientContract using(string|ProviderContract $provider)
 *
 * @see CortexManager
 */
class Cortex extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CortexManager::class;
    }
}
