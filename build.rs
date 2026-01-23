fn main() {
    // Configure the build for ext-php-rs
    // The build is handled by ext-php-rs automatically
    
    // For static builds on Linux with old glibc, provide compatibility wrappers
    // for __isoc23_* symbols that may be used by C++ dependencies
    #[cfg(target_os = "linux")]
    {
        // Check if we're building for static linking
        let profile = std::env::var("PROFILE").unwrap_or_default();
        
        // Create glibc compatibility wrapper
        println!("cargo:rerun-if-changed=build.rs");
        
        // Add wrapper object file for glibc compatibility
        // This provides __isoc23_* symbols that redirect to old symbols
        let wrapper_c = r#"
// GLIBC compatibility wrappers for __isoc23_* symbols
// These redirect new glibc 2.38+ symbols to old compatible versions

#include <stdlib.h>
#include <errno.h>

// Wrapper for strtol
long __isoc23_strtol(const char *nptr, char **endptr, int base) {
    return strtol(nptr, endptr, base);
}

// Wrapper for strtoul  
unsigned long __isoc23_strtoul(const char *nptr, char **endptr, int base) {
    return strtoul(nptr, endptr, base);
}

// Wrapper for strtoll
long long __isoc23_strtoll(const char *nptr, char **endptr, int base) {
    return strtoll(nptr, endptr, base);
}

// Wrapper for strtoull
unsigned long long __isoc23_strtoull(const char *nptr, char **endptr, int base) {
    return strtoull(nptr, endptr, base);
}
"#;
        
        // Write wrapper to temporary file
        let out_dir = std::env::var("OUT_DIR").unwrap();
        let wrapper_path = format!("{}/glibc_compat.c", out_dir);
        std::fs::write(&wrapper_path, wrapper_c).expect("Failed to write glibc wrapper");
        
        // Compile the wrapper
        cc::Build::new()
            .file(&wrapper_path)
            .compile("glibc_compat");
        
        println!("cargo:rustc-link-lib=static=glibc_compat");
    }
}

