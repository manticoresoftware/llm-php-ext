# Changelog

All notable changes to the LLM PHP Extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of LLM PHP Extension
- Multi-provider support (OpenAI, Anthropic, OpenRouter, Google Vertex AI, Amazon Bedrock, Cloudflare Workers AI, DeepSeek, Z.ai)
- Structured output with JSON and JSON Schema validation
- Tool calling with function definitions
- Fluent interface for LLM configuration
- Builder pattern for structured output and tool calling
- Message and MessageCollection classes
- Comprehensive error handling with custom exceptions
- Full PHPDoc stubs for IDE support
- Cross-platform build support (Linux, macOS, Windows)
- Static compilation support for embedding in PHP binary
- Comprehensive test suite
- Practical examples

### Classes
- `LLM` - Main class for LLM interactions
- `Response` - Basic completion response
- `StructuredResponse` - Structured output response
- `ToolResponse` - Tool calling response
- `Usage` - Token usage information
- `StructuredBuilder` - Builder for structured output
- `ToolBuilder` - Builder for tool calling
- `Tool` - Tool definition
- `ToolCall` - Tool call from LLM
- `Message` - Message in conversation
- `MessageCollection` - Collection of messages

### Exceptions
- `LLMException` - Base exception
- `LLMConnectionException` - Network/API errors
- `LLMValidationException` - Validation errors
- `LLMStructuredOutputException` - Structured output errors
- `LLMToolCallException` - Tool calling errors

## [0.1.0] - 2025-01-21

### Added
- Initial release
