use crate::tool_builder::ToolResponse;
use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendClassObject, ZendHashTable as PhpArray, Zval};
use octolib::llm::{Message as OctoMessage, MessageBuilder};

/// Message in conversation
#[php_class]
#[derive(Clone)]
pub struct Message {
    role: String,
    content: String,
    tool_call_id: Option<String>,
    id: Option<String>,
    tool_calls: Option<String>,
}

#[php_impl]
impl Message {
    /// Create a user message
    pub fn user(content: String) -> PhpResult<Self> {
        Ok(Self {
            role: "user".to_string(),
            content,
            tool_call_id: None,
            id: None,
            tool_calls: None,
        })
    }

    /// Create an assistant message
    pub fn assistant(content: String) -> PhpResult<Self> {
        Ok(Self {
            role: "assistant".to_string(),
            content,
            tool_call_id: None,
            id: None,
            tool_calls: None,
        })
    }

    /// Create a system message
    pub fn system(content: String) -> PhpResult<Self> {
        Ok(Self {
            role: "system".to_string(),
            content,
            tool_call_id: None,
            id: None,
            tool_calls: None,
        })
    }

    /// Create a tool result message
    pub fn tool(tool_call_id: String, result: String) -> PhpResult<Self> {
        Ok(Self {
            role: "tool".to_string(),
            content: result,
            tool_call_id: Some(tool_call_id),
            id: None,
            tool_calls: None,
        })
    }

    /// Create from ToolResponse
    pub fn from_response(response: &ToolResponse) -> PhpResult<Self> {
        // Serialize tool_calls to JSON if present
        let tool_calls_json = if response.has_tool_calls() {
            let calls = response.get_tool_calls();
            let calls_array: Vec<serde_json::Value> = calls
                .iter()
                .map(|call| {
                    // Parse the arguments_json back to Value for proper serialization
                    let args_value: serde_json::Value =
                        serde_json::from_str(call.get_arguments_json())
                            .unwrap_or(serde_json::Value::Null);
                    serde_json::json!({
                        "id": call.get_id(),
                        "name": call.get_name(),
                        "arguments": args_value,
                    })
                })
                .collect();
            Some(serde_json::to_string(&calls_array).map_err(|e| {
                PhpException::default(format!("Failed to serialize tool calls: {}", e))
            })?)
        } else {
            None
        };

        Ok(Self {
            role: "assistant".to_string(),
            content: response.get_content(),
            tool_call_id: None,
            id: response.get_id(),
            tool_calls: tool_calls_json,
        })
    }

    /// Create from array
    pub fn from_array(data: &PhpArray) -> PhpResult<Self> {
        let role = data
            .get("role")
            .and_then(|v| v.str())
            .ok_or_else(|| {
                PhpException::from_class::<crate::error::LLMValidationException>(
                    "Message must have 'role' field".to_string(),
                )
            })?
            .to_string();

        let content = data
            .get("content")
            .and_then(|v| v.str())
            .ok_or_else(|| {
                PhpException::from_class::<crate::error::LLMValidationException>(
                    "Message must have 'content' field".to_string(),
                )
            })?
            .to_string();

        let tool_call_id = data
            .get("tool_call_id")
            .and_then(|v| v.str())
            .map(|s| s.to_string());

        let id = data.get("id").and_then(|v| v.str()).map(|s| s.to_string());

        let tool_calls = data
            .get("tool_calls")
            .and_then(|v| v.str())
            .map(|s| s.to_string());

        Ok(Self {
            role,
            content,
            tool_call_id,
            id,
            tool_calls,
        })
    }

    pub fn get_role(&self) -> String {
        self.role.clone()
    }

    pub fn get_content(&self) -> String {
        self.content.clone()
    }

    pub fn get_tool_calls(&self) -> Option<String> {
        self.tool_calls.clone()
    }

    pub fn get_id(&self) -> Option<String> {
        self.id.clone()
    }

    pub fn get_tool_call_id(&self) -> Option<String> {
        self.tool_call_id.clone()
    }

    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        arr.insert("role", self.role.clone())?;
        arr.insert("content", self.content.clone())?;
        if let Some(ref tool_id) = self.tool_call_id {
            arr.insert("tool_call_id", &**tool_id)?;
        }
        if let Some(ref msg_id) = self.id {
            arr.insert("id", &**msg_id)?;
        }
        if let Some(ref calls) = self.tool_calls {
            arr.insert("tool_calls", &**calls)?;
        }
        Ok(arr.into_zval(false)?)
    }
    pub fn to_json(&self) -> PhpResult<String> {
        match serde_json::to_string(&serde_json::json!({
            "role": self.role,
            "content": self.content,
            "tool_call_id": self.tool_call_id,
            "id": self.id,
            "tool_calls": self.tool_calls,
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
impl Message {
    pub(crate) fn to_octo(&self) -> Result<OctoMessage, PhpException> {
        match self.role.as_str() {
            "user" => Ok(MessageBuilder::user(&self.content).build().map_err(|e| {
                PhpException::from_class::<crate::error::LLMValidationException>(format!(
                    "Failed to build message: {}",
                    e
                ))
            })?),
            "assistant" => {
                let mut builder = MessageBuilder::assistant(&self.content);
                if let Some(ref msg_id) = self.id {
                    builder = builder.id(msg_id);
                }
                let mut msg = builder.build().map_err(|e| {
                    PhpException::from_class::<crate::error::LLMValidationException>(format!(
                        "Failed to build message: {}",
                        e
                    ))
                })?;

                // Set tool_calls directly on the Message struct if present
                if let Some(ref calls_json) = self.tool_calls {
                    if let Ok(calls_value) = serde_json::from_str::<serde_json::Value>(calls_json) {
                        msg.tool_calls = Some(calls_value);
                    }
                }
                Ok(msg)
            }
            "system" => Ok(MessageBuilder::system(&self.content).build().map_err(|e| {
                PhpException::from_class::<crate::error::LLMValidationException>(format!(
                    "Failed to build message: {}",
                    e
                ))
            })?),
            "tool" => {
                if let Some(ref id) = self.tool_call_id {
                    Ok(MessageBuilder::tool(&self.content, id, &String::new())
                        .build()
                        .map_err(|e| {
                            PhpException::from_class::<crate::error::LLMValidationException>(
                                format!("Failed to build message: {}", e),
                            )
                        })?)
                } else {
                    Err(PhpException::from_class::<
                        crate::error::LLMValidationException,
                    >(
                        "Tool message must have tool_call_id".to_string()
                    ))
                }
            }
            _ => Err(PhpException::from_class::<
                crate::error::LLMValidationException,
            >(format!(
                "Invalid message role: {}",
                self.role
            ))),
        }
    }
}

/// Collection of messages
#[php_class]
pub struct MessageCollection {
    messages: Vec<Message>,
}

#[php_impl]
impl MessageCollection {
    /// Create a new message collection
    #[php(constructor)]
    pub fn __construct(messages: Option<&mut PhpArray>) -> PhpResult<Self> {
        let mut msgs = Vec::new();

        if let Some(arr) = messages {
            for (_, val) in arr.iter() {
                if let Some(msg_arr) = val.array() {
                    let msg = Message::from_array(msg_arr)?;
                    msgs.push(msg);
                }
            }
        }

        Ok(Self { messages: msgs })
    }

    /// Create from array
    pub fn from_array(messages: &mut PhpArray) -> PhpResult<Self> {
        Self::__construct(Some(messages))
    }

    /// Add a message
    pub fn add<'a>(
        self_: &'a mut ZendClassObject<MessageCollection>,
        message: &mut Message,
    ) -> &'a mut ZendClassObject<MessageCollection> {
        self_.messages.push(message.clone());
        self_
    }

    /// Add a user message
    pub fn add_user(
        self_: &mut ZendClassObject<MessageCollection>,
        content: String,
    ) -> &mut ZendClassObject<MessageCollection> {
        if let Ok(msg) = Message::user(content) {
            self_.messages.push(msg);
        }
        self_
    }

    /// Add an assistant message
    pub fn add_assistant(
        self_: &mut ZendClassObject<MessageCollection>,
        content: String,
    ) -> &mut ZendClassObject<MessageCollection> {
        if let Ok(msg) = Message::assistant(content) {
            self_.messages.push(msg);
        }
        self_
    }

    /// Add a system message
    pub fn add_system(
        self_: &mut ZendClassObject<MessageCollection>,
        content: String,
    ) -> &mut ZendClassObject<MessageCollection> {
        if let Ok(msg) = Message::system(content) {
            self_.messages.push(msg);
        }
        self_
    }

    /// Add a tool result message
    pub fn add_tool_result(
        self_: &mut ZendClassObject<MessageCollection>,
        tool_call_id: String,
        result: String,
    ) -> &mut ZendClassObject<MessageCollection> {
        if let Ok(msg) = Message::tool(tool_call_id, result) {
            self_.messages.push(msg);
        }
        self_
    }

    /// Get message at index
    pub fn get(&self, index: i64) -> Option<Message> {
        if index >= 0 && (index as usize) < self.messages.len() {
            Some(self.messages[index as usize].clone())
        } else {
            None
        }
    }

    /// Get all messages
    pub fn all(&self) -> Vec<Message> {
        self.messages.clone()
    }

    /// Get message count
    pub fn count(&self) -> i64 {
        self.messages.len() as i64
    }

    /// Convert to array
    pub fn to_array(&self) -> PhpResult<Zval> {
        let mut arr = PhpArray::new();
        for msg in &self.messages {
            arr.push(msg.clone().into_zval(false)?)?;
        }
        Ok(arr.into_zval(false)?)
    }

    /// Convert to JSON
    pub fn to_json(&self) -> PhpResult<String> {
        let messages: Vec<serde_json::Value> = self
            .messages
            .iter()
            .map(|m| {
                serde_json::json!({
                    "role": m.role,
                    "content": m.content,
                    "tool_call_id": m.tool_call_id,
                    "id": m.id,
                    "tool_calls": m.tool_calls,
                })
            })
            .collect();
        match serde_json::to_string(&messages) {
            Ok(json) => Ok(json),
            Err(e) => Err(PhpException::default(format!(
                "Failed to serialize to JSON: {}",
                e
            ))),
        }
    }
}

// Internal methods - not exposed to PHP
impl MessageCollection {
    /// Convert to octolib messages
    pub(crate) fn to_octo(&self) -> Result<Vec<OctoMessage>, PhpException> {
        self.messages.iter().map(|m| m.to_octo()).collect()
    }
}