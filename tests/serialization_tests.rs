#[cfg(test)]
mod tests {
    use serde_json::{json, Value};

    // Test basic JSON serialization/deserialization
    #[test]
    fn test_json_serialization() {
        let data = json!({
            "name": "test_tool",
            "description": "A test tool",
            "parameters": {
                "type": "object",
                "properties": {
                    "location": {
                        "type": "string",
                        "description": "City and state"
                    }
                }
            }
        });

        let serialized = serde_json::to_string(&data).unwrap();
        let deserialized: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(deserialized["name"], "test_tool");
        assert_eq!(deserialized["description"], "A test tool");
        assert_eq!(deserialized["parameters"]["type"], "object");
    }

    // Test message JSON structure
    #[test]
    fn test_message_json_structure() {
        let message = json!({
            "role": "user",
            "content": "Hello, world!",
            "tool_call_id": null,
            "id": null,
            "tool_calls": null
        });

        assert_eq!(message["role"], "user");
        assert_eq!(message["content"], "Hello, world!");
        assert!(message["tool_call_id"].is_null());
    }

    // Test tool parameters validation
    #[test]
    fn test_tool_parameters_validation() {
        let valid_params = json!({
            "type": "object",
            "properties": {
                "param1": {"type": "string"}
            },
            "required": ["param1"]
        });

        let serialized = serde_json::to_string(&valid_params).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["type"], "object");
        assert!(parsed["properties"].is_object());
        assert!(parsed["required"].is_array());
    }

    // Test message collection serialization
    #[test]
    fn test_message_collection_serialization() {
        let messages = json!([
            {
                "role": "user",
                "content": "Hello",
                "tool_call_id": null,
                "id": null,
                "tool_calls": null
            },
            {
                "role": "assistant",
                "content": "Hi there!",
                "tool_call_id": null,
                "id": null,
                "tool_calls": null
            }
        ]);

        let serialized = serde_json::to_string(&messages).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert!(parsed.is_array());
        assert_eq!(parsed.as_array().unwrap().len(), 2);
        assert_eq!(parsed[0]["role"], "user");
        assert_eq!(parsed[1]["role"], "assistant");
    }

    // Test tool call serialization
    #[test]
    fn test_tool_call_serialization() {
        let tool_call = json!({
            "id": "call_123",
            "name": "get_weather",
            "arguments": {
                "location": "San Francisco, CA",
                "unit": "celsius"
            }
        });

        let serialized = serde_json::to_string(&tool_call).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["id"], "call_123");
        assert_eq!(parsed["name"], "get_weather");
        assert_eq!(parsed["arguments"]["location"], "San Francisco, CA");
    }

    // Test complex nested parameters
    #[test]
    fn test_complex_nested_parameters() {
        let complex_params = json!({
            "type": "object",
            "properties": {
                "search": {
                    "type": "object",
                    "properties": {
                        "query": {"type": "string"},
                        "limit": {"type": "integer"}
                    }
                },
                "filters": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "field": {"type": "string"},
                            "value": {"type": "string"}
                        }
                    }
                }
            }
        });

        let serialized = serde_json::to_string(&complex_params).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["type"], "object");
        assert_eq!(parsed["properties"]["search"]["type"], "object");
        assert_eq!(parsed["properties"]["filters"]["type"], "array");
    }

    // Test usage statistics structure
    #[test]
    fn test_usage_statistics_structure() {
        let usage = json!({
            "prompt_tokens": 100,
            "output_tokens": 50,
            "total_tokens": 150,
            "reasoning_tokens": 0,
            "cached_tokens": 0
        });

        let serialized = serde_json::to_string(&usage).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["prompt_tokens"], 100);
        assert_eq!(parsed["output_tokens"], 50);
        assert_eq!(parsed["total_tokens"], 150);
    }

    // Test error message formatting
    #[test]
    fn test_error_message_formatting() {
        let error = json!({
            "error": {
                "message": "Invalid API key",
                "type": "authentication_error",
                "code": "invalid_api_key"
            }
        });

        let serialized = serde_json::to_string(&error).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["error"]["message"], "Invalid API key");
        assert_eq!(parsed["error"]["type"], "authentication_error");
    }

    // Test tool response structure
    #[test]
    fn test_tool_response_structure() {
        let response = json!({
            "content": "The weather is sunny",
            "tool_calls": [
                {
                    "id": "call_123",
                    "name": "get_weather",
                    "arguments": {"location": "SF"}
                }
            ],
            "usage": {
                "prompt_tokens": 50,
                "output_tokens": 20,
                "total_tokens": 70
            },
            "model": "gpt-4",
            "id": "resp_456"
        });

        let serialized = serde_json::to_string(&response).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["content"], "The weather is sunny");
        assert!(parsed["tool_calls"].is_array());
        assert_eq!(parsed["model"], "gpt-4");
    }

    // Test enum values in parameters
    #[test]
    fn test_enum_values_in_parameters() {
        let params = json!({
            "type": "object",
            "properties": {
                "unit": {
                    "type": "string",
                    "enum": ["celsius", "fahrenheit"]
                }
            }
        });

        let serialized = serde_json::to_string(&params).unwrap();
        let parsed: Value = serde_json::from_str(&serialized).unwrap();

        assert_eq!(parsed["properties"]["unit"]["type"], "string");
        assert!(parsed["properties"]["unit"]["enum"].is_array());
        assert_eq!(parsed["properties"]["unit"]["enum"][0], "celsius");
    }
}