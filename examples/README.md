# Examples

Runnable scripts for the LLM PHP extension. Build first (`make build`), then run
any script with the extension loaded.

```bash
make example                                                    # runs quick_demo.php
php -d 'extension=target/debug/libllm.dylib' examples/demo.php  # macOS
php -d 'extension=target/debug/libllm.so'    examples/demo.php  # Linux
```

Set the key for whichever provider the script uses:

```bash
export OPENAI_API_KEY="sk-..."
export ANTHROPIC_API_KEY="sk-ant-..."
```

## What's here

| Script | Shows |
|---|---|
| `quick_demo.php` | Everything at a glance: completion, multi-turn, tools, options, token usage. Start here. |
| `basic_completion.php` | One-shot completion. |
| `multi_turn.php` | Carrying conversation history in a `MessageCollection`. |
| `tool_calling.php` | Tool definitions and reading back tool calls. |
| `structured_output.php` | JSON Schema output. |
| `fluent_interface.php` | Chained configuration setters. |
| `demo.php` | Full demo with simulated tool execution over several turns. |

## Picking a model string

Model strings are always `provider:model` — the prefix is required. The full
provider table, including which prefixes use the OpenAI **Responses** API versus
the older **Chat Completions** API, is in the [main README](../README.md#choosing-a-provider-and-an-endpoint).

The short version:

```php
new LLM('openai:gpt-5.4-mini');        // OpenAI, Responses API
new LLM('anthropic:claude-opus-4-5');  // Anthropic Messages API
new LLM('openrouter:openai/gpt-5.4');  // OpenRouter
new LLM('google-studio:gemini-3-pro'); // Gemini API — note: 'google:' is not a prefix
new LLM('local:llama3.2');             // Ollama on localhost (default LOCAL_API_URL)
```

For **any OpenAI-compatible `/v1/chat/completions` endpoint** — vLLM, LM Studio,
LiteLLM, a self-hosted gateway — use `local:` and give the complete URL:

```php
$llm = new LLM('local:qwen3-coder', [
    'base_url' => 'https://inference.internal/v1/chat/completions',
    'api_key'  => 'sk-...',
]);
```

`base_url` is written verbatim into `LOCAL_API_URL`; it is a full endpoint URL,
not a prefix. Pointing `openai:` at a chat/completions server does not work —
that prefix speaks the Responses API.

## Core objects

### MessageCollection

```php
$messages = new MessageCollection();
$messages->addSystem('You are helpful');
$messages->addUser('Hello');
$messages->addAssistant('Hi there!');
$messages->addToolResult('call_id', 'result');

echo $messages->count();   // 4
$msg = $messages->get(0);
$all = $messages->all();
```

`new MessageCollection([...])` and `MessageCollection::fromArray([...])` expect
message **arrays** (`['role' => 'user', 'content' => '...']`), not `Message`
objects. To start from objects, chain `add()` or pass the array of objects
directly to `complete()`.

### Tool

```php
$params = [
    'type' => 'object',
    'properties' => [
        'location' => ['type' => 'string'],
        'unit' => ['type' => 'string', 'enum' => ['celsius', 'fahrenheit']],
    ],
    'required' => ['location'],
];

$tool = new Tool('get_weather', 'Get weather info', $params);
```

`$params` must be a variable — the constructor takes it by reference.

### Configuration

```php
$llm->setTemperature(0.7)->setMaxTokens(1000)->setTopP(0.9);

$llm->withOptions(['temperature' => 0.7, 'max_tokens' => 1000]);
```

`setFrequencyPenalty()` and `setPresencePenalty()` are accepted but never sent to
the provider.

### Response

```php
$response = $llm->complete($messages);

$response->getContent();
$response->getModel();
$response->getFinishReason();
$response->toArray();
$response->toJson();

$usage = $response->getUsage();
$usage->getPromptTokens();
$usage->getOutputTokens();
$usage->getTotalTokens();
```

## Tool execution loop

Tools are never executed for you — `setAutoExecute()` is a no-op. Run the
function yourself and feed the result back, keeping the assistant turn that
requested it:

```php
$response = $toolBuilder->complete($messages);

if ($response->hasToolCalls()) {
    $messages->add(Message::fromResponse($response));   // preserves tool_calls

    foreach ($response->getToolCalls() as $call) {
        $result = executeFunction($call->getName(), $call->getArguments());
        $messages->addToolResult($call->getId(), $result);
    }

    $final = $toolBuilder->complete($messages);
}
```

Dropping the `Message::fromResponse()` line makes most providers reject the
follow-up request.

## Structured output

```php
$schema = json_encode(['type' => 'object', 'properties' => [...]]);
$response = $llm->structured($schema)->complete($messages);

$data = json_decode($response->getContent(), true);
```

`getStructured()` currently returns `null` — it is stubbed pending a Zval cloning
fix in ext-php-rs. Decode `getContent()`.

## Not supported

Streaming, reasoning/thinking blocks, prompt caching, cost reporting, vision and
other multimodal input, and `tool_choice` are not exposed by this extension.

## Troubleshooting

**`LLM extension not loaded`** — pass `-d 'extension=target/debug/libllm.dylib'`
(macOS) or `.so` (Linux), or run `make install`.

**`OPENAI_API_KEY not set`** — export the key, or pass `['api_key' => '...']` to
the constructor.

**`Tool::__construct(): Argument #3 ($parameters) could not be passed by reference`** —
assign the array to a variable first.

**`Invalid model format`** — you left off the `provider:` prefix.

**`Unsupported provider`** — check the prefix against the
[provider table](../README.md#provider-reference). There is no `google:`,
`vertex:`, `bedrock:` or `workers-ai:`.

## More

- [Main README](../README.md) — full API reference and provider configuration
- [INSTRUCTIONS.md](../INSTRUCTIONS.md) — developer onboarding
- [`tests/`](../tests/) — more usage patterns
