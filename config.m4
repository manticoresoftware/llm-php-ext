PHP_ARG_ENABLE([llm],
  [whether to enable llm extension support],
  [AS_HELP_STRING([--enable-llm],
    [Enable llm extension support])],
  [no])

PHP_ARG_WITH([llm-static],
  [whether to statically link llm extension],
  [AS_HELP_STRING([--with-llm-static],
    [Statically link llm extension into PHP])],
  [no],
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

  # Determine extension filename and build type based on static/shared
  if test "$PHP_LLM_STATIC" != "no"; then
    # Static linking
    BUILD_TYPE="static"
    case "$UNAME_S" in
      Darwin)
        EXT_LIB="libllm.a"
        ;;
      MINGW*|MSYS*|CYGWIN*)
        EXT_LIB="llm.lib"
        ;;
      *)
        EXT_LIB="libllm.a"
        ;;
    esac
    CARGO_BUILD_MODE="build --release"
    CARGO_TARGET_DIR="target/release"
  else
    # Shared linking (default)
    BUILD_TYPE="shared"
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
    
    # Set build mode based on PHP build configuration
    if test "$PHP_DEBUG" = "yes"; then
      CARGO_BUILD_MODE="build"
      CARGO_TARGET_DIR="target/debug"
    else
      CARGO_BUILD_MODE="build --release"
      CARGO_TARGET_DIR="target/release"
    fi
  fi

  # Build the extension using cargo
  AC_MSG_CHECKING([building llm extension with cargo ($BUILD_TYPE mode)])
  
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
  if test "$BUILD_TYPE" = "static"; then
    if test ! -f "$CARGO_TARGET_DIR/$EXT_LIB"; then
      AC_MSG_ERROR([Static library not found: $CARGO_TARGET_DIR/$EXT_LIB])
    fi
  else
    if test ! -f "$CARGO_TARGET_DIR/$EXT_SO"; then
      AC_MSG_ERROR([Extension file not found: $CARGO_TARGET_DIR/$EXT_SO])
    fi
  fi

  # Handle static vs shared linking
  if test "$BUILD_TYPE" = "static"; then
    # Static linking: Add the static library to PHP's build
    AC_MSG_CHECKING([configuring static linking])
    
    # Get the full path to the static library
    LLM_STATIC_LIB="$abs_srcdir/$CARGO_TARGET_DIR/$EXT_LIB"
    
    # Add the static library to EXTRA_LDFLAGS
    EXTRA_LDFLAGS="$EXTRA_LDFLAGS $LLM_STATIC_LIB"
    
    # For static linking, we need to link against system libraries that Rust might need
    case "$UNAME_S" in
      Darwin)
        # macOS: link against System framework and other required libs
        EXTRA_LDFLAGS="$EXTRA_LDFLAGS -framework System -framework CoreFoundation -framework Security"
        ;;
      Linux)
        # Linux: link against pthread, dl, etc.
        EXTRA_LDFLAGS="$EXTRA_LDFLAGS -lpthread -ldl -lm"
        ;;
    esac
    
    PHP_SUBST(EXTRA_LDFLAGS)
    AC_MSG_RESULT([success])
    
    # Define the extension for PHP (static linking)
    PHP_NEW_EXTENSION(llm, llm.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
    
    # Add the static library to the extension's shared libadd
    PHP_ADD_LIBRARY_WITH_PATH(llm, $CARGO_TARGET_DIR, LLM_SHARED_LIBADD)
    PHP_SUBST(LLM_SHARED_LIBADD)
    
  else
    # Shared linking: Copy the built extension to the PHP extension directory
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

    # Define the extension name for PHP (shared linking)
    PHP_NEW_EXTENSION(llm, llm.c, $ext_shared,, -DZEND_ENABLE_STATIC_TSRMLS_CACHE=1)
    
    # Since we're building with cargo, we don't need PHP to compile anything
    # Override the build commands
    PHP_SUBST(LLM_SHARED_LIBADD)
    PHP_SUBST(EXT_LLM)
  fi

  AC_MSG_NOTICE([
    ========================================
    LLM Extension Configuration Complete
    ========================================
    Extension: llm
    Build type: $BUILD_TYPE
    Build mode: $CARGO_BUILD_MODE
    OS: $UNAME_S
  ])
  
  if test "$BUILD_TYPE" = "static"; then
    AC_MSG_NOTICE([Static library: $CARGO_TARGET_DIR/$EXT_LIB])
  else
    AC_MSG_NOTICE([Extension file: $PHP_EXT_DIR/llm.so])
  fi
fi
