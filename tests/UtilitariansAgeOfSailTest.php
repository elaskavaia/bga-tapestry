<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

/**
 * BGA #252057: UTILITARIANS Lighthouse played while an explore is pending with AGE OF SAIL active.
 * The benefit stack is the GameUT in-memory model, not real SQL. The Lighthouse bonus row is cashed
 * by hand (tiles moved to discard) instead of going through the bonus UI.
 */
final class UtilitariansAgeOfSailTest extends TestCase {
    private PsionicsUT $game;

    protected function setUp(): void {
        $game = new PsionicsUT();
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $game->tapestries = [TAP_AGE_OF_SAIL];
        $game->fillDeck(CARD_TERRITORY, 30);
        $game->giveCiv(PsionicsUT::ROLLER, CIV_UTILITARIENS);
        $game->addCubeAt(PsionicsUT::ROLLER, "civ_39_5"); // Lighthouse active
        $this->game = $game;
    }

    private function handCount(): int {
        return count($this->game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
    }

    private function playLighthouse(): void {
        $game = $this->game;
        $game->action_activatedAbility("civ_39_5", null);
        $bonus = $game->benefitQueue()[0];
        $this->assertEquals("bonus", $bonus["benefit_category"]);
        foreach (array_slice(array_keys($game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY)), 0, 2) as $card_id) {
            $game->effect_discardCard($card_id, PsionicsUT::ROLLER);
        }
        $game->benefitCashed($bonus["benefit_id"]);
        $game->gamestate->jumpToState(18);
        $game->runManager();
    }

    /** BGA #252057: returning to the explore after the Lighthouse does not draw Age of Sail again. */
    function testLighthouseDuringExploreDoesNotRedrawAgeOfSail() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17));
        $game->runManager();
        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals(3, $this->handCount());

        $this->playLighthouse();
        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals([[PsionicsUT::ROLLER, 17]], $game->rows());
        $this->assertEquals(1, $this->handCount());
    }

    /** Splitting the row to fix BGA #252057 leaves each explore of a quantity 2 row its own three. */
    function testAgeOfSailDrawsForEachExploreOfTheRow() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17), 2);
        $game->runManager();
        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals(3, $this->handCount());

        $game->subtractCurrentBenefit();
        $game->gamestate->jumpToState(18);
        $game->runManager();
        $this->assertEquals("explore", $game->stateName());
        $this->assertEquals(6, $this->handCount());
    }

    function testLighthouseOncePerTurn() {
        $game = $this->game;
        for ($i = 0; $i < 4; $i++) {
            $game->addCard(CARD_TERRITORY, "hand", PsionicsUT::ROLLER);
        }
        $this->playLighthouse();
        // documents buggy behavior for BGA #252057: nothing records the Lighthouse use, so it is
        // playable again in the same turn; flips to false when the fix lands
        $this->assertTrue($game->ownsLighthouseAndCanPlayIt(PsionicsUT::ROLLER));
    }
}
