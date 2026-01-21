<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Broadcasting;

use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\StreamedResponse;

interface BroadcasterContract
{
    /**
     * Broadcast a stream to a channel and return the final response.
     */
    public function broadcast(string $channel, StreamedResponse $stream): ChatResponse;
}
