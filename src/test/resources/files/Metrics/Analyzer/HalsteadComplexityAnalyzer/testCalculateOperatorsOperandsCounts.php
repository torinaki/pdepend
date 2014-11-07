<?php
function operators()
{
    $a = (--$a++ * ++$a--) + $a;
    @$a;
    // String
    <<<EOD
abcd12345
EOD;
    // array
    array(1, 1, 1);
    $a[1];
    $a and $a or $a;
    (object)$a;
    clone $a;
}

function control_structures1() {
    try {

    } catch (\Exception $a) {

    }

    switch ($a) {
        case 'a':
            break;

        default:
    }

    if ($a === false && $a !== 1) {

    } else if ($a) {

    } elseif ($a) {

    } else {

    }
        
}

function control_structures2()
{
    while (false) {
        continue;                   // `;` will be ignored
    }
    do {
        break;                      // `;` will be ignored
    } while (true);                 // `;` will be ignored
    for ($i = 1; $i < 1; $i++) {    // all `;` will be ignored
    }
    declare(ticks=42) {}            // directives will be ignored
    goto a;                         // operator "goto" and operand "a", `;` will be ignored
    a:                              // operand "a" and operator ":"
}

function closures() {
    $a = function () {
        $a;
        return $a;
    };
    return $a;
}

function others() {
    static $a;
    eval('$a;');                    // string of code will not be parsed
    exit;
    include "";
    include_once "";
    require "";
    require_once "";
    isset($a);
    list($a) = $a;
};

function key_words ()
{
    global $a;                      // `;` will be ignored
    unset($a);                      // `;` will be ignored
    throw $a;                       // `;` will be ignored
    echo "Hello world";             // `;` will be ignored
}

function strings() {
    "...";
    "... $a ...";           // operands "... ", $a, " ..."
    "... ${$a} ...";        // operands "... ", $a, " ..."
                            // operators $, {}
}

class TestClass extends stdClass
{
    protected $property = 'value';

    function method()
    {
        Foo::method();
        Foo::class;
        new Foo();
        $foo->{$foo}();
        Foo::$${$foo}();
        Foo::$foo();
        Foo::$$foo();
        Foo::$$$foo();
        parent::method();   // `parent` operator
        self::method();     // `self` operator
        static::method();   // `static` operator
    }
}

trait TestTrait
{
    function method()
    {
        Foo::method();
    }
}

interface TestInterface
{
    function method();
}