#!/usr/bin/env php
<?php
/**
 * Simple LLM Extension Demo
 * 
 * Shows key features:
 * - Basic completion
 * - Multi-turn conversation
 * - Tool/function definitions
 * - Message collection
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded\n");
}

echo "🚀 LLM PHP Extension - Quick Demo\n";
echo str_repeat("=", 70) . "\n\n";

// ============================================================================
// Example 1: Simple Completion
// ============================================================================

echo "📝 Example 1: Simple Completion\n";
echo str_repeat("-", 70) . "\n";

$llm = new LLM('openai:gpt-4o-mini');
$llm->setTemperature(0.7);
$llm->setMaxTokens(100);

echo "Model: openai:gpt-4o-mini | Temp: 0.7 | Max tokens: 100\n\n";

$messages = new MessageCollection();
$messages->addSystem('You are a helpful assistant. Be concise.');
$messages->addUser('What is PHP?');

echo "👤 User: What is PHP?\n";

try {
    $response = $llm->complete($messages);
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    $usage = $response->getUsage();
    echo "📊 Tokens: {$usage->getPromptTokens()} prompt + {$usage->getOutputTokens()} output = {$usage->getTotalTokens()} total\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// ============================================================================
// Example 2: Multi-turn Conversation
// ============================================================================

echo "💬 Example 2: Multi-turn Conversation\n";
echo str_repeat("-", 70) . "\n\n";

$conversation = new MessageCollection();
$conversation->addSystem('You are a helpful coding assistant.');

// Turn 1
$conversation->addUser('Write a PHP function to reverse a string');
echo "👤 Turn 1: Write a PHP function to reverse a string\n";

try {
    $response1 = $llm->complete($conversation);
    $answer1 = $response1->getContent();
    echo "🤖 Assistant: " . substr($answer1, 0, 100) . "...\n\n";
    
    // Add assistant response to conversation
    $conversation->addAssistant($answer1);
    
    // Turn 2
    $conversation->addUser('Now make it case-insensitive');
    echo "👤 Turn 2: Now make it case-insensitive\n";
    
    $response2 = $llm->complete($conversation);
    echo "🤖 Assistant: " . substr($response2->getContent(), 0, 100) . "...\n\n";
    
    echo "📊 Conversation has {$conversation->count()} messages\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// ============================================================================
// Example 3: Tool/Function Definition
// ============================================================================

echo "🔧 Example 3: Tool Definition (Function Calling)\n";
echo str_repeat("-", 70) . "\n\n";

// Define a weather tool
$weatherParams = [
    'type' => 'object',
    'properties' => [
        'location' => [
            'type' => 'string',
            'description' => 'City name'
        ],
        'unit' => [
            'type' => 'string',
            'enum' => ['celsius', 'fahrenheit']
        ]
    ],
    'required' => ['location']
];

$weatherTool = new Tool('get_weather', 'Get weather for a location', $weatherParams);

echo "Tool: " . $weatherTool->getName() . "\n";
echo "Description: " . $weatherTool->getDescription() . "\n";
echo "Parameters: " . $weatherTool->getParameters() . "\n\n";

// Define a calculator tool
$calcParams = [
    'type' => 'object',
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number'],
        'operation' => [
            'type' => 'string',
            'enum' => ['add', 'multiply', 'subtract', 'divide']
        ]
    ],
    'required' => ['a', 'b', 'operation']
];

$calcTool = new Tool('calculate', 'Perform math operations', $calcParams);

echo "Tool: " . $calcTool->getName() . "\n";
echo "Description: " . $calcTool->getDescription() . "\n\n";

// Create tool builder
$toolBuilder = $llm->withTools([$weatherTool, $calcTool]);
echo "✓ Tool builder created with 2 tools\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// ============================================================================
// Example 4: Structured Output
// ============================================================================

echo "📋 Example 4: Structured Output (JSON Schema)\n";
echo str_repeat("-", 70) . "\n\n";

echo "Note: Structured output builder available via \$llm->structured(\$schema)\n";
echo "      See examples/structured_output.php for detailed usage\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// ============================================================================
// Example 5: Configuration Options
// ============================================================================

echo "⚙️  Example 5: Configuration Options\n";
echo str_repeat("-", 70) . "\n\n";

$llm2 = new LLM('openai:gpt-4o-mini');

// Method 1: Individual setters
$llm2->setTemperature(0.9);
$llm2->setMaxTokens(200);
$llm2->setTopP(0.95);
$llm2->setFrequencyPenalty(0.5);
$llm2->setPresencePenalty(0.5);

echo "✓ Configuration set via individual methods\n";

// Method 2: Batch options
$llm3 = new LLM('openai:gpt-4o-mini');
$llm3->withOptions([
    'temperature' => 0.8,
    'max_tokens' => 150,
    'top_p' => 0.9
]);

echo "✓ Configuration set via withOptions()\n\n";

echo "Available setters:\n";
echo "  • setTemperature(float)      - Randomness (0.0-2.0)\n";
echo "  • setMaxTokens(int)          - Max output length\n";
echo "  • setTopP(float)             - Nucleus sampling (0.0-1.0)\n";
echo "  • setFrequencyPenalty(float) - Reduce repetition (-2.0-2.0)\n";
echo "  • setPresencePenalty(float)  - Encourage new topics (-2.0-2.0)\n";

echo "\n" . str_repeat("=", 70) . "\n\n";

// ============================================================================
// Summary
// ============================================================================

echo "✅ Demo Complete!\n\n";
echo "Key Features Demonstrated:\n";
echo "  ✓ Basic LLM completion\n";
echo "  ✓ Multi-turn conversations with MessageCollection\n";
echo "  ✓ Tool/function definitions for function calling\n";
echo "  ✓ Structured output with JSON schema\n";
echo "  ✓ Configuration options (temperature, tokens, etc.)\n";
echo "  ✓ Token usage tracking\n\n";

echo "Available Classes:\n";
echo "  • LLM                - Main LLM interface\n";
echo "  • MessageCollection  - Manage conversation messages\n";
echo "  • Tool               - Define callable functions\n";
echo "  • ToolBuilder        - Builder for tool calling\n";
echo "  • StructuredBuilder  - Builder for structured output\n";
echo "  • Response           - LLM response with content & usage\n";
echo "  • StructuredResponse - Response with parsed JSON\n";
echo "  • ToolResponse       - Response with tool calls\n";
echo "  • Usage              - Token usage information\n\n";

echo "For more examples, see:\n";
echo "  • examples/basic_completion.php\n";
echo "  • examples/multi_turn.php\n";
echo "  • examples/tool_calling.php\n";
echo "  • examples/structured_output.php\n";
echo "  • examples/fluent_interface.php\n\n";
