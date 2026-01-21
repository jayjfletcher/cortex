<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Concerns;

use Illuminate\Support\Facades\Config;
use JayI\Cortex\Events\CortexEvent;

trait DispatchesCortexEvents
{
    protected function dispatchCortexEvent(CortexEvent $event): void
    {
        if (! Config::get('cortex.events.enabled', true)) {
            return;
        }

        $disabledEvents = Config::get('cortex.events.disabled', []);

        if (in_array(get_class($event), $disabledEvents, true)) {
            return;
        }

        event($event);
    }
}
