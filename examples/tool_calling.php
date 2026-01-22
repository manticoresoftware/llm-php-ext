<?php
/**
 * Tool calling example
 * 
 * Demonstrates the full tool calling workflow with a tool the model
 * CANNOT answer without external data (current time in specific timezone).
 * 
 * Workflow:
 * 1. Define tools (get_current_time)
 * 2. Call complete() - model asks for tool
 * 3. Execute tool and add result
 * 4. Call complete() again - model provides final answer
 * 
 * Note: For IDE autocomplete, include php/llm.php separately.
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded. Build it with: make build\n");
}

echo "🔧 Tool Calling Demo\n";
echo str_repeat("=", 50) . "\n\n";

// Tool that returns current time in a specific timezone
function getCurrentTime(string $timezone): string {
    try {
        $dt = new DateTime('now', new DateTimeZone($timezone));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return "Error: Invalid timezone";
    }
}

try {
    // Define time tool
    $timeParams = [
        'type' => 'object',
        'properties' => [
            'timezone' => [
                'type' => 'string',
                'description' => 'Timezone (e.g., America/New_York, Europe/London, Asia/Tokyo)',
                'example' => 'Asia/Tokyo'
            ]
        ],
        'required' => ['timezone']
    ];
    
    $timeTool = new Tool('get_current_time', 'Get the current date and time for a timezone', $timeParams);
    
    echo "✅ Created tool: {$timeTool->getName()}\n";
    echo "   Description: {$timeTool->getDescription()}\n\n";
    
    // Step 1: Create conversation
    $messages = new MessageCollection();
    $messages->addSystem('You are a precise assistant. For ANY question about current time, you MUST call the get_current_time tool with the correct timezone. Do not guess the time.');
    $messages->addUser('What is the exact current time in Tokyo, Japan?');
    
    echo "👤 User: What is the exact current time in Tokyo, Japan?\n\n";
    
    // Step 2: First completion with low temperature for deterministic behavior
    $toolBuilder = (new LLM('openai:gpt-4o'))
        ->setTemperature(0.0)
        ->withTools([$timeTool]);
    
    $response = $toolBuilder->complete($messages);
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    // Step 3: Check for tool calls
    if ($response->hasToolCalls()) {
        echo "🔧 Tool calls received:\n";
        
        // Add the assistant's response to the conversation (preserves id, tool_calls)
        $assistantMsg = Message::fromResponse($response);
        $messages->add($assistantMsg);
        
        foreach ($response->getToolCalls() as $call) {
            $args = $call->getArguments();
            $timezone = $args['timezone'];
            echo "   - {$call->getName()}(timezone: {$timezone})\n";
            echo "     ID: {$call->getId()}\n";
            
            // Execute the tool
            $result = getCurrentTime($timezone);
            echo "     Result: $result\n\n";
            
            // Add tool result to conversation
            $messages->addToolResult($call->getId(), $result);
        }
        
        // Step 4: Second completion with tool result
        echo "🔄 Calling complete() again with tool result...\n\n";
        $response2 = $toolBuilder->complete($messages);
        echo "🤖 Final response: " . $response2->getContent() . "\n\n";
        
        $usage = $response2->getUsage();
    } else {
        echo "⚠️  Model did not call the tool (this can happen with some models)\n\n";
        $usage = $response->getUsage();
    }
    
    echo "📊 Total tokens: " . $usage->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n📊 Note: Tool calling effectiveness depends on the model.\n";
}
