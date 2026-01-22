<?php
/**
 * Message class tests
 * 
 * Note: Message static factory methods (user, assistant, system, tool) are not exposed
 * in the current implementation. Tests use MessageCollection instead.
 */

class MessageTest {
    public static function testMessageCollectionEmpty(): void {
        $collection = new MessageCollection();
        TestAssert::assertEquals(0, $collection->count());
    }
    
    public static function testMessageCollectionAdd(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        
        TestAssert::assertEquals(1, $collection->count());
    }
    
    public static function testMessageCollectionAddUser(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        
        TestAssert::assertEquals(1, $collection->count());
        $msg = $collection->get(0);
        TestAssert::assertNotNull($msg);
        // Note: Message::getRole() is not exposed, so we can't verify the role
    }
    
    public static function testMessageCollectionAddAssistant(): void {
        $collection = new MessageCollection();
        $collection->addAssistant('Hi!');
        
        TestAssert::assertEquals(1, $collection->count());
        $msg = $collection->get(0);
        TestAssert::assertNotNull($msg);
    }
    
    public static function testMessageCollectionAddSystem(): void {
        $collection = new MessageCollection();
        $collection->addSystem('Be helpful');
        
        TestAssert::assertEquals(1, $collection->count());
        $msg = $collection->get(0);
        TestAssert::assertNotNull($msg);
    }
    
    public static function testMessageCollectionAddToolResult(): void {
        $collection = new MessageCollection();
        $collection->addToolResult('call_123', 'Result');
        
        TestAssert::assertEquals(1, $collection->count());
        $msg = $collection->get(0);
        TestAssert::assertNotNull($msg);
    }
    
    public static function testMessageCollectionGet(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        $collection->addAssistant('Hi!');
        
        $msg = $collection->get(0);
        TestAssert::assertNotNull($msg);
        
        $msg = $collection->get(1);
        TestAssert::assertNotNull($msg);
        
        $msg = $collection->get(2);
        TestAssert::assertNull($msg);
    }
    
    public static function testMessageCollectionAll(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        $collection->addAssistant('Hi!');
        
        $all = $collection->all();
        TestAssert::assertCount(2, $all);
    }
    
    public static function testMessageCollectionToArray(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        
        $arr = $collection->toArray();
        TestAssert::assertIsArray($arr);
        TestAssert::assertCount(1, $arr);
    }
    
    public static function testMessageCollectionToJson(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        
        $json = $collection->toJson();
        $data = json_decode($json, true);
        TestAssert::assertIsArray($data);
        TestAssert::assertCount(1, $data);
    }
    
    public static function testMessageCollectionMultipleMessages(): void {
        $collection = new MessageCollection();
        $collection->addUser('Hello');
        $collection->addAssistant('Hi!');
        $collection->addSystem('Be helpful');
        $collection->addToolResult('call_123', 'Result');
        
        TestAssert::assertEquals(4, $collection->count());
    }
}
