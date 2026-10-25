<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * upgradeTableDb on a real MySQL. The income spot repair of v261025-1540 has to fix the undo copy too,
 * since an undo to a savepoint taken before the upgrade brings its rows back, and a re-run (an undo can
 * call the upgrade again) must not move anything.
 */
final class UpgradeTableDbTest extends TestWithRealDb {
    const UNDO = "zz_savepoint_";
    /** Income level per track; 12 has an exhausted track */
    const LEVELS = [11 => [1 => 1, 2, 3, 4], 12 => [1 => 6, 5, 1, 2]];

    private GameUT $game;

    protected function setUp(): void {
        parent::setUp();
        $this->game = new GameUT();
        foreach (self::LEVELS as $player_id => $levels) {
            $this->game->DbQuery(
                "INSERT INTO playerextra (player_id, player_name, player_avatar, player_color) VALUES ($player_id, 'p$player_id', '', '000000')"
            );
            foreach ($levels as $track => $level) {
                $this->setLevel($player_id, $track, $level);
                for ($spot = $level + 1; $spot <= 6; $spot++) {
                    $this->game->DbQuery(
                        "INSERT INTO structure (card_type, card_type_arg, card_location, card_location_arg, card_location_arg2) VALUES ($track, 0, 'income', $player_id, $spot)"
                    );
                }
            }
        }
        // what doUndoSavePoint does at the end of a move; tapcommon skips it in the setup state
        BgaTestDb::copyTables("", self::UNDO);
    }

    private function setLevel(int $player_id, int $track, int $level, string $prefix = ""): void {
        $field = $this->game->getIncomeTrackDbColumn($track);
        $this->game->DbQuery("UPDATE {$prefix}playerextra SET $field = $level WHERE player_id = $player_id");
    }

    /** The layout before v261024-2009: six buildings of each type on the track while level 1, none with a spot. */
    private function unmigrate(string $prefix): void {
        $this->game->DbQuery("UPDATE {$prefix}structure SET card_location_arg2 = '0' WHERE card_location = 'income'");
        $this->game->DbQuery(
            "INSERT INTO {$prefix}structure (card_type, card_type_arg, card_location, card_location_arg)
            SELECT t.track, 0, 'income', p.player_id FROM {$prefix}playerextra p
            JOIN (SELECT 1 track UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) t"
        );
    }

    private function snapshot(): array {
        $levels = implode(", ", array_map(fn($track) => $this->game->getIncomeTrackDbColumn($track), range(1, 4)));
        $snapshot = [];
        foreach (["", self::UNDO] as $prefix) {
            $snapshot[$prefix] = [
                "spots" => $this->game->getCollectionFromDB(
                    "SELECT card_id, card_location_arg2 FROM {$prefix}structure WHERE card_location = 'income'",
                    true
                ),
                "levels" => $this->game->getCollectionFromDB("SELECT player_id, $levels FROM {$prefix}playerextra"),
            ];
        }
        return $snapshot;
    }

    public static function unmigratedTables(): array {
        return [
            "undo copy only" => [[self::UNDO]],
            "live and undo copy" => [["", self::UNDO]],
            "already migrated" => [[]],
        ];
    }

    #[DataProvider("unmigratedTables")]
    public function testRestoresTheSpotsOfEveryTable(array $prefixes): void {
        $before = $this->snapshot();
        foreach ($prefixes as $prefix) {
            $this->unmigrate($prefix);
        }
        $this->assertNotEquals($prefixes ? $before : [], $this->snapshot());

        $this->game->upgradeTableDb(2610250248);

        $this->assertEquals($before, $this->snapshot());
    }

    /** A leftover sixth building claimed from an unmigrated level 6 track left the track at level 7. */
    public function testCapsALevelSevenTrack(): void {
        $before = $this->snapshot();
        $this->setLevel(12, 1, 7);
        $this->setLevel(12, 1, 7, self::UNDO);

        $this->game->upgradeTableDb(2610250248);

        $this->assertEquals($before, $this->snapshot());
    }

    /** Placed rows are never moved: an Artificers slide leaves a gap that is not the id order. */
    public function testLeavesASlidTrackAlone(): void {
        $this->game->DbQuery(
            "UPDATE structure SET card_location_arg2 = '4' WHERE card_location_arg = 11 AND card_type = '4' AND card_location_arg2 = '5'"
        );
        $before = $this->snapshot();

        $this->game->upgradeTableDb(2610250248);

        $this->assertEquals($before, $this->snapshot());
    }

    public function testSkipsNewerTables(): void {
        $this->unmigrate("");
        $before = $this->snapshot();

        $this->game->upgradeTableDb(2610250249);

        $this->assertEquals($before, $this->snapshot());
    }
}
