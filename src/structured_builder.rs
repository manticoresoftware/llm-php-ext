use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendClassObject, ZendHashTable as PhpArray, Zval};
use octolib::llm::{ChatCompletionParams, ProviderFactory, StructuredOutputRequest, TokenUsage};
use std::sync::Arc;
use tokio::runtime::Runtime;

use crate::convert::{json_value_to_php, php_to_messages};
use crate::error::IntoPhpException;
use crate::llm_class::Usage;

/// Builder for structured output
#[php_class]
pub struct StructuredBuilder {
    model: String,
    temperature: f32,
    max_tokens: u32,
    top_p: f32,
    schema: Option<String>,
    format: String,
    runtime: Arc<Runtime>,
}

// Internal constructor - not exposed to PHP
impl StructuredBuilder {
    pub(crate) fn new(
        model: String,
        temperature: f32,
        max_tokens: u32,
        top_p: f32,
        schema: Option<String>,
        runtime: Arc<Runtime>,
    ) -> Self {
        Self {
            model,
            temperature,
            max_tokens,
            top_p,
            schema,
            format: "json".to_string(),
            runtime,
        }
    }
}

#[php_impl]
impl StructuredBuilder {
    /// Complete with structured output
    pub fn complete(&self, messages: &Zval) -> PhpResult<StructuredResponse> {
        let this = self;
        let rt = this.runtime.clone();

        let messages_vec = php_to_messages(messages)?;

        let (provider, model) = rt
            .block_on(async { ProviderFactory::get_provider_for_model(&this.model) })
            .map_err(|e| e.into_php_exception())?;

        // Check if provider supports structured output
        if !provider.supports_structured_output(&model) {
            return Err(PhpException::from_class::<
                crate::error::LLMStructuredOutputException,
            >(
                "Structured output not supported by this provider/model".to_string(),
            ));
        }

        // Create structured output request
        let structured_request = if let Some(schema) = &this.schema {
            let schema_value: serde_json::Value = serde_json::from_str(schema).map_err(|e| {
                PhpException::from_class::<crate::error::LLMStructuredOutputException>(format!(
                    "Invalid JSON schema: {}",
                    e
                ))
            })?;
            StructuredOutputRequest::json_schema(schema_value)
        } else {
            StructuredOutputRequest::json()
        };

        let params = ChatCompletionParams::new(
            &messages_vec,
            &model,
            this.temperature,
            this.top_p,
            50,
            this.max_tokens,
        )
        .with_structured_output(structured_request);

        let response = rt
            .block_on(async { provider.chat_completion(params).await })
            .map_err(|e| e.into_php_exception())?;

        // Extract structured output
        let structured = response.structured_output.ok_or_else(|| {
            PhpException::from_class::<crate::error::LLMStructuredOutputException>(
                "No structured output in response".to_string(),
            )
        })?;

        let structured_php = json_value_to_php(&structured)?;

        Ok(StructuredResponse::new(
            response.content,
            structured_php,
            response.exchange.usage.unwrap_or(TokenUsage {
                prompt_tokens: 0,
                output_tokens: 0,
                reasoning_tokens: 0,
                total_tokens: 0,
                cached_tokens: 0,
                cost: None,
                request_time_ms: None,
            }),
            model,
        ))
    }

    /// Set JSON schema
    pub fn with_schema(
        self_: &mut ZendClassObject<StructuredBuilder>,
        schema: String,
    ) -> &mut ZendClassObject<StructuredBuilder> {
        self_.schema = Some(schema);
        self_
    }

    /// Set format ('json' or 'json_schema')
    pub fn with_format(
        self_: &mut ZendClassObject<StructuredBuilder>,
        format: String,
    ) -> &mut ZendClassObject<StructuredBuilder> {
        self_.format = format;
        self_
    }

    /// Set temperature
    pub fn set_temperature(
        self_: &mut ZendClassObject<StructuredBuilder>,
        temperature: f64,
    ) -> &mut ZendClassObject<StructuredBuilder> {
        self_.temperature = temperature as f32;
        self_
    }

    /// Set max tokens
    pub fn set_max_tokens(
        self_: &mut ZendClassObject<StructuredBuilder>,
        max_tokens: i64,
    ) -> &mut ZendClassObject<StructuredBuilder> {
        self_.max_tokens = max_tokens as u32;
        self_
    }
}

/// Structured response with JSON output
#[php_class]
pub struct StructuredResponse {
    content: String,
    structured: Zval,
    usage: Usage,
    model: String,
}

// Internal constructor - not exposed to PHP
impl StructuredResponse {
    pub(crate) fn new(content: String, structured: Zval, usage: TokenUsage, model: String) -> Self {
        Self {
            content,
            structured,
            usage: Usage::from_octo(usage),
            model,
        }
    }
}

#[php_impl]
impl StructuredResponse {
    pub fn get_content(&self) -> String {
        self.content.clone()
    }

    pub fn get_structured(&self) -> Zval {
        // Simple approach: return empty Zval for now
        // TODO: Fix Zval cloning issue in ext-php-rs 0.15.x
        Zval::new()
    }

    pub fn get_usage(&self) -> Usage {
        self.usage.clone()
    }

    pub fn get_model(&self) -> String {
        self.model.clone()
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("content", self.content.clone())?;

        // Recreate structured data since we can't clone Zval
        if let Some(json_str) = self.structured.string() {
            let mut nested = PhpArray::new();
            let _ = nested.insert("json", json_str);
            arr.insert("structured", nested)?;
        } else if let Some(inner_arr) = self.structured.array() {
            let mut nested = PhpArray::new();
            for (k, v) in inner_arr.iter() {
                // Extract values and re-insert them
                if let Some(s) = v.string() {
                    let _ = match k {
                        ext_php_rs::types::ArrayKey::String(key) => nested.insert(key, s),
                        ext_php_rs::types::ArrayKey::Long(idx) => nested.insert(idx, s),
                        ext_php_rs::types::ArrayKey::Str(_) => nested.insert(k, s),
                    };
                } else if let Some(i) = v.long() {
                    let _ = match k {
                        ext_php_rs::types::ArrayKey::String(key) => nested.insert(key, i),
                        ext_php_rs::types::ArrayKey::Long(idx) => nested.insert(idx, i),
                        ext_php_rs::types::ArrayKey::Str(_) => nested.insert(k, i),
                    };
                } else if let Some(b) = v.bool() {
                    let _ = match k {
                        ext_php_rs::types::ArrayKey::String(key) => nested.insert(key, b),
                        ext_php_rs::types::ArrayKey::Long(idx) => nested.insert(idx, b),
                        ext_php_rs::types::ArrayKey::Str(_) => nested.insert(k, b),
                    };
                }
            }
            arr.insert("structured", nested)?;
        }

        arr.insert("usage", self.usage.clone())?;
        arr.insert("model", self.model.clone())?;
        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        // Zval doesn't implement Serialize, so extract value manually
        let structured_value = if let Some(json_str) = self.structured.string() {
            serde_json::Value::String(json_str.to_string())
        } else if let Some(arr) = self.structured.array() {
            let mut map = serde_json::Map::new();
            for (k, v) in arr.iter() {
                let key = match k {
                    ext_php_rs::types::ArrayKey::Str(s) => s.to_string(),
                    ext_php_rs::types::ArrayKey::String(s) => s,
                    ext_php_rs::types::ArrayKey::Long(i) => i.to_string(),
                };
                let value = if let Some(s) = v.string() {
                    serde_json::Value::String(s.to_string())
                } else if let Some(i) = v.long() {
                    serde_json::Value::Number(i.into())
                } else if let Some(b) = v.bool() {
                    serde_json::Value::Bool(b)
                } else {
                    serde_json::Value::Null
                };
                map.insert(key, value);
            }
            serde_json::Value::Object(map)
        } else {
            serde_json::Value::Null
        };

        match serde_json::to_string(&serde_json::json!({
            "content": self.content,
            "structured": structured_value,
            "usage": {
                "prompt_tokens": self.usage.get_prompt_tokens(),
                "output_tokens": self.usage.get_output_tokens(),
                "total_tokens": self.usage.get_total_tokens(),
            },
            "model": self.model,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}
