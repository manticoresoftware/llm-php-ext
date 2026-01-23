# CRITICAL FIX NEEDED

## The Problem

The generated `main/internal_functions_cli.c` is **MISSING HEADER INCLUDES** for most extensions.

**It only includes 11 headers but references 28 extensions!**

This means the build system is broken or incomplete.

## What You Need To Do

```bash
cd /path/to/php-src

# 1. Clean everything
make distclean

# 2. Rebuild the build system
./buildconf --force

# 3. Configure again
./configure --disable-shared --enable-llm --enable-swoole --enable-mbstring --enable-standard --enable-spl

# 4. Check if headers are now included
head -50 main/internal_functions_cli.c
```

## What Should Happen

After `./buildconf --force`, the file `main/internal_functions_cli.c` should include:

```c
#include "ext/standard/php_standard.h"
#include "ext/spl/php_spl.h"
#include "ext/llm/php_llm.h"
#include "ext/mbstring/php_mbstring.h"
#include "ext/swoole/php_swoole.h"
// ... etc for ALL enabled extensions
```

## Files I Created

1. **php_llm.h** - Header file that declares `llm_module_entry`
2. **llm_stub.c** - Empty C file for registration
3. **config.m4** - Calls `PHP_NEW_EXTENSION(llm, llm_stub.c, $ext_shared)`

## The Issue

The build system (`./buildconf`) generates `main/internal_functions_cli.c` by scanning all extensions.

If it's not including headers for standard, spl, mbstring, etc., then either:
1. You didn't run `./buildconf --force` after adding the llm extension
2. The build system is corrupted
3. Those extensions aren't properly configured

## Test This

```bash
# Check if buildconf sees the llm extension
./buildconf --force 2>&1 | grep -i llm

# Check what extensions are configured
./configure --help | grep -E "enable-(llm|standard|spl|mbstring)"
```

**Please run `./buildconf --force` and try again!**
