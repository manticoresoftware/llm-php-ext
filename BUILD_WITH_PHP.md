# Building LLM Extension with PHP from Source

This document explains how to properly build and link the LLM extension when compiling PHP from source.

## Overview

The LLM extension is built with **ext-php-rs** (Rust), which generates all necessary PHP extension symbols automatically. **NO C source files are needed** because ext-php-rs provides the `get_module()` function and all Zend API integration through Rust macros.

## Build Modes

The extension automatically detects whether to build statically or dynamically based on PHP's configuration.

### Auto-Detection Priority

1. **Explicit flag:** `--with-llm-static` → Always static
2. **PHP build mode:** `--disable-shared` or `--enable-static` → Auto-detect static
3. **Default:** Shared (dynamic) extension

### 1. Static Linking

**Triggered by:**
- `--with-llm-static` (explicit)
- `--disable-shared --enable-llm` (auto-detected)
- `--enable-static --enable-llm` (auto-detected)

**What it does:**
- Embeds the Rust static library (`.a`) directly into the PHP binary
- No separate `.so` file needed
- Extension is always available (built-in)

**How to use:**
```bash
cd /path/to/php-src
cp -r /path/to/llm-php-ext ext/llm
./buildconf --force

# Option 1: Explicit static flag
./configure --enable-llm --with-llm-static [other options]

# Option 2: Auto-detect from PHP static build
./configure --disable-shared --enable-llm [other options]

make -j$(nproc)
sudo make install
```

**What happens:**
1. `configure` detects Rust/Cargo and builds `libllm.a`
2. The static library is added to `EXTRA_LIBS`
3. PHP linker embeds the library into the `php` binary
4. Extension is available without `extension=llm.so` in php.ini

**Verify:**
```bash
php -m | grep llm
# Should show: llm
```

### 2. Dynamic Linking

**Triggered by:**
- `--enable-llm` (default when PHP uses shared extensions)

**What it does:**
- Builds the Rust shared library (`.so`/`.dylib`)
- Installs it to PHP's extension directory during `make install`
- Extension loaded via `extension=llm.so` in php.ini

**How to use:**
```bash
cd /path/to/php-src
cp -r /path/to/llm-php-ext ext/llm
./buildconf --force
./configure --enable-llm [other options]
make -j$(nproc)
sudo make install
```

**What happens:**
1. `configure` detects Rust/Cargo and builds `libllm.so` (or `.dylib` on macOS)
2. `Makefile.frag` adds installation rule
3. `make install` copies the `.so` to PHP's extension directory
4. Add `extension=llm.so` to php.ini to load it

**Verify:**
```bash
php -dextension=llm.so -m | grep llm
# Should show: llm
```

## Why NO llm.c is Needed

### Traditional PHP Extensions (C):
```c
// php_myext.c
PHP_FUNCTION(my_function) { ... }

zend_module_entry myext_module_entry = {
    STANDARD_MODULE_HEADER,
    "myext",
    myext_functions,
    ...
};

ZEND_GET_MODULE(myext)  // Defines get_module()
```

You need `PHP_NEW_EXTENSION(myext, myext.c, ...)` to compile this C file.

### ext-php-rs Extensions (Rust):
```rust
// lib.rs
#[php_function]
pub fn my_function() { ... }

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
```

**ext-php-rs automatically generates:**
- `get_module()` function (required by PHP)
- `zend_module_entry` structure
- All Zend API bindings
- FFI exports in the `.so`/`.a` file

**The compiled library already contains everything PHP needs!**

## Technical Details

### Static Linking Process

1. **Cargo builds:** `cargo build --release` → `target/release/libllm.a`
2. **config.m4 adds to EXTRA_LIBS:**
   ```bash
   EXTRA_LIBS="$EXTRA_LIBS /path/to/libllm.a -lpthread -ldl -lm"
   ```
3. **PHP's Makefile links:**
   ```bash
   gcc ... -o sapi/cli/php ... libllm.a -lpthread -ldl -lm
   ```
4. **Result:** PHP binary contains the extension

### Dynamic Linking Process

1. **Cargo builds:** `cargo build --release` → `target/release/libllm.so`
2. **Makefile.frag installs:**
   ```bash
   install -m 0755 target/release/libllm.so /usr/lib/php/extensions/llm.so
   ```
3. **PHP loads at runtime:**
   ```bash
   php -dextension=llm.so
   ```
4. **Result:** Extension loaded dynamically

## System Dependencies

### macOS
- LLVM 17 (for PHP 8.5): `brew install llvm@17`
- Rust toolchain: `curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh`

### Linux
- libclang: `apt-get install libclang-dev clang` (Ubuntu/Debian)
- Rust toolchain: `curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh`

## Troubleshooting

### "cargo not found"
```bash
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env
```

### "libclang not found" (Linux)
```bash
sudo apt-get install libclang-dev clang
```

### "LLVM 17 not found" (macOS)
```bash
brew install llvm@17
export LLVM_PATH=/opt/homebrew/opt/llvm@17
```

### Extension not loading
```bash
# Check if built
ls -lh ext/llm/target/release/libllm.*

# Check if installed (dynamic)
php-config --extension-dir
ls -lh $(php-config --extension-dir)/llm.so

# Check if loaded
php -m | grep llm
```

### Static build: "undefined reference to get_module"
This means the static library wasn't linked. Check:
```bash
nm sapi/cli/php | grep get_module
# Should show: T get_module
```

If missing, verify `EXTRA_LIBS` contains the path to `libllm.a`:
```bash
grep EXTRA_LIBS Makefile
```

## Comparison with Traditional Extensions

| Aspect | Traditional (C) | ext-php-rs (Rust) |
|--------|----------------|-------------------|
| Source files | `*.c` files required | NO C files needed |
| PHP_NEW_EXTENSION | Required | NOT needed |
| get_module() | Manual in C | Auto-generated |
| Compilation | PHP compiles C | Cargo compiles Rust |
| Static linking | Link `.o` files | Link `.a` file |
| Dynamic linking | Build `.so` from C | Use Cargo's `.so` |

## Summary

**Key Points:**
1. ✅ **NO `llm.c` needed** - ext-php-rs generates all PHP symbols
2. ✅ **Static mode** - embeds `.a` into PHP binary via `EXTRA_LIBS`
3. ✅ **Dynamic mode** - installs `.so` via `Makefile.frag`
4. ✅ **Both modes work** - no fake C files required
5. ✅ **`make install` handles everything** - automatic installation

**The config.m4 now properly:**
- Detects and builds with Cargo
- Links statically without dummy C files
- Installs dynamically without manual copying
- Works with standard PHP build process
