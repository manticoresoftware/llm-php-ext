# config.m4 Changes Summary

## Problem Statement

The original `config.m4` had issues when building PHP from source with the LLM extension:

1. **Static linking:** Referenced non-existent `llm.c` file via `PHP_NEW_EXTENSION`
2. **Dynamic linking:** No automatic installation during `make install`
3. **Confusion:** Unclear why a C file was needed for a Rust extension

## Root Cause

**ext-php-rs generates ALL PHP extension symbols automatically:**
- The `#[php_module]` macro creates the `get_module()` function
- The compiled `.so` (cdylib) or `.a` (staticlib) already contains all Zend API integration
- **NO C source files are needed!**

Traditional PHP extensions need `PHP_NEW_EXTENSION(name, sources.c, ...)` because they compile C code. Rust extensions are pre-compiled by Cargo.

## Solution

### Static Linking (`--with-llm-static`)

**Before:**
```m4
PHP_NEW_EXTENSION(llm, llm.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
PHP_ADD_LIBRARY_WITH_PATH(llm, $LLM_TARGET_DIR, LLM_SHARED_LIBADD)
```
❌ References non-existent `llm.c`
❌ Uses wrong linking approach

**After:**
```m4
EXTRA_LIBS="$EXTRA_LIBS $LLM_STATIC_LIB"
EXTRA_LIBS="$EXTRA_LIBS -lpthread -ldl -lm -lgcc_s"  # Linux
EXTRA_LIBS="$EXTRA_LIBS -framework System -framework CoreFoundation -framework Security"  # macOS
PHP_ADD_BUILD_DIR(ext/llm, 1)
PHP_SUBST(EXTRA_LIBS)
```
✅ Directly links `libllm.a` into PHP binary
✅ Adds required system libraries
✅ No C files needed

### Dynamic Linking (`--enable-llm`)

**Before:**
```m4
# The extension is already built by cargo
# It will be available at: $LLM_TARGET_DIR/$EXT_SO
# Users need to manually copy it or use: cargo php install
```
❌ No automatic installation
❌ Manual copying required

**After:**
```m4
AC_SUBST(LLM_TARGET_DIR)
AC_SUBST(EXT_SO)
PHP_ADD_MAKEFILE_FRAGMENT([Makefile.frag])
```
✅ Exports variables for Makefile
✅ Includes `Makefile.frag` for installation
✅ `make install` automatically installs the `.so`

### New File: Makefile.frag

```makefile
install-llm:
	@echo "Installing LLM extension..."
	@$(mkinstalldirs) $(INSTALL_ROOT)$(phplibdir)
	@if test -f "@LLM_TARGET_DIR@/@EXT_SO@"; then \
		$(INSTALL) -m 0755 "@LLM_TARGET_DIR@/@EXT_SO@" "$(INSTALL_ROOT)$(phplibdir)/llm.$(SHLIB_SUFFIX_NAME)"; \
		echo "LLM extension installed to $(INSTALL_ROOT)$(phplibdir)/llm.$(SHLIB_SUFFIX_NAME)"; \
	else \
		echo "ERROR: Extension file not found at @LLM_TARGET_DIR@/@EXT_SO@"; \
		exit 1; \
	fi

install: install-llm
```

This fragment:
- Hooks into PHP's `make install` target
- Copies the Cargo-built `.so` to PHP's extension directory
- Validates the file exists before installing

## Testing

### Static Build
```bash
cd /path/to/php-src
cp -r /path/to/llm-php-ext ext/llm
./buildconf --force
./configure --with-llm-static --prefix=/usr/local/php-static
make -j$(nproc)
sudo make install

# Verify
/usr/local/php-static/bin/php -m | grep llm
# Should show: llm (built-in, no php.ini needed)
```

### Dynamic Build
```bash
cd /path/to/php-src
cp -r /path/to/llm-php-ext ext/llm
./buildconf --force
./configure --enable-llm --prefix=/usr/local/php-dynamic
make -j$(nproc)
sudo make install

# Verify installation
ls -lh $(php-config --extension-dir)/llm.so

# Test loading
php -dextension=llm.so -m | grep llm
# Should show: llm
```

## Key Insights

1. **ext-php-rs is self-contained:** The Rust library contains all PHP extension code
2. **No C wrapper needed:** Unlike traditional FFI, ext-php-rs generates proper PHP extensions
3. **Static linking is simple:** Just add the `.a` to `EXTRA_LIBS`
4. **Dynamic linking needs Makefile.frag:** PHP's build system needs explicit install rules
5. **Both modes work seamlessly:** No hacks or dummy files required

## Files Modified

1. **config.m4** - Fixed static/dynamic linking logic
2. **Makefile.frag** (NEW) - Installation rules for dynamic mode
3. **BUILD_WITH_PHP.md** (NEW) - Comprehensive documentation

## Benefits

✅ **Clean solution:** No fake `llm.c` file
✅ **Automatic installation:** `make install` handles everything
✅ **Both modes work:** Static and dynamic linking supported
✅ **Standard PHP workflow:** Follows PHP extension conventions
✅ **Well documented:** Clear explanation of the approach
