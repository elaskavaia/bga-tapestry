<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

class PsionicsAgeOfSailUT extends PsionicsUT {
    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries) ? ["card_type_arg" => $tapestry_id, "card_id" => 1, "card_location_arg2" => 0] : null;
    }

    /** ROLLER is seat 1, the same id as PLAYER_AUTOMA, which would explore at random instead. */
    function awardBenefitsAutoma($player_id, $ben, $count = 1, $reason = "") {
        return false;
    }

    function getExplorationTargets($anywhere = false, $player_id = null) {
        return ["0_0"];
    }
}

/** BGA #248010: PSIONICS with AGE OF SAIL active on an explore. */
final class PsionicsAgeOfSailTest extends TestCase {
    private PsionicsAgeOfSailUT $game;

    protected function setUp(): void {
        $this->game = new PsionicsAgeOfSailUT();
        $this->game->init();
        $this->game->sample();
        $this->game->tapestries = [TAP_AGE_OF_SAIL];
        $this->game->fillDeck(CARD_TERRITORY, 20);
    }

    // Pins the buggy behavior for BGA #248010; the assertions flip when the fix lands.
    function testExploreReachesTheExploreStateBeforeTheAgeOfSailTilesAreDrawn() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17));
        $game->runManager();

        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals(BE_PSIONICS_TERRITORY, $game->getCurrentBenefitType());
        $this->assertCount(0, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
        $this->assertEquals(
            [
                [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY],
                [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY],
                [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY],
                [PsionicsUT::ROLLER, 17],
            ],
            $game->rows()
        );
    }

    // Pins the buggy behavior for BGA #248010; the assertions flip when the fix lands.
    function testTheVoidExploreEatsAKeepRowAndTheExploreRowRetriggersAgeOfSail() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17));
        $game->runManager();

        $game->stExplore();
        $this->assertEquals([[PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY], [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY], [PsionicsUT::ROLLER, 17]], $game->rows());

        for ($i = 0; $i < 2; $i++) {
            $game->gamestate->jumpToState(18);
            $game->runManager();
            $this->assertEquals("keepCard", $game->stateName());
            $game->action_keepCard([$game->drawn(CARD_TERRITORY)[0]], null);
        }
        $this->assertCount(2, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));

        $game->gamestate->jumpToState(18);
        $game->runManager();
        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals(BE_PSIONICS_TERRITORY, $game->getCurrentBenefitType());
        $this->assertCount(4, $game->rows());
    }
}
