<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use JayI\Cortex\Plugins\Chat\ChatResponse;

class ChatStreamCompleteEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $channelName,
        public ChatResponse $response,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel($this->channelName),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'response' => [
                'content' => $this->response->content(),
                'usage' => $this->response->usage->toArray(),
                'stopReason' => $this->response->stopReason->value,
                'model' => $this->response->model,
            ],
        ];
    }

    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'chat.complete';
    }
}
