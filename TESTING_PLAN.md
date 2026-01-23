# Testing Plan: PHP_NEW_EXTENSION with Stub File

## Current Setup

- **llm_stub.c**: Empty C file for registration
- **config.m4**: Calls `PHP_NEW_EXTENSION(llm, llm_stub.c, $ext_shared)`
- **libllm.a**: Rust library with actual extension code

## Expected Behavior

When `./buildconf` runs:
1. Scans all `ext/*/config.m4` files
2. Each calls `PHP_NEW_EXTENSION(name, sources, $ext_shared)`
3. Generates `main/internal_functions.c` with all extensions

When `./configure --disable-shared --enable-llm` runs:
1. Sets `ext_shared=no` for all extensions
2. Each extension's config.m4 executes
3. `PHP_NEW_EXTENSION` registers the extension

When `make` runs:
1. Compiles `llm_stub.c` → `llm_stub.o` (empty object)
2. Links `llm_stub.o` + `libllm.a` + other extensions
3. PHP binary contains all extensions

## Why It Might Be Breaking

**Hypothesis 1**: The stub file path is wrong
- `PHP_NEW_EXTENSION(llm, llm_stub.c, ...)` expects file in `ext/llm/`
- File must be at: `/path/to/php-src/ext/llm/llm_stub.c`

**Hypothesis 2**: Calling PHP_NEW_EXTENSION at wrong time
- Must be called AFTER cargo build?
- Must be called BEFORE if/else block?

**Hypothesis 3**: The $ext_shared variable is corrupted
- Something in our config.m4 changes $ext_shared globally?
- Need to save/restore it?

## Testing Steps

1. **Verify stub file location**:
   ```bash
   ls -la /path/to/php-src/ext/llm/llm_stub.c
   ```

2. **Check if PHP_NEW_EXTENSION is called**:
   ```bash
   ./buildconf --force 2>&1 | grep -i llm
   ```

3. **Check generated internal_functions.c**:
   ```bash
   ./configure --disable-shared --enable-llm
   grep phpext_llm_ptr main/internal_functions.c
   ```

4. **Check if other extensions are affected**:
   ```bash
   grep phpext_swoole_ptr main/internal_functions.c
   grep phpext_zip_ptr main/internal_functions.c
   ```

## What to Report

Please run these commands and share the output:

```bash
cd /path/to/php-src
./buildconf --force
./configure --disable-shared --enable-llm --enable-swoole --enable-zip 2>&1 | tail -50
cat main/internal_functions.c | head -100
```

This will show:
- Whether llm is registered
- Whether other extensions are registered
- What's in the generated file
