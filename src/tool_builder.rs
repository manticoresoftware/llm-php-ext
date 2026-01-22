use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendClassObject, ZendHashTable as PhpArray, Zval};
use octolib::llm::{ChatCompletionParams, FunctionDefinition, ProviderFactory, TokenUsage};
use serde_json::Value;
use std::sync::Arc;
use tokio::runtime::Runtime;

use crate::convert::php_to_messages;
use crate::error::IntoPhpException;
use crate::llm_class::Usage;

/// Recursively convert PHP Zval to serde_json::Value
fn zval_to_json_value(zval: &Zval) -> serde_json::Value {
    if let Some(s) = zval.string() {
        serde_json::Value::String(s.to_string())
    } else if let Some(i) = zval.long() {
        serde_json::Value::Number(i.into())
    } else if let Some(f) = zval.double() {
        serde_json::Number::from_f64(f)
            .map(serde_json::Value::Number)
            .unwrap_or(serde_json::Value::Null)
    } else if let Some(b) = zval.bool() {
        serde_json::Value::Bool(b)
    } else if let Some(arr) = zval.array() {
        // Check if it's an associative array (object) or indexed array
        let mut is_object = false;
        for (k, _) in arr.iter() {
            match k {
                ext_php_rs::types::ArrayKey::Str(_) | ext_php_rs::types::ArrayKey::String(_) => {
                    is_object = true;
                    break;
                }
                _ => {}
            }
        }

        if is_object {
            // Convert to JSON object
            let mut map = serde_json::Map::new();
            for (k, v) in arr.iter() {
                let key = match k {
                    ext_php_rs::types::ArrayKey::Str(s) => s.to_string(),
                    ext_php_rs::types::ArrayKey::String(s) => s,
                    ext_php_rs::types::ArrayKey::Long(i) => i.to_string(),
                };
                map.insert(key, zval_to_json_value(v));
            }
            serde_json::Value::Object(map)
        } else {
            // Convert to JSON array
            let mut vec = Vec::new();
            for (_, v) in arr.iter() {
                vec.push(zval_to_json_value(v));
            }
            serde_json::Value::Array(vec)
        }
    } else {
        serde_json::Value::Null
    }
}

/// Tool definition
#[php_class]
#[derive(Clone)]
pub struct Tool {
    pub(crate) name: String,
    pub(crate) description: String,
    pub(crate) parameters: String, // JSON string
}

#[php_impl]
impl Tool {
    /// Create a tool definition
    #[php(constructor)]
    pub fn __construct(
        name: String,
        description: String,
        parameters: &mut Zval,
    ) -> PhpResult<Self> {
        // Convert Zval to JSON string using recursive conversion
        let params_json = if let Some(s) = parameters.string() {
            s.to_string()
        } else if parameters.array().is_some() {
            // Use recursive conversion for nested arrays
            let json_value = zval_to_json_value(parameters);
            serde_json::to_string(&json_value).map_err(|e| {
                PhpException::from_class::<crate::error::LLMValidationException>(format!(
                    "Invalid JSON schema: {}",
                    e
                ))
            })?
        } else {
            return Err(PhpException::from_class::<
                crate::error::LLMValidationException,
            >(
                "Parameters must be a string or array".to_string()
            ));
        };

        // Validate it's valid JSON
        serde_json::from_str::<Value>(&params_json).map_err(|e| {
            PhpException::from_class::<crate::error::LLMValidationException>(format!(
                "Invalid JSON schema: {}",
                e
            ))
        })?;

        Ok(Self {
            name,
            description,
            parameters: params_json,
        })
    }

    /// Create from array
    pub fn from_array(data: &PhpArray) -> PhpResult<Self> {
        let name = data
            .get("name")
            .and_then(|v| v.str())
            .ok_or_else(|| {
                PhpException::from_class::<crate::error::LLMValidationException>(
                    "Tool must have 'name' field".to_string(),
                )
            })?
            .to_string();

        let description = data
            .get("description")
            .and_then(|v| v.str())
            .ok_or_else(|| {
                PhpException::from_class::<crate::error::LLMValidationException>(
                    "Tool must have 'description' field".to_string(),
                )
            })?
            .to_string();

        let parameters = data.get("parameters").ok_or_else(|| {
            PhpException::from_class::<crate::error::LLMValidationException>(
                "Tool must have 'parameters' field".to_string(),
            )
        })?;

        // Convert Zval to JSON string using recursive conversion
        let params_json = if let Some(s) = parameters.string() {
            s.to_string()
        } else if parameters.array().is_some() {
            let json_value = zval_to_json_value(parameters);
            serde_json::to_string(&json_value).map_err(|e| {
                PhpException::from_class::<crate::error::LLMValidationException>(format!(
                    "Invalid JSON schema: {}",
                    e
                ))
            })?
        } else {
            return Err(PhpException::from_class::<
                crate::error::LLMValidationException,
            >(
                "Parameters must be a string or array".to_string()
            ));
        };

        Ok(Self {
            name,
            description,
            parameters: params_json,
        })
    }

    pub fn get_name(&self) -> String {
        self.name.clone()
    }

    pub fn get_description(&self) -> String {
        self.description.clone()
    }

    pub fn get_parameters(&self) -> String {
        self.parameters.clone()
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("name", self.name.clone())?;
        arr.insert("description", self.description.clone())?;
        arr.insert("parameters", self.parameters.clone())?;
        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        match serde_json::to_string(&serde_json::json!({
            "name": self.name,
            "description": self.description,
            "parameters": self.parameters,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

// Internal methods - not exposed to PHP
impl Tool {
    fn to_octo(&self) -> Result<FunctionDefinition, PhpException> {
        let params_value: Value = serde_json::from_str(&self.parameters).map_err(|e| {
            PhpException::from_class::<crate::error::LLMValidationException>(format!(
                "Invalid parameters JSON: {}",
                e
            ))
        })?;

        Ok(FunctionDefinition {
            name: self.name.clone(),
            description: self.description.clone(),
            parameters: params_value,
            cache_control: None,
        })
    }
}

/// Tool call from LLM
#[php_class]
pub struct ToolCall {
    id: String,
    name: String,
    arguments_json: String, // Store as JSON string to avoid Zval clone issues
}

// Manual Clone implementation
impl Clone for ToolCall {
    fn clone(&self) -> Self {
        Self {
            id: self.id.clone(),
            name: self.name.clone(),
            arguments_json: self.arguments_json.clone(),
        }
    }
}

// Internal constructor - not exposed to PHP
impl ToolCall {
    pub(crate) fn new(id: String, name: String, arguments: Value) -> PhpResult<Self> {
        // Store arguments as JSON string
        let arguments_json = serde_json::to_string(&arguments)
            .map_err(|e| PhpException::default(format!("Failed to serialize arguments: {}", e)))?;

        Ok(Self {
            id,
            name,
            arguments_json,
        })
    }

    // Internal method to get arguments as JSON string
    pub(crate) fn get_arguments_json(&self) -> &str {
        &self.arguments_json
    }
}

#[php_impl]
impl ToolCall {
    pub fn get_id(&self) -> String {
        self.id.clone()
    }

    pub fn get_name(&self) -> String {
        self.name.clone()
    }

    pub fn get_arguments(&self) -> Zval {
        // Parse JSON string and convert to PHP array
        match serde_json::from_str::<Value>(&self.arguments_json) {
            Ok(json_value) => match crate::convert::json_value_to_php(&json_value) {
                Ok(zval) => zval,
                Err(_) => Zval::new(),
            },
            Err(_) => Zval::new(),
        }
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("id", self.id.clone())?;
        arr.insert("name", self.name.clone())?;

        // Parse JSON and convert to PHP array
        if let Ok(json_value) = serde_json::from_str::<Value>(&self.arguments_json) {
            if let Ok(args_zval) = crate::convert::json_value_to_php(&json_value) {
                arr.insert("arguments", args_zval)?;
            }
        }

        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        match serde_json::to_string(&serde_json::json!({
            "id": self.id,
            "name": self.name,
            "arguments": self.arguments_json,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

/// Response with tool calls
#[php_class]
pub struct ToolResponse {
    content: String,
    tool_calls: Vec<ToolCall>,
    usage: Usage,
    model: String,
    response_id: Option<String>,
}

// Internal constructor - not exposed to PHP
impl ToolResponse {
    pub(crate) fn new_with_opt_usage(
        content: String,
        tool_calls: Vec<ToolCall>,
        usage: Option<TokenUsage>,
        model: String,
        response_id: Option<String>,
    ) -> Self {
        let usage = usage.unwrap_or(TokenUsage {
            prompt_tokens: 0,
            output_tokens: 0,
            reasoning_tokens: 0,
            total_tokens: 0,
            cached_tokens: 0,
            cost: None,
            request_time_ms: None,
        });
        Self {
            content,
            tool_calls,
            usage: Usage::from_octo(usage),
            model,
            response_id,
        }
    }
}

#[php_impl]
impl ToolResponse {
    pub fn get_content(&self) -> String {
        self.content.clone()
    }

    pub fn get_tool_calls(&self) -> Vec<ToolCall> {
        self.tool_calls.to_vec()
    }

    pub fn get_usage(&self) -> Usage {
        self.usage.clone()
    }

    pub fn get_model(&self) -> String {
        self.model.clone()
    }

    pub fn get_response_id(&self) -> Option<String> {
        self.response_id.clone()
    }

    pub fn has_tool_calls(&self) -> bool {
        !self.tool_calls.is_empty()
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("content", self.content.clone())?;

        let mut calls_arr = PhpArray::new();
        for call in &self.tool_calls {
            calls_arr.push(call.clone().into_zval(false)?)?;
        }
        arr.insert("tool_calls", calls_arr)?;

        arr.insert("usage", self.usage.clone())?;
        arr.insert("model", self.model.clone())?;
        if let Some(ref resp_id) = self.response_id {
            arr.insert("response_id", &**resp_id)?;
        }
        Ok(arr.into_zval(false)?)
    }

    pub fn to_json(&self) -> PhpResult<String> {
        let calls: Vec<serde_json::Value> = self
            .tool_calls
            .iter()
            .map(|call| {
                serde_json::json!({
                    "id": call.id,
                    "name": call.name,
                    "arguments": call.arguments_json,
                })
            })
            .collect();

        match serde_json::to_string(&serde_json::json!({
            "content": self.content,
            "tool_calls": calls,
            "usage": {
                "prompt_tokens": self.usage.get_prompt_tokens(),
                "output_tokens": self.usage.get_output_tokens(),
                "total_tokens": self.usage.get_total_tokens(),
            },
            "model": self.model,
            "response_id": self.response_id,
        })) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

/// Builder for tool calling
#[php_class]
pub struct ToolBuilder {
    model: String,
    temperature: f32,
    max_tokens: u32,
    top_p: f32,
    tools: Vec<Tool>,
    auto_execute: bool,
    runtime: Arc<Runtime>,
}

// Internal constructor - not exposed to PHP
impl ToolBuilder {
    pub(crate) fn new(
        model: String,
        temperature: f32,
        max_tokens: u32,
        top_p: f32,
        tools: Vec<Tool>,
        runtime: Arc<Runtime>,
    ) -> Self {
        Self {
            model,
            temperature,
            max_tokens,
            top_p,
            tools,
            auto_execute: false,
            runtime,
        }
    }
}

#[php_impl]
impl ToolBuilder {
    /// Complete with tool calling
    pub fn complete(&self, messages: &Zval) -> PhpResult<ToolResponse> {
        let this = self;
        let rt = this.runtime.clone();

        let messages_vec = php_to_messages(messages)?;

        let (provider, model) = rt
            .block_on(async { ProviderFactory::get_provider_for_model(&this.model) })
            .map_err(|e| e.into_php_exception())?;

        // Convert tools to octolib format
        let octo_tools: Result<Vec<_>, _> = this.tools.iter().map(|t| t.to_octo()).collect();
        let octo_tools = octo_tools?;

        let params = ChatCompletionParams::new(
            &messages_vec,
            &model,
            this.temperature,
            this.top_p,
            50,
            this.max_tokens,
        )
        .with_tools(octo_tools);

        let response = rt
            .block_on(async { provider.chat_completion(params).await })
            .map_err(|e| e.into_php_exception())?;

        // Convert tool calls
        let tool_calls = if let Some(calls) = response.tool_calls {
            calls
                .iter()
                .map(|c| ToolCall::new(c.id.clone(), c.name.clone(), c.arguments.clone()))
                .collect::<Result<Vec<_>, _>>()?
        } else {
            Vec::new()
        };

        Ok(ToolResponse::new_with_opt_usage(
            response.content,
            tool_calls,
            response.exchange.usage,
            model,
            response.response_id,
        ))
    }

    /// Add a tool
    pub fn add_tool<'a>(
        self_: &'a mut ZendClassObject<ToolBuilder>,
        tool: &mut Tool,
    ) -> &'a mut ZendClassObject<ToolBuilder> {
        self_.tools.push(tool.clone());
        self_
    }

    /// Set all tools
    pub fn set_tools<'a>(
        self_: &'a mut ZendClassObject<ToolBuilder>,
        tools: &mut PhpArray,
    ) -> &'a mut ZendClassObject<ToolBuilder> {
        let s = &mut *self_;
        s.tools.clear();
        for (_, val) in tools.iter() {
            if let Some(arr) = val.array() {
                if let Ok(tool) = Tool::from_array(arr) {
                    s.tools.push(tool);
                }
            }
        }
        self_
    }

    /// Set auto execute
    pub fn set_auto_execute(
        self_: &mut ZendClassObject<ToolBuilder>,
        auto: bool,
    ) -> &mut ZendClassObject<ToolBuilder> {
        self_.auto_execute = auto;
        self_
    }

    /// Set temperature
    pub fn set_temperature(
        self_: &mut ZendClassObject<ToolBuilder>,
        temperature: f64,
    ) -> &mut ZendClassObject<ToolBuilder> {
        self_.temperature = temperature as f32;
        self_
    }

    /// Set max tokens
    pub fn set_max_tokens(
        self_: &mut ZendClassObject<ToolBuilder>,
        max_tokens: i64,
    ) -> &mut ZendClassObject<ToolBuilder> {
        self_.max_tokens = max_tokens as u32;
        self_
    }
}
