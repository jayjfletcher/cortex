<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Subscribers;

use JayI\Cortex\Events\CortexEvent;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class EventLoggingSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen('JayI\Cortex\Events\*', [$this, 'handleEvent']);
    }

    public function handleEvent(string $eventName, array $data): void
    {
        if (! Config::get('cortex.events.logging.enabled', false)) {
            return;
        }

        $event = $data[0] ?? null;

        if (! $event instanceof CortexEvent) {
            return;
        }

        $allowedEvents = Config::get('cortex.events.logging.events', []);

        if (! empty($allowedEvents) && ! in_array(get_class($event), $allowedEvents, true)) {
            return;
        }

        $channel = Config::get('cortex.events.logging.channel', 'cortex');
        $level = Config::get('cortex.events.logging.level', 'debug');

        $context = [
            'timestamp' => $event->timestamp,
            'tenant_id' => $event->tenantId,
            'correlation_id' => $event->correlationId,
            'metadata' => $event->metadata,
        ];

        // Add event-specific context
        $context = array_merge($context, $this->extractEventContext($event));

        Log::channel($channel)->log($level, $eventName, $context);
    }

    protected function extractEventContext(CortexEvent $event): array
    {
        $context = [];

        // Extract public readonly properties from the event
        $reflection = new \ReflectionClass($event);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            // Skip base class properties
            if (in_array($name, ['timestamp', 'tenantId', 'correlationId', 'metadata'], true)) {
                continue;
            }

            $value = $property->getValue($event);

            // Convert objects to identifiable strings for logging
            if (is_object($value)) {
                if ($value instanceof \Throwable) {
                    $context[$name] = [
                        'class' => get_class($value),
                        'message' => $value->getMessage(),
                        'code' => $value->getCode(),
                    ];
                } elseif (method_exists($value, 'id')) {
                    $context[$name] = $value->id();
                } elseif (method_exists($value, 'toArray')) {
                    $context[$name] = $value->toArray();
                } else {
                    $context[$name] = get_class($value);
                }
            } elseif (is_array($value)) {
                $context[$name] = $value;
            } else {
                $context[$name] = $value;
            }
        }

        return $context;
    }
}
