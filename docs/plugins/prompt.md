# Prompt Plugin

The Prompt Plugin provides templated prompts with versioning, validation, and file-based loading.

## Overview

- **Plugin ID:** `prompt`
- **Dependencies:** None
- **Provides:** `prompts`

## Creating Prompts

### Basic Prompt

```php
use JayI\Cortex\Plugins\Prompt\Prompt;

$prompt = new Prompt(
    id: 'greeting',
    template: 'Hello, {{ $name }}! Welcome to {{ $company }}.',
    description: 'A friendly greeting prompt',
    version: '1.0.0',
    defaults: ['company' => 'Cortex'],
    requiredVariables: ['name'],
);

// Render the prompt
$rendered = $prompt->render(['name' => 'John']);
// "Hello, John! Welcome to Cortex."
```

### Creating from Array

```php
$prompt = Prompt::fromArray([
    'id' => 'test-prompt',
    'template' => 'Test {{ $var }}',
    'description' => 'Test description',
    'version' => '2.0.0',
    'defaults' => ['var' => 'default'],
    'required' => ['var'],
    'metadata' => ['author' => 'Team'],
]);
```

### Converting to Array

```php
$array = $prompt->toArray();
// [
//     'id' => 'greeting',
//     'template' => '...',
//     'description' => '...',
//     'version' => '1.0.0',
//     'defaults' => [...],
//     'required' => [...],
//     'metadata' => [...],
// ]
```

## Template Variables

Templates use Blade-style variable syntax:

```php
$prompt = new Prompt(
    id: 'email',
    template: <<<TEMPLATE
Dear {{ $recipient }},

{{ $body }}

Best regards,
{{ $sender }}
TEMPLATE,
);

$rendered = $prompt->render([
    'recipient' => 'John',
    'body' => 'Thank you for your inquiry.',
    'sender' => 'Support Team',
]);
```

### Default Values

Provide default values for optional variables:

```php
$prompt = new Prompt(
    id: 'greeting',
    template: 'Hello, {{ $name }}! Your role is {{ $role }}.',
    defaults: [
        'name' => 'Guest',
        'role' => 'user',
    ],
);

$prompt->render([]); // "Hello, Guest! Your role is user."
$prompt->render(['name' => 'John']); // "Hello, John! Your role is user."
```

### Required Variables

Enforce required variables with validation:

```php
$prompt = new Prompt(
    id: 'notification',
    template: 'Alert: {{ $message }} for user {{ $userId }}',
    requiredVariables: ['message', 'userId'],
);

// Throws PromptValidationException
$prompt->render(['message' => 'Test']);
```

## Prompt Registry

The registry manages multiple prompts with version support:

```php
use JayI\Cortex\Plugins\Prompt\PromptRegistry;

$registry = new PromptRegistry();

// Register prompts
$registry->register($prompt);

// Check existence
$registry->has('greeting'); // true

// Get prompt
$prompt = $registry->get('greeting');

// Render directly
$rendered = $registry->render('greeting', ['name' => 'Jane']);

// List all prompts
$all = $registry->all();

// List prompt IDs
$ids = $registry->ids();

// Remove prompt
$registry->remove('greeting');
```

### Version Support

Register multiple versions of the same prompt:

```php
$registry->register(new Prompt('greeting', 'Hello v1', version: '1.0.0'));
$registry->register(new Prompt('greeting', 'Hello v2', version: '2.0.0'));

$v1 = $registry->get('greeting', '1.0.0');    // Returns v1
$v2 = $registry->get('greeting', '2.0.0');    // Returns v2
$latest = $registry->get('greeting');          // Returns v2 (latest)
```

## Loading Prompts from Files

### FilePromptLoader

Load prompts from YAML or JSON files:

```php
use JayI\Cortex\Plugins\Prompt\FilePromptLoader;

$loader = new FilePromptLoader(resource_path('prompts'));

// Load single prompt
$prompt = $loader->load('greeting');

// Load all prompts from directory
$prompts = $loader->loadAll();
```

### YAML Format

```yaml
# resources/prompts/greeting.yaml
id: greeting
template: |
  Hello, {{ $name }}!

  You are a {{ $role }} assistant.
  {{ $instructions }}
description: Standard greeting prompt
version: "1.0.0"
defaults:
  role: helpful
required:
  - name
  - instructions
metadata:
  author: Team
  category: greetings
```

### JSON Format

```json
{
  "id": "greeting",
  "template": "Hello, {{ $name }}!",
  "description": "A greeting prompt",
  "version": "1.0.0",
  "defaults": {
    "name": "World"
  }
}
```

### Subdirectory Support

Organize prompts in subdirectories using dot notation:

```
resources/prompts/
├── greetings/
│   └── welcome.yaml
└── notifications/
    └── alert.yaml
```

```php
$loader->load('greetings.welcome');
$loader->load('notifications.alert');
```

## Using with CortexManager

Access prompts via the Cortex facade:

```php
// Render a prompt
$rendered = Cortex::prompt('greeting', ['name' => 'World']);

// Access the registry
$registry = Cortex::prompts();
$all = $registry->all();
```

## Exceptions

### PromptNotFoundException

Thrown when a prompt is not found:

```php
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptNotFoundException;

try {
    $registry->get('nonexistent');
} catch (PromptNotFoundException $e) {
    // Handle missing prompt
}
```

### PromptValidationException

Thrown when required variables are missing:

```php
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptValidationException;

try {
    $prompt->render([]);
} catch (PromptValidationException $e) {
    // $e->getMessage() contains missing variable names
}
```

## Configuration

```php
// config/cortex.php
'prompt' => [
    'paths' => [
        resource_path('prompts'),
    ],
],
```

## Complete Example

```php
use JayI\Cortex\Plugins\Prompt\Prompt;
use JayI\Cortex\Plugins\Prompt\PromptRegistry;
use JayI\Cortex\Plugins\Prompt\FilePromptLoader;

// Create registry
$registry = new PromptRegistry();

// Load prompts from files
$loader = new FilePromptLoader(resource_path('prompts'));
foreach ($loader->loadAll() as $prompt) {
    $registry->register($prompt);
}

// Register programmatic prompts
$registry->register(new Prompt(
    id: 'system.assistant',
    template: <<<TEMPLATE
You are {{ $name }}, a {{ $role }} assistant.

Your primary responsibilities:
{{ $responsibilities }}

Guidelines:
- Be helpful and accurate
- Stay within your area of expertise
- Ask clarifying questions when needed
TEMPLATE,
    defaults: [
        'name' => 'Claude',
        'role' => 'helpful',
    ],
    requiredVariables: ['responsibilities'],
    metadata: [
        'category' => 'system',
        'author' => 'AI Team',
    ],
));

// Use in chat request
$systemPrompt = $registry->render('system.assistant', [
    'responsibilities' => '- Answer questions\n- Provide explanations\n- Help with coding',
]);

$request = ChatRequest::make()
    ->system($systemPrompt)
    ->user('Hello!');
```
