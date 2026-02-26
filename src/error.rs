use ext_php_rs::exception::PhpException;
use ext_php_rs::prelude::*;
use octolib::errors::{ProviderError, StructuredOutputError, ToolCallError};

/// Convert octolib errors to PHP exceptions
pub trait IntoPhpException {
    fn into_php_exception(self) -> PhpException;
}

// Implement for references to avoid clone issues
impl IntoPhpException for &ProviderError {
    fn into_php_exception(self) -> PhpException {
        match self {
            ProviderError::NetworkError(msg) => {
                PhpException::from_class::<crate::error::LLMConnectionException>(msg.to_string())
            }
            ProviderError::ApiError {
                provider,
                status,
                message,
            } => PhpException::from_class::<crate::error::LLMConnectionException>(format!(
                "API Error [{}] ({}): {}",
                provider, status, message
            )),
            ProviderError::ModelNotSupported { model, provider } => {
                PhpException::from_class::<crate::error::LLMValidationException>(format!(
                    "Model '{}' not supported by provider '{}'",
                    model, provider
                ))
            }
            ProviderError::TimeoutError { provider } => {
                PhpException::from_class::<crate::error::LLMConnectionException>(format!(
                    "Request timeout for provider: {}",
                    provider
                ))
            }
            _ => PhpException::from_class::<crate::error::LLMException>(format!(
                "Provider error: {:?}",
                self
            )),
        }
    }
}

impl IntoPhpException for &StructuredOutputError {
    fn into_php_exception(self) -> PhpException {
        // Use a catch-all pattern since the enum structure may vary
        PhpException::from_class::<crate::error::LLMStructuredOutputException>(format!(
            "Structured output error: {:?}",
            self
        ))
    }
}

impl IntoPhpException for &ToolCallError {
    fn into_php_exception(self) -> PhpException {
        // Use a catch-all pattern since the enum structure may vary
        PhpException::from_class::<crate::error::LLMToolCallException>(format!(
            "Tool call error: {:?}",
            self
        ))
    }
}

impl IntoPhpException for anyhow::Error {
    fn into_php_exception(self) -> PhpException {
        // Try to downcast to known error types
        if let Some(err) = self.downcast_ref::<ProviderError>() {
            return err.into_php_exception();
        }
        if let Some(err) = self.downcast_ref::<StructuredOutputError>() {
            return err.into_php_exception();
        }
        if let Some(err) = self.downcast_ref::<ToolCallError>() {
            return err.into_php_exception();
        }

        // Fallback to generic exception
        PhpException::from_class::<crate::error::LLMException>(self.to_string())
    }
}

// Exception classes for PHP — all extend \Exception so they implement Throwable
// and can be caught with catch (\Throwable $e) in userland.
//
// We store message/code as #[php(prop)] fields so that Exception::getMessage()
// and Exception::getCode() work correctly. The __construct populates them;
// ext-php-rs requires a #[php_impl] block to allow PHP-side instantiation.

macro_rules! php_exception_class {
    ($rust_name:ident, $php_name:literal) => {
        #[php_class]
        #[php(name = $php_name, extends(ce = ext_php_rs::zend::ce::exception, stub = "\\Exception"))]
        pub struct $rust_name {
            #[php(prop, flags = ext_php_rs::flags::PropertyFlags::Protected)]
            message: String,
            #[php(prop, flags = ext_php_rs::flags::PropertyFlags::Protected)]
            code: i64,
        }

        #[php_impl]
        impl $rust_name {
            pub fn __construct(message: Option<String>, code: Option<i64>) -> Self {
                Self {
                    message: message.unwrap_or_default(),
                    code: code.unwrap_or(0),
                }
            }
        }
    };
}

php_exception_class!(LLMException, "LLMException");
php_exception_class!(LLMConnectionException, "LLMConnectionException");
php_exception_class!(LLMValidationException, "LLMValidationException");
php_exception_class!(LLMStructuredOutputException, "LLMStructuredOutputException");
php_exception_class!(LLMToolCallException, "LLMToolCallException");
