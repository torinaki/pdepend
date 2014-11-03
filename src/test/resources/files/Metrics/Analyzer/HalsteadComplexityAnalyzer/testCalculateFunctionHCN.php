<?php
function operators1()
{
    $a = ($a * $a) + $a;
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

function classes() {
    stdClass::method();
    stdClass::class;
    new stdClass();
}
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