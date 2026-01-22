<?php
/**
 * Multi-turn conversation example
 * 
 * Demonstrates maintaining conversation context with MessageCollection.
 * 
 * Note: For IDE autocomplete, include php/llm.php separately.
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded. Build it with: make build\n");
}

echo "💬 Multi-turn Conversation Demo\n";
echo str_repeat("=", 50) . "\n\n";

try {
    $llm = new LLM('openai:gpt-4o-mini');
    
    $conversation = new MessageCollection();
    $conversation->addSystem('You are a helpful assistant. Be concise.');
    
    // First turn
    echo "👤 Turn 1: What is 2 + 2?\n";
    $conversation->addUser('What is 2 + 2?');
    
    $response = $llm->complete($conversation);
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    $conversation->addAssistant($response->getContent());
    
    // Second turn
    echo "👤 Turn 2: What about 3 + 3?\n";
    $conversation->addUser('What about 3 + 3?');
    
    $response = $llm->complete($conversation);
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    $conversation->addAssistant($response->getContent());
    
    // Third turn
    echo "👤 Turn 3: And 5 + 5?\n";
    $conversation->addUser('And 5 + 5?');
    
    $response = $llm->complete($conversation);
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    echo "📊 Conversation has {$conversation->count()} messages\n";
    echo "📊 Total tokens used: " . $response->getUsage()->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
