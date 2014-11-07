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

function operators2()
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