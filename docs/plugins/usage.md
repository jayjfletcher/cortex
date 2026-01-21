# Usage Plugin

The Usage plugin tracks token consumption, estimates costs, and manages usage budgets for LLM interactions.

## Installation

The Usage plugin has no dependencies:

```php
use JayI\Cortex\Plugins\Usage\UsagePlugin;

$pluginManager->register(new UsagePlugin($container, [
    'budgets' => [
        ['max_cost' => 100.0, 'period' => 'monthly'],
    ],
]));
```

## Quick Start

### Recording Usage

```php
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;

$tracker = app(UsageTrackerContract::class);

$record = UsageRecord::create(
    model: 'claude-3-5-sonnet',
    inputTokens: 1500,
    outputTokens: 500,
    cost: 0.012,
    userId: 'user-123',
);

$tracker->record($record);
```

### Checking Budgets

```php
use JayI\Cortex\Plugins\Usage\Contracts\BudgetManagerContract;

$budgetManager = app(BudgetManagerContract::class);

// Check if any budgets would be exceeded
$exceeded = $budgetManager->checkBudgetsForRequest(
    userId: 'user-123',
    model: 'claude-3-5-sonnet',
);

if (!empty($exceeded)) {
    throw new BudgetExceededException('Budget limit reached', $exceeded);
}
```

## Usage Tracking

### UsageRecord

Records individual API usage events:

```php
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;

$record = UsageRecord::create(
    model: 'claude-3-5-sonnet',
    inputTokens: 1500,
    outputTokens: 500,
    cost: 0.012,
    requestId: 'req-abc123',
    userId: 'user-123',
    sessionId: 'session-xyz',
    metadata: ['source' => 'api', 'feature' => 'chat'],
);

// Get total tokens
$total = $record->totalTokens(); // 2000
```

### UsageSummary

Aggregate usage over a time period:

```php
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;

$tracker = app(UsageTrackerContract::class);

$summary = $tracker->getSummary(
    start: new DateTimeImmutable('2024-01-01'),
    end: new DateTimeImmutable('2024-01-31'),
    userId: 'user-123',
    model: 'claude-3-5-sonnet',
);

echo "Total tokens: {$summary->totalTokens()}";
echo "Total cost: \${$summary->totalCost}";
echo "Request count: {$summary->requestCount}";
echo "Average tokens per request: {$summary->averageTokensPerRequest()}";

// Usage by model
foreach ($summary->tokensByModel as $model => $tokens) {
    echo "{$model}: {$tokens} tokens";
}
```

## Cost Estimation

### AnthropicCostEstimator

Estimate costs for Claude models:

```php
use JayI\Cortex\Plugins\Usage\Estimators\AnthropicCostEstimator;

$estimator = new AnthropicCostEstimator();

// Estimate cost
$cost = $estimator->estimate(
    model: 'claude-3-5-sonnet',
    inputTokens: 10000,
    outputTokens: 5000,
);

// Get pricing
$inputPrice = $estimator->getInputPricePerMillion('claude-3-5-sonnet'); // 3.0
$outputPrice = $estimator->getOutputPricePerMillion('claude-3-5-sonnet'); // 15.0

// Custom pricing
$estimator->setPricing('custom-model', inputPrice: 5.0, outputPrice: 20.0);
```

Supported models:
- Claude 3 Opus ($15/$75 per million tokens)
- Claude 3.5 Sonnet ($3/$15 per million tokens)
- Claude 3 Sonnet ($3/$15 per million tokens)
- Claude 3 Haiku ($0.25/$1.25 per million tokens)
- Claude 3.5 Haiku ($1/$5 per million tokens)

## Budget Management

### Creating Budgets

```php
use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;

// Cost-based budget
$costBudget = Budget::cost(
    maxCost: 100.0,
    period: BudgetPeriod::Monthly,
    userId: 'user-123',        // Optional: scope to user
    model: 'claude-3-opus',    // Optional: scope to model
    hardLimit: true,           // Block requests when exceeded
);

// Token-based budget
$tokenBudget = Budget::tokens(
    maxTokens: 1000000,
    period: BudgetPeriod::Daily,
);

// Request-based budget
$requestBudget = Budget::requests(
    maxRequests: 100,
    period: BudgetPeriod::Weekly,
);
```

### Budget Periods

```php
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;

BudgetPeriod::Daily;    // Resets daily at midnight
BudgetPeriod::Weekly;   // Resets Monday at midnight
BudgetPeriod::Monthly;  // Resets 1st of month
BudgetPeriod::Yearly;   // Resets January 1st
```

### Checking Budget Status

```php
use JayI\Cortex\Plugins\Usage\Contracts\BudgetManagerContract;

$manager = app(BudgetManagerContract::class);

// Add budget
$manager->addBudget($budget);

// Check specific budget
$status = $manager->checkBudget($budget->id);

if ($status->exceeded) {
    echo "Budget exceeded!";
} elseif ($status->isApproachingLimit(80)) {
    echo "Warning: {$status->usagePercentage}% of budget used";
}

// Get remaining allowances
echo "Remaining cost: \${$status->remainingCost}";
echo "Remaining tokens: {$status->remainingTokens}";
echo "Remaining requests: {$status->remainingRequests}";

// Check all budgets
$allStatuses = $manager->checkAllBudgets();
```

## Configuration

```php
$config = [
    // Custom pricing overrides
    'pricing' => [
        'custom-model' => ['input' => 5.0, 'output' => 20.0],
    ],

    // Default budgets
    'budgets' => [
        [
            'max_cost' => 100.0,
            'period' => 'monthly',
            'hard_limit' => true,
        ],
        [
            'max_tokens' => 1000000,
            'period' => 'daily',
            'user_id' => null,  // Global
            'model' => null,     // All models
        ],
    ],
];
```

## API Reference

### UsageTrackerContract

| Method | Description |
|--------|-------------|
| `record(UsageRecord $record)` | Record a usage event |
| `getSummary(...)` | Get usage summary for period |
| `getRecords(...)` | Get individual records |
| `getRecentRecords(int $limit)` | Get most recent records |
| `clear()` | Clear all records (testing) |

### BudgetManagerContract

| Method | Description |
|--------|-------------|
| `addBudget(Budget $budget)` | Add a budget |
| `removeBudget(string $id)` | Remove a budget |
| `getBudget(string $id)` | Get budget by ID |
| `getAllBudgets()` | Get all budgets |
| `getBudgetsForUser(?string $userId)` | Get user's budgets |
| `checkBudget(string $id)` | Check budget status |
| `checkBudgetsForRequest(...)` | Check if request exceeds budgets |
| `checkAllBudgets()` | Check all budget statuses |

### BudgetStatus

| Property | Description |
|----------|-------------|
| `budget` | The budget being checked |
| `usage` | Current usage summary |
| `exceeded` | Whether budget is exceeded |
| `usagePercentage` | Percentage of budget used |
| `remainingCost` | Remaining cost allowance |
| `remainingTokens` | Remaining token allowance |
| `remainingRequests` | Remaining request allowance |
