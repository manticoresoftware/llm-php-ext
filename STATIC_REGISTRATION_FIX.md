# Static Extension Registration Fix

## Problem

The static library `libllm.a` was successfully linked into the PHP binary, but the extension didn't appear in `php -m` output.

## Root Cause

For **static extensions**, PHP needs to:
1. Link the library (✅ we did this with `EXTRA_LIBS`)
2. **Register the extension module** (❌ we forgot this!)

## The Fix

Use `PHP_NEW_EXTENSION` with **empty source list**:

```m4
if test "$BUILD_TYPE" = "static"; then
    # Link the static library
    EXTRA_LIBS="$EXTRA_LIBS $LLM_STATIC_LIB"
    
    # Register the extension with PHP (empty source list)
    # ext-php-rs provides get_module() symbol, no C sources needed
    PHP_NEW_EXTENSION(llm, , no, , , )
    
    PHP_ADD_BUILD_DIR(ext/llm, 1)
    PHP_SUBST(EXTRA_LIBS)
fi
```

## PHP_NEW_EXTENSION Parameters

```m4
PHP_NEW_EXTENSION(name, sources, shared, sapi_class, extra_cflags, cxx)
```

For our case:
- `name`: `llm` - extension name
- `sources`: `` (empty) - no C sources to compile
- `shared`: `no` - static build
- `sapi_class`: `` (empty) - default
- `extra_cflags`: `` (empty) - none needed
- `cxx`: `` (empty) - not C++

## What This Does

1. **Registers the extension** in PHP's build system
2. **Adds to `main/internal_functions.c`:**
   ```c
   extern zend_module_entry llm_module_entry;
   #define phpext_llm_ptr &llm_module_entry
   
   static zend_module_entry *php_builtin_extensions[] = {
       ...
       phpext_llm_ptr,  // ← Added here
       ...
   };
   ```
3. **No compilation** happens (empty source list)
4. **PHP startup** calls `get_module()` from the linked `.a` file

## Why Empty Source List Works

- Traditional extensions: `PHP_NEW_EXTENSION(ext, ext.c, ...)` compiles C files
- Rust extensions: `PHP_NEW_EXTENSION(ext, , ...)` just registers, no compilation
- The `.a` file already contains all code and symbols
- PHP just needs to know the extension exists

## Testing

```bash
./configure --disable-shared --enable-llm
make clean && make -j$(nproc)

# Check extension registered
php -m | grep llm
# Output: llm ✓

# Check it works
php -r "var_dump(extension_loaded('llm'));"
# Output: bool(true) ✓
```

## Summary

**Problem:** Static library linked but extension not registered  
**Cause:** Missing `PHP_NEW_EXTENSION` call  
**Fix:** Added `PHP_NEW_EXTENSION(llm, , no, , , )` with empty sources  
**Result:** Extension registered and appears in `php -m`

