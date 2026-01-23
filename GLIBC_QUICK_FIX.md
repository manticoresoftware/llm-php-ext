# Quick Fix: glibc __isoc23_* Undefined Reference Errors

## TL;DR

**You're trying to build STATIC but your glibc is too old.**

### Fastest Solution: Use Dynamic Linking

```bash
cd /path/to/php-src

# Clean previous build
make clean
rm -f config.cache

# Reconfigure WITHOUT static flag
./configure --enable-llm  # Remove --with-llm-static or --disable-shared

# Build
make -j$(nproc)
sudo make install

# Verify
php -dextension=llm.so -m | grep llm
# Output: llm ✓
```

Add to `php.ini`:
```ini
extension=llm.so
```

**Done! No more glibc errors.**

---

## Why This Happens

| Build Mode | What Happens | glibc Requirement |
|------------|--------------|-------------------|
| **Static** (`--disable-shared` or `--with-llm-static`) | Embeds `.a` into PHP binary | Needs glibc 2.38+ |
| **Dynamic** (`--enable-llm`) | Builds `.so` file | Works with any glibc |

Your error means:
- You're building **static**
- Your glibc is **< 2.38**
- Rust dependencies need **glibc 2.38+** symbols

---

## Solution Comparison

### Option 1: Dynamic Linking (5 minutes)
```bash
./configure --enable-llm
make && make install
```
✅ Works immediately  
✅ No system changes needed  
✅ Standard PHP extension approach  
⚠️ Requires `extension=llm.so` in php.ini

### Option 2: Upgrade glibc (30+ minutes, risky)
```bash
# Check current version
ldd --version

# Upgrade (Ubuntu example)
sudo apt update && sudo apt upgrade libc6
```
✅ Enables static builds  
✅ System-wide benefit  
⚠️ Can break system if done wrong  
⚠️ May require OS upgrade

### Option 3: Build in Container (15 minutes)
```bash
# Use Ubuntu 24.04 which has glibc 2.39
docker run -it ubuntu:24.04 bash

# Inside container:
apt update && apt install -y build-essential curl clang libclang-dev
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
source $HOME/.cargo/env

# Build PHP with static LLM
./configure --disable-shared --enable-llm
make -j$(nproc)
```
✅ Safe (isolated)  
✅ Reproducible builds  
✅ Works for CI/CD  
⚠️ Requires Docker

---

## Check Your glibc Version

```bash
ldd --version
```

**Output examples:**

```
ldd (GNU libc) 2.35  ← TOO OLD for static builds
ldd (GNU libc) 2.38  ← OK for static builds ✓
ldd (GNU libc) 2.39  ← OK for static builds ✓
```

---

## What Changed in glibc 2.38?

glibc 2.38 (August 2023) added new ISO C23-compliant functions:
- `__isoc23_strtol` (replaces old `strtol`)
- `__isoc23_strtoul` (replaces old `strtoul`)
- `__isoc23_strtoll` (replaces old `strtoll`)
- `__isoc23_strtoull` (replaces old `strtoull`)

Rust's C++ dependencies (protobuf, onnxruntime) use these when compiled on systems with glibc 2.38+.

---

## OS Versions with glibc 2.38+

| OS | Version | glibc |
|----|---------|-------|
| Ubuntu | 24.04 LTS | 2.39 ✓ |
| Ubuntu | 23.10 | 2.38 ✓ |
| Ubuntu | 22.04 LTS | 2.35 ✗ |
| Debian | 13 (Trixie) | 2.38 ✓ |
| Debian | 12 (Bookworm) | 2.36 ✗ |
| Fedora | 39+ | 2.38+ ✓ |
| RHEL | 9 | 2.34 ✗ |
| Alpine | 3.19+ | musl (different) |

---

## Recommended Approach

**For most users:**
```bash
./configure --enable-llm  # Dynamic linking
```

**For static builds:**
- Use Ubuntu 24.04 or newer
- Or build in Docker container
- Or upgrade your system glibc (risky)

---

## Verify Your Build

### Dynamic build:
```bash
# Check .so exists
ls -lh $(php-config --extension-dir)/llm.so

# Test loading
php -dextension=llm.so -r "var_dump(extension_loaded('llm'));"
# Output: bool(true) ✓
```

### Static build:
```bash
# Check symbol in binary
nm sapi/cli/php | grep get_module
# Should show: T get_module

# Test built-in
php -r "var_dump(extension_loaded('llm'));"
# Output: bool(true) ✓ (no -d flag needed)
```

---

## Still Having Issues?

1. **Clean everything:**
   ```bash
   make clean
   rm -f config.cache
   cargo clean  # In ext/llm directory
   ```

2. **Verify Rust is using correct glibc:**
   ```bash
   cd ext/llm
   cargo clean
   cargo build --release
   nm -D target/release/libllm.so | grep isoc23
   # If you see __isoc23_*, your Rust build uses new glibc
   ```

3. **Check PHP's configure output:**
   ```bash
   ./configure --enable-llm 2>&1 | grep -A5 "glibc"
   # Should show glibc version detection
   ```

4. **Use dynamic linking** - it just works! 🎉

---

## Summary

**Problem:** Static linking + old glibc = linker errors  
**Solution:** Use dynamic linking (`--enable-llm`)  
**Alternative:** Upgrade to glibc 2.38+ or build in container

**99% of users should use dynamic linking.** Static builds are only needed for special deployment scenarios.
