PHP_ARG_ENABLE([llm],
  [whether to enable llm extension support],
  [AS_HELP_STRING([--enable-llm],
    [Enable llm extension support])],
  [no])

if test "$PHP_LLM" != "no"; then
  AC_MSG_CHECKING([for cargo])
  if command -v cargo >/dev/null 2>&1; then
    CARGO_VERSION=$(cargo --version | awk '{print $2}')
    AC_MSG_RESULT([found $CARGO_VERSION])
  else
    AC_MSG_ERROR([cargo not found. Please install Rust from https://rustup.rs/])
  fi

  AC_MSG_CHECKING([for rustc])
  if command -v rustc >/dev/null 2>&1; then
    RUSTC_VERSION=$(rustc --version | awk '{print $2}')
    AC_MSG_RESULT([found $RUSTC_VERSION])
  else
    AC_MSG_ERROR([rustc not found. Please install Rust from https://rustup.rs/])
  fi

  # Detect OS
  AC_MSG_CHECKING([operating system])
  UNAME_S=$(uname -s)
  AC_MSG_RESULT([$UNAME_S])

  # macOS-specific configuration
  if test "$UNAME_S" = "Darwin"; then
    AC_MSG_CHECKING([for LLVM 17 (required for PHP 8.5 on macOS)])
    
    # Check for LLVM 17 in common locations
    if test -d "/opt/homebrew/opt/llvm@17"; then
      LLVM_PATH="/opt/homebrew/opt/llvm@17"
      AC_MSG_RESULT([found at $LLVM_PATH])
    elif test -d "/usr/local/opt/llvm@17"; then
      LLVM_PATH="/usr/local/opt/llvm@17"
      AC_MSG_RESULT([found at $LLVM_PATH])
    elif test -n "$LLVM_PATH" && test -d "$LLVM_PATH"; then
      AC_MSG_RESULT([using LLVM_PATH=$LLVM_PATH])
    else
      AC_MSG_ERROR([
        LLVM 17 not found. On macOS with PHP 8.5, LLVM 17 is required.
        Install it with: brew install llvm@17
        Or set LLVM_PATH environment variable to your LLVM 17 installation.
      ])
    fi

    # Set LLVM environment variables for the build
    export LIBCLANG_PATH="$LLVM_PATH/lib"
    export LLVM_CONFIG_PATH="$LLVM_PATH/bin/llvm-config"
    export PATH="$LLVM_PATH/bin:$PATH"
    
    AC_MSG_NOTICE([Using LLVM 17 from: $LLVM_PATH])
  fi

  # Determine extension filename based on OS
  case "$UNAME_S" in
    Darwin)
      EXT_SO="libllm.dylib"
      ;;
    MINGW*|MSYS*|CYGWIN*)
      EXT_SO="llm.dll"
      ;;
    *)
      EXT_SO="libllm.so"
      ;;
  esac

  # Build the extension using cargo
  AC_MSG_CHECKING([building llm extension with cargo])
  
  # Set build mode based on PHP build configuration
  if test "$PHP_DEBUG" = "yes"; then
    CARGO_BUILD_MODE="build"
    CARGO_TARGET_DIR="target/debug"
  else
    CARGO_BUILD_MODE="build --release"
    CARGO_TARGET_DIR="target/release"
  fi

  # Build with appropriate environment
  if test "$UNAME_S" = "Darwin"; then
    LIBCLANG_PATH="$LIBCLANG_PATH" \
    LLVM_CONFIG_PATH="$LLVM_CONFIG_PATH" \
    PATH="$LLVM_PATH/bin:$PATH" \
    cargo $CARGO_BUILD_MODE 2>&1
  else
    cargo $CARGO_BUILD_MODE 2>&1
  fi

  if test $? -ne 0; then
    AC_MSG_ERROR([Failed to build extension with cargo])
  fi

  AC_MSG_RESULT([success])

  # Verify the extension was built
  if test ! -f "$CARGO_TARGET_DIR/$EXT_SO"; then
    AC_MSG_ERROR([Extension file not found: $CARGO_TARGET_DIR/$EXT_SO])
  fi

  # Copy the built extension to the PHP extension directory
  AC_MSG_CHECKING([for PHP extension directory])
  PHP_EXT_DIR=$($PHP_CONFIG --extension-dir 2>/dev/null)
  if test -z "$PHP_EXT_DIR"; then
    PHP_EXT_DIR="modules"
  fi
  AC_MSG_RESULT([$PHP_EXT_DIR])

  # Copy the extension
  AC_MSG_CHECKING([copying extension to $PHP_EXT_DIR])
  $PHP_SHELL -c "cp $CARGO_TARGET_DIR/$EXT_SO $PHP_EXT_DIR/llm.so"
  if test $? -ne 0; then
    AC_MSG_ERROR([Failed to copy extension to $PHP_EXT_DIR])
  fi
  AC_MSG_RESULT([success])

  # Define the extension name for PHP
  PHP_NEW_EXTENSION(llm, llm.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
  
  # Since we're building with cargo, we don't need PHP to compile anything
  # Override the build commands
  PHP_SUBST(LLM_SHARED_LIBADD)
  PHP_SUBST(EXT_LLM)

  AC_MSG_NOTICE([
    ========================================
    LLM Extension Configuration Complete
    ========================================
    Extension: llm
    Build mode: $CARGO_BUILD_MODE
    Extension file: $PHP_EXT_DIR/llm.so
    OS: $UNAME_S
  ])
fi
