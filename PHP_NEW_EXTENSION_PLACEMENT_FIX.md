# Fix: PHP_NEW_EXTENSION Placement

## The Error

```
main/internal_functions_cli.c:65:9: error: 'phpext_swoole_ptr' undeclared
main/internal_functions_cli.c:66:9: error: 'phpext_sysvsem_ptr' undeclared
main/internal_functions_cli.c:67:9: error: 'phpext_zip_ptr' undeclared
main/internal_functions_cli.c:68:9: error: 'phpext_zstd_ptr' undeclared
```

## The Problem

`PHP_NEW_EXTENSION` was called INSIDE the `if test "$BUILD_TYPE" = "static"` block, which caused it to be called at the wrong time in the configure process, breaking other extensions.

## The Fix

Move `PHP_NEW_EXTENSION` OUTSIDE and BEFORE the if/else block:

### Wrong (Before):
```m4
if test "$BUILD_TYPE" = "static"; then
    EXTRA_LIBS="$EXTRA_LIBS $LLM_STATIC_LIB"
    PHP_NEW_EXTENSION(llm, , $ext_shared, , , )  # ← WRONG PLACE
    ...
fi
```

### Correct (After):
```m4
# Register extension FIRST (outside if/else)
PHP_NEW_EXTENSION(llm, , $ext_shared, , , )

# Then handle static vs shared
if test "$BUILD_TYPE" = "static"; then
    EXTRA_LIBS="$EXTRA_LIBS $LLM_STATIC_LIB"
    ...
fi
```

## Why This Matters

1. **PHP_NEW_EXTENSION** must be called in the correct order relative to other extensions
2. Calling it inside conditional blocks can disrupt the extension registration sequence
3. The `$ext_shared` variable is already set correctly by PHP's build system
4. Moving it outside ensures proper registration without affecting other extensions

## Testing

```bash
./buildconf --force
./configure --disable-shared --enable-llm --enable-swoole --enable-zip
make clean && make -j$(nproc)

# Should build without errors
# All extensions should work
php -m | grep -E "llm|swoole|zip"
```

## Summary

**Problem:** `PHP_NEW_EXTENSION` inside if block broke other extensions  
**Cause:** Wrong placement in configure sequence  
**Fix:** Move `PHP_NEW_EXTENSION` before if/else block  
**Result:** All extensions build correctly
