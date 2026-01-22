# CI Documentation

This document describes the continuous integration (CI) setup for the LLM PHP Extension.

## Overview

The CI pipeline runs on every push and pull request to `main` and `develop` branches. It ensures code quality, builds the extension on multiple platforms, and runs tests.

## CI Workflow

The CI workflow consists of the following jobs:

### 1. Rust Quality Checks

Runs on: `ubuntu-latest`

**Checks performed:**
- `cargo fmt --all -- --check` - Verifies code formatting
- `cargo clippy --all-targets --all-features -- -D warnings` - Lints code with clippy
- `cargo test --verbose` - Runs Rust unit tests

**Purpose:** Ensures code quality and catches common Rust issues early.

### 2. Ubuntu Build & Test

Runs on: `ubuntu-latest`

**Steps:**
1. Installs PHP and development tools
2. Builds extension in debug mode
3. Builds extension in release mode
4. Runs PHP integration tests
5. Generates PHP stubs
6. Verifies stubs were generated correctly

**Purpose:** Validates the extension builds and works on Linux.

### 3. macOS Build & Test

Runs on: `macos-latest`

**Steps:**
1. Installs LLVM 17 via Homebrew
2. Sets up environment variables for LLVM
3. Runs clippy with LLVM environment variables
4. Builds extension in debug mode
5. Builds extension in release mode
6. Runs PHP integration tests
7. Generates PHP stubs
8. Verifies stubs were generated correctly

**Purpose:** Validates extension builds and works on macOS with proper LLVM configuration. Clippy is run with the same LLVM environment as the build to ensure consistency.

### 4. CI Status

Runs on: `ubuntu-latest`

**Purpose:** Final status check that ensures all platform-specific jobs passed.

## Running CI Locally

You can run the entire CI pipeline locally using the Makefile:

```bash
make ci
```

This will execute all CI checks in order:

1. ✅ Check code formatting
2. ✅ Run clippy lints
3. ✅ Run Rust unit tests
4. ✅ Build the extension
5. ✅ Run PHP integration tests
6. ✅ Generate stubs

### Individual Checks

You can also run individual checks:

```bash
# Check formatting
cargo fmt --all -- --check

# Run clippy
cargo clippy --all-targets --all-features -- -D warnings

# Run Rust tests
cargo test --verbose

# Build extension
make build

# Run PHP tests
make test

# Generate stubs
make stubs
```

## Platform-Specific Requirements

### Ubuntu/Linux

- PHP 8.0+ with development headers
- Rust toolchain (stable)
- Standard build tools (gcc, make)

Install on Ubuntu:
```bash
sudo apt-get update
sudo apt-get install -y php php-dev php-cli php-json
```

### macOS

- PHP 8.0+ (usually pre-installed or via Homebrew)
- Rust toolchain (stable)
- LLVM 17 (required for PHP 8.5 support)

Install on macOS:
```bash
brew install llvm@17
```

The Makefile automatically detects macOS and sets the required environment variables:
- `LIBCLANG_PATH=/opt/homebrew/opt/llvm@17/lib`
- `LLVM_CONFIG_PATH=/opt/homebrew/opt/llvm@17/bin/llvm-config`

### Windows

Windows builds are supported via cross-compilation targets but not currently tested in CI.

## Tests

### Rust Unit Tests

Located in `tests/serialization_tests.rs`, these tests verify:
- JSON serialization/deserialization
- Message structure validation
- Tool parameters validation
- Error message formatting
- Complex nested structures

**No API keys required** - all tests use mock data.

### PHP Integration Tests

Located in `tests/` directory:
- `run_tests.php` - Test runner
- `LLMTest.php` - LLM class tests
- `MessageTest.php` - Message class tests
- `ToolTest.php` - Tool class tests

**No API keys required** - tests verify class instantiation, method calls, and data structures.

## Troubleshooting

### CI Fails on Formatting

If formatting check fails:
```bash
cargo fmt --all
```

### CI Fails on Clippy

If clippy finds issues:
```bash
cargo clippy --all-targets --all-features -- -D warnings
```

Review the warnings and fix them. Common issues:
- Unused variables
- Unnecessary clones
- Missing error handling

### CI Fails on macOS Build

**Error**: `thread 'main' panicked at bindgen/function.rs:259:34: Cannot turn unknown calling convention to tokens: 20`

**Solution**: This is a known issue with ext-php-rs bindgen and LLVM 17 on macOS. The issue is in the ext-php-rs dependency, not in our code.

**Workaround**: The CI will still catch code quality issues via clippy and fmt checks. For full macOS testing, run tests manually after a successful build:

```bash
# Build first (may fail on bindgen)
make build

# If build succeeds, run tests
make test
```

**Note**: This issue only affects the bindgen/build step. Our Rust code quality checks (fmt, clippy) work correctly on macOS.

### CI Fails on PHP Tests

If PHP tests fail:
```bash
# Check PHP version
php -v

# Verify extension is built
ls -la target/debug/libllm.so  # Linux
ls -la target/debug/libllm.dylib  # macOS

# Run tests manually
php -d 'extension=target/debug/libllm.so' tests/run_tests.php  # Linux
php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php  # macOS
```

### CI Fails on Stub Generation

If stub generation fails:
```bash
# Generate stubs manually
cargo php stubs --stdout > php/llm.php

# Verify stub file
cat php/llm.php
```

## Caching

The CI workflow uses GitHub Actions caching to speed up builds:

- `~/.cargo/registry` - Cargo registry cache
- `~/.cargo/git` - Cargo git dependencies cache
- `target/` - Build artifacts cache

Cache keys are based on `Cargo.lock` to ensure cache invalidation when dependencies change.

## Environment Variables

The CI workflow sets these environment variables:

- `CARGO_TERM_COLOR=always` - Colored cargo output
- `RUST_BACKTRACE=1` - Enable Rust backtraces for debugging

On macOS, additional variables are set:
- `LIBCLANG_PATH` - Path to LLVM library
- `LLVM_CONFIG_PATH` - Path to llvm-config
- `PATH` - Updated to include LLVM binaries

## Best Practices

1. **Run `make ci` before pushing** - Catch issues locally
2. **Keep dependencies updated** - Run `cargo update` regularly
3. **Write tests for new features** - Add both Rust and PHP tests
4. **Document API changes** - Update stubs and documentation
5. **Follow Rust style guidelines** - Use `cargo fmt` and `cargo clippy`

## Contributing

When contributing to this project:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run `make ci` locally
5. Push and create a pull request
6. Wait for CI to pass
7. Address any CI failures

## Additional Resources

- [ext-php-rs Documentation](https://github.com/davidcole1340/ext-php-rs)
- [Rust Documentation](https://doc.rust-lang.org/)
- [PHP Extension Development](https://www.php.net/manual/en/internals2.php)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
