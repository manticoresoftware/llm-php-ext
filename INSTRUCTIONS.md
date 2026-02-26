# LLM PHP Extension — Developer Onboarding

## What is this?

A PHP extension written in Rust (via [ext-php-rs](https://github.com/davidcole1340/ext-php-rs)) that wraps the `octolib` crate to expose LLM functionality to PHP. Supports OpenAI, Anthropic, and other providers.

---

## Project Structure

```
src/
  lib.rs              — entry point, registers all PHP classes/modules
  llm_class.rs        — LLM main class
  message.rs          — Message, MessageCollection
  tool_builder.rs     — Tool, ToolBuilder, ToolCall
  structured_builder.rs — StructuredBuilder
  error.rs            — PHP exception classes + Rust→PHP error mapping
  convert.rs          — type conversion helpers
php/
  llm.php             — generated IDE stubs (do not edit manually)
tests/
  run_tests.php       — test runner
  *.php               — test suites
```

---

## Build & Test

**Always use `make` — never raw `cargo build` directly** (macOS needs LLVM 17 env vars set).

```bash
make build       # debug build
make release     # release build
make test        # build + run PHP tests
make stubs       # regenerate php/llm.php stubs
make ci          # full CI check locally
```

Run tests manually:
```bash
php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php
```

---

## Key ext-php-rs Patterns

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

### Exception classes that extend `\Exception`

**The tricky part.** Two rules:
1. Must have `#[php_impl]` with a `__construct` — without it ext-php-rs blocks PHP-side instantiation with "You cannot instantiate this class from PHP."
2. The Rust `__construct` **shadows** `Exception::__construct`, so the message/code are **never stored** unless you explicitly put them as `#[php(prop)]` fields on the struct.

**Correct pattern:**
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
        Self {
            message: message.unwrap_or_default(),
            code: code.unwrap_or(0),
        }
    }
}
```

`Exception::getMessage()` reads the `message` property directly, so declaring it as a prop on the struct is what makes it work.

### Throwing exceptions from Rust
```rust
// In error.rs — map Rust errors to PHP exceptions
PhpException::from_class::<LLMConnectionException>("something went wrong".into())
```

Return `PhpResult<T>` from any `#[php_impl]` method and ext-php-rs will throw it automatically.

---

## Stubs (`php/llm.php`)

Generated via `make stubs`. Used for IDE autocompletion only — not loaded at runtime. Regenerate after any public API change.

---

## Common Pitfalls

| Problem | Cause | Fix |
|---|---|---|
| `cargo build` fails with `Cannot turn unknown calling convention` | Missing LLVM 17 env vars | Use `make build` |
| Exception `getMessage()` returns empty string | Rust `__construct` shadows parent, message never stored | Add `message`/`code` as `#[php(prop)]` fields |
| "You cannot instantiate this class from PHP." | No `#[php_impl]` block on the class | Add `#[php_impl]` with `__construct` |
| Stubs out of date | Forgot to regenerate after API change | `make stubs` |
