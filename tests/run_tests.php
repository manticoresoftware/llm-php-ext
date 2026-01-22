<?php
/**
 * Test runner for LLM extension
 * 
 * Note: Do NOT include the stub file (php/llm.php) when running with the actual extension.
 * The extension already defines all classes.
 */

// Check if extension is loaded
if (!extension_loaded('llm')) {
    die("ERROR: LLM extension is not loaded. Run with: php -d 'extension=target/debug/libllm.dylib' tests/run_tests.php\n");
}

class TestRunner {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    
    public function addTest(string $name, callable $test): void {
        $this->tests[] = ['name' => $name, 'test' => $test];
    }
    
    public function run(): void {
        echo "Running LLM Extension Tests\n";
        echo str_repeat("=", 50) . "\n\n";
        
        foreach ($this->tests as $test) {
            $this->runTest($test['name'], $test['test']);
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        exit($this->failed > 0 ? 1 : 0);
    }
    
    private function runTest(string $name, callable $test): void {
        echo "Testing: {$name}... ";
        
        try {
            $test();
            echo "✓ PASSED\n";
            $this->passed++;
        } catch (TestAssert $e) {
            echo "✗ FAILED\n";
            echo "  " . $e->getMessage() . "\n";
            $this->failed++;
        } catch (Exception $e) {
            echo "✗ ERROR\n";
            echo "  " . $e->getMessage() . "\n";
            $this->failed++;
        }
    }
}

class TestAssert extends Exception {
    public static function assert(bool $condition, string $message): void {
        if (!$condition) {
            throw new self($message);
        }
    }
    
    public static function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new self(
                ($message ? $message . ': ' : '') .
                "Expected " . var_export($expected, true) .
                ", got " . var_export($actual, true)
            );
        }
    }
    
    public static function assertInstanceOf(string $class, $object, string $message = ''): void {
        if (!($object instanceof $class)) {
            throw new self(
                ($message ? $message . ': ' : '') .
                "Expected instance of {$class}, got " . get_class($object)
            );
        }
    }
    
    public static function assertNotNull($value, string $message = ''): void {
        if ($value === null) {
            throw new self(($message ? $message . ': ' : '') . 'Value is null');
        }
    }
    
    public static function assertCount(int $expected, $countable, string $message = ''): void {
        $actual = count($countable);
        if ($expected !== $actual) {
            throw new self(
                ($message ? $message . ': ' : '') .
                "Expected count {$expected}, got {$actual}"
            );
        }
    }
    
    public static function assertIsArray($value, string $message = ''): void {
        if (!is_array($value)) {
            throw new self(
                ($message ? $message . ': ' : '') .
                "Expected array, got " . gettype($value)
            );
        }
    }
    
    public static function assertNull($value, string $message = ''): void {
        if ($value !== null) {
            throw new self(
                ($message ? $message . ': ' : '') .
                "Expected null, got " . var_export($value, true)
            );
        }
    }
}

// Include all test files
require_once __DIR__ . '/LLMTest.php';
require_once __DIR__ . '/MessageTest.php';
require_once __DIR__ . '/ToolTest.php';

// Run tests
$runner = new TestRunner();

// LLM tests
$runner->addTest('LLM instantiation', function() {
    $llm = new LLM('openai:gpt-4o');
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM setters', function() {
    $llm = new LLM('openai:gpt-4o');
    // Setters return null, not $this, so no chaining
    $llm->setTemperature(0.8);
    $llm->setMaxTokens(500);
    $llm->setTopP(0.9);
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM fluent setters', function() {
    $llm = (new LLM('openai:gpt-4o'))
        ->setTemperature(0.8)
        ->setMaxTokens(500)
        ->setTopP(0.9);
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM withOptions', function() {
    $llm = new LLM('openai:gpt-4o', [
        'api_key' => 'test-key',
        'timeout' => 60
    ]);
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM withOptions', function() {
    $llm = new LLM('openai:gpt-4o');
    $llm->withOptions([
        'temperature' => 0.7,
        'max_tokens' => 1000
    ]);
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM fluent setters', function() {
    $llm = (new LLM('openai:gpt-4o'))
        ->setTemperature(0.8)
        ->setMaxTokens(500)
        ->setTopP(0.9);
    TestAssert::assertInstanceOf('LLM', $llm);
});

$runner->addTest('LLM withOptions fluent', function() {
    $llm = (new LLM('openai:gpt-4o'))
        ->withOptions([
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9
        ]);
    TestAssert::assertInstanceOf('LLM', $llm);
});

// Message tests - using MessageCollection since Message static methods are not exposed
$runner->addTest('MessageCollection add methods', function() {
    $collection = new MessageCollection();
    $collection->addUser('Hello');
    $collection->addAssistant('Hi!');
    $collection->addSystem('Be helpful');
    $collection->addToolResult('call_123', 'Result');
    
    TestAssert::assertEquals(4, $collection->count());
    
    $msg = $collection->get(0);
    TestAssert::assertNotNull($msg);
    // Note: Message class doesn't expose getRole() method, so we can't test it
});

$runner->addTest('MessageCollection serialization', function() {
    $collection = new MessageCollection();
    $collection->addUser('Hello');
    
    $arr = $collection->toArray();
    TestAssert::assertIsArray($arr);
    TestAssert::assertCount(1, $arr);
    
    $json = $collection->toJson();
    $data = json_decode($json, true);
    TestAssert::assertIsArray($data);
    TestAssert::assertCount(1, $data);
});

$runner->addTest('MessageCollection fluent API', function() {
    $collection = new MessageCollection();
    // Methods return null, not $this, so no chaining
    $collection->addUser('Hello');
    $collection->addAssistant('Hi!');
    $collection->addSystem('Be helpful');
    
    TestAssert::assertEquals(3, $collection->count());
});

$runner->addTest('MessageCollection fromArray', function() {
    // Create messages using MessageCollection
    $collection1 = new MessageCollection();
    $collection1->addUser('Hello');
    $collection1->addAssistant('Hi!');
    
    TestAssert::assertEquals(2, $collection1->count());
});

// Tool tests
$runner->addTest('Tool creation', function() {
    $params = ['type' => 'object', 'properties' => []];
    $tool = new Tool(
        'test_func',
        'Test function',
        $params
    );
    
    TestAssert::assertEquals('test_func', $tool->getName());
    TestAssert::assertEquals('Test function', $tool->getDescription());
});

$runner->addTest('Tool fromArray', function() {
    $tool = Tool::fromArray([
        'name' => 'test_func',
        'description' => 'Test function',
        'parameters' => ['type' => 'object']
    ]);
    
    TestAssert::assertEquals('test_func', $tool->getName());
});

$runner->addTest('Tool serialization', function() {
    $params = ['type' => 'object'];
    $tool = new Tool(
        'test_func',
        'Test function',
        $params
    );
    $arr = $tool->toArray();
    
    TestAssert::assertEquals('test_func', $arr['name']);
    TestAssert::assertEquals('Test function', $arr['description']);
});

$runner->addTest('Tool JSON serialization', function() {
    $params = ['type' => 'object'];
    $tool = new Tool(
        'test_func',
        'Test function',
        $params
    );
    $json = $tool->toJson();
    
    $data = json_decode($json, true);
    TestAssert::assertEquals('test_func', $data['name']);
});

// Run all tests
$runner->run();
