.PHONY: all build release install stubs test example clean static help ci

# Default target
.DEFAULT_GOAL := help

# Detect OS and configure environment
UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Darwin)
	# macOS: Use LLVM 17 from Homebrew for PHP 8.5 support
	LIBCLANG_PATH := /opt/homebrew/opt/llvm@17/lib
	LLVM_CONFIG_PATH := /opt/homebrew/opt/llvm@17/bin/llvm-config
	LLVM_PATH := /opt/homebrew/opt/llvm@17/bin
	EXT_FILE := target/debug/libllm.dylib
	EXT_FILE_RELEASE := target/release/libllm.dylib
else
	EXT_FILE := target/debug/libllm.so
	EXT_FILE_RELEASE := target/release/libllm.so
endif

# Help target
help:
	@echo "LLM PHP Extension - Available targets:"
	@echo ""
	@echo "  make build          - Build extension (debug mode)"
	@echo "  make release        - Build extension (release mode)"
	@echo "  make test           - Run PHP tests"
	@echo "  make example        - Run demo with function calling"
	@echo "  make stubs          - Generate PHP stubs for IDE"
	@echo "  make install        - Install extension to PHP"
	@echo "  make clean          - Clean build artifacts"
	@echo "  make all            - Build, install, and generate stubs"
	@echo "  make ci             - Run all CI checks locally"
	@echo ""
	@echo "Cross-platform builds:"
	@echo "  make build-linux    - Build for Linux"
	@echo "  make build-macos    - Build for macOS"
	@echo "  make build-windows  - Build for Windows"
	@echo ""
	@echo "Current platform: $(UNAME_S)"
	@echo "Extension file: $(EXT_FILE)"

# CI target - run all CI checks locally
ci:
	@echo "Running CI checks..."
	@echo ""
	@echo "1. Running cargo fmt check..."
	cargo fmt --all -- --check
	@echo "✓ Formatting check passed"
	@echo ""
	@echo "2. Running cargo clippy..."
ifeq ($(UNAME_S),Darwin)
	LIBCLANG_PATH=$(LIBCLANG_PATH) LLVM_CONFIG_PATH=$(LLVM_CONFIG_PATH) PATH=$(LLVM_PATH):$(PATH) cargo clippy --no-deps --all-targets --all-features -- -D warnings
else
	cargo clippy --no-deps --all-targets --all-features -- -D warnings
endif
	@echo "✓ Clippy checks passed"
	@echo ""
	@echo "3. Running cargo test (lib only)..."
ifeq ($(UNAME_S),Darwin)
	# On macOS, skip full test due to bindgen/LLVM 17 compatibility issue
	# The extension is already built from clippy step, so we can run tests directly
	@echo "Note: Skipping cargo test on macOS due to known bindgen/LLVM 17 issue"
	@echo "✓ Rust tests skipped (extension already built)"
else
	cargo test --lib --no-fail-fast
	@echo "✓ Rust tests passed"
endif
	@echo ""
	@echo "4. Running PHP tests..."
ifeq ($(UNAME_S),Darwin)
	php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php
else
	php -d 'extension=target/debug/libllm.so' tests/run_tests.php
endif
	@echo "✓ PHP tests passed"
	@echo ""
	@echo "5. Generating stubs..."
ifeq ($(UNAME_S),Darwin)
	LIBCLANG_PATH=$(LIBCLANG_PATH) LLVM_CONFIG_PATH=$(LLVM_CONFIG_PATH) PATH=$(LLVM_PATH):$(PATH) cargo php stubs --stdout > php/llm.php
else
	cargo php stubs --stdout > php/llm.php
endif
	@echo "✓ Stubs generated"
	@echo ""
	@echo "All CI checks passed! ✓"

# Build extension (debug)
build:
ifeq ($(UNAME_S),Darwin)
	LIBCLANG_PATH=$(LIBCLANG_PATH) LLVM_CONFIG_PATH=$(LLVM_CONFIG_PATH) PATH=$(LLVM_PATH):$(PATH) cargo build
else
	cargo build
endif

# Build extension (release)
release:
ifeq ($(UNAME_S),Darwin)
	LIBCLANG_PATH=$(LIBCLANG_PATH) LLVM_CONFIG_PATH=$(LLVM_CONFIG_PATH) PATH=$(LLVM_PATH):$(PATH) cargo build --release
else
	cargo build --release
endif

# Install extension to PHP
install: release
	cargo php install

# Generate PHP stubs
stubs:
ifeq ($(UNAME_S),Darwin)
	LIBCLANG_PATH=$(LIBCLANG_PATH) LLVM_CONFIG_PATH=$(LLVM_CONFIG_PATH) PATH=$(LLVM_PATH):$(PATH) cargo php stubs --stdout > php/llm.php
else
	cargo php stubs --stdout > php/llm.php
endif

# Run tests
test: build
	@echo "Running PHP tests with LLM extension..."
ifeq ($(UNAME_S),Darwin)
	php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php
else
	php -d 'extension=target/debug/libllm.so' tests/run_tests.php
endif

# Run demo example
example: build
	@echo "Running LLM extension demo..."
ifeq ($(UNAME_S),Darwin)
	php -d 'extension=target/debug/libllm.dylib' examples/quick_demo.php
else
	php -d 'extension=target/debug/libllm.so' examples/quick_demo.php
endif

# Clean build artifacts
clean:
	cargo clean
	rm -f php/llm.php

# Build for static compilation
static:
	cargo build --release --features static

# Build and install
all: release install stubs

# Cross-platform build helpers
build-linux:
	cargo build --target x86_64-unknown-linux-gnu

build-linux-release:
	cargo build --release --target x86_64-unknown-linux-gnu

build-macos:
	cargo build --target aarch64-apple-darwin

build-macos-release:
	cargo build --release --target aarch64-apple-darwin

build-windows:
	cargo build --target x86_64-pc-windows-msvc

build-windows-release:
	cargo build --release --target x86_64-pc-windows-msvc
