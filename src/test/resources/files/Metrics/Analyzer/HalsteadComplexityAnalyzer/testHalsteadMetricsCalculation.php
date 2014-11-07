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