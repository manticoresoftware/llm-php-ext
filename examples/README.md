# LLM PHP Extension - Examples

This directory contains example scripts demonstrating the LLM PHP extension features.

## Quick Start

Run the comprehensive demo:

```bash
make example
```

Or manually:

```bash
php -d 'extension=target/debug/libllm.dylib' examples/quick_demo.php
```

## Examples Overview

### 🚀 quick_demo.php (Recommended Start)

Comprehensive demo showing all major features:
- ✅ Basic completion
- ✅ Multi-turn conversations
- ✅ Tool/function definitions
- ✅ Configuration options
- ✅ Token usage tracking

**Run**: `make example`

### 📝 basic_completion.php

Simple single-turn completion example.

```php
$llm = new LLM('openai:gpt-4o-mini');
$messages = new MessageCollection();
$messages->addUser('Hello!');
$response = $llm->complete($messages);
```

### 💬 multi_turn.php

Multi-turn conversation with context preservation.

```php
$conversation = new MessageCollection();
$conversation->addUser('First question');
$response1 = $llm->complete($conversation);
$conversation->addAssistant($response1->getContent());
$conversation->addUser('Follow-up question');
$response2 = $llm->complete($conversation);
```

### 🔧 tool_calling.php

Function calling with tool definitions.

```php
$params = ['type' => 'object', 'properties' => [...]];
$tool = new Tool('function_name', 'Description', $params);
$toolBuilder = $llm->withTools([$tool]);
$response = $toolBuilder->complete($messages);
```

### 📋 structured_output.php

JSON schema-based structured output.

```php
$schema = json_encode(['type' => 'object', ...]);
$builder = $llm->structured($schema);
$response = $builder->complete($messages);
$data = $response->getStructured();
```

### 🎯 fluent_interface.php

Configuration and setup examples.

```php
$llm = new LLM('openai:gpt-4o-mini');
$llm->setTemperature(0.8);
$llm->setMaxTokens(500);
$llm->setTopP(0.9);
```

### 🎬 demo.php

Full-featured demo with simulated tool execution and multi-turn conversation.

## Running Examples

### With Extension Loaded

```bash
# macOS
php -d 'extension=target/debug/libllm.dylib' examples/quick_demo.php

# Linux
php -d 'extension=target/debug/libllm.so' examples/quick_demo.php
```

### Environment Variables

Set your API key:

```bash
export OPENAI_API_KEY="sk-..."
export ANTHROPIC_API_KEY="sk-ant-..."
```

## Key Concepts

### MessageCollection

Manages conversation messages:

```php
$messages = new MessageCollection();
$messages->addSystem('You are helpful');
$messages->addUser('Hello');
$messages->addAssistant('Hi there!');
$messages->addToolResult('call_id', 'result');

echo $messages->count();  // 4
$msg = $messages->get(0); // Get first message
$all = $messages->all();  // Get all messages
```

### Tool Definition

Define callable functions:

```php
$params = [
    'type' => 'object',
    'properties' => [
        'location' => ['type' => 'string'],
        'unit' => ['type' => 'string', 'enum' => ['celsius', 'fahrenheit']]
    ],
    'required' => ['location']
];

$tool = new Tool('get_weather', 'Get weather info', $params);
```

### Configuration

Two ways to configure:

```php
// Method 1: Individual setters
$llm->setTemperature(0.7);
$llm->setMaxTokens(1000);

// Method 2: Batch options
$llm->withOptions([
    'temperature' => 0.7,
    'max_tokens' => 1000
]);
```

### Response Handling

```php
$response = $llm->complete($messages);

// Get content
$content = $response->getContent();

// Get usage
$usage = $response->getUsage();
echo $usage->getTotalTokens();

// Get metadata
$model = $response->getModel();
$reason = $response->getFinishReason();

// Serialize
$array = $response->toArray();
$json = $response->toJson();
```

## Supported Models

### OpenAI
- `openai:gpt-4o`
- `openai:gpt-4o-mini`
- `openai:gpt-4-turbo`
- `openai:gpt-3.5-turbo`

### Anthropic
- `anthropic:claude-3-5-sonnet-20241022`
- `anthropic:claude-3-opus-20240229`
- `anthropic:claude-3-sonnet-20240229`
- `anthropic:claude-3-haiku-20240307`

### Google
- `google:gemini-2.0-flash-exp`
- `google:gemini-1.5-pro`
- `google:gemini-1.5-flash`

## Common Patterns

### Error Handling

```php
try {
    $response = $llm->complete($messages);
    echo $response->getContent();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Streaming (Not Yet Supported)

Streaming responses are not yet implemented in this version.

### Tool Execution Loop

```php
$response = $toolBuilder->complete($messages);

if ($response->hasToolCalls()) {
    foreach ($response->getToolCalls() as $call) {
        $result = executeFunction($call->getName(), $call->getArguments());
        $messages->addToolResult($call->getId(), $result);
    }
    
    // Get final response
    $finalResponse = $toolBuilder->complete($messages);
}
```

## Tips

1. **API Keys**: Set environment variables before running
2. **Token Limits**: Monitor usage with `$response->getUsage()`
3. **Temperature**: Lower (0.0-0.3) for factual, higher (0.7-1.0) for creative
4. **Context**: Use MessageCollection to maintain conversation history
5. **Tools**: Define clear, specific function descriptions

## Troubleshooting

### Extension Not Loaded

```
❌ LLM extension not loaded
```

**Solution**: Load the extension with `-d` flag:
```bash
php -d 'extension=target/debug/libllm.dylib' script.php
```

### API Key Missing

```
⚠️  WARNING: OPENAI_API_KEY not set
```

**Solution**: Export your API key:
```bash
export OPENAI_API_KEY="sk-..."
```

### Tool Parameters Error

```
Error: Tool::__construct(): Argument #3 ($parameters) could not be passed by reference
```

**Solution**: Use a variable:
```php
$params = ['type' => 'object'];
$tool = new Tool('name', 'desc', $params);
```

## More Information

- **Documentation**: See `TESTING.md` for API details
- **Tests**: See `tests/` directory for more examples
- **Issues**: Check `TESTING_QUICK.md` for known limitations

## License

Same as the main project.
