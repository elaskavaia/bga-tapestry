<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base for tests that run the game's raw SQL against a real MySQL 8 (BgaTestDb in the shared stubs, see
 * misc/SQL_LOCAL_PLAN.md). The schema is dbmodel.sql and every test starts from empty tables. Skipped when
 * MySQL is not installed.
 */
abstract class TestWithRealDb extends TestCase {
    protected function setUp(): void {
        if (!BgaTestDb::start(__DIR__ . "/../../dbmodel.sql")) {
            $this->markTestSkipped("needs mysql-server and php8.4-mysql");
        }
        BgaTestDb::attach();
    }

    protected function tearDown(): void {
        BgaTestDb::detach();
    }
}
