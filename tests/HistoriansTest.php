<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * BGA bug #203108 - gaining HISTORIANS midgame in era 4 with no advancement track landmarks left
 * awarded none of the exposed benefits.
 */
class HistoriansUT extends GameUT {
    public array $queued = [];

    function queueBenefitNormal($benefit, $player_id = null, $reason = "", $count = 1) {
        $this->queued[] = $benefit;
    }
}

final class HistoriansTest extends TestCase {
    public $game;

    protected function setUp(): void {
        $this->game = new HistoriansUT();
        $this->game->init();
        $this->game->doAdjustMaterial(2, 8);
        $this->game->setGameStateValue("variant_adjustments", 8);
        $this->game->era = 4;
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
        $this->game->setLandmarkMat([]);
        $this->assertTrue($this->historians()->noTrackLandmarksLeft());

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(4, count($this->game->queued));
    }

    /**
     * BGA #203108: landmarks 13-19 are the extra pool and never sit on an advancement track, so an
     * exhausted track must still fire the midgame "no landmarks remaining" clause.
     */
    function testTrackLandmarksExhaustedButMatStillHoldsExtras() {
        $this->game->setLandmarkMat([13, 14, 15, 16, 17, 18, 19]);
        $this->assertTrue($this->historians()->noTrackLandmarksLeft());

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(4, count($this->game->queued));
    }

    function testTrackLandmarkRemainingBlocksTheClause() {
        $this->game->setLandmarkMat([12, 13, 14]);
        $this->assertFalse($this->historians()->noTrackLandmarksLeft());

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(0, count($this->game->queued));
    }

    /**
     * The clause is printed only on the a4/a8 card, the original mat must never award it.
     */
    function testEmptyMatAwardsNothingWithoutTheAdjustmentPack() {
        $this->game->setGameStateValue("variant_adjustments", 2);
        $this->game->setLandmarkMat([]);

        $this->historians()->sendHistorianTokensMidGame();
        $this->assertEquals(0, count($this->game->queued));
    }
}
