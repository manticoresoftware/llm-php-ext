# Solution: Stub File for Extension Registration

## The Problem

`PHP_NEW_EXTENSION` requires at least one source file. Using an empty source list breaks PHP's build system and causes other extensions to fail registration.

## The Solution

Create a minimal stub C file (`llm_stub.c`) that does nothing:

```c
/* Stub file for LLM extension registration
 * The actual extension is implemented in Rust (libllm.a)
 * This file is only needed to register the extension with PHP's build system
 */

/* Empty - all functionality provided by Rust library */
```

Then use it in `PHP_NEW_EXTENSION`:

```m4
PHP_NEW_EXTENSION(llm, llm_stub.c, $ext_shared, , , )
```

## Why This Works

1. **PHP's build system** requires at least one source file to register an extension
2. **The stub file** satisfies this requirement (compiles to empty object file)
3. **The Rust library** (`libllm.a`) provides all actual functionality via `get_module()` symbol
4. **Static linking** combines the stub object + Rust library into PHP binary
5. **PHP finds** the `llm_module_entry` symbol from the Rust library at startup

## How It Works

```
┌─────────────────────────────────────┐
│ llm_stub.c (empty)                  │
│ Compiles to: llm_stub.o             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ PHP Build System                    │
│ Registers: phpext_llm_ptr           │
│ Adds to: internal_functions.c      │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ Linker combines:                    │
│ - llm_stub.o (empty)                │
│ - libllm.a (Rust, has get_module)  │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ PHP Binary                          │
│ Calls: get_module() from libllm.a  │
│ Extension: Registered and working   │
└─────────────────────────────────────┘
```

## Files

- **llm_stub.c** - Empty stub file for registration
- **config.m4** - Uses `PHP_NEW_EXTENSION(llm, llm_stub.c, $ext_shared, , , )`
- **libllm.a** - Rust library with actual extension code

## Testing

```bash
./buildconf --force
./configure --disable-shared --enable-llm
make clean && make -j$(nproc)

# Should build successfully
php -m | grep llm
# Output: llm ✓

php -r "var_dump(extension_loaded('llm'));"
# Output: bool(true) ✓
```

## Why Not Use Empty Source List?

PHP's `PHP_NEW_EXTENSION` macro expects at least one source file:
- Empty list: Breaks build system, other extensions fail
- Stub file: Satisfies requirement, everything works

## Comparison with Traditional Extensions

| Type | Source Files | Functionality |
|------|--------------|---------------|
| **Traditional C** | `ext.c` | C code provides everything |
| **Rust (our case)** | `llm_stub.c` (empty) | Rust library provides everything |

The stub is just for registration - all real code is in Rust.

## Summary

**Problem:** `PHP_NEW_EXTENSION` needs at least one source file  
**Solution:** Created empty `llm_stub.c` stub file  
**Result:** Extension registers properly without breaking other extensions  
**Benefit:** Clean solution that works with PHP's build system
