<?php
/**
 * Structured output example
 * 
 * Demonstrates requesting structured JSON output from LLM using JSON Schema.
 * 
 * Note: For IDE autocomplete, include php/llm.php separately.
 */

if (!extension_loaded('llm')) {
    die("❌ LLM extension not loaded. Build it with: make build\n");
}

echo "📋 Structured Output Demo\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Define a JSON Schema for structured output
    $schema = json_encode([
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'number'],
            'skills' => [
                'type' => 'array',
                'items' => ['type' => 'string']
            ],
            'experience_years' => ['type' => 'number']
        ],
        'required' => ['name', 'age', 'skills']
    ], JSON_PRETTY_PRINT);
    
    echo "✅ Created JSON Schema\n\n";
    
    // Create messages
    $messages = new MessageCollection();
    $messages->addSystem('You are a helpful assistant that provides structured data.');
    $messages->addUser('Tell me about a senior software engineer');
    
    echo "👤 User: Tell me about a senior software engineer\n\n";
    
    // Use structured builder
    $response = (new LLM('openai:gpt-4o-mini'))
        ->structured($schema)
        ->setTemperature(0.8)
        ->complete($messages);
    
    echo "🤖 Assistant: " . $response->getContent() . "\n\n";
    
    // Note: getStructured() has a known issue with Zval cloning in ext-php-rs 0.15.x
    // The structured data is in the content field as JSON
    echo "📊 Structured Data (from content):\n";
    $data = json_decode($response->getContent(), true);
    print_r($data);
    
    echo "\n📊 Total tokens: " . $response->getUsage()->getTotalTokens() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
