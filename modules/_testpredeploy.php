<?php

declare(strict_types=1);

// run this before deploy!

define("FAKE_PHPUNIT", 1);


require_once "modules/_autoload.php";
require_once "modules/tests/GameTest.php";


$x = new GameTest("GameTest");
$methods = get_class_methods($x);
foreach ($methods as $method) {
    if (startsWith($method,"test")) {
        echo("calling $method\n");
        $x->runTestMethod($method);
    }
}

echo "DONE, ALL GOOD\n";