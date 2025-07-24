<?php

namespace App\Tests\Util;

use App\Util\OptionalPropertyTrait;
use PHPUnit\Framework\TestCase;

class OptionalPropertyTraitTest extends TestCase
{

    public function test_has_property(): void
    {
        $object = new MyClass();

        // Test with initialized property
        $object->initializedProperty = 10;
        $this->assertTrue($object->hasProperty('initializedProperty'));

        // Test with uninitialized property
        $this->assertFalse($object->hasProperty('uninitializedProperty'));
    }

}

class MyClass {
    use OptionalPropertyTrait;

    public int $initializedProperty;
    public int $uninitializedProperty;
}