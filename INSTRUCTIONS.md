# ext-php-rs 0.15.x API Guide

This document outlines the key API changes and rules when using ext-php-rs 0.15.x version.

## Core API Changes

### 1. PhpException::from_class Signature

**Old API (deprecated):**
```rust
PhpException::from_class::<ExceptionClass>("class_name", "message")
```

**New API (0.15.x):**
```rust
PhpException::from_class::<ExceptionClass>("message".to_string())
```

The `from_class` method now takes only a message parameter (String), not the class name. The class type is specified via the generic parameter `<T>`.

### 2. IntoZval Trait Import

**Required import:**
```rust
use ext_php_rs::convert::IntoZval;
```

This trait must be imported to use `into_zval()` method on types like `ZendHashTable`, `Zval`, etc.

**Example:**
```rust
let mut arr = PhpArray::new();
arr.insert("key", "value")?;
Ok(arr.into_zval(false)?)  // Requires IntoZval import
```

### 3. MessageBuilder API Changes

**Old API (deprecated):**
```rust
builder.user(&content)
builder.assistant(&content)
builder.system(&content)
builder.tool_result(id, &content)
```

**New API (0.15.x):**
```rust
MessageBuilder::user(&content)
MessageBuilder::assistant(&content)
MessageBuilder::system(&content)
MessageBuilder::tool_result(id, &content)
```

These are now **associated functions** (static methods), not instance methods.

### 4. PhpArray Parameter Types for FromZvalMut

**Old API (deprecated):**
```rust
pub fn from_array(messages: PhpArray) -> PhpResult<Self>
pub fn __construct(messages: Option<PhpArray>) -> PhpResult<Self>
```

**New API (0.15.x):**
```rust
pub fn from_array(messages: &mut PhpArray) -> PhpResult<Self>
pub fn __construct(messages: Option<&mut PhpArray>) -> PhpResult<Self>
```

Use `&mut PhpArray` instead of `PhpArray` to satisfy the `FromZvalMut` trait bound.

### 5. StructuredOutputRequest::json_schema Parameter

**Old API (deprecated):**
```rust
StructuredOutputRequest::json_schema(&schema_string)
```

**New API (0.15.x):**
```rust
let schema_value: serde_json::Value = serde_json::from_str(schema)?;
StructuredOutputRequest::json_schema(schema_value)
```

The `json_schema` method now requires a `serde_json::Value`, not a string reference.

### 6. ProviderResponse Field Access

**Old API (deprecated):**
```rust
response.usage
```

**New API (0.15.x):**
```rust
response.exchange.usage
```

The `usage` field is now nested under `exchange` in the response structure.

### 7. Zval Clone Limitations

**Important:** `Zval` does not have a `clone()` method in ext-php-rs 0.15.x.

**Alternatives:**
```rust
// Use try_clone() if available
let cloned = zval.try_clone()?;

// Or pass by reference instead
fn process(zval: &Zval) { ... }

// Or use into_zval() to create new Zval
let new_zval = Zval::from(value)?;
```

### 8. PhpException::default() Signature

**Old API (deprecated):**
```rust
PhpException::default("message")
```

**New API (0.15.x):**
```rust
PhpException::default("message".to_string())
```

The `default()` method now requires a `String` parameter, not `&str`.

## Common Patterns

### Creating Exceptions

```rust
use ext_php_rs::convert::IntoZval;

// Custom exception
Err(PhpException::from_class::<LLMException>(
    "Something went wrong".to_string()
))

// Default exception
Err(PhpException::default("Error message".to_string()))
```

### Working with Arrays

```rust
use ext_php_rs::convert::IntoZval;

let mut arr = PhpArray::new();
arr.insert("key", "value")?;
arr.insert("number", 42)?;
Ok(arr.into_zval(false)?)  // Requires IntoZval import
```

### Converting Messages

```rust
use octolib::llm::{MessageBuilder, Message as OctoMessage};

let mut builder = MessageBuilder::new();
builder = MessageBuilder::user("Hello");
builder = MessageBuilder::assistant("Hi there!");
let msg = builder.build()?;
```

### Accessing Response Data

```rust
let response = provider.chat_completion(params).await?;
let usage = response.exchange.usage.unwrap_or_else(|| TokenUsage { ... });
let content = response.content;
```

## Migration Checklist

When upgrading to ext-php-rs 0.15.x:

- [ ] Update all `PhpException::from_class` calls to use new signature
- [ ] Add `use ext_php_rs::convert::IntoZval;` to all files using `into_zval()`
- [ ] Change `MessageBuilder` method calls to associated functions
- [ ] Update `PhpArray` parameters to `&mut PhpArray`
- [ ] Parse JSON strings to `Value` before passing to `StructuredOutputRequest::json_schema()`
- [ ] Update `response.usage` to `response.exchange.usage`
- [ ] Replace `Zval::clone()` with `try_clone()` or pass by reference
- [ ] Update `PhpException::default()` calls to use `.to_string()`
- [ ] Make internal constructors like `from_octo()` visible with `pub(crate)` if needed

## Version Information

- **ext-php-rs**: 0.15.3
- **octolib**: 0.5.0
- **PHP**: 8.0+ required
