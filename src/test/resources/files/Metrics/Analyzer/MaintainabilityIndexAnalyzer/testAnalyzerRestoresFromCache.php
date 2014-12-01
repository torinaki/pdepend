<?php
function testFunction()
{
    // comment
    // second comment
    $a + $b;
    $c - $d;
}

namespace Test {

    abstract class TestClass
    {
        public function testMethod()
        {
            // comment
            // second comment
            $a + $b;
            $c - $d;
        }
    }

    abstract class TestClass
    {
        abstract function testAbstract();
    }
}