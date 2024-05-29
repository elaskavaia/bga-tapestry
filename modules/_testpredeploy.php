<?php

declare(strict_types=1);

// run this before deploy!

define("FAKE_PHPUNIT", 1);


require_once "modules/_autoload.php";
require_once "modules/tests/GameTest.php";


$x = new GameTest();
$methods = get_class_methods($x);
foreach ($methods as $method) {
    if (startsWith($method,"test")) {
        echo("calling $method\n");
        call_user_func_array([$x, $method], []);
    }
}

echo "DONE, ALL GOOD\n";