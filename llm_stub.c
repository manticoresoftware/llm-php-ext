/* Stub file for LLM extension registration
 * The actual extension is implemented in Rust (libllm.a)
 * This file bridges PHP's expectation of llm_module_entry with Rust's get_module()
 */

#include "php.h"
#include "php_llm.h"

/* Rust library exports get_module() function */
extern zend_module_entry *get_module(void);

/* Create the module entry variable that PHP expects */
zend_module_entry llm_module_entry;

/* Constructor to initialize the module entry from Rust */
static void __attribute__((constructor)) llm_init_module_entry(void) {
    zend_module_entry *rust_module = get_module();
    if (rust_module) {
        llm_module_entry = *rust_module;
    }
}
