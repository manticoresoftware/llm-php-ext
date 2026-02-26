<?php
/**
 * Exception class tests
 *
 * Verifies that all LLM exception classes:
 * 1. Are registered (exist as classes)
 * 2. Extend \Exception (and therefore implement \Throwable)
 * 3. Can be instantiated and thrown/caught
 */

class ExceptionTest {
    private static array $exceptionClasses = [
        'LLMException',
        'LLMConnectionException',
        'LLMValidationException',
        'LLMStructuredOutputException',
        'LLMToolCallException',
    ];

    public static function testAllExceptionClassesExist(): void {
        foreach (self::$exceptionClasses as $class) {
            TestAssert::assert(
                class_exists($class),
                "Class {$class} does not exist — not registered in get_module()"
            );
        }
    }

    public static function testAllExceptionClassesExtendException(): void {
        foreach (self::$exceptionClasses as $class) {
            TestAssert::assert(
                class_exists($class),
                "Class {$class} does not exist"
            );
            $parents = class_parents($class);
            TestAssert::assert(
                isset($parents['Exception']) || in_array('Exception', $parents, true),
                "{$class} does not extend \\Exception — catch(\\Throwable) will not work"
            );
        }
    }

    public static function testAllExceptionClassesImplementThrowable(): void {
        foreach (self::$exceptionClasses as $class) {
            TestAssert::assert(
                class_exists($class),
                "Class {$class} does not exist"
            );
            $interfaces = class_implements($class);
            TestAssert::assert(
                isset($interfaces['Throwable']) || in_array('Throwable', $interfaces, true),
                "{$class} does not implement \\Throwable"
            );
        }
    }

    public static function testExceptionClassesCanBeThrown(): void {
        foreach (self::$exceptionClasses as $class) {
            $caught = false;
            try {
                throw new $class("test message");
            } catch (\Exception $e) {
                $caught = true;
                TestAssert::assertEquals("test message", $e->getMessage(), "{$class} message mismatch");
                TestAssert::assertInstanceOf($class, $e, "Caught wrong type for {$class}");
            }
            TestAssert::assert($caught, "{$class} was not caught by catch(\\Exception)");
        }
    }

    public static function testExceptionClassesCaughtAsThrowable(): void {
        foreach (self::$exceptionClasses as $class) {
            $caught = false;
            try {
                throw new $class("throwable test");
            } catch (\Throwable $e) {
                $caught = true;
                TestAssert::assertInstanceOf($class, $e, "Caught wrong type for {$class} via Throwable");
            }
            TestAssert::assert($caught, "{$class} was not caught by catch(\\Throwable)");
        }
    }
}
