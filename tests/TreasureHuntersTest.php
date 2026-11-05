<?php

declare(strict_types=1);

use Bga\GameFramework\VisibleSystemException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/**
 * The income turn placement: a token from a mat slot goes on an empty territory adjacent to one
 * the owner controls that shows the slot terrain. With an outpost on 2_0 of the small map, the
 * mountain slot 3 offers 1_-1 and 2_1, while 3_0 is adjacent but not mountain.
 */
final class TreasureHuntersTest extends TestCase {
    private MapUT $game;
    private int $cube;

    protected function setUp(): void {
        $game = new MapUT();
        $game->init();
        $game->doAdjustMaterial(2, 1);
        $game->addOutpostAt(MapUT::OWNER, "2_0");
        $game->giveCiv(MapUT::OWNER, CIV_TREASURE_HUNTERS);
        $this->cube = $game->addCubeAt(MapUT::OWNER, "civ_38_3");
        $game->benefitCivEntry(CIV_TREASURE_HUNTERS, MapUT::OWNER);
        $this->game = $game;
    }

    function testPlacingOnAnOfferedTerritoryTakesItAndQueuesTheTreasure() {
        $game = $this->game;
        $args = $game->argCivAbilitySingle(MapUT::OWNER, CIV_TREASURE_HUNTERS, $game->getCurrentBenefit(CIV_TREASURE_HUNTERS, "civ"));
        $this->assertEqualsCanonicalizing(["1_-1", "2_1"], $args["slots_choice"][3]["targets"]);

        $game->civTokenAdvance(CIV_TREASURE_HUNTERS, MapUT::OWNER, 3, "land_1_-1");

        $this->assertEquals("land_1_-1", $game->structureLocation($this->cube));
        $this->assertEquals([MapUT::OWNER], array_values($game->hexOwners("1_-1")));
        $this->assertEquals("1_-1", $game->getSelectedMapHex()["map_coords"]);
        $this->assertTrue($game->benefitPosition("standard", RES_WORKER, MapUT::OWNER) >= 0);
    }

    /**
     * Studio error 761167: clicking the slot on the civilization mat sends civTokenAdvance with no
     * territory, which reached setSelectedMapHex as "" + 50.
     */
    function testPlacingWithoutATerritoryIsRejected() {
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("[ERR:TreasureHunters:01]");
        $this->game->civTokenAdvance(CIV_TREASURE_HUNTERS, MapUT::OWNER, 3, "");
    }

    function testPlacingOnATerritoryOfAnotherTerrainIsRejected() {
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("[ERR:TreasureHunters:01]");
        $this->game->civTokenAdvance(CIV_TREASURE_HUNTERS, MapUT::OWNER, 3, "land_3_0");
    }

    function testPlacingFromASlotWithoutATokenIsRejected() {
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("[ERR:TreasureHunters:01]");
        $this->game->civTokenAdvance(CIV_TREASURE_HUNTERS, MapUT::OWNER, 1, "land_1_-1");
    }

    function testPlacingWithAListOfTerritoriesIsRejected() {
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("[ERR:TreasureHunters:01]");
        $this->game->civTokenAdvance(CIV_TREASURE_HUNTERS, MapUT::OWNER, 3, ["land_1_-1"]);
    }
}
