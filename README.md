# LLM PHP Extension

High-level PHP extension for interacting with Large Language Models using the octolib crate. Provides structured output and tool calling support with a clean, object-oriented API.

## Features

- ✅ **Multi-provider support**: OpenAI, Anthropic, OpenRouter, Google Vertex AI, Amazon Bedrock, Cloudflare Workers AI, DeepSeek, Z.ai
- ✅ **Structured output**: JSON and JSON Schema validation
- ✅ **Tool calling**: Function definitions with auto-execution
- ✅ **Fluent interface**: Chainable methods for elegant code
- ✅ **Builder pattern**: Separate builders for complex operations
- ✅ **Type safety**: Strong typing throughout
- ✅ **Static compilation**: Can be embedded in PHP binary
- ✅ **Comprehensive error handling**: Custom exception hierarchy
- ✅ **IDE support**: Full PHPDoc stubs

## Requirements

- PHP 8.1 or later
- Rust 1.70 or later
- Clang 5.0 or later
- cargo-php (install via `cargo install cargo-php`)

## Installation

### Via cargo-php (Recommended)

```bash
cargo install cargo-php --locked
cd llm-php-ext
cargo php install
```

### From Source

```bash
# Build extension
cargo build --release

# Install to PHP
cargo php install

# Generate IDE stubs
cargo php stubs --stdout > php/llm.php
```

### Cross-Platform Builds

```bash
# Linux
make build-linux-release

# macOS
make build-macos-release

# Windows
make build-windows-release
```

## Quick Start

### Basic Completion

```php
<?php
$llm = new LLM('openai:gpt-4o');

$messages = MessageCollection::fromArray([
    Message::user('What is PHP?')
]);

$response = $llm->complete($messages);
echo $response->getContent();
echo "Tokens used: " . $response->getUsage()->getTotalTokens();
```

### Structured Output

```php
<?php
$schema = json_encode([
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'number'],
        'skills' => ['type' => 'array', 'items' => ['type' => 'string']]
    ]
]);

$llm = new LLM('openai:gpt-4o');

$response = $llm->structured($schema)
    ->complete([Message::user('Tell me about a software engineer')]);

print_r($response->getStructured());
```

### Tool Calling

```php
<?php
$weatherTool = new Tool(
    'get_weather',
    'Get current weather for a location',
    [
        'type' => 'object',
        'properties' => [
            'location' => ['type' => 'string']
        ]
    ]
);

$llm = new LLM('openai:gpt-4o');

$response = $llm->withTools([$weatherTool])
    ->complete([Message::user("What's the weather in Tokyo?")]);

if ($response->hasToolCalls()) {
    foreach ($response->getToolCalls() as $call) {
        $result = getWeather($call->getArguments()['location']);
        // Continue conversation with tool result
    }
}
```

### Fluent Interface

```php
<?php
$response = (new LLM('openai:gpt-4o'))
    ->setTemperature(0.8)
    ->setMaxTokens(500)
    ->setTopP(0.9)
    ->complete([
        Message::user('Write a haiku about Rust')
    ]);

echo $response->getContent();
```

## API Reference

### LLM Class

Main class for interacting with language models.

#### Constructor

```php
__construct(string $model, array $options = [])
```

- `$model`: Model identifier (e.g., `'openai:gpt-4o'`, `'anthropic:claude-3-opus'`)
- `$options`: Configuration options
  - `api_key`: API key (optional, uses environment variable if not provided)
  - `base_url`: Custom base URL (optional)
  - `timeout`: Request timeout in seconds (default: 30)

#### Methods

```php
complete(array|MessageCollection $messages): Response
structured(?string $schema = null): StructuredBuilder
withTools(array $tools = []): ToolBuilder
withOptions(array $options): self
setTemperature(float $temperature): self
setMaxTokens(int $maxTokens): self
setTopP(float $topP): self
setFrequencyPenalty(float $penalty): self
setPresencePenalty(float $penalty): self
```

### Response Classes

#### Response

```php
$content = $response->getContent();
$usage = $response->getUsage();
$model = $response->getModel();
$finishReason = $response->getFinishReason();
$array = $response->toArray();
$json = $response->toJson();
```

#### StructuredResponse

```php
$content = $response->getContent();
$structured = $response->getStructured(); // Parsed JSON
$usage = $response->getUsage();
```

#### ToolResponse

```php
$content = $response->getContent();
$toolCalls = $response->getToolCalls();
$hasTools = $response->hasToolCalls();
```

### Usage Class

```php
$promptTokens = $usage->getPromptTokens();
$completionTokens = $usage->getCompletionTokens();
$totalTokens = $usage->getTotalTokens();
```

### Message Classes

#### Message

```php
$userMsg = Message::user('Hello');
$assistantMsg = Message::assistant('Hi there!');
$systemMsg = Message::system('You are helpful.');
$toolMsg = Message::tool('call_123', 'Result');
```

#### MessageCollection

```php
$messages = new MessageCollection();
$messages->addUser('Hello')
         ->addAssistant('Hi!')
         ->addSystem('Be helpful');

// Or from array
$messages = MessageCollection::fromArray([
    Message::user('Hello'),
    Message::assistant('Hi!')
]);
```

### Tool Classes

#### Tool

```php
$tool = new Tool(
    'function_name',
    'Function description',
    [
        'type' => 'object',
        'properties' => [
            'param' => ['type' => 'string']
        ]
    ]
);
```

#### ToolCall

```php
$id = $call->getId();
$name = $call->getName();
$args = $call->getArguments();
```

## Supported Providers

- **OpenAI**: `openai:gpt-4o`, `openai:gpt-4-turbo`, etc.
- **Anthropic**: `anthropic:claude-3-opus`, `anthropic:claude-3-sonnet`, etc.
- **OpenRouter**: `openrouter:model-name`
- **Google Vertex AI**: `vertex:model-name`
- **Amazon Bedrock**: `bedrock:model-name`
- **Cloudflare Workers AI**: `workers-ai:model-name`
- **DeepSeek**: `deepseek:deepseek-chat`
- **Z.ai**: `zai:model-name`

## Configuration

### API Keys

Set via environment variables or constructor options:

```bash
export OPENAI_API_KEY="sk-..."
export ANTHROPIC_API_KEY="sk-ant-..."
```

Or in code:

```php
$llm = new LLM('openai:gpt-4o', [
    'api_key' => 'sk-...'
]);
```

### Model Parameters

```php
$llm->setTemperature(0.7)      // 0.0-2.0, default 0.7
     ->setMaxTokens(1000)        // Maximum tokens, default 1000
     ->setTopP(0.9)            // 0.0-1.0, default 1.0
     ->setFrequencyPenalty(0.0)  // -2.0-2.0, default 0.0
     ->setPresencePenalty(0.0);  // -2.0-2.0, default 0.0
```

## Error Handling

```php
try {
    $response = $llm->complete($messages);
} catch (LLMConnectionException $e) {
    // Network or API errors
    echo "Connection error: " . $e->getMessage();
} catch (LLMValidationException $e) {
    // Invalid parameters
    echo "Validation error: " . $e->getMessage();
} catch (LLMStructuredOutputException $e) {
    // Structured output errors
    echo "Structured output error: " . $e->getMessage();
} catch (LLMToolCallException $e) {
    // Tool calling errors
    echo "Tool call error: " . $e->getMessage();
} catch (LLMException $e) {
    // Generic errors
    echo "LLM error: " . $e->getMessage();
}
```

## Testing

```bash
# Run all tests
make test

# Or directly
php tests/run_tests.php
```

## Building from Source

### Development Setup

```bash
# Clone repository
git clone https://github.com/manticoresearch/llm-php-ext.git
cd llm-php-ext

# Install dependencies
cargo build

# Generate stubs
cargo php stubs --stdout > php/llm.php
```

### Static Compilation

```bash
# Build for static linking
make static

# This creates a static library that can be embedded in PHP
```

### Cross-Platform Builds

```bash
# Linux
cargo build --release --target x86_64-unknown-linux-gnu

# macOS
cargo build --release --target aarch64-apple-darwin

# Windows
cargo build --release --target x86_64-pc-windows-msvc
```

## Troubleshooting

### Build Errors

**Error**: `Cannot turn unknown calling convention to tokens: 20`

**Solution**: This is a bindgen issue with ext-php-rs. Try:
```bash
export LIBCLANG_PATH=$(xcrun --show-sdk-path)/usr/lib
cargo clean
cargo build
```

**Error**: `Library not loaded: @rpath/libclang.dylib`

**Solution**: Install Xcode command line tools:
```bash
xcode-select --install
```

### Runtime Errors

**Error**: `Authentication failed`

**Solution**: Set API key via environment variable or constructor:
```bash
export OPENAI_API_KEY="sk-..."
```

**Error**: `Model not supported`

**Solution**: Check model identifier format: `provider:model`

## Examples

See the `examples/` directory for more examples:
- `basic_completion.php` - Simple completion
- `structured_output.php` - JSON schema validation
- `tool_calling.php` - Function calling
- `fluent_interface.php` - Fluent API usage
- `multi_turn.php` - Multi-turn conversations

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## License

Apache-2.0

## Support

- GitHub Issues: https://github.com/manticoresearch/llm-php-ext/issues
- Documentation: https://github.com/manticoresearch/llm-php-ext
- octolib: https://crates.io/crates/octolib
