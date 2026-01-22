<?php
/**
 * LLM class tests
 */

class LLMTest {
    public static function testInstantiation(): void {
        $llm = new LLM('openai:gpt-4o');
        TestAssert::assertInstanceOf('LLM', $llm);
    }
    
    public static function testInstantiationWithOptions(): void {
        $llm = new LLM('openai:gpt-4o', [
            'api_key' => 'test-key',
            'timeout' => 60
        ]);
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
