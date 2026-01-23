# GLIBC Compatibility Issue with Static Linking

## The Problem

When building the LLM extension **statically** on systems with **glibc < 2.38**, you get linker errors:

```
undefined reference to `__isoc23_strtol'
undefined reference to `__isoc23_strtoul'
undefined reference to `__isoc23_strtoll'
undefined reference to `__isoc23_strtoull'
```

## Root Cause

The `__isoc23_*` symbols are from **glibc 2.38+** (released August 2023). These are new ISO C23-compliant versions of string-to-number conversion functions.

**The issue:** The Rust extension depends on `octolib`, which includes heavy C++ dependencies:
- **protobuf** (Google Protocol Buffers)
- **onnxruntime** (ONNX Runtime for ML inference)
- Other C++ libraries

When Cargo builds these dependencies, they compile against your system's glibc headers. If you have glibc 2.38+, they use the new `__isoc23_*` symbols. But when linking statically into PHP (which may be built with older glibc), the symbols are missing.

## Why This Only Affects Static Builds

- **Dynamic linking (`.so`)**: The extension loads at runtime and uses the system's glibc dynamically. Symbol resolution happens at runtime, so it works.
- **Static linking (`.a`)**: All symbols must be resolved at compile time. The PHP binary must contain or link to all required glibc symbols.

## Solutions (in order of preference)

### Solution 1: Use Dynamic Linking (RECOMMENDED)

**This is the easiest and most reliable solution.**

```bash
cd /path/to/php-src
./configure --enable-llm  # NOT --with-llm-static
make -j$(nproc)
sudo make install
```

**Why this works:**
- The `.so` file links dynamically to system glibc
- Symbol resolution happens at runtime
- No glibc version conflicts

**Usage:**
```bash
# Add to php.ini
extension=llm.so

# Or load dynamically
php -dextension=llm.so script.php
```

### Solution 2: Upgrade System glibc to 2.38+

**Best for production static builds.**

Check your glibc version:
```bash
ldd --version
# Output: ldd (GNU libc) 2.35  ← Too old!
```

Upgrade options:

#### Ubuntu/Debian
```bash
# Ubuntu 24.04+ has glibc 2.39
# Ubuntu 23.10 has glibc 2.38
sudo apt update
sudo apt upgrade libc6
```

#### Build from source (advanced)
```bash
wget https://ftp.gnu.org/gnu/glibc/glibc-2.39.tar.gz
tar xzf glibc-2.39.tar.gz
cd glibc-2.39
mkdir build && cd build
../configure --prefix=/usr
make -j$(nproc)
sudo make install
```

**⚠️ WARNING:** Upgrading glibc can break your system if done incorrectly. Use a container or VM for testing.

### Solution 3: Build in a Container with Newer glibc

**Safest way to get static builds with new glibc.**

```dockerfile
# Dockerfile
FROM ubuntu:24.04

RUN apt-get update && apt-get install -y \
    build-essential \
    autoconf \
    bison \
    re2c \
    libxml2-dev \
    libssl-dev \
    curl \
    clang \
    libclang-dev

# Install Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:$PATH"

# Build PHP with static LLM extension
WORKDIR /build
COPY php-src /build/php-src
COPY llm-php-ext /build/php-src/ext/llm

RUN cd /build/php-src && \
    ./buildconf --force && \
    ./configure --disable-shared --enable-llm --prefix=/usr/local/php && \
    make -j$(nproc) && \
    make install

# Result: /usr/local/php/bin/php with embedded LLM extension
```

Build:
```bash
docker build -t php-static-llm .
docker run --rm php-static-llm /usr/local/php/bin/php -m | grep llm
```

### Solution 4: Use Older Rust Toolchain (NOT RECOMMENDED)

You could try using an older Rust toolchain that targets older glibc, but this is complex and may not work with modern dependencies.

### Solution 5: Provide Pre-built Binaries

Build static PHP binaries on systems with glibc 2.38+ and distribute them:

```bash
# On Ubuntu 24.04 or similar
./configure --disable-shared --enable-llm --enable-static
make -j$(nproc)

# Result: sapi/cli/php (static binary)
# Copy this to target systems
```

The binary will work on systems with glibc 2.38+ but NOT on older systems.

## Technical Deep Dive

### What are __isoc23_* symbols?

In glibc 2.38, the C library added new versions of `strtol`, `strtoul`, `strtoll`, `strtoull` that comply with ISO C23 standard. The new versions:

- Handle binary literals (`0b1010`)
- Have stricter error handling
- Are more standards-compliant

The old symbols still exist for backward compatibility:
- `strtol@@GLIBC_2.2.5` (old)
- `strtol@@GLIBC_2.38` → `__isoc23_strtol` (new)

### Why does Rust use the new symbols?

When Rust's C++ dependencies (protobuf, onnxruntime) compile, they call `strtol()`. The compiler/linker sees glibc 2.38+ headers and binds to the new `__isoc23_strtol` symbol.

### Can we force old symbols?

**Theoretically yes, but practically very difficult:**

1. **Symbol versioning:** You'd need to create a version script and rebuild ALL dependencies
2. **Build scripts:** Rust's build.rs scripts compile C++ code automatically - hard to control
3. **Transitive dependencies:** octolib → protobuf → ... (many layers)

**It's not worth the effort.** Use dynamic linking or upgrade glibc.

## Checking Your Environment

### Check glibc version:
```bash
ldd --version
# Output: ldd (GNU libc) 2.35
```

### Check what symbols the static library uses:
```bash
nm -D ext/llm/target/release/libllm.a | grep isoc23
# If you see __isoc23_* symbols, you need glibc 2.38+
```

### Check PHP's glibc dependency:
```bash
ldd sapi/cli/php | grep libc
# Output: libc.so.6 => /lib/x86_64-linux-gnu/libc.so.6 (0x...)
```

### Check available glibc symbols:
```bash
objdump -T /lib/x86_64-linux-gnu/libc.so.6 | grep strtol
# Look for __isoc23_strtol - if missing, you have old glibc
```

## Recommendation Matrix

| Your Situation | Recommended Solution |
|----------------|---------------------|
| Development environment | **Dynamic linking** (`--enable-llm`) |
| Production, can upgrade | **Upgrade glibc to 2.38+** |
| Production, can't upgrade | **Dynamic linking** |
| Need portable static binary | **Build in Ubuntu 24.04 container** |
| CI/CD pipeline | **Use Docker with Ubuntu 24.04+** |
| Legacy system (glibc < 2.38) | **Dynamic linking only** |

## Summary

**The glibc 2.38 requirement is NOT a bug** - it's a consequence of using modern Rust dependencies with C++ libraries.

**Best solution:** Use dynamic linking (`--enable-llm`) unless you have a specific reason for static builds.

**For static builds:** Ensure your build environment has glibc 2.38+ (Ubuntu 24.04, Debian 13, Fedora 39+, etc.).

**The config.m4 now warns you** if it detects this issue during configure.
