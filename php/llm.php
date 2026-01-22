<?php

// Stubs for llm

namespace {
    /**
     * Main LLM class for interacting with language models
     */
    class Llm {
        /**
         * Complete a conversation
         */
        public function complete(mixed $messages): \Response {}

        /**
         * Create a builder for structured output
         */
        public function structured(?string $schema = null): \StructuredBuilder {}

        /**
         * Create a builder for tool calling
         */
        public function withTools(?array $tools = null): \ToolBuilder {}

        /**
         * Set configuration options
         */
        public function withOptions(array $options): \Llm {}

        /**
         * Set temperature
         */
        public function setTemperature(float $_temperature): \Llm {}

        /**
         * Set max tokens
         */
        public function setMaxTokens(int $_max_tokens): \Llm {}

        /**
         * Set top_p
         */
        public function setTopP(float $_top_p): \Llm {}

        /**
         * Set frequency penalty
         */
        public function setFrequencyPenalty(float $_penalty): \Llm {}

        /**
         * Set presence penalty
         */
        public function setPresencePenalty(float $_penalty): \Llm {}

        /**
         * Create a new LLM instance
         */
        public function __construct(string $model, ?array $_options = null) {}
    }

    /**
     * Response from LLM completion
     */
    class Response {
        public function getContent(): string {}

        public function getUsage(): \Usage {}

        public function getModel(): string {}

        public function getFinishReason(): string {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Token usage information
     */
    class Usage {
        public function getPromptTokens(): int {}

        public function getOutputTokens(): int {}

        public function getTotalTokens(): int {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Builder for structured output
     */
    class StructuredBuilder {
        /**
         * Complete with structured output
         */
        public function complete(mixed $messages): \StructuredResponse {}

        /**
         * Set JSON schema
         */
        public function withSchema(string $schema): \StructuredBuilder {}

        /**
         * Set format ('json' or 'json_schema')
         */
        public function withFormat(string $format): \StructuredBuilder {}

        /**
         * Set temperature
         */
        public function setTemperature(float $temperature): \StructuredBuilder {}

        /**
         * Set max tokens
         */
        public function setMaxTokens(int $max_tokens): \StructuredBuilder {}

        public function __construct() {}
    }

    /**
     * Structured response with JSON output
     */
    class StructuredResponse {
        public function getContent(): string {}

        public function getStructured(): mixed {}

        public function getUsage(): \Usage {}

        public function getModel(): string {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Builder for tool calling
     */
    class ToolBuilder {
        /**
         * Complete with tool calling
         */
        public function complete(mixed $messages): \ToolResponse {}

        /**
         * Add a tool
         */
        public function addTool(\Tool $tool): \ToolBuilder {}

        /**
         * Set all tools
         */
        public function setTools(array $tools): \ToolBuilder {}

        /**
         * Set auto execute
         */
        public function setAutoExecute(bool $auto): \ToolBuilder {}

        /**
         * Set temperature
         */
        public function setTemperature(float $temperature): \ToolBuilder {}

        /**
         * Set max tokens
         */
        public function setMaxTokens(int $max_tokens): \ToolBuilder {}

        public function __construct() {}
    }

    /**
     * Tool definition
     */
    class Tool {
        /**
         * Create from array
         */
        public static function fromArray(array $data): \Tool {}

        public function getName(): string {}

        public function getDescription(): string {}

        public function getParameters(): string {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        /**
         * Create a tool definition
         */
        public function __construct(string $name, string $description, mixed $parameters) {}
    }

    /**
     * Tool call from LLM
     */
    class ToolCall {
        public function getId(): string {}

        public function getName(): string {}

        public function getArguments(): mixed {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Response with tool calls
     */
    class ToolResponse {
        public function getContent(): string {}

        public function getToolCalls(): array {}

        public function getUsage(): \Usage {}

        public function getModel(): string {}

        public function getResponseId(): ?string {}

        public function hasToolCalls(): bool {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Message in conversation
     */
    class Message {
        /**
         * Create a user message
         */
        public static function user(string $content): \Message {}

        /**
         * Create an assistant message
         */
        public static function assistant(string $content): \Message {}

        /**
         * Create a system message
         */
        public static function system(string $content): \Message {}

        /**
         * Create a tool result message
         */
        public static function tool(string $tool_call_id, string $result): \Message {}

        /**
         * Create from ToolResponse
         */
        public static function fromResponse(\ToolResponse $response): \Message {}

        /**
         * Create from array
         */
        public static function fromArray(array $data): \Message {}

        public function getRole(): string {}

        public function getContent(): string {}

        public function getToolCalls(): ?string {}

        public function getId(): ?string {}

        public function getToolCallId(): ?string {}

        public function toArray(): mixed {}

        public function toJson(): string {}

        public function __construct() {}
    }

    /**
     * Collection of messages
     */
    class MessageCollection {
        /**
         * Create from array
         */
        public static function fromArray(array $messages): \MessageCollection {}

        /**
         * Add a message
         */
        public function add(\Message $message): \MessageCollection {}

        /**
         * Add a user message
         */
        public function addUser(string $content): \MessageCollection {}

        /**
         * Add an assistant message
         */
        public function addAssistant(string $content): \MessageCollection {}

        /**
         * Add a system message
         */
        public function addSystem(string $content): \MessageCollection {}

        /**
         * Add a tool result message
         */
        public function addToolResult(string $tool_call_id, string $result): \MessageCollection {}

        /**
         * Get message at index
         */
        public function get(int $index): ?\Message {}

        /**
         * Get all messages
         */
        public function all(): array {}

        /**
         * Get message count
         */
        public function count(): int {}

        /**
         * Convert to array
         */
        public function toArray(): mixed {}

        /**
         * Convert to JSON
         */
        public function toJson(): string {}

        /**
         * Create a new message collection
         */
        public function __construct(?array $messages = null) {}
    }

    class LLMException {
        public function __construct(string $_message, int $_code) {}
    }

    class LLMValidationException {
        public function __construct(string $_message, int $_code) {}
    }

    class LLMStructuredOutputException {
        public function __construct(string $_message, int $_code) {}
    }
}
