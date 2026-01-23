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

  # Configure LLVM/Clang for ext-php-rs (required for bindgen)
  if test "$UNAME_S" = "Darwin"; then
    # macOS-specific configuration
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
    
    AC_MSG_NOTICE([Using LLVM from: $LLVM_PATH])
  else
    # Linux/Unix configuration
    AC_MSG_CHECKING([for libclang (required for ext-php-rs)])
    
    # Check if LIBCLANG_PATH is already set
    if test -n "$LIBCLANG_PATH" && test -d "$LIBCLANG_PATH"; then
      AC_MSG_RESULT([using LIBCLANG_PATH=$LIBCLANG_PATH])
    else
      # Try to find libclang in common locations (prefer newer versions)
      LIBCLANG_FOUND=0
      for CLANG_LIB_DIR in \
        /usr/lib/llvm-18/lib \
        /usr/lib/llvm-17/lib \
        /usr/lib/llvm-16/lib \
        /usr/lib/llvm-15/lib \
        /usr/lib/llvm-14/lib \
        /usr/lib/x86_64-linux-gnu \
        /usr/lib64 \
        /usr/lib \
        /usr/local/lib; do
        if test -f "$CLANG_LIB_DIR/libclang.so" || test -f "$CLANG_LIB_DIR/libclang.so.1"; then
          export LIBCLANG_PATH="$CLANG_LIB_DIR"
          LIBCLANG_FOUND=1
          AC_MSG_RESULT([found at $LIBCLANG_PATH])
          break
        fi
      done
      
      if test $LIBCLANG_FOUND -eq 0; then
        AC_MSG_ERROR([
          libclang not found. ext-php-rs requires libclang for building.
          Install it with:
            Ubuntu/Debian: sudo apt-get install libclang-dev clang
            Fedora/RHEL: sudo dnf install clang-devel
            Arch: sudo pacman -S clang
          Or set LIBCLANG_PATH environment variable to your libclang library directory.
        ])
      fi
    fi
    
    # Also set clang binary path for bindgen
    if test -d "/usr/lib/llvm-17/bin"; then
      export PATH="/usr/lib/llvm-17/bin:$PATH"
      AC_MSG_NOTICE([Using LLVM 17 binaries from: /usr/lib/llvm-17/bin])
    elif test -d "/usr/lib/llvm-18/bin"; then
      export PATH="/usr/lib/llvm-18/bin:$PATH"
      AC_MSG_NOTICE([Using LLVM 18 binaries from: /usr/lib/llvm-18/bin])
    fi
    
    AC_MSG_NOTICE([Using libclang from: $LIBCLANG_PATH])
  fi

  # Auto-detect build type based on PHP's configuration
  # Priority:
  #   1. Explicit --with-llm-static flag
  #   2. PHP built with --disable-shared or --enable-static (ext_shared=no)
  #   3. Default to shared
  
  AC_MSG_CHECKING([whether to build llm extension as static or shared])
  
  if test "$PHP_LLM_STATIC" != "no"; then
    # Explicit --with-llm-static flag
    BUILD_TYPE="static"
    AC_MSG_RESULT([static (explicit --with-llm-static)])
  elif test "$ext_shared" = "no"; then
    # PHP is built statically (--disable-shared or --enable-static)
    BUILD_TYPE="static"
    AC_MSG_RESULT([static (PHP built with --disable-shared or --enable-static)])
  else
    # Default to shared
    BUILD_TYPE="shared"
    AC_MSG_RESULT([shared (default)])
  fi
  
  # Determine extension filename based on build type
  if test "$BUILD_TYPE" = "static"; then
    # Static linking - build .a library
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
    # Shared linking - build .so/.dylib
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
  
  # Determine source directory based on build context
  # When building from PHP source tree with --enable-llm:
  #   - Configure runs from build directory (e.g., /workdir/build/)
  #   - Extension source is at: <php_src>/ext/llm/
  #   - We need to find the actual extension source directory
  
  # Try to locate the extension source directory
  # Method 1: Check if we're in a PHP source tree build (ext/llm exists relative to srcdir)
  if test -f "$srcdir/ext/llm/Cargo.toml"; then
    LLM_SRC_DIR="$srcdir/ext/llm"
  # Method 2: Check one level up (for out-of-tree builds)
  elif test -f "../ext/llm/Cargo.toml"; then
    LLM_SRC_DIR="../ext/llm"
  # Method 3: Use srcdir directly (for phpize builds)
  else
    LLM_SRC_DIR="$srcdir"
  fi
  
  # Convert to absolute path
  LLM_SRC_DIR=$(cd "$LLM_SRC_DIR" && pwd)
  CARGO_MANIFEST="$LLM_SRC_DIR/Cargo.toml"
  
  # Verify Cargo.toml exists
  if test ! -f "$CARGO_MANIFEST"; then
    AC_MSG_ERROR([Cargo.toml not found at $CARGO_MANIFEST. Searched in: srcdir=$srcdir, srcdir/ext/llm, ../ext/llm])
  fi
  
  AC_MSG_NOTICE([Using Cargo.toml from: $CARGO_MANIFEST])
  
  # For static builds on Linux, inform about glibc compatibility
  if test "$BUILD_TYPE" = "static" && test "$UNAME_S" != "Darwin"; then
    AC_MSG_CHECKING([glibc version])
    GLIBC_VERSION=$(ldd --version 2>/dev/null | head -n1 | awk '{print $NF}')
    if test -n "$GLIBC_VERSION"; then
      AC_MSG_RESULT([$GLIBC_VERSION])
      AC_MSG_NOTICE([
        The extension includes glibc compatibility wrappers in build.rs
        that provide __isoc23_* symbols for older glibc versions.
        Static builds should work on glibc 2.17+ systems.
      ])
    else
      AC_MSG_RESULT([unknown])
    fi
  fi
  
  # Build with appropriate environment and manifest path
  if test "$UNAME_S" = "Darwin"; then
    # macOS build with LLVM
    LIBCLANG_PATH="$LIBCLANG_PATH" \
    LLVM_CONFIG_PATH="$LLVM_CONFIG_PATH" \
    PATH="$LLVM_PATH/bin:$PATH" \
    cargo $CARGO_BUILD_MODE --manifest-path="$CARGO_MANIFEST" 2>&1
  else
    # Linux/Unix build with libclang
    # Set bindgen-specific environment variables to use correct clang version
    LIBCLANG_PATH="$LIBCLANG_PATH" \
    CLANG_PATH="$LIBCLANG_PATH/../bin/clang" \
    BINDGEN_EXTRA_CLANG_ARGS="-I$LIBCLANG_PATH/../lib/clang/$(ls $LIBCLANG_PATH/../lib/clang 2>/dev/null | sort -V | tail -1)/include" \
    cargo $CARGO_BUILD_MODE --manifest-path="$CARGO_MANIFEST" 2>&1
  fi

  if test $? -ne 0; then
    AC_MSG_ERROR([Failed to build extension with cargo])
  fi

  AC_MSG_RESULT([success])
  
  # Verify the extension was built
  # The target directory is relative to the source directory
  LLM_TARGET_DIR="$LLM_SRC_DIR/$CARGO_TARGET_DIR"
  
  if test "$BUILD_TYPE" = "static"; then
    if test ! -f "$LLM_TARGET_DIR/$EXT_LIB"; then
      AC_MSG_ERROR([Static library not found: $LLM_TARGET_DIR/$EXT_LIB])
    fi
  else
    if test ! -f "$LLM_TARGET_DIR/$EXT_SO"; then
      AC_MSG_ERROR([Extension file not found: $LLM_TARGET_DIR/$EXT_SO])
    fi
  fi
  # Handle static vs shared linking
  if test "$BUILD_TYPE" = "static"; then
    # Static linking: Embed the Rust static library directly into PHP binary
    AC_MSG_CHECKING([configuring static linking])
    
    # Get the full path to the static library
    LLM_STATIC_LIB="$LLM_TARGET_DIR/$EXT_LIB"
    
    # Add the static library directly to PHP's linker flags
    # This embeds the extension into the PHP binary
    EXTRA_LIBS="$EXTRA_LIBS $LLM_STATIC_LIB"
    
    # Add required system libraries that Rust depends on
    case "$UNAME_S" in
      Darwin)
        # macOS: link against System framework and other required libs
        EXTRA_LIBS="$EXTRA_LIBS -framework System -framework CoreFoundation -framework Security"
        ;;
      Linux)
        # Linux: link against pthread, dl, etc.
        EXTRA_LIBS="$EXTRA_LIBS -lpthread -ldl -lm -lgcc_s"
        ;;
    esac
    
    # Register the extension as built-in (no C sources needed)
    # ext-php-rs already provides get_module() symbol in the .a file
    PHP_ADD_BUILD_DIR(ext/llm, 1)
    
    PHP_SUBST(EXTRA_LIBS)
    AC_MSG_RESULT([success - extension will be embedded in PHP binary])
    
  else
    # Shared linking: Install the .so file to PHP extensions directory
    AC_MSG_CHECKING([configuring shared linking])
    
    # Export variables for Makefile.frag
    AC_SUBST(LLM_TARGET_DIR)
    AC_SUBST(EXT_SO)
    
    # Create installation rule for the shared extension
    PHP_ADD_MAKEFILE_FRAGMENT([Makefile.frag])
    
    AC_MSG_RESULT([success - extension will be installed during 'make install'])
  fi

  AC_MSG_NOTICE([
    ========================================
    LLM Extension Configuration Complete
    ========================================
    Extension: llm
    Build type: $BUILD_TYPE
    Build mode: $CARGO_BUILD_MODE
    OS: $UNAME_S
    Source dir: $LLM_SRC_DIR
  ])
  
  if test "$BUILD_TYPE" = "static"; then
    AC_MSG_NOTICE([Static library: $LLM_TARGET_DIR/$EXT_LIB])
  else
    AC_MSG_NOTICE([Shared library: $LLM_TARGET_DIR/$EXT_SO])
    AC_MSG_NOTICE([Extension will be installed by 'make install'])
  fi
fi
