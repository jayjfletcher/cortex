# Structured Output Plugin

The Structured Output plugin enforces typed, validated responses from LLMs using JSON Schema. It automatically selects the best strategy based on provider capabilities.

## Overview

- **Plugin ID:** `structured-output`
- **Dependencies:** `schema`, `provider`, `chat`
- **Provides:** `structured-output`

## Basic Usage

### With Schema

```php
use JayI\Cortex\Plugins\StructuredOutput\Contracts\StructuredOutputContract;
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Plugins\Schema\Schema;

$handler = app(StructuredOutputContract::class);

$schema = Schema::object()
    ->property('sentiment', Schema::enum(['positive', 'negative', 'neutral']))
    ->property('confidence', Schema::number()->minimum(0)->maximum(1))
    ->property('keywords', Schema::array(Schema::string()))
    ->required('sentiment', 'confidence');

$request = (new ChatRequestBuilder())
    ->message('Analyze: "This product is amazing! Best purchase ever."')
    ->build();

$response = $handler->generate($request, $schema);

if ($response->valid) {
    $data = $response->toArray();
    // ['sentiment' => 'positive', 'confidence' => 0.95, 'keywords' => ['amazing', 'best']]
}
```

### With Data Classes

Generate schemas from Spatie Laravel Data classes:

```php
use Spatie\LaravelData\Data;
use JayI\Cortex\Plugins\Schema\Attributes\SchemaProperty;

class SentimentAnalysis extends Data
{
    public function __construct(
        #[SchemaProperty(description: 'Overall sentiment')]
        public string $sentiment,

        #[SchemaProperty(minimum: 0, maximum: 1)]
        public float $confidence,

        #[SchemaProperty(description: 'Key topics mentioned')]
        public array $keywords = [],
    ) {}
}

// Generate as Data class directly
$result = $handler->generateAs($request, SentimentAnalysis::class);
// Returns SentimentAnalysis instance
```

## Structured Response

The `StructuredResponse` object provides access to validated data:

```php
$response = $handler->generate($request, $schema);

// Check validity
$response->valid;              // bool
$response->validationErrors;   // ValidationError[]
$response->errorMessages();    // string[]

// Access data
$response->data;               // mixed - Parsed data
$response->toArray();          // array
$response->get('key');         // Get specific key
$response->get('key', 'default'); // With default

// Get raw response
$response->rawResponse;        // ChatResponse

// Throw on invalid
$response->throw();            // Throws StructuredOutputException if invalid

// Convert to Data class
$data = $response->toData(SentimentAnalysis::class);
```

### Validation Errors

```php
if (!$response->valid) {
    foreach ($response->validationErrors as $error) {
        echo "Path: {$error->path}\n";
        echo "Message: {$error->message}\n";
        echo "Value: " . json_encode($error->value) . "\n";
    }
}
```

## Generation Strategies

The handler automatically selects the best strategy based on provider capabilities:

### Native Strategy

Used when the provider supports structured output natively (e.g., response_format parameter):

```php
// Provider capabilities check
$capabilities->structuredOutput; // true

// Schema is passed directly to the API
```

### JSON Mode Strategy

Used when the provider supports JSON mode but not native schemas:

```php
// Provider capabilities check
$capabilities->jsonMode; // true

// JSON mode is enabled, schema is added to system prompt
```

### Prompt-Based Strategy

Fallback when no native support exists:

```php
// Schema instruction is added to system prompt
// Response is parsed and validated
// Automatic retry on validation failure
```

### Manual Strategy Selection

```php
// config/cortex.php
'structured-output' => [
    'strategy' => 'auto', // 'auto', 'native', 'json_mode', or 'prompt'
],
```

## Retry Behavior

For prompt-based generation, the handler can retry on validation failures:

```php
// config/cortex.php
'structured-output' => [
    'retry' => [
        'enabled' => true,
        'max_attempts' => 2,
    ],
],
```

When validation fails, the handler:
1. Adds the assistant's invalid response to the conversation
2. Adds a user message explaining the validation errors
3. Requests a corrected response
4. Repeats until valid or max attempts reached

## Complex Schemas

### Nested Objects

```php
$schema = Schema::object()
    ->property('user', Schema::object()
        ->property('name', Schema::string())
        ->property('email', Schema::string()->format('email'))
        ->required('name', 'email')
    )
    ->property('preferences', Schema::object()
        ->property('theme', Schema::enum(['light', 'dark']))
        ->property('notifications', Schema::boolean())
    )
    ->required('user');
```

### Arrays

```php
$schema = Schema::object()
    ->property('items', Schema::array(
        Schema::object()
            ->property('id', Schema::integer())
            ->property('name', Schema::string())
            ->required('id', 'name')
    )->minItems(1)->maxItems(10))
    ->required('items');
```

### Union Types

```php
$schema = Schema::object()
    ->property('result', Schema::anyOf(
        Schema::object()
            ->property('success', Schema::boolean())
            ->property('data', Schema::string()),
        Schema::object()
            ->property('error', Schema::string())
    ));
```

## Error Handling

```php
use JayI\Cortex\Exceptions\StructuredOutputException;

try {
    $response = $handler->generate($request, $schema)->throw();
} catch (StructuredOutputException $e) {
    $context = $e->context();

    // Validation errors
    if (isset($context['errors'])) {
        foreach ($context['errors'] as $error) {
            echo "{$error['path']}: {$error['message']}\n";
        }
    }

    // Parse failure
    if (isset($context['raw_content'])) {
        echo "Failed to parse: {$context['raw_content']}\n";
    }
}
```

Exception types:

```php
// Validation failed
StructuredOutputException::validationFailed($errors);

// JSON parse failed
StructuredOutputException::parseFailed($message, $rawContent);

// Max retries exceeded
StructuredOutputException::maxRetriesExceeded($attempts);

// Invalid data type for conversion
StructuredOutputException::invalidDataType($expected, $actual);
```

## With Cortex Facade

```php
use JayI\Cortex\Facades\Cortex;

$schema = Schema::object()
    ->property('answer', Schema::string())
    ->property('confidence', Schema::number())
    ->required('answer', 'confidence');

$response = Cortex::structuredOutput()->generate(
    Cortex::chat()->message('What is 2 + 2?')->build(),
    $schema
);
```

## Configuration

```php
// config/cortex.php
'structured-output' => [
    // Strategy selection: 'auto', 'native', 'json_mode', 'prompt'
    'strategy' => 'auto',

    // Retry configuration for prompt-based strategy
    'retry' => [
        'enabled' => true,
        'max_attempts' => 2,
    ],
],
```

## Complete Example

```php
use JayI\Cortex\Plugins\StructuredOutput\Contracts\StructuredOutputContract;
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Plugins\Schema\Schema;
use Spatie\LaravelData\Data;

// Define output structure with Data class
class MovieReview extends Data
{
    public function __construct(
        public string $title,
        public int $rating,
        public string $summary,
        public array $pros,
        public array $cons,
        public bool $recommended,
    ) {}
}

// Or define with Schema
$schema = Schema::object()
    ->property('title', Schema::string()->description('Movie title'))
    ->property('rating', Schema::integer()->minimum(1)->maximum(10))
    ->property('summary', Schema::string()->maxLength(500))
    ->property('pros', Schema::array(Schema::string())->maxItems(5))
    ->property('cons', Schema::array(Schema::string())->maxItems(5))
    ->property('recommended', Schema::boolean())
    ->required('title', 'rating', 'summary', 'recommended');

$handler = app(StructuredOutputContract::class);

$request = (new ChatRequestBuilder())
    ->system('You are a movie critic. Analyze movies objectively.')
    ->message('Review the movie "Inception" (2010)')
    ->build();

// Generate with schema
$response = $handler->generate($request, $schema);

if ($response->valid) {
    echo "Title: " . $response->get('title') . "\n";
    echo "Rating: " . $response->get('rating') . "/10\n";
    echo "Summary: " . $response->get('summary') . "\n";

    echo "\nPros:\n";
    foreach ($response->get('pros', []) as $pro) {
        echo "- {$pro}\n";
    }

    echo "\nCons:\n";
    foreach ($response->get('cons', []) as $con) {
        echo "- {$con}\n";
    }

    echo "\nRecommended: " . ($response->get('recommended') ? 'Yes' : 'No') . "\n";
} else {
    echo "Validation errors:\n";
    foreach ($response->errorMessages() as $error) {
        echo "- {$error}\n";
    }
}

// Or generate directly as Data class
$review = $handler->generateAs($request, MovieReview::class);
echo "Title: {$review->title}\n";
echo "Rating: {$review->rating}/10\n";
```

## Testing

Use `FakeProvider` to test structured output:

```php
use JayI\Cortex\Plugins\Provider\Providers\FakeProvider;

$fake = FakeProvider::text('{"sentiment": "positive", "confidence": 0.95}');

// Bind in container for testing
$this->app->instance(ProviderRegistryContract::class, new class($fake) {
    public function __construct(private $provider) {}
    public function get($id) { return $this->provider; }
    public function default() { return $this->provider; }
});

$response = $handler->generate($request, $schema);

expect($response->valid)->toBeTrue();
expect($response->get('sentiment'))->toBe('positive');
```
