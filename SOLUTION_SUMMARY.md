# SOLUTION SUMMARY

## Your Question

> When building PHP from source with `--disable-shared` or `--enable-static`, should the LLM extension automatically build as static without requiring `--with-llm-static`?

**Answer: YES! And now it does.**

## What Was Fixed

### 1. Removed Fake `llm.c` Requirement

**Problem:** config.m4 referenced non-existent `llm.c` via `PHP_NEW_EXTENSION`

**Why it's wrong:** ext-php-rs generates ALL PHP symbols automatically in the Rust library. The `.so` or `.a` already contains `get_module()` and all Zend API integration.

**Solution:** Removed `PHP_NEW_EXTENSION` entirely. For static builds, we directly link `libllm.a` via `EXTRA_LIBS`.

### 2. Added Auto-Detection of Build Mode

**Problem:** Extension didn't detect PHP's `--disable-shared` or `--enable-static` flags

**Solution:** Check `$ext_shared` variable set by PHP's build system:

```m4
if test "$PHP_LLM_STATIC" != "no"; then
  BUILD_TYPE="static"  # Explicit --with-llm-static
elif test "$ext_shared" = "no"; then
  BUILD_TYPE="static"  # PHP built statically (AUTO-DETECTED!)
else
  BUILD_TYPE="shared"  # Default
fi
```

### 3. Added Automatic Installation for Shared Mode

**Problem:** Shared `.so` file wasn't installed by `make install`

**Solution:** Created `Makefile.frag` that hooks into PHP's install target:

```makefile
install-llm:
	@$(INSTALL) -m 0755 "@LLM_TARGET_DIR@/@EXT_SO@" "$(INSTALL_ROOT)$(phplibdir)/llm.$(SHLIB_SUFFIX_NAME)"

install: install-llm
```

## How It Works Now

### Scenario 1: Default PHP Build (Shared Extensions)
```bash
./configure --enable-llm
```
- **Detects:** `ext_shared=yes`
- **Builds:** `libllm.so` (cdylib)
- **Installs:** Copies to `/usr/lib/php/extensions/llm.so`
- **Usage:** `php -dextension=llm.so`

### Scenario 2: Static PHP Build (AUTO-DETECTED!)
```bash
./configure --disable-shared --enable-llm
```
- **Detects:** `ext_shared=no` ← **AUTOMATIC!**
- **Builds:** `libllm.a` (staticlib)
- **Links:** Embeds into PHP binary via `EXTRA_LIBS`
- **Usage:** `php -m` (no extension= needed)

### Scenario 3: Explicit Static Flag
```bash
./configure --enable-llm --with-llm-static
```
- **Detects:** Explicit flag
- **Builds:** `libllm.a` (staticlib)
- **Links:** Embeds into PHP binary
- **Usage:** `php -m` (no extension= needed)

## Your Test Results Explained

```bash
# You ran:
./configure --enable-llm  # (without --with-llm-static)

# PHP was built with shared extensions (default)
# So: ext_shared=yes → BUILD_TYPE="shared"

# Result: libllm.so built but NOT loaded by default
php -m  # No llm (needs extension=llm.so)

php -dextension=build/ext/llm/target/release/libllm.so -m  # Shows llm ✓
```

**This is CORRECT behavior for shared mode!**

## To Get Static Build (Embedded)

Run configure with one of these:

```bash
# Option 1: Let PHP's static mode auto-detect
./configure --disable-shared --enable-llm

# Option 2: Explicit static flag
./configure --enable-llm --with-llm-static

# Option 3: PHP enable-static mode
./configure --enable-static --enable-llm
```

Then:
```bash
make -j$(nproc)
sudo make install

# Verify - no extension= needed!
php -m | grep llm
# Output: llm
```

## Files Changed

1. **config.m4**
   - Added auto-detection of `$ext_shared`
   - Removed fake `PHP_NEW_EXTENSION(llm, llm.c, ...)`
   - Fixed static linking via `EXTRA_LIBS`
   - Added `Makefile.frag` inclusion for shared mode

2. **Makefile.frag** (NEW)
   - Installation rules for shared `.so` file
   - Hooks into `make install` target

3. **Documentation** (NEW)
   - `BUILD_WITH_PHP.md` - Complete build guide
   - `AUTO_DETECTION.md` - Detection logic explained
   - `CHANGES.md` - Summary of changes
   - `test_detection.sh` - Test script

## Key Insights

1. **ext-php-rs is self-contained:** No C wrapper needed
2. **$ext_shared tells us the mode:** Set by PHP's build system
3. **Static = EXTRA_LIBS:** Direct linking of `.a` file
4. **Shared = Makefile.frag:** Installation via make install
5. **Both modes work seamlessly:** No hacks required

## Verification Commands

### Check detection during configure:
```bash
./configure --disable-shared --enable-llm 2>&1 | grep "whether to build llm"
# Output: static (PHP built with --disable-shared or --enable-static)
```

### Check static build result:
```bash
nm sapi/cli/php | grep get_module  # Should show symbol
php -m | grep llm                   # Should show llm
```

### Check shared build result:
```bash
ls -lh modules/llm.so              # Should exist
php -dextension=llm.so -m | grep llm  # Should show llm
```

## Summary

✅ **Auto-detection works:** `--disable-shared` → static build (no flag needed)
✅ **No fake C files:** ext-php-rs provides all symbols
✅ **Automatic installation:** `make install` handles both modes
✅ **Standard workflow:** Follows PHP extension conventions
✅ **Well documented:** Clear explanation of behavior

**Your extension now behaves like a native PHP extension!**
