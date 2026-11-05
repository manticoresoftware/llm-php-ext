<?php

// Stubs for llm

namespace {
    class LLMConnectionException extends \Exception {
        protected int $code;

        protected string $message;

        /**
         * @param string|null $message
         * @param int|null $code
         */
        public function __construct(?string $message = null, ?int $code = null) {}
    }

    class LLMException extends \Exception {
        protected int $code;

        protected string $message;

        /**
         * @param string|null $message
         * @param int|null $code
         */
        public function __construct(?string $message = null, ?int $code = null) {}
    }

    class LLMStructuredOutputException extends \Exception {
        protected int $code;

        protected string $message;

        /**
         * @param string|null $message
         * @param int|null $code
         */
        public function __construct(?string $message = null, ?int $code = null) {}
    }

    class LLMToolCallException extends \Exception {
        protected int $code;

        protected string $message;

        /**
         * @param string|null $message
         * @param int|null $code
         */
        public function __construct(?string $message = null, ?int $code = null) {}
    }

    class LLMValidationException extends \Exception {
        protected int $code;

        protected string $message;

        /**
         * @param string|null $message
         * @param int|null $code
         */
        public function __construct(?string $message = null, ?int $code = null) {}
    }

    /**
     * Main LLM class for interacting with language models
     */
    class Llm {
        /**
         * Create a new LLM instance
         *
         * @param string $model
         * @param array|null $options
         */
        public function __construct(string $model, ?array $options = null) {}

        /**
         * Complete a conversation
         *
         * @param mixed $messages
         * @return \Response
         */
        public function complete(mixed $messages): \Response {}

        /**
         * Set frequency penalty
         *
         * @param float $penalty
         * @return \Llm
         */
        public function setFrequencyPenalty(float $penalty): \Llm {}

        /**
         * Set max tokens
         *
         * @param int $max_tokens
         * @return \Llm
         */
        public function setMaxTokens(int $max_tokens): \Llm {}

        /**
         * Set presence penalty
         *
         * @param float $penalty
         * @return \Llm
         */
        public function setPresencePenalty(float $penalty): \Llm {}

        /**
         * Set temperature
         *
         * @param float $temperature
         * @return \Llm
         */
        public function setTemperature(float $temperature): \Llm {}

        /**
         * Set top_p
         *
         * @param float $top_p
         * @return \Llm
         */
        public function setTopP(float $top_p): \Llm {}

        /**
         * Create a builder for structured output
         *
         * @param string|null $schema
         * @return \StructuredBuilder
         */
        public function structured(?string $schema = null): \StructuredBuilder {}

        /**
         * Set configuration options
         *
         * @param array $options
         * @return \Llm
         */
        public function withOptions(array $options): \Llm {}

        /**
         * Create a builder for tool calling
         *
         * @param array|null $tools
         * @return \ToolBuilder
         */
        public function withTools(?array $tools = null): \ToolBuilder {}
    }

    /**
     * Message in conversation
     */
    class Message {
        public function __construct() {}

        /**
         * Create an assistant message
         *
         * @param string $content
         * @return \Message
         */
        public static function assistant(string $content): \Message {}

        /**
         * Create from array
         *
         * @param array $data
         * @return \Message
         */
        public static function fromArray(array $data): \Message {}

        /**
         * Create from ToolResponse
         *
         * @param \ToolResponse $response
         * @return \Message
         */
        public static function fromResponse(\ToolResponse $response): \Message {}

        /**
         * @return string
         */
        public function getContent(): string {}

        /**
         * @return string|null
         */
        public function getId(): ?string {}

        /**
         * @return string
         */
        public function getRole(): string {}

        /**
         * @return string|null
         */
        public function getToolCallId(): ?string {}

        /**
         * @return string|null
         */
        public function getToolCalls(): ?string {}

        /**
         * Create a system message
         *
         * @param string $content
         * @return \Message
         */
        public static function system(string $content): \Message {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}

        /**
         * Create a tool result message
         *
         * @param string $tool_call_id
         * @param string $result
         * @return \Message
         */
        public static function tool(string $tool_call_id, string $result): \Message {}

        /**
         * Create a user message
         *
         * @param string $content
         * @return \Message
         */
        public static function user(string $content): \Message {}
    }

    /**
     * Collection of messages
     */
    class MessageCollection {
        /**
         * Create a new message collection
         *
         * @param array|null $messages
         */
        public function __construct(?array $messages = null) {}

        /**
         * Add a message
         *
         * @param \Message $message
         * @return \MessageCollection
         */
        public function add(\Message $message): \MessageCollection {}

        /**
         * Add an assistant message
         *
         * @param string $content
         * @return \MessageCollection
         */
        public function addAssistant(string $content): \MessageCollection {}

        /**
         * Add a system message
         *
         * @param string $content
         * @return \MessageCollection
         */
        public function addSystem(string $content): \MessageCollection {}

        /**
         * Add a tool result message
         *
         * @param string $tool_call_id
         * @param string $result
         * @return \MessageCollection
         */
        public function addToolResult(string $tool_call_id, string $result): \MessageCollection {}

        /**
         * Add a user message
         *
         * @param string $content
         * @return \MessageCollection
         */
        public function addUser(string $content): \MessageCollection {}

        /**
         * Get all messages
         *
         * @return array
         */
        public function all(): array {}

        /**
         * Get message count
         *
         * @return int
         */
        public function count(): int {}

        /**
         * Create from array
         *
         * @param array $messages
         * @return \MessageCollection
         */
        public static function fromArray(array $messages): \MessageCollection {}

        /**
         * Get message at index
         *
         * @param int $index
         * @return \Message|null
         */
        public function get(int $index): ?\Message {}

        /**
         * Convert to array
         *
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * Convert to JSON
         *
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Response from LLM completion
     */
    class Response {
        public function __construct() {}

        /**
         * @return string
         */
        public function getContent(): string {}

        /**
         * @return string
         */
        public function getFinishReason(): string {}

        /**
         * @return string
         */
        public function getModel(): string {}

        /**
         * @return \Usage
         */
        public function getUsage(): \Usage {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Builder for structured output
     */
    class StructuredBuilder {
        public function __construct() {}

        /**
         * Complete with structured output
         *
         * @param mixed $messages
         * @return \StructuredResponse
         */
        public function complete(mixed $messages): \StructuredResponse {}

        /**
         * Set max tokens
         *
         * @param int $max_tokens
         * @return \StructuredBuilder
         */
        public function setMaxTokens(int $max_tokens): \StructuredBuilder {}

        /**
         * Set temperature
         *
         * @param float $temperature
         * @return \StructuredBuilder
         */
        public function setTemperature(float $temperature): \StructuredBuilder {}

        /**
         * Set format ('json' or 'json_schema')
         *
         * @param string $format
         * @return \StructuredBuilder
         */
        public function withFormat(string $format): \StructuredBuilder {}

        /**
         * Set JSON schema
         *
         * @param string $schema
         * @return \StructuredBuilder
         */
        public function withSchema(string $schema): \StructuredBuilder {}
    }

    /**
     * Structured response with JSON output
     */
    class StructuredResponse {
        public function __construct() {}

        /**
         * @return string
         */
        public function getContent(): string {}

        /**
         * @return string
         */
        public function getModel(): string {}

        /**
         * @return mixed
         */
        public function getStructured(): mixed {}

        /**
         * @return \Usage
         */
        public function getUsage(): \Usage {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Tool definition
     */
    class Tool {
        /**
         * Create a tool definition
         *
         * @param string $name
         * @param string $description
         * @param mixed $parameters
         */
        public function __construct(string $name, string $description, mixed $parameters) {}

        /**
         * Create from array
         *
         * @param array $data
         * @return \Tool
         */
        public static function fromArray(array $data): \Tool {}

        /**
         * @return string
         */
        public function getDescription(): string {}

        /**
         * @return string
         */
        public function getName(): string {}

        /**
         * @return string
         */
        public function getParameters(): string {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Builder for tool calling
     */
    class ToolBuilder {
        public function __construct() {}

        /**
         * Add a tool
         *
         * @param \Tool $tool
         * @return \ToolBuilder
         */
        public function addTool(\Tool $tool): \ToolBuilder {}

        /**
         * Complete with tool calling
         *
         * @param mixed $messages
         * @return \ToolResponse
         */
        public function complete(mixed $messages): \ToolResponse {}

        /**
         * Set auto execute
         *
         * @param bool $auto
         * @return \ToolBuilder
         */
        public function setAutoExecute(bool $auto): \ToolBuilder {}

        /**
         * Set max tokens
         *
         * @param int $max_tokens
         * @return \ToolBuilder
         */
        public function setMaxTokens(int $max_tokens): \ToolBuilder {}

        /**
         * Set temperature
         *
         * @param float $temperature
         * @return \ToolBuilder
         */
        public function setTemperature(float $temperature): \ToolBuilder {}

        /**
         * Set all tools
         *
         * @param array $tools
         * @return \ToolBuilder
         */
        public function setTools(array $tools): \ToolBuilder {}
    }

    /**
     * Tool call from LLM
     */
    class ToolCall {
        public function __construct() {}

        /**
         * @return mixed
         */
        public function getArguments(): mixed {}

        /**
         * @return string
         */
        public function getId(): string {}

        /**
         * @return string
         */
        public function getName(): string {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Response with tool calls
     */
    class ToolResponse {
        public function __construct() {}

        /**
         * @return string
         */
        public function getContent(): string {}

        /**
         * @return string|null
         */
        public function getId(): ?string {}

        /**
         * @return string
         */
        public function getModel(): string {}

        /**
         * @return array
         */
        public function getToolCalls(): array {}

        /**
         * @return \Usage
         */
        public function getUsage(): \Usage {}

        /**
         * @return bool
         */
        public function hasToolCalls(): bool {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }

    /**
     * Token usage information
     */
    class Usage {
        public function __construct() {}

        /**
         * @return int
         */
        public function getOutputTokens(): int {}

        /**
         * @return int
         */
        public function getPromptTokens(): int {}

        /**
         * @return int
         */
        public function getTotalTokens(): int {}

        /**
         * @return mixed
         */
        public function toArray(): mixed {}

        /**
         * @return string
         */
        public function toJson(): string {}
    }
}
