<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/**
 * The territory map: what getMap reports, what conquest targeting does with it, and the two places
 * that read a structure on a territory as more than it is - the topple proxy of effect_endOfConquer
 * and the stand up toggle.
 *
 * An inert cube on a territory (an Infiltrators token today, the Celestials floating capital next)
 * occupies a territory without controlling it, which is what most of these pin.
 */
final class MapTest extends TestCase {
    private MapUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): MapUT {
        $game = new MapUT($players);
        $game->init();
        return $game;
    }

    private function targets(bool $nomads = false, bool $anywhere = false, bool $only_empty = false): array {
        $found = $this->game->getConquerTargets($nomads, $anywhere, MapUT::OWNER, $only_empty);
        sort($found);
        return $found;
    }

    // -------------------------------------------------------- conquest targeting

    function testAnEmptyMapOffersNoTargetsUntilTheOwnerHoldsSomething() {
        $this->assertEquals([], $this->targets());
        $this->assertCount(13, $this->targets(false, true), "anywhere needs a tile, not a territory of your own");
    }

    function testAdjacencyOffersOnlyTheTiledNeighbours() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");

        $this->assertEquals(["1_-1", "2_1", "3_0"], $this->targets());
    }

    function testAnywhereOffersEveryTiledHexTheOwnerDoesNotHold() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");

        $found = $this->targets(false, true);

        $this->assertCount(12, $found, "the thirteen tiles of the small map less the one held");
        $this->assertContains("0_0", $found);
        $this->assertNotContains("2_0", $found);
        $this->assertNotContains("1_0", $found, "untiled hexes are not territories");
    }

    function testNomadsOffersOwnHexesWithRoomAndEmptyNeighbours() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");

        $this->assertEquals(["1_-1", "2_0", "2_1", "3_0"], $this->targets(true));
    }

    function testOnlyEmptyOffersTheNeighboursWithNoStructure() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $this->game->addOutpostAt(MapUT::OPPONENT, "3_0");

        $this->assertEquals(["1_-1", "2_1"], $this->targets(false, false, true));
    }

    function testTwoStructuresOnAHexBlockItForEveryone() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $this->game->addOutpostAt(MapUT::OPPONENT, "3_0");
        $this->game->addOutpostAt(MapUT::OPPONENT, "3_0", 1);

        $this->assertEquals(["1_-1", "2_1"], $this->targets());
        $this->assertTrue($this->game->isHexBlockedForConquer(MapUT::OTHER, "3_0", $this->game->getMap()));
    }

    function testAnAlliedHexIsNotATarget() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $this->game->addOutpostAt(MapUT::OPPONENT, "3_0");
        $this->game->allyWith(MapUT::OWNER, MapUT::OPPONENT);

        $this->assertEquals(["1_-1", "2_1"], $this->targets());
    }

    function testExploringAHexTurnsItIntoATarget() {
        $this->game->addOutpostAt(MapUT::OWNER, "0_0");

        $this->assertEquals([], $this->targets(), "the centre island has no tiled neighbour at setup");

        $this->game->setTile("1_0");

        $this->assertEquals(["1_0"], $this->targets());
    }

    // -------------------------------------------------------------- getMap

    /** The dedupe used to run once after the loop, so it only ever cleaned the last hex read. */
    function testTwoOutpostsOfOnePlayerAreOneOwnerOnEveryHex() {
        $this->game->addOutpostAt(MapUT::OWNER, "3_0");
        $this->game->addOutpostAt(MapUT::OWNER, "3_0", 1);
        $this->game->addOutpostAt(MapUT::OPPONENT, "1_-1");

        $this->assertEquals([MapUT::OWNER], $this->game->hexOwners("3_0"));
        $this->assertEquals([MapUT::OWNER], $this->game->hexOccupants("3_0"));
        $this->assertEquals(2, $this->game->hexOccupancy("3_0"));
        $this->assertEquals([MapUT::OPPONENT], $this->game->hexOwners("1_-1"));
    }

    // ------------------------------------------------------- an inert token

    function testAnInertTokenOccupiesATerritoryWithoutControllingIt() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $this->game->addTokenAt(MapUT::OWNER, "3_0");

        $this->assertEquals([], $this->game->hexOwners("3_0"));
        $this->assertEquals(1, $this->game->hexOccupancy("3_0"));
        $this->assertFalse($this->game->isHexOwner(MapUT::OWNER, "3_0"));
        $this->assertEquals(["2_0"], array_keys($this->game->getControlHexes(MapUT::OWNER)));
        $this->assertEquals(1, $this->game->getNumberOfControlledTerritories(MapUT::OWNER));
        $this->assertContains("3_0", $this->targets(), "one item alone leaves the territory conquerable");
    }

    function testATokenSharingATerritoryWithAnOutpostBlocksIt() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $this->game->addOutpostAt(MapUT::OPPONENT, "3_0");
        $this->game->addTokenAt(MapUT::OTHER, "3_0");

        $this->assertEquals([MapUT::OPPONENT], $this->game->hexOwners("3_0"));
        $this->assertEquals(2, $this->game->hexOccupancy("3_0"));
        $this->assertNotContains("3_0", $this->targets());
        $this->assertNotContains("3_0", $this->targets(false, true), "anywhere reads the same occupancy");
        $this->assertNotContains("3_0", $this->targets(true), "and so does nomads");
    }

    // ------------------------------------------------------- effect_conquer

    private function conquer(string $coord, int $player_id = MapUT::OWNER): void {
        $this->game->giveOutposts($player_id, 1);
        $this->game->seedRand(0, 0);
        $this->game->effect_conquer($player_id, $coord, [$coord], false, null, "");
    }

    function testConqueringAnOwnedTerritoryTopplesItsOwner() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $victim = $this->game->addOutpostAt(MapUT::OPPONENT, "3_0");

        $this->conquer("3_0");

        $this->assertEquals(1, $this->game->toppleFlag($victim));
        $this->assertEquals(MapUT::OPPONENT, $this->game->getGameStateValue("toppled_player"));
        $this->assertEquals([MapUT::OWNER], $this->game->hexOwners("3_0"));
        $this->assertGreaterThanOrEqual(0, $this->game->benefitPosition("standard", 140, MapUT::OPPONENT), "trap offered");
        $this->assertGreaterThanOrEqual(0, $this->game->benefitPosition("standard", 141, MapUT::OWNER), "die pick queued");
    }

    function testConqueringAnEmptyTerritoryTopplesNobody() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");

        $this->conquer("3_0");

        $this->assertEquals(0, $this->game->getGameStateValue("toppled_player"));
        $this->assertEquals([MapUT::OWNER], $this->game->hexOwners("3_0"));
        $this->assertEquals(-1, $this->game->benefitPosition("standard", 140, MapUT::OPPONENT), "no trap offered");
        $this->assertGreaterThanOrEqual(0, $this->game->benefitPosition("standard", 141, MapUT::OWNER));
    }

    function testConqueringATerritoryHoldingOnlyATokenTopplesNobodyAndLeavesTheToken() {
        $this->game->addOutpostAt(MapUT::OWNER, "2_0");
        $token = $this->game->addTokenAt(MapUT::OPPONENT, "3_0");

        $this->conquer("3_0");

        $this->assertEquals(0, $this->game->getGameStateValue("toppled_player"));
        $this->assertEquals("land_3_0", $this->game->structureLocation($token));
        $this->assertEquals(1, $this->game->toppleFlag($token), "a token has no upright state");
        $this->assertEquals([MapUT::OWNER], $this->game->hexOwners("3_0"));
        $this->assertEquals(2, $this->game->hexOccupancy("3_0"), "now unconquerable at two items");
        $this->assertEquals(-1, $this->game->benefitPosition("standard", 140, MapUT::OPPONENT), "no trap offered");
    }

    // -------------------------------------------------- effect_endOfConquer

    /** Two of the owner's territories hold a toppled opponent outpost, so the award is due. */
    private function seedToppleAward(MapUT $game): void {
        foreach (["2_0", "3_0"] as $coord) {
            $game->addOutpostAt(MapUT::OWNER, $coord);
            $game->addOutpostAt(MapUT::OPPONENT, $coord, 1);
        }
        $game->addOutpostAt(MapUT::OWNER, "1_-1");
    }

    private function endOfConquer(MapUT $game, string $coord, int $toppled_player): int {
        $game->setSelectedMapHex($coord);
        $game->setGameStateValue("toppled_player", $toppled_player);
        $game->effect_endOfConquer(MapUT::OWNER);
        return $game->dbGetScore(MapUT::OWNER);
    }

    function testTheToppleAwardFollowsTheToppledPlayer() {
        $this->seedToppleAward($this->game);

        $this->assertEquals(10, $this->endOfConquer($this->game, "2_0", MapUT::OPPONENT));
    }

    function testTheToppleAwardIsDueOnATerritoryWithASingleOccupant() {
        $this->seedToppleAward($this->game);

        $this->assertEquals(10, $this->endOfConquer($this->game, "1_-1", MapUT::OPPONENT));
    }

    function testNothingIsToppledWhenTheConqueredTerritoryWasEmpty() {
        $this->seedToppleAward($this->game);

        $this->assertEquals(0, $this->endOfConquer($this->game, "2_0", 0));
    }

    function testAThirdOccupantDoesNotHideTheTopple() {
        $game = $this->newGame(3);
        $this->seedToppleAward($game);
        $game->addTokenAt(MapUT::OTHER, "2_0");

        $this->assertEquals(3, count($game->hexOccupants("2_0")));
        $this->assertEquals(10, $this->endOfConquer($game, "2_0", MapUT::OPPONENT));
    }

    // ------------------------------------------------------- action_standup

    function testStandingUpFlipsTheOutpostsAndLeavesATokenAlone() {
        $game = $this->game;
        $mine = $game->addOutpostAt(MapUT::OWNER, "2_0", 1);
        $theirs = $game->addOutpostAt(MapUT::OPPONENT, "2_0");
        $token = $game->addTokenAt(MapUT::OPPONENT, "2_0");
        $game->queueBenefitNormal(BE_STANDUP_3_OUTPOSTS, MapUT::OWNER);
        $game->gamestate->jumpToState(22);

        $game->action_standup([$mine]);

        $this->assertEquals(0, $game->toppleFlag($mine), "mine stands up");
        $this->assertEquals(1, $game->toppleFlag($theirs), "theirs topples");
        $this->assertEquals(1, $game->toppleFlag($token), "the token is not an outpost");
        $this->assertEquals([MapUT::OWNER], $game->hexOwners("2_0"));
        $this->assertCount(2, $game->notificationLike("stands up an outpost")["args"]["outposts"]);
    }
}
