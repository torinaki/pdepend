<?php
function pdepend1($x, $y)
{
    $a = 1 + 1;
    $a = ' ' . ' ';
    $a += (9 + 1);
    $a += (9 + 1);
    $a = true ? 1 : 2 ;
    switch ($x) {
    case 'a':
        if ($a === true) {
             
        } else if ($a === false && $a !== 17) {
            
        } else {
            
        }
        break;
        
    default:
        if ($a === true) {}
        break;
    }
        
}

function pdepend2($x)
{
    foreach ($x as $y) {
        for ($i = 0; $i < $y; ++$i) {
            try {
                if ($x->get($i) === 0 and $x->get($i) > 23) {
                    return false;
                } else if ($x->get($i) === 1 || true) {
                    return true;
                } else if ($x->get($i) === 1 or false) {
                    return false;
                }
            } catch (Exception $e) {}
        }
    }
}