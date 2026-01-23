# Auto-Detection of Static vs Shared Build Mode

## Problem

When building PHP with `--disable-shared` or `--enable-static`, ALL extensions should be built statically. However, the LLM extension was not detecting this automatically and required explicit `--with-llm-static` flag.

## Solution

The `config.m4` now auto-detects the build mode using PHP's `$ext_shared` variable:

```m4
if test "$PHP_LLM_STATIC" != "no"; then
  BUILD_TYPE="static"  # Explicit --with-llm-static
elif test "$ext_shared" = "no"; then
  BUILD_TYPE="static"  # PHP built statically
else
  BUILD_TYPE="shared"  # Default
fi
```

## Detection Priority

1. **Explicit flag:** `--with-llm-static` → Always static
2. **PHP build mode:** `--disable-shared` or `--enable-static` → Auto-detect static
3. **Default:** Shared (dynamic) extension

## Test Cases

### Case 1: PHP with shared extensions (default)
```bash
./configure --enable-llm
# Result: BUILD_TYPE="shared" (ext_shared=yes)
# Builds: libllm.so
# Install: Copies to extension directory
```

### Case 2: PHP with static build
```bash
./configure --disable-shared --enable-llm
# Result: BUILD_TYPE="static" (ext_shared=no, auto-detected!)
# Builds: libllm.a
# Install: Embedded in PHP binary
```

### Case 3: Explicit static flag
```bash
./configure --enable-llm --with-llm-static
# Result: BUILD_TYPE="static" (explicit)
# Builds: libllm.a
# Install: Embedded in PHP binary
```

### Case 4: Force shared even with static PHP (not recommended)
```bash
./configure --disable-shared --enable-llm=shared
# Result: BUILD_TYPE="shared" (ext_shared=yes overrides)
# Builds: libllm.so
# Note: May not work properly with static PHP
```

## How $ext_shared Works

The `$ext_shared` variable is set by PHP's build system based on:

1. **Global PHP mode:**
   - `--disable-shared` → All extensions: `ext_shared=no`
   - `--enable-static` → All extensions: `ext_shared=no`
   - Default → All extensions: `ext_shared=yes`

2. **Per-extension override:**
   - `--enable-llm=shared` → Force `ext_shared=yes` for llm
   - `--enable-llm=static` → Force `ext_shared=no` for llm

3. **PHP_ARG_ENABLE behavior:**
   ```m4
   PHP_ARG_ENABLE([llm], ...)
   # Sets: $PHP_LLM = "yes" or "no" or "shared"
   # Sets: $ext_shared based on PHP mode and override
   ```

## Verification

### Check build mode during configure:
```bash
./configure --enable-llm 2>&1 | grep "whether to build llm"
# Output: checking whether to build llm extension as static or shared... shared (default)

./configure --disable-shared --enable-llm 2>&1 | grep "whether to build llm"
# Output: checking whether to build llm extension as static or shared... static (PHP built with --disable-shared or --enable-static)
```

### Check result after build:
```bash
# Static build
nm sapi/cli/php | grep get_module
# Should show: T get_module (symbol in binary)

php -m | grep llm
# Should show: llm (no extension= needed)

# Shared build
ls -lh modules/llm.so
# Should exist

php -dextension=llm.so -m | grep llm
# Should show: llm
```

## Benefits

✅ **Automatic detection:** No manual flag needed for static PHP builds
✅ **Consistent behavior:** Follows PHP's extension build conventions
✅ **Explicit override:** `--with-llm-static` still works for forcing static
✅ **Clear messaging:** Configure output shows detected mode and reason
✅ **Standard workflow:** Works like other PHP extensions

## Example: Building Static PHP with LLM

```bash
cd /path/to/php-src
cp -r /path/to/llm-php-ext ext/llm

./buildconf --force

# Build fully static PHP with embedded LLM extension
./configure \
  --disable-shared \
  --enable-static \
  --enable-llm \
  --disable-all \
  --enable-cli \
  --enable-mbstring \
  --enable-json \
  --prefix=/usr/local/php-static

make -j$(nproc)
sudo make install

# Verify - no extension= needed!
/usr/local/php-static/bin/php -m | grep llm
# Output: llm

# Check binary size (extension is embedded)
ls -lh /usr/local/php-static/bin/php
# Larger binary due to embedded Rust code
```

## Technical Details

### What happens in static mode:

1. **Configure detects:** `ext_shared=no`
2. **Cargo builds:** `libllm.a` (staticlib)
3. **config.m4 adds to EXTRA_LIBS:**
   ```m4
   EXTRA_LIBS="$EXTRA_LIBS /path/to/libllm.a -lpthread -ldl -lm"
   ```
4. **PHP's Makefile links:**
   ```bash
   gcc -o sapi/cli/php ... libllm.a -lpthread -ldl -lm
   ```
5. **Result:** Single binary with embedded extension

### What happens in shared mode:

1. **Configure detects:** `ext_shared=yes`
2. **Cargo builds:** `libllm.so` (cdylib)
3. **config.m4 includes:** `Makefile.frag`
4. **make install runs:**
   ```bash
   install -m 0755 libllm.so /usr/lib/php/extensions/llm.so
   ```
5. **Result:** Separate `.so` file in extension directory

## Comparison with Other Extensions

This behavior matches standard PHP extensions:

```bash
# Standard extension (e.g., mysqli)
./configure --disable-shared --enable-mysqli
# Result: mysqli built statically

# LLM extension (now fixed!)
./configure --disable-shared --enable-llm
# Result: llm built statically (auto-detected!)
```

## Summary

The fix ensures that:
- `--disable-shared` → LLM built as static (auto)
- `--enable-static` → LLM built as static (auto)
- `--with-llm-static` → LLM built as static (explicit)
- Default → LLM built as shared

**No more manual intervention needed!** The extension respects PHP's build configuration automatically.
