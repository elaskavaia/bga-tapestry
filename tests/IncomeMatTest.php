<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/**
 * The income mat: a building row carries the spot it covers, income pays the uncovered spots, a
 * claim takes the leftmost building. Written against the prefix layout every table has today, so
 * the seams that let a track become an arbitrary subset (Artificers) prove they changed nothing.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2).
 */
class IncomeMatUT extends MapUT {
    /** [player_id, benefit, count, reason] per awardBenefits call: the fingerprint of an income phase. */
    public array $awarded = [];

    function awardBenefits($player_id, $ben, $count = 1, $reason = "") {
        $this->awarded[] = [(int) $player_id, (int) $ben, (int) $count, $reason];
        return true;
    }

    /** isTapestryActive() reads the card table with raw SQL, which the in memory model never sees. */
    public array $tapestries = [];

    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries[$player_id] ?? []);
    }

    /** A track as today's tables lay it out: spots 1..level uncovered, a building on every spot after. */
    function layoutPrefix(int $player_id, int $type, int $level): array {
        return $this->layoutTrack($player_id, $type, $level < 6 ? range($level + 1, 6) : []);
    }

    function layoutPrefixMat(int $player_id, int $markets = 1, int $houses = 1, int $farms = 1, int $armories = 1): void {
        $this->layoutPrefix($player_id, BUILDING_MARKET, $markets);
        $this->layoutPrefix($player_id, BUILDING_HOUSE, $houses);
        $this->layoutPrefix($player_id, BUILDING_FARM, $farms);
        $this->layoutPrefix($player_id, BUILDING_ARMORY, $armories);
    }

    /** An old table: six buildings per type were created and none carries a spot. */
    function layoutUnmigrated(int $player_id, int $type, int $level): array {
        $ids = [];
        for ($i = 0; $i < 7 - $level; $i++) {
            $ids[] = $this->dbAddStructure($player_id, $type, 0, "income", 0);
        }
        $this->income[$player_id][$type] = $level;
        return $ids;
    }

    function spotOf(int $structure_id): int {
        return (int) $this->getStructureInfoById($structure_id)["card_location_arg2"];
    }

    function traders(): Traders {
        return $this->getCivilizationInstance(CIV_TRADERS, true);
    }
}

final class IncomeMatTest extends TestCase {
    private IncomeMatUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): IncomeMatUT {
        $game = new IncomeMatUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    /** What effect_IncomeBenefits awards for spots 1..level of every track, read off the material. */
    private function expectedIncome(IncomeMatUT $game, array $levels): array {
        $expected = [];
        foreach ($levels as $track => $level) {
            for ($spot = 1; $spot <= $level; $spot++) {
                foreach ($game->income_tracks[$track][$spot]["benefit"] as $ben) {
                    $expected[] = [IncomeMatUT::OWNER, $ben, 1, reason("inspot", "{$track}_{$spot}")];
                }
            }
        }
        return $expected;
    }

    // ------------------------------------------------------------ income

    function testIncomePaysSpots1ToLevelOfEveryTrackAtEveryLevel() {
        for ($level = 1; $level <= 6; $level++) {
            $game = $this->newGame();
            $game->layoutPrefixMat(IncomeMatUT::OWNER, $level, $level, $level, $level);
            $game->effect_IncomeBenefits(null, IncomeMatUT::OWNER);
            $this->assertEquals($this->expectedIncome($game, array_fill(1, 4, $level)), $game->awarded, "level $level");
        }
    }

    function testEveryTrackPaysItsOwnLevel() {
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, 1, 3, 5, 6);
        $this->game->effect_IncomeBenefits(null, IncomeMatUT::OWNER);
        $this->assertEquals($this->expectedIncome($this->game, [1 => 1, 2 => 3, 3 => 5, 4 => 6]), $this->game->awarded);
    }

    function testVpIncomePaysOnlyTheVpBenefitsOfTheUncoveredSpots() {
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, markets: 3, armories: 3);
        $this->game->effect_gainVPIncome(IncomeMatUT::OWNER);
        $owner = IncomeMatUT::OWNER;
        $this->assertEquals(
            [
                [$owner, BE_VP_TECH, 1, reason("inspot", "1_1")],
                [$owner, BE_VP_TECH, 1, reason("inspot", "1_3")],
                [$owner, BE_VP_CAPITAL, 1, reason("inspot", "2_1")],
                [$owner, BE_VP_TERRITORY, 1, reason("inspot", "4_3")],
            ],
            $this->game->awarded
        );
    }

    function testResourceIncomePaysOnlyTheResourcesOfTheUncoveredSpots() {
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, markets: 3);
        $this->game->effect_gainResourcesIncome(IncomeMatUT::OWNER);
        $owner = IncomeMatUT::OWNER;
        $this->assertEquals(
            [
                [$owner, RES_COIN, 1, reason("inspot", "1_1")],
                [$owner, RES_COIN, 1, reason("inspot", "1_2")],
                [$owner, RES_WORKER, 1, reason("inspot", "2_1")],
                [$owner, RES_FOOD, 1, reason("inspot", "3_1")],
                [$owner, RES_CULTURE, 1, reason("inspot", "4_1")],
            ],
            $this->game->awarded
        );
    }

    function testCardIncomePaysOnlyTheCardsOfTheUncoveredSpots() {
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, markets: 3, farms: 2);
        $this->game->effect_gainCardsIncome(IncomeMatUT::OWNER);
        $owner = IncomeMatUT::OWNER;
        $this->assertEquals(
            [[$owner, BE_TERRITORY, 1, reason("inspot", "3_1")], [$owner, BE_TAPESTRY, 1, reason("inspot", "4_1")]],
            $this->game->awarded
        );
    }

    /** MERCANTILISM converts the food total of the phase, two spots of the farms track here. */
    function testMercantilismFiresOffTheFoodTotal() {
        $this->game->tapestries[IncomeMatUT::OWNER] = [TAP_MERCANTILISM];
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, farms: 3);
        $this->game->effect_gainResourcesIncome(IncomeMatUT::OWNER);
        $this->assertEquals([(string) BE_ANYRES], $this->game->benefitLabels());
        $this->assertEquals(2, $this->game->benefitQueue()[0]["benefit_quantity"]);
    }

    /** CAPITALISM pays 2 VP per coin of the phase, two spots of the markets track here. */
    function testCapitalismFiresOffTheCoinTotal() {
        $this->game->tapestries[IncomeMatUT::OWNER] = [8];
        $this->game->layoutPrefixMat(IncomeMatUT::OWNER, markets: 3);
        $this->game->effect_gainResourcesIncome(IncomeMatUT::OWNER);
        $this->assertEquals(4, $this->game->dbGetScore(IncomeMatUT::OWNER));
    }

    // ------------------------------------------------------ uncovered spots

    function testUncoveredSpotsAreThePrefixAtEveryLevel() {
        for ($level = 1; $level <= 6; $level++) {
            $game = $this->newGame();
            $game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_MARKET, $level);
            $this->assertEquals(range(1, $level), $game->getIncomeUncoveredSpots(IncomeMatUT::OWNER, BUILDING_MARKET), "level $level");
        }
    }

    function testUncoveredSpotsFollowTheRows() {
        $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_FARM, [3, 5, 6]);
        $this->assertEquals([1, 2, 4], $this->game->getIncomeUncoveredSpots(IncomeMatUT::OWNER, BUILDING_FARM));
    }

    /** The level column and the rows are two views of one fact; a table where they disagree stops here, not in a wrong payout. */
    function testLevelDisagreeingWithTheRowsIsAnError() {
        $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_FARM, [4, 5, 6]);
        $this->game->income[IncomeMatUT::OWNER][BUILDING_FARM] = 2;
        $this->expectOutputRegex("/Internal Error during move 0: ERR:game:04/");
        $this->expectException(BgaUserException::class);
        $this->game->getIncomeUncoveredSpots(IncomeMatUT::OWNER, BUILDING_FARM);
    }

    // ------------------------------------------------------------- claim

    function testClaimTakesTheLeftmostBuildingAndUncoversItsSpotAtEveryLevel() {
        for ($type = 1; $type <= 4; $type++) {
            for ($level = 1; $level <= 5; $level++) {
                $game = $this->newGame();
                $ids = $game->layoutPrefix(IncomeMatUT::OWNER, $type, $level);
                $this->assertFalse($game->claimIncomeStructure($type, null), "track $type level $level");
                $row = $game->getStructureInfoById($ids[$level + 1]);
                $this->assertEquals("capital_structure", $row["card_location"], "track $type level $level");
                $this->assertEquals(0, $row["card_location_arg2"], "track $type level $level");
                $this->assertEquals($level + 1, $game->dbGetIncomeTrackLevel($type, IncomeMatUT::OWNER), "track $type level $level");
                $this->assertEquals(
                    range(1, $level + 1),
                    $game->getIncomeUncoveredSpots(IncomeMatUT::OWNER, $type),
                    "track $type level $level"
                );
            }
        }
    }

    function testClaimOnAnEmptyTrackDoesNothing() {
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_HOUSE, 6);
        $this->assertTrue($this->game->claimIncomeStructure(BUILDING_HOUSE, null));
        $this->assertEquals(6, $this->game->dbGetIncomeTrackLevel(BUILDING_HOUSE, IncomeMatUT::OWNER));
        $this->assertCount(1, $this->game->notificationsOfType("message_error"));
    }

    function testTheLeftmostBuildingIsTheOneOnTheSmallestSpot() {
        $ids = $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_FARM, [5, 2, 4]);
        $this->assertEquals($ids[2], $this->game->dbGetIncomeBuildingOfType(BUILDING_FARM, false, true, IncomeMatUT::OWNER));
    }

    /** Exhaustion is an empty track: the last building, on spot 6, is still there to claim. */
    function testExhaustionIsAnEmptyTrackNotLevel6() {
        $ids = $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_HOUSE, [6]);
        $this->assertEquals($ids[6], $this->game->dbGetIncomeBuildingOfType(BUILDING_HOUSE, false, true, IncomeMatUT::OWNER));

        $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_ARMORY, []);
        $this->assertNull($this->game->dbGetIncomeBuildingOfType(BUILDING_ARMORY, false, true, IncomeMatUT::OWNER));
        $this->assertCount(1, $this->game->notificationsOfType("message_error"));
    }

    function testExhaustionWithThrowIsAUserError() {
        $this->game->layoutTrack(IncomeMatUT::OWNER, BUILDING_ARMORY, []);
        $this->expectException(BgaUserException::class);
        $this->game->dbGetIncomeBuildingOfType(BUILDING_ARMORY, true, true, IncomeMatUT::OWNER);
    }

    // ----------------------------------------------------------- traders

    /** The opponent is paid the benefit of the spot the placed building uncovered, whatever the level. */
    function testTradersPaysTheOpponentTheRevealedSpot() {
        foreach ([1, 2, 4, 5] as $level) {
            $game = $this->newGame();
            $game->setGameStateValue("variant_adjustments", 4);
            $game->giveCiv(IncomeMatUT::OWNER, CIV_TRADERS);
            $game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, $level);
            $game->setTile("2_0");
            $game->addOutpostAt(IncomeMatUT::OPPONENT, "2_0");

            $game->traders()->sendTrader(IncomeMatUT::OWNER, ["coords" => "land_2_0", "bt" => BUILDING_ARMORY]);

            $revealed = $game->income_tracks[BUILDING_ARMORY][$level + 1]["benefit"];
            $this->assertEquals(array_map("strval", $revealed), $game->benefitLabels(), "level $level");
            $this->assertEquals([IncomeMatUT::OPPONENT], array_unique(array_column($game->benefitQueue(), "benefit_player_id")));
            $this->assertEquals($level + 1, $game->dbGetIncomeTrackLevel(BUILDING_ARMORY, IncomeMatUT::OWNER), "level $level");
        }
    }

    // --------------------------------------------------------- assign spots

    /** Rows with no spot get level+1..6 in id order; what is left over keeps 0 for the migration to delete. */
    function testAssignSpotsHandsOutTheCoveredSpotsInIdOrder() {
        for ($level = 1; $level <= 6; $level++) {
            $game = $this->newGame();
            $ids = $game->layoutUnmigrated(IncomeMatUT::OWNER, BUILDING_HOUSE, $level);
            $game->dbAssignIncomeSpots(IncomeMatUT::OWNER);
            $expected = $level < 6 ? range($level + 1, 6) : [];
            $expected[] = 0;
            $this->assertEquals($expected, array_map(fn($id) => $game->spotOf($id), $ids), "level $level");
        }
    }

    /** Setup creates five per type at level 1: spots 2..6, nothing left over. */
    function testSetupLayoutCoversSpots2To6() {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->game->dbAddStructure(IncomeMatUT::OWNER, BUILDING_MARKET, 0, "income", 0);
        }
        $this->game->dbAssignIncomeSpots(IncomeMatUT::OWNER);
        $this->assertEquals([2, 3, 4, 5, 6], array_map(fn($id) => $this->game->spotOf($id), $ids));
        $this->assertEquals([1], $this->game->getIncomeUncoveredSpots(IncomeMatUT::OWNER, BUILDING_MARKET));
    }

    // ------------------------------------------------------ vp per building

    /** Two farms off the track, in the city and on the map: the same count the location list gave before stage 1. */
    function testVpPerBuildingCountsBuildingsInTheCityAndOnTheMap() {
        $owner = IncomeMatUT::OWNER;
        $this->game->layoutPrefix($owner, BUILDING_FARM, 3);
        $this->game->dbAddStructure($owner, BUILDING_FARM, 0, "capital_cell_{$owner}_5_5");
        $this->game->dbAddStructure($owner, BUILDING_FARM, 0, "land_2_0");
        $this->game->VPincomeStructure($owner, BUILDING_FARM, 2, reason_civ(CIV_TRADERS));
        $this->assertEquals(4, $this->game->dbGetScore($owner));
    }

    /**
     * The one scoring change of stage 1: a building beside the mat (out of bounds placement, row
     * 144, set aside) or on the Collectors mat scored nothing before and counts now (BUILDING.1,
     * BUILDING.4). The location list is gone; every building off the track is on the table somewhere.
     */
    function testVpPerBuildingCountsBuildingsBesideTheMatAndOnACivMat() {
        $owner = IncomeMatUT::OWNER;
        $this->game->layoutPrefix($owner, BUILDING_HOUSE, 3);
        $this->game->dbAddStructure($owner, BUILDING_HOUSE, 0, "hand");
        $this->game->dbAddStructure($owner, BUILDING_HOUSE, 0, "civ_" . CIV_COLLECTORS . "_1");
        $this->game->VPincomeStructure($owner, BUILDING_HOUSE, 1, reason_civ(CIV_COLLECTORS));
        $this->assertEquals(2, $this->game->dbGetScore($owner));
    }

    function testVpPerBuildingIsZeroOnAnUntouchedTrack() {
        $this->game->layoutPrefix(IncomeMatUT::OWNER, BUILDING_ARMORY, 1);
        $this->game->VPincomeStructure(IncomeMatUT::OWNER, BUILDING_ARMORY, 3, reason_civ(CIV_TRADERS));
        $this->assertEquals(0, $this->game->dbGetScore(IncomeMatUT::OWNER));
    }
}
