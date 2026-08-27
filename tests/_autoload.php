<?php

// PHPUnit bootstrap (see phpunit.xml). The real BGA framework is not available locally, so the
// tests run against the in-memory stubs in ~/git/bga-sharedcode, which also define the global
// `Table` and `Deck` classes the game extends.
define("APP_GAMEMODULE_PATH", getenv("APP_GAMEMODULE_PATH"));
require_once APP_GAMEMODULE_PATH . "/php/stubs/BgaFrameworkStubs.php";
