<?php

define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));

spl_autoload_register(function ($class_name) {
    switch ($class_name) {
        case "APP_GameClass":
        case "APP_Object":
            var_dump($class_name);
            //var_dump(APP_GAMEMODULE_PATH);
            include_once APP_GAMEMODULE_PATH . "/module/table/table.game.php";
            break;
        case "PHPUnit\\Framework\\TestCase":
            if (FAKE_PHPUNIT) {
                include "tests/FakeTestCase.php";
                break;
            }
            include $class_name . ".php";
            break;
        case "Tapestry":
            include_once "tapestry.game.php";
            break;
        case "Deck":
            include_once APP_GAMEMODULE_PATH . "/module/common/deck.game.php";
            break;
        default:
            include_once "modules/civs/" . $class_name . ".php";
            break;
    }
});
