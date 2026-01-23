# Debug: Check Rust Library Symbols

Please run these commands to see what symbols the Rust library exports:

```bash
# Check what symbols are in the Rust library
nm -g /workdir/build/ext/llm/target/release/libllm.a | grep -i module

# Check what symbols are in the stub object
nm -g ext/llm/llm_stub.o | grep -i module

# Check if get_module exists
nm -g /workdir/build/ext/llm/target/release/libllm.a | grep get_module
```

The issue might be:
1. Rust exports `get_module()` but not `llm_module_entry`
2. The symbol name is mangled
3. The symbol is not exported

Please share the output of these commands.
