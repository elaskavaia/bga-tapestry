<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

class CapitalMatUT extends GameUT {
    const PLAYER = 11;
    const TECH_HUB = 1; // 3 wide, 2 high
    const TANK_FACTORY = 5; // 3 by 3, exactly one district

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::PLAYER, self::PLAYER + $players - 1), []));
        $this->curid = self::PLAYER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
        $this->setCapitalMat(self::PLAYER, 0);
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::PLAYER);
        $this->gamestate->jumpToState(2);
    }

    /** A structure waiting to be placed, where effect_gainLandmark and the income building benefits leave it. */
    function pendingStructure(int $type, int $landmark = 0): int {
        return $this->dbAddStructure(self::PLAYER, $type, 0, "capital_structure", $landmark);
    }

    function occupy(int $x, int $y, int $building = BUILDING_FARM): void {
        $this->dbSetCapitalCell(self::PLAYER, $x, $y, $building + 1);
    }

    /** A landmark as placed: the anchor in the location, the landmark in arg2, the rotation in type_arg. */
    function placedLandmark(int $landmark, int $x, int $y, int $rot = 0): array {
        $cell = "capital_cell_" . self::PLAYER . "_{$x}_{$y}";
        return $this->getStructureInfoById($this->dbAddStructure(self::PLAYER, BUILDING_LANDMARK, $rot, $cell, $landmark));
    }
}

/**
 * argPlaceStructure, effect_placeOnCapitalMat and the row and column count behind BE_VP_CAPITAL
 * were rewritten around getStructureCells and getCapitalData for the Weefolk plot token. These
 * pin what they still do for the structures every game places.
 */
final class CapitalMatTest extends TestCase {
    private CapitalMatUT $game;

    protected function setUp(): void {
        $this->game = new CapitalMatUT();
        $this->game->init();
    }

    /** Rotation 0 stands the 3 by 2 Tech Hub 2 across and 3 down, rotation 1 lays it flat. */
    function testLandmarkFootprintFollowsTheRotation() {
        $game = $this->game;

        $this->assertEquals(
            [[3, 3], [3, 4], [3, 5], [4, 3], [4, 4], [4, 5]],
            $game->getStructureCells(BUILDING_LANDMARK, CapitalMatUT::TECH_HUB, 3, 3, 0)
        );
        $this->assertEquals(
            [[3, 3], [3, 4], [4, 3], [4, 4], [5, 3], [5, 4]],
            $game->getStructureCells(BUILDING_LANDMARK, CapitalMatUT::TECH_HUB, 3, 3, 1)
        );
        $this->assertEquals([[7, 8]], $game->getStructureCells(BUILDING_FARM, 0, 7, 8));
    }

    /**
     * Every anchor whose footprint touches the mat without covering a building, so a landmark may
     * hang over the edge: the 9 by 9 mat gives the 2 by 3 footprint 10 x 11 anchors per rotation,
     * the farm at 5,5 takes 6 of them away each.
     */
    function testLandmarkOptionsSkipBuildingsAndMayOverhangTheEdge() {
        $game = $this->game;
        $game->occupy(5, 5);
        $game->pendingStructure(BUILDING_LANDMARK, CapitalMatUT::TECH_HUB);

        $options = $game->argPlaceStructure()["options"];

        $this->assertEquals([0, 1], array_keys($options));
        $this->assertContains("3_3", $options[0]);
        $this->assertContains("2_3", $options[0], "one column off the mat is allowed");
        $this->assertNotContains("0_0", $options[0], "no cell on the mat");
        $this->assertNotContains("4_4", $options[0], "covers the farm at 5,5");
        $this->assertNotContains("4_4", $options[1], "covers the farm at 5,5");
        $this->assertEquals(104, count($options[0]));
        $this->assertEquals(104, count($options[1]));
    }

    function testBuildingOptionsAreTheEmptyCells() {
        $game = $this->game;
        $game->occupy(5, 5);
        $game->pendingStructure(BUILDING_FARM);

        $options = $game->argPlaceStructure()["options"];

        $this->assertEquals([0], array_keys($options));
        $this->assertEquals(80, count($options[0]));
        $this->assertNotContains("5_5", $options[0]);
        $this->assertNotContains("2_3", $options[0], "a building never overhangs");
    }

    function testPlacingALandmarkFillsItsCellsAndCompletesTheDistrict() {
        $game = $this->game;
        $id = $game->pendingStructure(BUILDING_LANDMARK, CapitalMatUT::TANK_FACTORY);

        $game->effect_placeOnCapitalMat($id, 3, 3, 0, CapitalMatUT::PLAYER);

        $capital = $game->getCapitalData(CapitalMatUT::PLAYER);
        foreach ($game->getStructureCells(BUILDING_LANDMARK, CapitalMatUT::TANK_FACTORY, 3, 3, 0) as [$x, $y]) {
            $this->assertEquals(BUILDING_LANDMARK + 1, $capital[$x][$y], "cell $x,$y");
        }
        $this->assertEquals(0, $capital[6][3], "the cell past the footprint stays free");
        $this->assertEquals("capital_cell_" . CapitalMatUT::PLAYER . "_3_3", $game->structureLocation($id));
        $this->assertContains('${player_name} completes district #${dn}', $game->notificationTexts());
        $this->assertEquals([(string) RES_ANY], $game->benefitLabels(), "one resource for the completed district");
    }

    function testPlacingOverABuildingIsRefused() {
        $game = $this->game;
        $game->occupy(4, 4);
        $id = $game->pendingStructure(BUILDING_LANDMARK, CapitalMatUT::TECH_HUB);

        $this->expectException(BgaUserException::class);
        $game->effect_placeOnCapitalMat($id, 3, 3, 0, CapitalMatUT::PLAYER);
    }

    /** BE_VP_CAPITAL: 1 VP per full row and per full column, times the benefit count. */
    function testCapitalVPCountsFullRowsAndColumns() {
        $game = $this->game;
        for ($a = 3; $a < 12; $a++) {
            $game->occupy(3, $a, BUILDING_FARM);
            $game->occupy($a, 7, BUILDING_HOUSE);
        }
        $game->occupy(8, 8);

        $game->awardBenefits(CapitalMatUT::PLAYER, BE_VP_CAPITAL, 3);

        $this->assertEquals(6, $game->dbGetScore(CapitalMatUT::PLAYER), "one row and one column, 3 VP each");
    }

    /** ARCHITECTS double a full line of a single building type; the column mixes the farm at 3,7 in. */
    function testArchitectsDoubleASingleTypeLine() {
        $game = $this->game;
        $game->giveCiv(CapitalMatUT::PLAYER, CIV_ARCHITECTS);
        for ($a = 3; $a < 12; $a++) {
            $game->occupy(3, $a, BUILDING_FARM);
            $game->occupy($a, 7, BUILDING_HOUSE);
        }
        $game->occupy(3, 7, BUILDING_FARM);

        $game->awardBenefits(CapitalMatUT::PLAYER, BE_VP_CAPITAL, 1);

        $this->assertEquals(3, $game->dbGetScore(CapitalMatUT::PLAYER), "2 for the farm row, 1 for the mixed column");
    }

    /**
     * Hanging over an edge is legal and already scores nothing for rows and columns; this is the
     * reader that names the state, for the civilizations that pay or charge for it.
     */
    function testALandmarkHangsOffWhenAnyCellLeavesTheMat() {
        $game = $this->game;
        $hub = CapitalMatUT::TECH_HUB;

        $this->assertFalse($game->isLandmarkOverhanging($game->placedLandmark($hub, 3, 3)));
        $this->assertTrue($game->isLandmarkOverhanging($game->placedLandmark($hub, 2, 3)), "off the left");
        $this->assertTrue($game->isLandmarkOverhanging($game->placedLandmark($hub, 3, 2)), "off the top");
        $this->assertTrue($game->isLandmarkOverhanging($game->placedLandmark($hub, 11, 3)), "off the right");
        $this->assertTrue($game->isLandmarkOverhanging($game->placedLandmark($hub, 3, 10)), "off the bottom");
    }

    function testTheRotationAloneCanHangALandmarkOff() {
        $game = $this->game;
        $hub = CapitalMatUT::TECH_HUB;

        $this->assertFalse($game->isLandmarkOverhanging($game->placedLandmark($hub, 10, 3, 0)));
        $this->assertTrue($game->isLandmarkOverhanging($game->placedLandmark($hub, 10, 3, 1)));
    }
}
