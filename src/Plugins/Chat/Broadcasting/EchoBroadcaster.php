<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Broadcasting;

use Illuminate\Contracts\Events\Dispatcher;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\StreamedResponse;

class EchoBroadcaster implements BroadcasterContract
{
    public function __construct(
        protected Dispatcher $events,
    ) {}

    /**
     * Broadcast a stream to a channel and return the final response.
     */
    public function broadcast(string $channel, StreamedResponse $stream): ChatResponse
    {
        foreach ($stream as $chunk) {
            // Dispatch event for Laravel Echo
            $this->events->dispatch(new ChatStreamChunkEvent($channel, $chunk));
        }

        $response = $stream->collect();

        // Dispatch completion event
        $this->events->dispatch(new ChatStreamCompleteEvent($channel, $response));

        return $response;
    }
}
