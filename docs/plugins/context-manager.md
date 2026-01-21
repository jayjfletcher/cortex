# Context Manager Plugin

The Context Manager plugin handles context window management for LLM conversations, including automatic truncation and intelligent message pruning.

## Installation

The Context Manager plugin has no dependencies:

```php
use JayI\Cortex\Plugins\ContextManager\ContextManagerPlugin;

$pluginManager->register(new ContextManagerPlugin($container, [
    'strategy' => 'truncate-oldest',
    'auto_reduce_threshold' => 0.9,
]));
```

## Quick Start

```php
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextManagerContract;
use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;

$manager = app(ContextManagerContract::class);

// Create a context window
$window = $manager->create(
    maxTokens: 128000, // Claude 3.5 Sonnet limit
    systemPrompt: 'You are a helpful assistant.',
);

// Add messages
$window = $manager->addMessage($window, ContextMessage::user('Hello!'));
$window = $manager->addMessage($window, ContextMessage::assistant('Hi there!'));

// Context is automatically managed when approaching capacity
```

## Context Window

### Creating Windows

```php
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

// Via manager
$window = $manager->create(maxTokens: 128000, systemPrompt: 'System prompt');

// Direct creation
$window = ContextWindow::create(
    maxTokens: 128000,
    systemPrompt: 'You are a helpful assistant.',
);
```

### Window Properties

```php
// Check capacity
$available = $window->availableTokens(); // Tokens left
$utilization = $window->utilization();   // Percentage used
$nearCapacity = $window->isNearCapacity(90.0); // Above threshold?

// Message info
$count = $window->messageCount();
$messages = $window->messages;
$total = $window->totalTokens;
```

## Messages

### Creating Messages

```php
use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;

// Simple messages
$userMsg = ContextMessage::user('Hello world');
$assistantMsg = ContextMessage::assistant('Hi there!');

// With importance scoring (0.0-1.0)
$important = ContextMessage::user('Critical info', importance: 0.9);
$routine = ContextMessage::user('Just chatting', importance: 0.3);

// Full control
$message = ContextMessage::create(
    role: 'user',
    content: 'Message content',
    tokens: 50,          // Optional: override token estimate
    importance: 0.7,
    pinned: false,
    metadata: ['source' => 'api'],
);

// Pin important messages (never auto-removed)
$pinned = $message->pin();

// Adjust importance
$adjusted = $message->withImportance(0.9);
```

## Reduction Strategies

### TruncateOldestStrategy

Removes oldest messages first, preserving pinned messages:

```php
use JayI\Cortex\Plugins\ContextManager\Strategies\TruncateOldestStrategy;

$strategy = new TruncateOldestStrategy(
    preservePinned: true,   // Keep pinned messages
    keepMinMessages: 2,     // Always keep at least N messages
);

$reduced = $strategy->reduce($window, targetTokens: 50000);
```

### ImportanceStrategy

Prioritizes messages by importance score and recency:

```php
use JayI\Cortex\Plugins\ContextManager\Strategies\ImportanceStrategy;

$strategy = new ImportanceStrategy(
    recencyWeight: 0.3,     // Weight for recency vs importance
    keepMinMessages: 2,
);

// Messages with higher importance scores are kept
$reduced = $strategy->reduce($window, targetTokens: 50000);
```

### SlidingWindowStrategy

Maintains a fixed number of recent messages:

```php
use JayI\Cortex\Plugins\ContextManager\Strategies\SlidingWindowStrategy;

$strategy = new SlidingWindowStrategy(
    maxMessages: 20,        // Maximum messages to keep
    preservePinned: true,
);

$reduced = $strategy->reduce($window, targetTokens: 50000);
```

## Auto-Reduction

The manager automatically reduces context when approaching capacity:

```php
$manager->setAutoReduceThreshold(0.9); // Trigger at 90% capacity

// When adding messages, if utilization exceeds threshold,
// the manager automatically applies the configured strategy
$window = $manager->addMessage($window, $message);
```

## Converting to API Format

```php
// Convert window to API message format
$apiMessages = $manager->toApiFormat($window);
// [
//     ['role' => 'system', 'content' => 'System prompt'],
//     ['role' => 'user', 'content' => 'Hello'],
//     ['role' => 'assistant', 'content' => 'Hi!'],
// ]
```

## Response Budget

Calculate available tokens for LLM response:

```php
// Reserve tokens for response
$budget = $manager->getResponseTokenBudget($window, reserveTokens: 4000);
// Returns available tokens minus reserved amount
```

## Integration Example

```php
class ConversationService
{
    public function __construct(
        private ContextManagerContract $contextManager,
        private LlmProviderContract $llm,
    ) {}

    public function chat(string $sessionId, string $userMessage): string
    {
        // Load or create context
        $window = $this->loadContext($sessionId)
            ?? $this->contextManager->create(128000, 'You are helpful.');

        // Add user message
        $window = $this->contextManager->addMessage(
            $window,
            ContextMessage::user($userMessage),
        );

        // Calculate response budget
        $maxTokens = $this->contextManager->getResponseTokenBudget(
            $window,
            reserveTokens: 1000,
        );

        // Call LLM
        $response = $this->llm->chat([
            'messages' => $this->contextManager->toApiFormat($window),
            'max_tokens' => min($maxTokens, 4096),
        ]);

        // Add assistant response
        $window = $this->contextManager->addMessage(
            $window,
            ContextMessage::assistant($response->content),
        );

        // Save context
        $this->saveContext($sessionId, $window);

        return $response->content;
    }
}
```

## Configuration

```php
$config = [
    // Strategy: 'truncate-oldest', 'importance', or 'sliding-window'
    'strategy' => 'truncate-oldest',

    // Auto-reduce threshold (0.5-1.0)
    'auto_reduce_threshold' => 0.9,

    // Always preserve pinned messages
    'preserve_pinned' => true,

    // Minimum messages to keep
    'keep_min_messages' => 2,

    // For importance strategy
    'recency_weight' => 0.3,

    // For sliding window strategy
    'max_messages' => 20,
];
```

## Best Practices

1. **Pin critical messages**: System prompts, important context, key decisions.

2. **Set appropriate importance**: Higher for key info, lower for casual chat.

3. **Choose strategy wisely**:
   - `truncate-oldest`: Simple conversations
   - `importance`: Complex multi-turn with varying importance
   - `sliding-window`: Fixed-size recent context

4. **Reserve response tokens**: Always leave room for LLM response.

5. **Persist context between requests**: Save and restore context windows.

## API Reference

### ContextManagerContract

| Method | Description |
|--------|-------------|
| `create(int $maxTokens, ?string $systemPrompt)` | Create new window |
| `addMessage(ContextWindow $window, ContextMessage $message)` | Add message |
| `addMessages(ContextWindow $window, array $messages)` | Add multiple messages |
| `fit(ContextWindow $window, ?int $targetTokens)` | Reduce to fit |
| `getResponseTokenBudget(ContextWindow $window, int $reserve)` | Get response budget |
| `setStrategy(ContextStrategyContract $strategy)` | Set reduction strategy |
| `getStrategy()` | Get current strategy |
| `toApiFormat(ContextWindow $window)` | Convert to API format |

### ContextWindow

| Property/Method | Description |
|-----------------|-------------|
| `messages` | Array of ContextMessage |
| `totalTokens` | Current token count |
| `maxTokens` | Maximum token limit |
| `systemPrompt` | System prompt text |
| `availableTokens()` | Remaining token capacity |
| `utilization()` | Usage percentage |
| `isNearCapacity(float $threshold)` | Check if near limit |
| `addMessage(ContextMessage $msg)` | Add message (returns new window) |
| `withMessages(array $messages)` | Replace messages |

### ContextMessage

| Property/Method | Description |
|-----------------|-------------|
| `role` | Message role (user/assistant/system) |
| `content` | Message content |
| `tokens` | Token count |
| `timestamp` | Creation time |
| `importance` | Importance score (0.0-1.0) |
| `pinned` | Whether message is pinned |
| `pin()` | Pin the message |
| `withImportance(float $score)` | Set importance |
