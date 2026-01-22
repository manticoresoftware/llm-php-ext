<?php
/**
 * Tool class tests
 */

class ToolTest {
    public static function testToolCreation(): void {
        $params = ['type' => 'object', 'properties' => []];
        $tool = new Tool(
            'test_func',
            'Test function',
            $params
        );
        
        TestAssert::assertEquals('test_func', $tool->getName());
        TestAssert::assertEquals('Test function', $tool->getDescription());
        $params = $tool->getParameters();
        $data = json_decode($params, true);
        TestAssert::assertEquals('object', $data['type']);
    }
    
    public static function testToolFromArray(): void {
        $tool = Tool::fromArray([
            'name' => 'test_func',
            'description' => 'Test function',
            'parameters' => ['type' => 'object', 'properties' => []]
        ]);
        
        TestAssert::assertEquals('test_func', $tool->getName());
        TestAssert::assertEquals('Test function', $tool->getDescription());
    }
    
    public static function testToolToArray(): void {
        $params = ['type' => 'object'];
        $tool = new Tool(
            'test_func',
            'Test function',
            $params
        );
        
        $arr = $tool->toArray();
        TestAssert::assertEquals('test_func', $arr['name']);
        TestAssert::assertEquals('Test function', $arr['description']);
        TestAssert::assertIsArray($arr['parameters']);
    }
    
    public static function testToolToJson(): void {
        $params = ['type' => 'object'];
        $tool = new Tool(
            'test_func',
            'Test function',
            $params
        );
        
        $json = $tool->toJson();
        $data = json_decode($json, true);
        
        TestAssert::assertEquals('test_func', $data['name']);
        TestAssert::assertEquals('Test function', $data['description']);
    }
    
    public static function testToolWithComplexParameters(): void {
        $params = [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City and state'
                ],
                'unit' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit']
                ]
            ],
            'required' => ['location']
        ];
        
        $tool = new Tool(
            'get_weather',
            'Get weather',
            $params
        );
        
        $toolParams = $tool->getParameters();
        $data = json_decode($toolParams, true);
        
        TestAssert::assertEquals('get_weather', $tool->getName());
        TestAssert::assertEquals('Get weather', $tool->getDescription());
        TestAssert::assertEquals('object', $data['type']);
        TestAssert::assertIsArray($data['properties']);
    }
}
