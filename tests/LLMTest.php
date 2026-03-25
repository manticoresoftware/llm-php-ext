<?php
/**
 * LLM class tests
 */

class LLMTest {
    public static function testInstantiationWithApiKey(): void {
        $llm = new LLM('openai:gpt-4o', [
            'api_key' => 'sk-test-key-123',
        ]);
        TestAssert::assertInstanceOf('LLM', $llm);
        // Verify env var was set
        TestAssert::assertEquals('sk-test-key-123', getenv('OPENAI_API_KEY'));
    }

    public static function testInstantiationWithBaseUrl(): void {
        $llm = new LLM('openai:gpt-4o', [
            'base_url' => 'https://custom.api.example.com/v1',
        ]);
        TestAssert::assertInstanceOf('LLM', $llm);
        TestAssert::assertEquals('https://custom.api.example.com/v1', getenv('OPENAI_API_URL'));
    }

    public static function testInstantiationWithAllOptions(): void {
        $llm = new LLM('anthropic:claude-3.5-sonnet', [
            'api_key' => 'sk-ant-test-456',
            'base_url' => 'https://custom.anthropic.example.com',
        ]);
        TestAssert::assertInstanceOf('LLM', $llm);
        TestAssert::assertEquals('sk-ant-test-456', getenv('ANTHROPIC_API_KEY'));
        TestAssert::assertEquals('https://custom.anthropic.example.com', getenv('ANTHROPIC_API_URL'));
    }

    public static function testInstantiationWithEmptyOptions(): void {
        $llm = new LLM('openai:gpt-4o', []);
        TestAssert::assertInstanceOf('LLM', $llm);
    }

    public static function testInstantiationWithoutOptions(): void {
        $llm = new LLM('openai:gpt-4o');
        TestAssert::assertInstanceOf('LLM', $llm);
    }
    
    public static function testSetTemperature(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->setTemperature(0.8);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testSetMaxTokens(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->setMaxTokens(500);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testSetTopP(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->setTopP(0.9);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testSetFrequencyPenalty(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->setFrequencyPenalty(0.5);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testSetPresencePenalty(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->setPresencePenalty(0.5);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testWithOptions(): void {
        $llm = new LLM('openai:gpt-4o');
        $result = $llm->withOptions([
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 0.9
        ]);
        TestAssert::assertInstanceOf('LLM', $result);
    }
    
    public static function testStructuredBuilder(): void {
        $llm = new LLM('openai:gpt-4o');
        $builder = $llm->structured();
        TestAssert::assertInstanceOf('StructuredBuilder', $builder);
    }
    
    public static function testStructuredBuilderWithSchema(): void {
        $llm = new LLM('openai:gpt-4o');
        $schema = json_encode(['type' => 'object']);
        $builder = $llm->structured($schema);
        TestAssert::assertInstanceOf('StructuredBuilder', $builder);
    }
    
    public static function testToolBuilder(): void {
        $llm = new LLM('openai:gpt-4o');
        $builder = $llm->withTools([]);
        TestAssert::assertInstanceOf('ToolBuilder', $builder);
    }
    
    public static function testToolBuilderWithTools(): void {
        $llm = new LLM('openai:gpt-4o');
        $params = ['type' => 'object'];
        $tool = new Tool('test', 'Test', $params);
        $builder = $llm->withTools([$tool]);
        TestAssert::assertInstanceOf('ToolBuilder', $builder);
    }
}
