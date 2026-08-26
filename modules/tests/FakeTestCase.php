<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

use feException;

class TestCase {
    function runTestMethod(string $method) {
        $this->setUp();
        $this->$method();
    }

    function fail($string = null) {
        if ($string) throw new feException($string);
        else throw new feException("assertion failed");
    }
    function assertNotNull($exp, $string = null) {
        if ($exp === null) $this->fail($string);
    }
    function assertNull($exp, $string = null) {
        if ($exp !== null) $this->fail($string);
    }
    function assertTrue($exp, $string = null) {
        if (!$exp) $this->fail($string);
    }
    
    function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected != $actual)  $this->fail($message);
    }
    function assertFalse($exp, $string = null) {
        if ($exp) $this->fail($string);
    }
    function assertStringContainsString($needle, $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) === false) $this->fail($message);
    }
    function assertStringNotContainsString($needle, $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) !== false) $this->fail($message);
    }
}
