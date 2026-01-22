#!/usr/bin/env php
<?php
/**
 * LLM Extension Demo - Multi-turn conversation with function calling
 * 
 * This demo shows:
 * - Creating an LLM instance
 * - Multi-turn conversation
 * - Function/tool calling
 * - Message collection management
 */

// Check if extension is loaded
if (!extension_loaded('llm')) {
    die("❌ ERROR: LLM extension is not loaded.\n");
}

echo "🚀 LLM PHP Extension Demo\n";
echo str_repeat("=", 60) . "\n\n";

// Check for API key
if (!getenv('OPENAI_API_KEY')) {
    echo "⚠️  WARNING: OPENAI_API_KEY not set. Set it with:\n";
    echo "   export OPENAI_API_KEY='sk-...'\n\n";
}

// ============================================================================
// 1. Define Tools (Functions)
// ============================================================================

echo "📦 Step 1: Defining tools...\n";

// Weather tool
$weatherParams = [
    'type' => 'object',
    'properties' => [
        'location' => [
            'type' => 'string',
            'description' => 'City name, e.g. "London" or "New York"'
        ],
        'unit' => [
            'type' => 'string',
            'enum' => ['celsius', 'fahrenheit'],
            'description' => 'Temperature unit'
        ]
    ],
    'required' => ['location']
];

$weatherTool = new Tool(
    'get_weather',
    'Get current weather for a location',
    $weatherParams
);

// Calculator tool
$calcParams = [
    'type' => 'object',
    'properties' => [
        'operation' => [
            'type' => 'string',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
            'description' => 'Math operation to perform'
        ],
        'a' => [
            'type' => 'number',
            'description' => 'First number'
        ],
        'b' => [
            'type' => 'number',
            'description' => 'Second number'
        ]
    ],
    'required' => ['operation', 'a', 'b']
];

$calcTool = new Tool(
    'calculate',
    'Perform basic math calculations',
    $calcParams
);

echo "   ✓ Weather tool defined\n";
echo "   ✓ Calculator tool defined\n\n";

// ============================================================================
// 2. Create LLM Instance
// ============================================================================

echo "🤖 Step 2: Creating LLM instance...\n";

$llm = new LLM('openai:gpt-4o-mini');
$llm->setTemperature(0.7);
$llm->setMaxTokens(500);

echo "   ✓ Model: openai:gpt-4o-mini\n";
echo "   ✓ Temperature: 0.7\n";
echo "   ✓ Max tokens: 500\n\n";

// ============================================================================
// 3. Create Tool Builder
// ============================================================================

echo "🔧 Step 3: Setting up tool builder...\n";

$toolBuilder = $llm->withTools([$weatherTool, $calcTool]);

echo "   ✓ Tool builder created with 2 tools\n\n";

// ============================================================================
// 4. First User Request - Weather
// ============================================================================

echo "💬 Step 4: First conversation turn (Weather)\n";
echo str_repeat("-", 60) . "\n";

$messages1 = new MessageCollection();
$messages1->addSystem('You are a helpful assistant with access to weather and calculator tools.');
$messages1->addUser('What\'s the weather like in London? Use celsius.');

echo "👤 User: What's the weather like in London? Use celsius.\n\n";

try {
    echo "🔄 Calling LLM...\n";
    $response1 = $toolBuilder->complete($messages1);
    
    echo "🤖 Assistant: " . $response1->getContent() . "\n";
    
    if ($response1->hasToolCalls()) {
        $toolCalls = $response1->getToolCalls();
        echo "\n📞 Tool calls requested: " . count($toolCalls) . "\n";
        
        foreach ($toolCalls as $toolCall) {
            $name = $toolCall->getName();
            $args = $toolCall->getArguments();
            $id = $toolCall->getId();
            
            echo "   • Function: $name\n";
            echo "     Arguments: " . json_encode($args) . "\n";
            echo "     Call ID: $id\n";
            
            // Simulate function execution
            $result = simulateToolCall($name, $args);
            echo "     Result: $result\n\n";
            
            // Add assistant message with tool call
            $messages1->addAssistant($response1->getContent());
            
            // Add tool result
            $messages1->addToolResult($id, $result);
        }
        
        // Get final response after tool execution
        echo "🔄 Getting final response...\n";
        $response1Final = $toolBuilder->complete($messages1);
        echo "🤖 Assistant (final): " . $response1Final->getContent() . "\n";
    }
    
    // Show usage
    $usage1 = $response1->getUsage();
    echo "\n📊 Token usage:\n";
    echo "   • Prompt: " . $usage1->getPromptTokens() . "\n";
    echo "   • Output: " . $usage1->getOutputTokens() . "\n";
    echo "   • Total: " . $usage1->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// ============================================================================
// 5. Second User Request - Calculator
// ============================================================================

echo "💬 Step 5: Second conversation turn (Calculator)\n";
echo str_repeat("-", 60) . "\n";

$messages2 = new MessageCollection();
$messages2->addSystem('You are a helpful assistant with access to weather and calculator tools.');
$messages2->addUser('Calculate 42 multiplied by 17');

echo "👤 User: Calculate 42 multiplied by 17\n\n";

try {
    echo "🔄 Calling LLM...\n";
    $response2 = $toolBuilder->complete($messages2);
    
    echo "🤖 Assistant: " . $response2->getContent() . "\n";
    
    if ($response2->hasToolCalls()) {
        $toolCalls = $response2->getToolCalls();
        echo "\n📞 Tool calls requested: " . count($toolCalls) . "\n";
        
        foreach ($toolCalls as $toolCall) {
            $name = $toolCall->getName();
            $args = $toolCall->getArguments();
            $id = $toolCall->getId();
            
            echo "   • Function: $name\n";
            echo "     Arguments: " . json_encode($args) . "\n";
            echo "     Call ID: $id\n";
            
            // Simulate function execution
            $result = simulateToolCall($name, $args);
            echo "     Result: $result\n\n";
            
            // Add assistant message with tool call
            $messages2->addAssistant($response2->getContent());
            
            // Add tool result
            $messages2->addToolResult($id, $result);
        }
        
        // Get final response after tool execution
        echo "🔄 Getting final response...\n";
        $response2Final = $toolBuilder->complete($messages2);
        echo "🤖 Assistant (final): " . $response2Final->getContent() . "\n";
    }
    
    // Show usage
    $usage2 = $response2->getUsage();
    echo "\n📊 Token usage:\n";
    echo "   • Prompt: " . $usage2->getPromptTokens() . "\n";
    echo "   • Output: " . $usage2->getOutputTokens() . "\n";
    echo "   • Total: " . $usage2->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ Demo completed!\n\n";

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Simulate tool/function execution
 */
function simulateToolCall(string $name, array $args): string {
    switch ($name) {
        case 'get_weather':
            $location = $args['location'] ?? 'Unknown';
            $unit = $args['unit'] ?? 'celsius';
            $temp = rand(15, 25);
            $conditions = ['sunny', 'cloudy', 'rainy', 'partly cloudy'][rand(0, 3)];
            
            return json_encode([
                'location' => $location,
                'temperature' => $temp,
                'unit' => $unit,
                'conditions' => $conditions,
                'humidity' => rand(40, 80) . '%'
            ]);
            
        case 'calculate':
            $a = $args['a'] ?? 0;
            $b = $args['b'] ?? 0;
            $op = $args['operation'] ?? 'add';
            
            $result = match($op) {
                'add' => $a + $b,
                'subtract' => $a - $b,
                'multiply' => $a * $b,
                'divide' => $b != 0 ? $a / $b : 'Error: Division by zero',
                default => 'Unknown operation'
            };
            
            return json_encode([
                'operation' => $op,
                'a' => $a,
                'b' => $b,
                'result' => $result
            ]);
            
        default:
            return json_encode(['error' => 'Unknown function']);
    }
}
