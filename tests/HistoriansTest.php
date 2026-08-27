<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * BGA bug #203108 - gaining HISTORIANS midgame in era 4 with no advancement track landmarks left
 * awarded none of the exposed benefits.
 */
class HistoriansUT extends GameUT {
    public array $landmark_supply = [];
    public array $queued = [];

    function getStructuresSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        if ($card_location === "landmark_mat_slot%") {
            return $this->landmark_supply;
        }
        return [];
    }

    function queueBenefitNormal($benefit, $player_id = null, $reason = "", $count = 1) {
        $this->queued[] = $benefit;
    }

    // the stubs do not persist globals or playerextra, pin the reported table's setup
    function getAdjustmentVariant() {
        return 8;
    }

    function getCurrentEra($player_id) {
        return 4;
    }

    function setLandmarkSupply(array $types) {
        $this->landmark_supply = [];
        foreach ($types as $type) {
            $this->landmark_supply[$type] = [
                "card_id" => $type,
                "card_type" => BUILDING_LANDMARK,
                "card_location" => "landmark_mat_slot$type",
                "card_location_arg2" => $type,
            ];
        }
    }
}

final class HistoriansTest extends TestCase {
    public $game;

    protected function setUp(): void {
        $this->game = new HistoriansUT();
        $this->game->init();
        $this->game->doAdjustMaterial(2, 8);
    }

    private function historians(): Historians {
        return $this->game->getCivilizationInstance(CIV_HISTORIANS, true);
    }

    function testExposedBenefitsMatchTheReportedMat() {
        $slots = $this->game->civilizations[CIV_HISTORIANS]["slots"];
        $this->assertEquals([BE_RESEARCH_NB], $slots[1]["benefit"]);
        $this->assertEquals(["p" => BE_TAPESTRY, "g" => BE_INVENT, 0 => 0], $slots[2]["benefit"]);
        $this->assertEquals([RES_FOOD], $slots[3]["benefit"]);
        $this->assertEquals([BE_VP_TERRITORY], $slots[4]["benefit"]);

        $this->historians()->activateBenefits(1);
        $this->assertEquals(4, count($this->game->queued));
    }

    function testEmptyLandmarkMatIsDetected() {
        $this->game->setLandmarkSupply([]);
        $this->assertTrue($this->historians()->noLandmarksLeft());

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(4, count($this->game->queued));
    }

    /**
     * Documents the buggy behavior of BGA #203108: landmarks 13-19 never sit on an advancement
     * track, yet they keep noLandmarksLeft() false, so the midgame "no landmarks remaining on
     * advancement tracks" clause never fires. Flip both assertions when the fix lands.
     */
    function testTrackLandmarksExhaustedButMatStillHoldsExtras() {
        $this->game->setLandmarkSupply([13, 14, 15, 16, 17, 18, 19]);
        $this->assertFalse($this->historians()->noLandmarksLeft());

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(0, count($this->game->queued));
    }
}
