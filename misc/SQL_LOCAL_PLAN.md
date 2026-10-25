# Real SQL in tests

Most tests run against the in-memory stubs in `~/git/bga-sharedcode/misc/php/stubs/`, which never
execute SQL. Tests that need the game's raw SQL to really run (migrations, Deck moves, undo copies)
extend `TestWithRealDb` and get a throwaway MySQL 8. Built 2026-10-25 for `upgradeTableDb`, see
[tests/UpgradeTableDbTest.php](../tests/UpgradeTableDbTest.php).

## Setup (once per machine)

- `sudo apt install mysql-server php8.4-mysql`, then `sudo systemctl disable --now mysql`: tests
  start their own server.
- Without them the real-DB tests are skipped, so `npm run tests` still passes.

## Writing a test

- Extend `TestWithRealDb` ([tests/Stubs/TestWithRealDb.php](../tests/Stubs/TestWithRealDb.php)).
  Every test starts from empty tables built from `dbmodel.sql`.
- Seed rows with `$game->DbQuery(...)`; read back with the usual `get*FromDB`.
- `BgaTestDb::copyTables("", "zz_savepoint_")` makes the undo copy, as `doUndoSavePoint` does at
  the end of a move (tapcommon skips it in a multiactive state such as setup).
- `GameUT` still keeps card, structure and benefit in memory for the Deck objects and the
  overridden effects. Only raw SQL goes to MySQL, so pick that path in a real-DB test.
- Prove the test can fail: break the code under test and watch it go red.

## How it works

- `BgaTestDb` (bga-sharedcode stubs) starts `mysqld` on 127.0.0.1:33061 with its data in
  `/tmp/bga-testdb` (`BGA_TESTDB_DIR` overrides), or reuses a server already running there. Each
  process gets its own database `bgatest_<pid>`, dropped at exit; a server it started is shut down.
- While a test is attached, the framework DB stubs (`DbQuery`, `get*FromDB`, `DbGetLastId`, ...)
  run on that connection; otherwise they keep their no-op behaviour.
- `applyDbUpgradeToAllDB` runs on the live tables and every `zz_` copy that exists, like BGA.
  `doUndoSavePoint` / `undoRestorePoint` copy the tables to and from `zz_savepoint_`.
- The framework `player` and `global` tables are an approximation of the BGA schema.
- Cost: about 6s for the 6 tests of UpgradeTableDbTest, server start and schema included.

## Target engine

- BGA wiki (Studio page): "SQL: MySQL 5.7 (prod) - on studio 8.0". Tests run on 8.0.46.
- Stay in the 5.7 subset: no CTEs (`WITH`), no window functions (`ROW_NUMBER() OVER`), no
  `LATERAL`, no `JSON_TABLE`. Nothing enforces it yet.

## Gotchas

- The socket path must be at most 107 bytes and is required even for TCP.
- A plain `mysqld ... &` from PHP inherits the test runner's output pipe, so a piped
  `npm run tests | tail` never ends; `BgaTestDb` closes the inherited descriptors.
- AppArmor did not block a datadir outside `/var/lib/mysql`.

## Ideas, not done

- A lint that rejects 8.0-only SQL in every query a real-DB test sends.
- More real-DB tests for other raw SQL in the game.
- Back GameUT's Deck objects with the real tables instead of DeckInMem.

## Fallback: test on studio with debug methods

What we did before the harness. Make one move so `zz_savepoint_*` exists, then a temporary `debug_`
method snapshots the rows, puts them back to the old layout with plain `DbQuery`, calls
`upgradeTableDb(<old version>)`, snapshots again and logs OK or FAILED via `debugConsole` (log, do
not throw: a throw rolls the transaction back). Delete the methods before commit.
