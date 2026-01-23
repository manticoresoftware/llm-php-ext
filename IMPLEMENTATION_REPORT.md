# Implementation Report: Static Linking with glibc Compatibility

## Problem Statement

When building PHP statically with `--disable-shared --enable-llm`, the linker fails with:
```
undefined reference to `__isoc23_strtol'
undefined reference to `__isoc23_strtoul'
undefined reference to `__isoc23_strtoll'
undefined reference to `__isoc23_strtoull'
```

## Root Cause

1. The build system has glibc 2.38+ (modern)
2. Rust's C++ dependencies (protobuf, onnxruntime from octolib) compile during `cargo build`
3. These C++ libraries call `strtol()` family functions
4. The C++ compiler binds to new glibc 2.38+ symbols: `__isoc23_strtol`
5. The resulting `libllm.a` contains references to these symbols
6. When linking into PHP, if the target environment doesn't have glibc 2.38+, linking fails

## Solution Implemented

### Approach: Provide Compatibility Wrappers

Instead of trying to prevent C++ code from using new symbols, we provide the symbols ourselves by creating wrapper functions that redirect to old symbols.

### Files Modified

#### 1. `build.rs`
**Added:** glibc compatibility wrapper generation for Linux builds

```rust
fn main() {
    #[cfg(target_os = "linux")]
    {
        // Create C wrapper file with compatibility functions
        let wrapper_c = r#"
        long __isoc23_strtol(const char *nptr, char **endptr, int base) {
            return strtol(nptr, endptr, base);
        }
        // ... similar for strtoul, strtoll, strtoull
        "#;
        
        // Compile wrapper and link it
        cc::Build::new()
            .file(&wrapper_path)
            .compile("glibc_compat");
    }
}
```

**What it does:**
- Detects Linux builds
- Creates wrapper functions that provide `__isoc23_*` symbols
- Redirects calls to old `strtol` symbols (available in glibc 2.2.5+)
- Compiles wrapper into static library
- Links it into the final extension

#### 2. `Cargo.toml`
**Added:** `cc = "1.0"` to `[build-dependencies]`

Required for compiling the C wrapper code in build.rs.

#### 3. `.cargo/config.toml`
**Added:** Documentation about glibc compatibility

Notes that compatibility wrappers are provided by build.rs.

#### 4. `config.m4`
**Changed:** Removed scary warning, added informational message

Now simply informs that compatibility wrappers are included, instead of warning about potential failures.

## How It Works

```
┌─────────────────────────────────────┐
│ C++ Code (protobuf/onnxruntime)     │
│ calls: __isoc23_strtol()            │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ Our Wrapper (glibc_compat.c)        │
│ long __isoc23_strtol(...) {         │
│     return strtol(...);              │
│ }                                    │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│ System glibc (any version 2.17+)    │
│ Provides: strtol (GLIBC_2.2.5)      │
└─────────────────────────────────────┘
```

## Expected Behavior

### Before Fix
```bash
./configure --disable-shared --enable-llm
make
# Error: undefined reference to `__isoc23_strtol'
```

### After Fix
```bash
./configure --disable-shared --enable-llm
make
# Success - wrapper provides missing symbols
```

## Compatibility

The wrapper approach should work with:
- glibc 2.17+ (RHEL 7, CentOS 7)
- glibc 2.27+ (Ubuntu 18.04)
- glibc 2.31+ (Ubuntu 20.04)
- glibc 2.35+ (Ubuntu 22.04)
- glibc 2.38+ (Ubuntu 24.04)
- Any future glibc version

## Testing Required

**Cannot be tested on macOS** - this is a Linux-specific glibc issue.

**Testing needed on Linux:**

1. **Build test:**
   ```bash
   cd /path/to/php-src
   cp -r /path/to/llm-php-ext ext/llm
   ./buildconf --force
   ./configure --disable-shared --enable-llm
   make -j$(nproc)
   ```
   Expected: Build succeeds without linker errors

2. **Symbol verification:**
   ```bash
   nm -g ext/llm/target/release/libllm.a | grep isoc23
   ```
   Expected: Should show `T __isoc23_strtol` etc. (provided by wrapper)

3. **Runtime test:**
   ```bash
   php -m | grep llm
   ```
   Expected: Shows `llm` extension loaded

4. **Functionality test:**
   ```bash
   php -r "var_dump(class_exists('LLM'));"
   ```
   Expected: `bool(true)`

## Limitations

1. **Linux-only:** The wrapper is only compiled on Linux (`#[cfg(target_os = "linux")]`)
2. **Build dependency:** Requires `cc` crate (C compiler) during build
3. **Static linking only:** Dynamic builds don't need this (they link to system glibc at runtime)

## Alternative Solutions Considered

1. **Symbol versioning:** Too complex, requires modifying all C++ dependencies
2. **Downgrade Rust:** Doesn't help - issue is in C++ dependencies, not Rust
3. **Force old glibc headers:** Not possible with system-installed dependencies
4. **Require glibc 2.38+:** Not user-friendly, limits deployment

**Chosen solution (wrappers) is simplest and most reliable.**

## Files Summary

### Modified
- `build.rs` - Added glibc compatibility wrapper generation
- `Cargo.toml` - Added `cc` build dependency
- `.cargo/config.toml` - Added compatibility notes
- `config.m4` - Simplified glibc detection message

### Created (Documentation)
- `GLIBC_COMPATIBILITY.md` - Detailed problem explanation
- `GLIBC_QUICK_FIX.md` - Quick fix guide
- `GLIBC_REAL_SOLUTION.md` - Technical solution details
- `FINAL_SOLUTION.md` - Summary
- `IMPLEMENTATION_REPORT.md` - This file

## Next Steps

**Testing required on Linux system with:**
1. glibc < 2.38 (e.g., Ubuntu 22.04 with glibc 2.35)
2. Build PHP statically with `--disable-shared --enable-llm`
3. Verify no linker errors
4. Verify extension loads and works

**If testing fails:**
- Check if `cc` crate is compiling the wrapper (look for `glibc_compat` in build output)
- Check if wrapper symbols are in the static library (`nm -g libllm.a | grep isoc23`)
- Check linker command to ensure wrapper is being linked

## Conclusion

The implementation provides glibc compatibility wrappers that should allow static linking to work on any glibc 2.17+ system, regardless of the build system's glibc version.

**The solution is implemented but requires Linux testing to verify it works as expected.**
