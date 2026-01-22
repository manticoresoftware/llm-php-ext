use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendClassObject, ZendHashTable as PhpArray, Zval};
use octolib::llm::{ChatCompletionParams, ProviderFactory, TokenUsage};
use std::sync::Arc;
use tokio::runtime::Runtime;

use crate::convert::php_to_messages;
use crate::error::IntoPhpException;
use crate::tool_builder::Tool;

/// Main LLM class for interacting with language models
#[php_class]
#[allow(clippy::upper_case_acronyms)]
pub struct LLM {
    model: String,
    temperature: f32,
    max_tokens: u32,
    top_p: f32,
    frequency_penalty: f32,
    presence_penalty: f32,
    runtime: Arc<Runtime>,
}

#[php_impl]
impl LLM {
    /// Create a new LLM instance
    #[php(constructor)]
    pub fn __construct(model: String, _options: Option<&PhpArray>) -> PhpResult<Self> {
        let runtime = Arc::new(Runtime::new().map_err(|e| {
            PhpException::from_class::<crate::error::LLMException>(format!(
                "Failed to create runtime: {}",
                e
            ))
        })?);

        Ok(Self {
            model,
            temperature: 0.7,
            max_tokens: 1000,
            top_p: 1.0,
            frequency_penalty: 0.0,
            presence_penalty: 0.0,
            runtime,
        })
    }

    /// Complete a conversation
    pub fn complete(&self, messages: &Zval) -> PhpResult<Response> {
        let rt = self.runtime.clone();

        let messages_vec = php_to_messages(messages)?;

        let (provider, model) = rt
            .block_on(async { ProviderFactory::get_provider_for_model(&self.model) })
            .map_err(|e| e.into_php_exception())?;

        let params = ChatCompletionParams::new(
            &messages_vec,
            &model,
            self.temperature,
            self.top_p,
            50,
            self.max_tokens,
        );

        let response = rt
            .block_on(async { provider.chat_completion(params).await })
            .map_err(|e| e.into_php_exception())?;

        let usage = response.exchange.usage.unwrap_or(TokenUsage {
            prompt_tokens: 0,
            output_tokens: 0,
            reasoning_tokens: 0,
            total_tokens: 0,
            cached_tokens: 0,
            cost: None,
            request_time_ms: None,
        });

        Ok(Response::new(
            response.content,
            usage,
            model,
            response.finish_reason.unwrap_or_else(|| "stop".to_string()),
        ))
    }

    /// Create a builder for structured output
    pub fn structured(&self, schema: Option<String>) -> PhpResult<StructuredBuilder> {
        Ok(StructuredBuilder::new(
            self.model.clone(),
            self.temperature,
            self.max_tokens,
            self.top_p,
            schema,
            self.runtime.clone(),
        ))
    }

    /// Create a builder for tool calling
    pub fn with_tools(&self, tools: Option<&PhpArray>) -> PhpResult<ToolBuilder> {
        let tools_vec = if let Some(tools_arr) = tools {
            // Convert PHP array to Vec<Tool>
            let mut result = Vec::new();
            for (_, val) in tools_arr.iter() {
                // Extract Tool object from Zval
                if let Some(tool_obj) = val.extract::<&ZendClassObject<Tool>>() {
                    result.push((*tool_obj).clone());
                }
            }
            result
        } else {
            Vec::new()
        };

        Ok(ToolBuilder::new(
            self.model.clone(),
            self.temperature,
            self.max_tokens,
            self.top_p,
            tools_vec,
            self.runtime.clone(),
        ))
    }

    /// Set configuration options
    pub fn with_options<'a>(
        self_: &'a mut ZendClassObject<LLM>,
        options: &PhpArray,
    ) -> &'a mut ZendClassObject<LLM> {
        let s = &mut *self_;
        if let Some(temp) = options.get("temperature").and_then(|v| v.double()) {
            s.temperature = temp as f32;
        }
        if let Some(tokens) = options.get("max_tokens").and_then(|v| v.long()) {
            s.max_tokens = tokens as u32;
        }
        if let Some(top_p) = options.get("top_p").and_then(|v| v.double()) {
            s.top_p = top_p as f32;
        }
        if let Some(fp) = options.get("frequency_penalty").and_then(|v| v.double()) {
            s.frequency_penalty = fp as f32;
        }
        if let Some(pp) = options.get("presence_penalty").and_then(|v| v.double()) {
            s.presence_penalty = pp as f32;
        }
        self_
    }

    /// Set temperature
    pub fn set_temperature(
        self_: &mut ZendClassObject<LLM>,
        _temperature: f64,
    ) -> &mut ZendClassObject<LLM> {
        self_.temperature = _temperature as f32;
        self_
    }

    /// Set max tokens
    pub fn set_max_tokens(
        self_: &mut ZendClassObject<LLM>,
        _max_tokens: i64,
    ) -> &mut ZendClassObject<LLM> {
        self_.max_tokens = _max_tokens as u32;
        self_
    }

    /// Set top_p
    pub fn set_top_p(self_: &mut ZendClassObject<LLM>, _top_p: f64) -> &mut ZendClassObject<LLM> {
        self_.top_p = _top_p as f32;
        self_
    }

    /// Set frequency penalty
    pub fn set_frequency_penalty(
        self_: &mut ZendClassObject<LLM>,
        _penalty: f64,
    ) -> &mut ZendClassObject<LLM> {
        self_.frequency_penalty = _penalty as f32;
        self_
    }

    /// Set presence penalty
    pub fn set_presence_penalty(
        self_: &mut ZendClassObject<LLM>,
        _penalty: f64,
    ) -> &mut ZendClassObject<LLM> {
        self_.presence_penalty = _penalty as f32;
        self_
    }
}

/// Response from LLM completion
#[php_class]
pub struct Response {
    content: String,
    usage: Usage,
    model: String,
    finish_reason: String,
}

// Internal constructor - not exposed to PHP
impl Response {
    pub(crate) fn new(
        content: String,
        usage: TokenUsage,
        model: String,
        finish_reason: String,
    ) -> Self {
        Self {
            content,
            usage: Usage::from_octo(usage),
            model,
            finish_reason,
        }
    }
}

#[php_impl]
impl Response {
    pub fn get_content(&self) -> String {
        self.content.clone()
    }

    pub fn get_usage(&self) -> Usage {
        self.usage.clone()
    }

    pub fn get_model(&self) -> String {
        self.model.clone()
    }

    pub fn get_finish_reason(&self) -> String {
        self.finish_reason.clone()
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("content", self.content.clone())?;
        arr.insert("usage", self.usage.to_array()?)?;
        arr.insert("model", self.model.clone())?;
        arr.insert("finish_reason", self.finish_reason.clone())?;
        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        match serde_json::to_string(&serde_json::json!({
            "content": self.content,
            "usage": {
                "prompt_tokens": self.usage.get_prompt_tokens(),
                "output_tokens": self.usage.get_output_tokens(),
                "total_tokens": self.usage.get_total_tokens(),
            },
            "model": self.model,
            "finish_reason": self.finish_reason,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

/// Token usage information
#[php_class]
#[derive(Clone)]
pub struct Usage {
    prompt_tokens: i64,
    output_tokens: i64,
    total_tokens: i64,
}

// Internal constructor - not exposed to PHP
impl Usage {
    pub(crate) fn from_octo(usage: TokenUsage) -> Self {
        Self {
            prompt_tokens: usage.prompt_tokens as i64,
            output_tokens: usage.output_tokens as i64,
            total_tokens: usage.total_tokens as i64,
        }
    }
}

#[php_impl]
impl Usage {
    pub fn get_prompt_tokens(&self) -> i64 {
        self.prompt_tokens
    }

    pub fn get_output_tokens(&self) -> i64 {
        self.output_tokens
    }

    pub fn get_total_tokens(&self) -> i64 {
        self.total_tokens
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("prompt_tokens", self.prompt_tokens)?;
        arr.insert("output_tokens", self.output_tokens)?;
        arr.insert("total_tokens", self.total_tokens)?;
        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        match serde_json::to_string(&serde_json::json!({
            "prompt_tokens": self.prompt_tokens,
            "output_tokens": self.output_tokens,
            "total_tokens": self.total_tokens,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

// Forward declarations for builders
pub use crate::structured_builder::StructuredBuilder;
pub use crate::tool_builder::ToolBuilder;
