use ext_php_rs::exception::PhpException;
use ext_php_rs::php_class;
use ext_php_rs::php_impl;
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

// Exception classes for PHP
#[php_class]
#[php(name = "LLMException")]
pub struct LLMException;

#[php_impl]
impl LLMException {
    #[php(constructor)]
    pub fn __construct(_message: String, _code: i64) -> Self {
        // This is just a marker, actual exception is thrown by ext-php-rs
        Self
    }
}

#[php_class]
#[php(name = "LLMConnectionException")]
pub struct LLMConnectionException;

#[php_impl]
impl LLMConnectionException {
    #[php(constructor)]
    pub fn __construct(_message: String, _code: i64) -> Self {
        Self
    }
}

#[php_class]
#[php(name = "LLMValidationException")]
pub struct LLMValidationException;

#[php_impl]
impl LLMValidationException {
    #[php(constructor)]
    pub fn __construct(_message: String, _code: i64) -> Self {
        Self
    }
}

#[php_class]
#[php(name = "LLMStructuredOutputException")]
pub struct LLMStructuredOutputException;

#[php_impl]
impl LLMStructuredOutputException {
    #[php(constructor)]
    pub fn __construct(_message: String, _code: i64) -> Self {
        Self
    }
}

#[php_class]
#[php(name = "LLMToolCallException")]
pub struct LLMToolCallException;

#[php_impl]
impl LLMToolCallException {
    #[php(constructor)]
    pub fn __construct(_message: String, _code: i64) -> Self {
        Self
    }
}
