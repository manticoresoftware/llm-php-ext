# LLM PHP Extension — Developer Onboarding

## What is this?

A PHP extension written in Rust via [ext-php-rs](https://github.com/davidcole1340/ext-php-rs)
that wraps the [octolib](https://github.com/muvon/octolib) crate (pinned to `0.36.3`,
built with `default-features = false, features = ["llm"]` — chat only, no
embeddings/reranking/media).

The extension is a thin translation layer. All provider logic, endpoints,
authentication and wire protocols live in octolib.

---

## Project structure

```
src/
  lib.rs                — module entry point, registers every PHP class
  llm_class.rs          — LLM, Response, Usage; option→env var mapping
  message.rs            — Message, MessageCollection
  tool_builder.rs       — Tool, ToolBuilder, ToolCall, ToolResponse
  structured_builder.rs — StructuredBuilder, StructuredResponse
  error.rs              — PHP exception classes + octolib error → PHP exception mapping
  convert.rs            — Zval ↔ serde_json conversion helpers
php/
  llm.php               — generated IDE stubs (never edit by hand)
tests/
  run_tests.php         — runner; the *.php files next to it are the suites
  serialization_tests.rs — Rust-side unit tests
examples/               — runnable PHP demos
```

---

## Build & test

**Always use `make`, never raw `cargo build`.** On macOS the Makefile exports the
LLVM 17 variables bindgen needs (`LIBCLANG_PATH`, `LLVM_CONFIG_PATH`, `PATH`
pointing at `/opt/homebrew/opt/llvm@17`). Without them the build fails with an
unhelpful bindgen error.

```bash
make build       # debug build
make release     # release build
make test        # build + run PHP tests
make stubs       # regenerate php/llm.php
make ci          # full CI check locally
make example     # run examples/quick_demo.php
```

Run tests manually:

```bash
php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php
```

The PHP tests make no network calls, so they need no API keys.

`make ci` skips `cargo test` on macOS (known bindgen/LLVM 17 issue); CI runs it
on Ubuntu.

---

## How the extension talks to octolib

Every call follows the same three steps:

```rust
let (provider, model) = ProviderFactory::get_provider_for_model(&self.model)?;
let params = ChatCompletionParams::new(&messages, &model, temperature, top_p, 50, max_tokens);
let response = runtime.block_on(provider.chat_completion(params))?;
```

A `tokio` multi-thread runtime is created per `LLM` instance and shared with the
builders it spawns; every PHP-facing call is blocking.

### Configuration is environment variables

octolib reads credentials and endpoints exclusively from env vars. The
constructor's `options` array is sugar over `std::env::set_var`:

```rust
// src/llm_class.rs
let prefix = get_env_prefix(&model);       // "openai:gpt-5.4" -> "OPENAI", "kimi" -> "MOONSHOT"
std::env::set_var(format!("{prefix}_API_KEY"), api_key);
std::env::set_var(format!("{prefix}_API_URL"), base_url);
```

Consequences worth remembering:

- `base_url` is a **full endpoint URL**, not a base. It is POSTed to verbatim.
- The naive uppercasing breaks for hyphenated prefixes (`google-vertex` →
  `GOOGLE-VERTEX_API_KEY`, which octolib never reads) and for providers whose env
  vars do not follow the pattern (`amazon` → `AWS_BEARER_TOKEN_BEDROCK`). See the
  gotchas table in the [README](README.md#known-gotchas-in-the-option-to-env-mapping).
- The variable is set process-wide and persists. Two `LLM` objects for the same
  provider with different keys will clobber each other in a long-running worker.
- `std::env::set_var` is `unsafe` in edition 2024 semantics and is not thread-safe.
  Fine for the typical single-threaded PHP request; a hazard under ZTS.

### Responses API vs Chat Completions

In octolib 0.36.3 the `openai:` and `xai:` prefixes speak the OpenAI **Responses
API** (`/v1/responses`); everything else OpenAI-flavoured speaks Chat Completions
via `openai_compat.rs`. There is no flag to downgrade `openai:` to Chat
Completions. The `local:` provider is the OpenAI-compatible Chat Completions
path and accepts any model name and any URL.

Authoritative sources when this drifts:
`octolib/src/llm/factory.rs` (prefix list) and the `*_API_URL` constants in
`octolib/src/llm/providers/*.rs`.

---

## Key ext-php-rs patterns

### Registering a plain class

```rust
#[php_class]
pub struct MyClass { ... }

#[php_impl]
impl MyClass {
    pub fn __construct(...) -> Self { ... }
    pub fn my_method(&self) -> String { ... }
}
```

Method names are converted to camelCase for PHP (`get_content` → `getContent`).
Class names are PascalCased in the stubs (`LLM` → `Llm`), which is harmless —
PHP class names are case-insensitive.

### Fluent setters

Return `&mut ZendClassObject<Self>` rather than `Self` so the same object is
returned to PHP and chaining works:

```rust
pub fn set_temperature(
    self_: &mut ZendClassObject<LLM>,
    temperature: f64,
) -> &mut ZendClassObject<LLM> {
    self_.temperature = temperature as f32;
    self_
}
```

### Exception classes that extend `\Exception`

Two rules:

1. The class needs a `#[php_impl]` block with a `__construct`, or ext-php-rs
   blocks PHP-side instantiation with "You cannot instantiate this class from PHP."
2. The Rust `__construct` **shadows** `Exception::__construct`, so the message and
   code are never stored unless they are declared as `#[php(prop)]` fields.

```rust
#[php_class]
#[php(name = "LLMException", extends(ce = ext_php_rs::zend::ce::exception, stub = "\\Exception"))]
pub struct LLMException {
    #[php(prop, flags = ext_php_rs::flags::PropertyFlags::Protected)]
    message: String,
    #[php(prop, flags = ext_php_rs::flags::PropertyFlags::Protected)]
    code: i64,
}

#[php_impl]
impl LLMException {
    pub fn __construct(message: Option<String>, code: Option<i64>) -> Self {
        Self { message: message.unwrap_or_default(), code: code.unwrap_or(0) }
    }
}
```

`Exception::getMessage()` reads the `message` property directly, which is why
declaring it on the struct is what makes it work. All five exception classes are
generated by the `php_exception_class!` macro in `src/error.rs`.

### Throwing from Rust

Return `PhpResult<T>` from any `#[php_impl]` method; ext-php-rs throws it.
octolib errors are mapped in `src/error.rs` via the `IntoPhpException` trait —
`ProviderError::NetworkError`/`ApiError`/`TimeoutError` → `LLMConnectionException`,
`ModelNotSupported` → `LLMValidationException`, and so on.

---

## Stubs (`php/llm.php`)

Generated by `make stubs` (`cargo php stubs`). IDE autocompletion only — never
loaded at runtime, and the test runner deliberately does not include it. Regenerate
after any public API change; CI regenerates them too.

---

## Known gaps

Things a reader might expect to work but that are not implemented:

| Gap | Where |
|---|---|
| `StructuredResponse::getStructured()` always returns `null` | `structured_builder.rs` — blocked on a Zval cloning issue in ext-php-rs 0.15.x |
| `ToolBuilder::setAutoExecute()` is a no-op | `auto_execute` is stored and never read |
| `StructuredBuilder::withFormat()` is a no-op | `format` is stored and never read |
| `setFrequencyPenalty()` / `setPresencePenalty()` never reach the provider | not passed to `ChatCompletionParams` |
| No `timeout` option | octolib's timeout/retry params are not exposed |
| `StructuredResponse::toArray()` / `toJson()` flatten nested data | only top-level scalars survive |
| No streaming, thinking blocks, caching, cost, vision, or `tool_choice` | octolib supports these; the extension does not surface them |

---

## Common pitfalls

| Problem | Cause | Fix |
|---|---|---|
| `cargo build` fails with `Cannot turn unknown calling convention` | missing LLVM 17 env vars | use `make build`; `brew install llvm@17` |
| Exception `getMessage()` returns an empty string | Rust `__construct` shadows the parent | add `message`/`code` as `#[php(prop)]` fields |
| "You cannot instantiate this class from PHP." | no `#[php_impl]` block on the class | add one with `__construct` |
| Stubs out of date | forgot to regenerate after an API change | `make stubs` |
| `base_url` appears to be ignored | hyphenated or non-standard provider prefix | set the real env var with `putenv()` |
| A 400 from an OpenAI-compatible server on `openai:` | `openai:` speaks the Responses API | use the `local:` prefix instead |
