<?php
/**
 * Fluent interface example
 * 
 * Demonstrates the fluent setter pattern for LLM configuration.
 * All setter methods return $this for method chaining.
 * 
 * Note: For IDE autocomplete, include php/llm.php separately.
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded. Build it with: make build\n");
}

echo "🚀 Fluent Interface Demo\n";
echo str_repeat("=", 50) . "\n\n";

try {
    $messages = new MessageCollection();
    $messages->addSystem('You are a helpful assistant. Be concise.');
    $messages->addUser('Write a haiku about Rust programming language');
    
    // Create LLM with fluent configuration - all methods return $this
    $response = (new LLM('openai:gpt-4o-mini'))
        ->setTemperature(0.8)
        ->setMaxTokens(100)
        ->setTopP(0.9)
        ->setFrequencyPenalty(0.1)
        ->setPresencePenalty(0.1)
        ->complete($messages);
    
    echo "👤 User: Write a haiku about Rust programming language\n\n";
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    echo "📊 Response Info:\n";
    echo "   Model: " . $response->getModel() . "\n";
    echo "   Finish reason: " . $response->getFinishReason() . "\n";
    echo "   Total tokens: " . $response->getUsage()->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
