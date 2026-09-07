# LLM PHP Extension

PHP extension for talking to LLM providers, written in Rust on top of the
[octolib](https://crates.io/crates/octolib) crate (pinned to `0.36.3`).
Provides completions, structured output and tool calling through a small
object-oriented API.

## Features

- **28 providers** through octolib: OpenAI, Anthropic, xAI, OpenRouter, Google Vertex/Studio,
  Amazon Bedrock, Cloudflare, DeepSeek, Groq, Cerebras, Moonshot, MiniMax, Z.ai, Together,
  Fireworks, NVIDIA, and any OpenAI-compatible endpoint via `local:`
- **Structured output**: JSON mode and JSON Schema
- **Tool calling**: function definitions, tool calls returned to PHP for you to execute
- **Fluent interface**: chainable setters
- **Exception hierarchy**: `LLMException` and four subclasses, all extending `\Exception`
- **IDE stubs**: `php/llm.php`

## Requirements

- PHP 8.1+ (CI builds and tests against PHP 8.4)
- Rust stable, `ext-php-rs` 0.15.3
- LLVM/Clang 17 (`ext-php-rs` uses bindgen)
- `cargo-php` — only needed for `make install` and `make stubs`

## Installation

Use `make`, not raw `cargo build`. On macOS the Makefile sets the LLVM 17
environment variables that bindgen needs; a bare `cargo build` will fail.

```bash
make build      # debug  -> target/debug/libllm.{dylib,so}
make release    # release -> target/release/libllm.{dylib,so}
make install    # release build + cargo php install
make stubs      # regenerate php/llm.php
make test       # build + run the PHP test suite
make ci         # everything CI runs, locally
```

macOS additionally needs LLVM 17 from Homebrew:

```bash
brew install llvm@17
```

Load the extension explicitly when you have not installed it into PHP:

```bash
# macOS
php -d 'extension=target/debug/libllm.dylib' script.php
# Linux
php -d 'extension=target/debug/libllm.so' script.php
```

Cross-compilation targets: `make build-linux[-release]`, `make build-macos[-release]`,
`make build-windows[-release]`, `make build-musl[-release]` (Alpine).

## Quick start

```php
<?php
$llm = new LLM('openai:gpt-5.4-mini');

$response = $llm->complete([Message::user('What is PHP?')]);

echo $response->getContent(), "\n";
echo "Tokens: ", $response->getUsage()->getTotalTokens(), "\n";
```

`complete()` accepts either a plain array of `Message` objects (or message arrays)
or a `MessageCollection`.

---

## Choosing a provider and an endpoint

This is the part that trips people up. Read it before anything else.

### Model strings

Every model string is `provider:model`. The provider prefix is **required** —
octolib rejects a bare model name.

```php
new LLM('openai:gpt-5.4');
new LLM('anthropic:claude-opus-4-5');
new LLM('local:qwen3-coder');
```

### How credentials and URLs are resolved

octolib reads everything from environment variables. The constructor's `options`
array is only a convenience that sets those variables for you:

| Option | Sets the env var | Notes |
|---|---|---|
| `api_key` | `{PROVIDER}_API_KEY` | |
| `base_url` | `{PROVIDER}_API_URL` | **Full endpoint URL, including the path.** Not a base. |

`{PROVIDER}` is the uppercased prefix from the model string (`kimi` is mapped to
`MOONSHOT`). These are the only two keys read; anything else in `options` is ignored.

```php
$llm = new LLM('openai:gpt-5.4', [
    'api_key'  => 'sk-...',
    'base_url' => 'https://my-gateway.example.com/v1/responses',
]);

// Equivalent:
putenv('OPENAI_API_KEY=sk-...');
putenv('OPENAI_API_URL=https://my-gateway.example.com/v1/responses');
$llm = new LLM('openai:gpt-5.4');
```

### Where exactly does the URL go?

`base_url` is a misleading name. It is not a base — it is the **complete URL
octolib POSTs to**, path and all. Nothing is appended to it.

```php
// ✅ correct — full path to the endpoint
'base_url' => 'https://inference.internal/v1/chat/completions'

// ❌ wrong — no path; the request is POSTed to / and the server rejects it
'base_url' => 'https://inference.internal'

// ❌ wrong — the version prefix alone; octolib does not append /chat/completions
'base_url' => 'https://inference.internal/v1'
```

Rule of thumb: take the URL you would `curl`, and paste that.

```bash
curl https://inference.internal/v1/chat/completions -d '{"model":"...","messages":[...]}'
#    └────────────── this whole thing is base_url ──────────────┘
```

The correct suffix depends on the protocol the provider speaks:

| Provider prefix | Suffix your URL must end with |
|---|---|
| `openai`, `xai` | `/v1/responses` |
| `local`, `ollama`, `openrouter`, `groq`, and the other OpenAI-compatible ones | `/v1/chat/completions` |
| `anthropic`, `minimax` | `/v1/messages` |

### Responses API vs OpenAI-compatible (Chat Completions)

There are two different OpenAI wire protocols, and picking the wrong prefix is
the usual cause of a confusing 400.

|  | **Responses API** | **OpenAI-compatible / Chat Completions** |
|---|---|---|
| Prefixes | `openai:`, `xai:` | `local:`, `ollama:`, `openrouter:`, `groq:`, `cerebras:`, … |
| Path | `/v1/responses` | `/v1/chat/completions` |
| Messages field | `input` | `messages` |
| Token limit field | `max_output_tokens` | `max_tokens` |
| Tool shape | top-level `{type, name, parameters}` | nested `{type, function:{...}}` |
| Response envelope | `output[]` | `choices[]` |

They are not interchangeable. If you point `OPENAI_API_URL` at a
`/v1/chat/completions` server, the server rejects the request or the response
fails to deserialize — and **there is no switch to make `openai:` speak Chat
Completions.**

**So: for any OpenAI-compatible endpoint, use `local:`.** Despite the name it is
not restricted to localhost — it accepts any model name and any URL, and its API
key is optional.

```php
// Self-hosted or third-party OpenAI-compatible API:
// vLLM, LM Studio, LocalAI, Jan, LiteLLM, a corporate gateway, a cloud provider
$llm = new LLM('local:qwen3-coder', [
    'base_url' => 'https://inference.internal/v1/chat/completions',
    'api_key'  => 'sk-...',   // optional — LOCAL_API_KEY may be empty
]);

// Ollama on this machine — already the default LOCAL_API_URL, no options needed
$llm = new LLM('local:llama3.2');

// LM Studio
$llm = new LLM('local:qwen3-coder', [
    'base_url' => 'http://localhost:1234/v1/chat/completions',
]);

// Real OpenAI — leave base_url alone, the default is correct
$llm = new LLM('openai:gpt-5.4', ['api_key' => 'sk-...']);

// A gateway that genuinely proxies the Responses API
$llm = new LLM('openai:gpt-5.4', [
    'base_url' => 'https://my-gateway.example.com/v1/responses',
]);
```

Choosing between them:

- The endpoint's docs say **"OpenAI-compatible"** and you `curl` it at
  `/v1/chat/completions` → use `local:`.
- You are calling **api.openai.com** itself, or a proxy that forwards the
  Responses API verbatim → use `openai:`.

### Provider reference

Default endpoint and URL-override variable for each provider prefix. Anything
under "Chat Completions" is OpenAI-compatible on the wire.

| Prefix | Protocol | URL override env | Default endpoint |
|---|---|---|---|
| `openai` | **Responses** | `OPENAI_API_URL` | `https://api.openai.com/v1/responses` |
| `xai` | **Responses** | `XAI_API_URL` | `https://api.x.ai/v1/responses` |
| `anthropic` | Anthropic Messages | `ANTHROPIC_API_URL` | `https://api.anthropic.com/v1/messages` |
| `minimax` | Anthropic Messages | `MINIMAX_API_URL` | `https://api.minimax.io/anthropic/v1/messages` |
| `local` | Chat Completions | `LOCAL_API_URL` | `http://localhost:11434/v1/chat/completions` |
| `ollama` | Chat Completions | `OLLAMA_API_URL` | `https://ollama.com/v1/chat/completions` |
| `openrouter` | Chat Completions | `OPENROUTER_API_URL` | `https://openrouter.ai/api/v1/chat/completions` |
| `groq` | Chat Completions | `GROQ_API_URL` | `https://api.groq.com/openai/v1/chat/completions` |
| `cerebras` | Chat Completions | `CEREBRAS_API_URL` | `https://api.cerebras.ai/v1/chat/completions` |
| `nvidia` | Chat Completions | `NVIDIA_API_URL` | `https://integrate.api.nvidia.com/v1/chat/completions` |
| `fireworks` | Chat Completions | `FIREWORKS_API_URL` | `https://api.fireworks.ai/inference/v1/chat/completions` |
| `featherless` | Chat Completions | `FEATHERLESS_API_URL` | `https://api.featherless.ai/v1/chat/completions` |
| `hetzner` | Chat Completions | `HETZNER_API_URL` | `https://inference.hetzner.com/api/v1/chat/completions` |
| `meta` | Chat Completions | `META_API_URL` | `https://api.meta.ai/v1/chat/completions` |
| `byteplus` | Chat Completions | `BYTEPLUS_API_URL` | `https://ark.ap-southeast.bytepluses.com/api/v3/chat/completions` |
| `zai` | Chat Completions | `ZAI_API_URL` | `https://api.z.ai/api/paas/v4/chat/completions` |
| `alibaba` | Chat Completions | `ALIBABA_API_URL` | `https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions` |
| `opencode-zen` | Chat Completions | `OPENCODE_ZEN_API_URL` | `https://opencode.ai/zen/v1/chat/completions` |
| `opencode-go` | Chat Completions | `OPENCODE_GO_API_URL` | `https://opencode.ai/zen/go/v1/chat/completions` |
| `octohub` | Chat Completions | `OCTOHUB_API_URL` | `https://hub.octomind.run` |
| `google-studio` | Google | `GOOGLE_STUDIO_API_URL` | Gemini API |
| `google-vertex` | Google | `GOOGLE_VERTEX_API_URL` | Vertex AI (templated per project/location) |
| `amazon` | Bedrock | `AWS_BEDROCK_API_URL` | Bedrock (templated per region) |
| `cloudflare` | Workers AI | `CLOUDFLARE_API_URL` | Workers AI (templated per account) |
| `deepseek` | Chat Completions | — none — | fixed |
| `moonshot` / `kimi` | Chat Completions | — none — | fixed |
| `together` | Chat Completions | — none — | `https://api.together.xyz/v1/chat/completions` |
| `cli` | local CLI subprocess | — | `cli:<backend>/<model>` |

### Known gotchas in the option-to-env mapping

The `api_key` / `base_url` options build the env var name from the provider prefix,
which does not always match what octolib reads. In these cases the options are
**silently ignored** — set the real variable with `putenv()` or in your environment:

| Model prefix | Option produces | octolib actually reads |
|---|---|---|
| `google-vertex` | `GOOGLE-VERTEX_API_KEY` / `_API_URL` | `GOOGLE_VERTEX_PROJECT_ID`, `GOOGLE_VERTEX_LOCATION`, `GOOGLE_VERTEX_API_URL`, `GOOGLE_VERTEX_CREDENTIAL_FILE` |
| `google-studio` | `GOOGLE-STUDIO_API_KEY` / `_API_URL` | `GOOGLE_STUDIO_API_KEY`, `GOOGLE_STUDIO_API_URL` |
| `opencode-zen` / `opencode-go` | `OPENCODE-ZEN_*` | `OPENCODE_API_KEY`, `OPENCODE_ZEN_API_URL` / `OPENCODE_GO_API_URL` |
| `amazon` | `AMAZON_API_KEY` / `AMAZON_API_URL` | `AWS_BEARER_TOKEN_BEDROCK`, `AWS_BEDROCK_REGION`, `AWS_BEDROCK_API_URL` |
| `cloudflare` | `CLOUDFLARE_API_KEY` / `_API_URL` | those, plus `CLOUDFLARE_ACCOUNT_ID` |
| `deepseek`, `moonshot`, `together` | `*_API_URL` | no URL override exists; only the key is used |

Two more things worth knowing:

- The constructor writes into the **process** environment. In a long-running SAPI
  (PHP-FPM, Swoole) a later `new LLM()` for the same provider overwrites the key for
  every subsequent request in that worker. Do not mix per-tenant credentials for the
  same provider in one process.
- `anthropic:` also accepts `ANTHROPIC_OAUTH_ACCESS_TOKEN`, and `openai:` accepts
  `OPENAI_OAUTH_ACCESS_TOKEN` + `OPENAI_OAUTH_ACCOUNT_ID`, in place of an API key.

---

## API reference

PHP class names are case-insensitive; the stubs declare `Llm` and the tests use
`LLM`. Both work.

### LLM

```php
__construct(string $model, ?array $options = null)

complete(array|MessageCollection $messages): Response
structured(?string $schema = null): StructuredBuilder
withTools(?array $tools = null): ToolBuilder          // array of Tool objects
withOptions(array $options): LLM
setTemperature(float $t): LLM
setMaxTokens(int $n): LLM
setTopP(float $p): LLM
setFrequencyPenalty(float $p): LLM
setPresencePenalty(float $p): LLM
```

`withOptions()` reads `temperature`, `max_tokens`, `top_p`, `frequency_penalty`,
`presence_penalty`. Defaults: temperature `0.7`, max tokens `1000`, top_p `1.0`,
penalties `0.0`. `top_k` is fixed at `50`.

`frequency_penalty` and `presence_penalty` are stored but never sent to the
provider. There is no `timeout` option.

### Response

```php
getContent(): string
getUsage(): Usage
getModel(): string
getFinishReason(): string
toArray(): array
toJson(): string
```

### Usage

```php
getPromptTokens(): int    // octolib TokenUsage.input_tokens
getOutputTokens(): int    // octolib TokenUsage.output_tokens
getTotalTokens(): int
toArray(): array
toJson(): string
```

octolib also reports reasoning tokens, cache read/write tokens, cost and request
time. Those are not surfaced by this extension.

### Message / MessageCollection

```php
Message::user(string $content): Message
Message::assistant(string $content): Message
Message::system(string $content): Message
Message::tool(string $toolCallId, string $result): Message
Message::fromResponse(ToolResponse $response): Message
Message::fromArray(array $data): Message              // needs 'role' and 'content'

$message->getRole(): string
$message->getContent(): string
$message->getToolCalls(): ?string    // JSON string, not an array
$message->getId(): ?string
$message->getToolCallId(): ?string

$c = new MessageCollection();                 // or MessageCollection::fromArray([...])
$c->add(Message $m): MessageCollection
$c->addUser(string $s): MessageCollection
$c->addAssistant(string $s): MessageCollection
$c->addSystem(string $s): MessageCollection
$c->addToolResult(string $id, string $result): MessageCollection
$c->get(int $i): ?Message
$c->all(): Message[]
$c->count(): int
```

`new MessageCollection([...])` and `MessageCollection::fromArray([...])` take
**message arrays**, not `Message` objects. To build from objects, chain `add()`,
or pass the object array straight to `complete()`.

### Tool / ToolCall

```php
new Tool(string $name, string $description, array|string $parameters)
Tool::fromArray(['name' => ..., 'description' => ..., 'parameters' => ...]): Tool
$tool->getName(): string
$tool->getDescription(): string
$tool->getParameters(): string        // JSON string

$call->getId(): string
$call->getName(): string
$call->getArguments(): array          // decoded
```

`Tool::__construct()` takes `$parameters` by reference, and
`MessageCollection::add()` takes its `Message` by reference. Passing a literal or
a function result to either raises `Only variables should be passed by reference`,
so assign first:

```php
$params = ['type' => 'object', 'properties' => [...]];
$tool = new Tool('get_weather', 'Get the weather', $params);

$msg = Message::fromResponse($response);
$messages->add($msg);
```

## Structured output

```php
$schema = json_encode([
    'type' => 'object',
    'properties' => [
        'name'   => ['type' => 'string'],
        'age'    => ['type' => 'number'],
        'skills' => ['type' => 'array', 'items' => ['type' => 'string']],
    ],
    'required' => ['name', 'age'],
]);

$response = (new LLM('openai:gpt-5.4-mini'))
    ->structured($schema)
    ->complete([Message::user('Describe a software engineer')]);

$data = json_decode($response->getContent(), true);
```

`structured()` with no schema requests plain JSON mode. With a schema it sends a
`json_schema` response format.

> **`getStructured()` currently returns `null`.** It is stubbed out pending a Zval
> cloning fix in ext-php-rs 0.15.x (`src/structured_builder.rs`). Decode
> `getContent()` instead — it holds the raw JSON text.

`StructuredBuilder::complete()` throws `LLMStructuredOutputException` when the
provider/model does not support structured output at all, when the schema is not
valid JSON, or when the provider returns no structured payload.
`withFormat()` exists on the builder but is not wired to anything.

Not every provider enforces schemas server-side — see octolib's
[provider support matrix](https://github.com/muvon/octolib#-provider-support-matrix).
Anthropic, Google Vertex, Amazon Bedrock and Cloudflare have no structured output support.

## Tool calling

There is **no auto-execution**. `setAutoExecute()` exists on `ToolBuilder` but is
never read. You run the functions yourself and feed results back.

```php
$params = [
    'type' => 'object',
    'properties' => ['location' => ['type' => 'string']],
    'required' => ['location'],
];
$tool = new Tool('get_weather', 'Get current weather for a location', $params);

$builder  = (new LLM('openai:gpt-5.4'))->withTools([$tool]);
$messages = new MessageCollection();
$messages->addUser("What's the weather in Tokyo?");

$response = $builder->complete($messages);

if ($response->hasToolCalls()) {
    // Keep the assistant turn that requested the calls.
    // Assign first — add() takes the Message by reference.
    $assistantTurn = Message::fromResponse($response);
    $messages->add($assistantTurn);

    foreach ($response->getToolCalls() as $call) {
        $result = getWeather($call->getArguments()['location']);
        $messages->addToolResult($call->getId(), $result);
    }

    $final = $builder->complete($messages);
    echo $final->getContent();
}
```

`Message::fromResponse()` is what preserves the assistant's `tool_calls` in the
history. Skipping it makes most providers reject the follow-up request.

`ToolResponse` exposes `getContent()`, `getToolCalls()`, `hasToolCalls()`,
`getUsage()`, `getModel()`, `getId()`, `toArray()`, `toJson()`.

`ToolBuilder::addTool()` takes a `Tool` object; `ToolBuilder::setTools()` takes an
array of **tool arrays** (same shape as `Tool::fromArray`). Tool choice is not exposed.

## Not exposed

octolib supports these; this extension does not surface them yet:
streaming, thinking/reasoning blocks and reasoning effort, prompt caching,
per-request cost, vision and other multimodal input, `tool_choice`, custom
timeouts and retry configuration, and embeddings/reranking (the crate is built
with `default-features = false, features = ["llm"]`).

## Error handling

All five classes extend `\Exception`.

```php
try {
    $response = $llm->complete($messages);
} catch (LLMConnectionException $e) {
    // network failure, HTTP error from the API, or timeout
} catch (LLMValidationException $e) {
    // bad model string, unsupported model, malformed message or tool schema
} catch (LLMStructuredOutputException $e) {
    // structured output unsupported, bad schema, or missing structured payload
} catch (LLMToolCallException $e) {
    // tool call errors from octolib
} catch (LLMException $e) {
    // anything else
}
```

## Testing

```bash
make test
# or
php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php
```

The suite covers construction, message building, tool definitions and the
exception classes. It does not make network calls, so no API keys are needed.

## Continuous integration

`.github/workflows/ci.yml` runs on `main` and `develop`: `cargo fmt --check`,
`cargo clippy -D warnings`, `cargo test`, then debug and release builds plus the
PHP test suite on Ubuntu (PHP 8.4, LLVM 17), macOS (ARM and Intel) and Alpine/musl,
followed by stub generation.

`make ci` runs the same checks locally. On macOS it skips `cargo test` because of
a known bindgen/LLVM 17 incompatibility.

## Troubleshooting

**`Cannot turn unknown calling convention to tokens: 20`** — bindgen cannot find
LLVM 17. Use `make build`, which sets `LIBCLANG_PATH`, `LLVM_CONFIG_PATH` and
`PATH` for you. On macOS install it first with `brew install llvm@17`.

**`Library not loaded: @rpath/libclang.dylib`** — `xcode-select --install`.

**`Invalid model format ... Must specify provider like 'provider:model'`** — the
provider prefix is mandatory. `gpt-5.4` is invalid; `openai:gpt-5.4` is not.

**`Unsupported provider: X`** — check the prefix against the provider table above.
`vertex`, `bedrock` and `workers-ai` are not prefixes; use `google-vertex`,
`amazon` and `cloudflare`.

**`Provider 'X' does not support model 'Y'`** — the provider rejected the model
name. For a third-party OpenAI-compatible endpoint, use `local:`, which accepts
any model name.

**A 400 from an OpenAI-compatible server on `openai:`** — you pointed
`OPENAI_API_URL` at a Chat Completions endpoint. Switch the prefix to `local:`.

**Extension not loaded** — pass `-d 'extension=...'` with the right file for your
platform (`libllm.dylib` on macOS, `libllm.so` on Linux), or `make install`.

## Examples

See [`examples/`](examples/) — `quick_demo.php` (run with `make example`),
`basic_completion.php`, `multi_turn.php`, `tool_calling.php`,
`structured_output.php`, `fluent_interface.php`, `demo.php`.

## Contributing

Fork, branch, change, add tests, run `make ci`, open a PR.
See [AGENTS.md](AGENTS.md) for the developer onboarding notes.

## License

Apache-2.0

## Links

- Issues: https://github.com/manticoresearch/llm-php-ext/issues
- octolib: https://crates.io/crates/octolib · https://github.com/muvon/octolib
- ext-php-rs: https://github.com/davidcole1340/ext-php-rs
