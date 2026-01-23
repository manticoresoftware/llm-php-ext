# Makefile fragment for LLM extension installation
# This file is included by PHP's build system when --enable-llm is used (shared mode)

# Variables are substituted by configure:
# @LLM_TARGET_DIR@ - Path to Rust target directory (e.g., /path/to/ext/llm/target/release)
# @EXT_SO@ - Extension filename (libllm.so, libllm.dylib, or llm.dll)

# Install the shared extension to PHP's extension directory
install-llm:
	@echo "Installing LLM extension..."
	@$(mkinstalldirs) $(INSTALL_ROOT)$(phplibdir)
	@if test -f "@LLM_TARGET_DIR@/@EXT_SO@"; then \
		$(INSTALL) -m 0755 "@LLM_TARGET_DIR@/@EXT_SO@" "$(INSTALL_ROOT)$(phplibdir)/llm.$(SHLIB_SUFFIX_NAME)"; \
		echo "LLM extension installed to $(INSTALL_ROOT)$(phplibdir)/llm.$(SHLIB_SUFFIX_NAME)"; \
	else \
		echo "ERROR: Extension file not found at @LLM_TARGET_DIR@/@EXT_SO@"; \
		exit 1; \
	fi

# Hook into PHP's install target
install: install-llm
