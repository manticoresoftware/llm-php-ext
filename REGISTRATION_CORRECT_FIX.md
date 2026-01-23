# FINAL FIX: Static Extension Registration

## The Error
```bash
./configure: line 46970: syntax error near unexpected token `llm'
./configure: line 46970: `    PHP_ADD_EXTENSION(llm)'
```

## The Problem
`PHP_ADD_EXTENSION` doesn't exist. I made a mistake.

## The Correct Fix

Use `PHP_NEW_EXTENSION` with **empty source list**:

```m4
PHP_NEW_EXTENSION(llm, , no, , , )
```

### Parameters Explained
```m4
PHP_NEW_EXTENSION(name, sources, shared, sapi_class, extra_cflags, cxx)
                  ↓     ↓        ↓      ↓           ↓            ↓
                  llm   (empty)  no     (empty)     (empty)      (empty)
```

- **name**: `llm` - extension name
- **sources**: `` - EMPTY! No C files to compile (Rust provides everything)
- **shared**: `no` - static build
- **sapi_class**: `` - default
- **extra_cflags**: `` - none
- **cxx**: `` - not C++

## What This Does

1. **Registers extension** with PHP's build system
2. **Adds to `main/internal_functions.c`:**
   ```c
   extern zend_module_entry llm_module_entry;
   #define phpext_llm_ptr &llm_module_entry
   ```
3. **No compilation** (empty source list)
4. **PHP finds `llm_module_entry`** in the linked `libllm.a`

## Why This Works

- **Traditional C extension:** `PHP_NEW_EXTENSION(ext, ext.c, ...)` - compiles C files
- **Rust extension:** `PHP_NEW_EXTENSION(ext, , ...)` - just registers, no compilation
- The `.a` file already has all code
- PHP just needs to know the extension exists

## Testing

```bash
cd /path/to/php-src
./buildconf --force
./configure --disable-shared --enable-llm
make clean && make -j$(nproc)

# Should work now
php -m | grep llm
# Output: llm ✓
```

## Summary

**Wrong:** `PHP_ADD_EXTENSION(llm)` - doesn't exist  
**Right:** `PHP_NEW_EXTENSION(llm, , no, , , )` - empty sources  
**Result:** Extension registered and appears in `php -m`

This is the correct way to register a Rust extension for static builds!
