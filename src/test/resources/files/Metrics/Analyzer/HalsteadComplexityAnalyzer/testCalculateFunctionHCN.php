<?php
function operators1()
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

function operators2() {
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

function operators3() {
    $a = function () {
        $a;
        return $a;
    };
    return $a;
}

//class TestClass extends stdClass
//{class TestClass extends stdClass
//{
    function classes()
    {
        Foo::method();
        Foo::class;
        new Foo();
        $foo->{$foo}();
        Foo::$${$foo}();
//        Foo::$bar();
//        Foo::$$bar();
//        Foo::$$$bar();
//        parent::method();
//        self::method();
//        static::method();
    }
//}

//function cycles()
//{
//    while (false) {
//        continue;
//    }
//    do {
//        break;
//    } while (true);
//    for ($i = 1; $i < 1; $i++) {
//    }
//}
//
//function others() {
//    global $a;
//    static $a;
//    declare(ticks=42) {};
//    echo "Hello world";
//    eval('$a;');
//    exit;
//
//    goto a;
//    a:
//    include "";
//    include_once "";
//    require "";
//    require_once "";
//    isset($a);
//    unset($a);
//    list($a) = $a;
//    throw $a;
//};
//
//function strings() {
//    "...";
//    "... $a ...";
//    "... ${$a} ...";
//}ction cycles()
//{
//    while (false) {
//        continue;
//    }
//    do {
//        break;
//    } while (true);
//    for ($i = 1; $i < 1; $i++) {
//    }
//}
//
//function others() {
//    global $a;
//    static $a;
//    declare(ticks=42) {};
//    echo "Hello world";
//    eval('$a;');
//    exit;
//
//    goto a;
//    a:
//    include "";
//    include_once "";
//    require "";
//    require_once "";
//    isset($a);
//    unset($a);
//    list($a) = $a;
//    throw $a;
//};
//
//function strings() {
//    "...";
//    "... $a ...";
//    "... ${$a} ...";
//}
//function pdepend2($x)
//{
//    foreach ($x as $y) {
//        for ($i = 0; $i < $y; ++$i) {
//            try {
//                if ($x->get($i) === 0 and $x->get($i) > 23) {
//                    return false;
//                } else if ($x->get($i) === 1 || true) {
//                    return true;
//                } else if ($x->get($i) === 1 or false) {
//                    return false;
//                }
//            } catch (Exception $e) {}
//        }
//    }
//}