<?php
/**
 * Basic completion example
 * 
 * Demonstrates simple single-turn completion with LLM.
 * 
 * Note: For IDE autocomplete, include php/llm.php separately.
 * Don't include stubs when running with the actual extension.
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded. Build it with: make build\n");
}

echo "🚀 Basic Completion Example\n";
echo str_repeat("=", 50) . "\n\n";

try {
    $llm = new LLM('openai:gpt-4o-mini');
    $llm->setTemperature(0.7);
    $llm->setMaxTokens(100);
    
    echo "Model: openai:gpt-4o-mini | Temp: 0.7 | Max tokens: 100\n\n";
    
    $messages = new MessageCollection();
    $messages->addSystem('You are a helpful assistant. Be concise.');
    $messages->addUser('What is PHP?');
    
    echo "👤 User: What is PHP?\n\n";
    
    $response = $llm->complete($messages);
    
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    $usage = $response->getUsage();
    echo "📊 Usage:\n";
    echo "   Prompt tokens: " . $usage->getPromptTokens() . "\n";
    echo "   Output tokens: " . $usage->getOutputTokens() . "\n";
    echo "   Total tokens: " . $usage->getTotalTokens() . "\n";
    
    echo "\n📄 Model: " . $response->getModel() . "\n";
    echo "🏁 Finish reason: " . $response->getFinishReason() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
