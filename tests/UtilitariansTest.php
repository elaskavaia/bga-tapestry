<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Regression tests for BGA bug #202072.
 *
 * The benefit stack and the civilization cube live in the GameUT in memory tables.
 *
 * The stub gamestate is wired with ->game, so gamestate->state() models what the production
 * framework does: the studio stack trace for table T950560 move 11 shows state() going through
 * loadStateArgs() -> getStateClassArgs() -> LegacyGameState->getArgs() -> PGameXBody
 * ->argCivAbility(). The fix keeps that reload out of the notification path:
 * getMostlyActivePlayerId() asks isMultiactiveState(), which does not reload args.
 */
class UtilitariansUT extends GameUT {
    public $active_player_lookups = 0;
    public $benefit_at_lookup = "not-called";
    public $civ_arg_reloads = 0;

    function __construct() {
        parent::__construct();
        $this->gamestate->game = $this;
    }

    function getMostlyActivePlayerId() {
        $this->active_player_lookups++;
        $this->benefit_at_lookup = $this->getCurrentBenefit();
        return parent::getMostlyActivePlayerId();
    }

    function argCivAbility() {
        $this->civ_arg_reloads++;
        return parent::argCivAbility();
    }
}

final class UtilitariansTest extends TestCase {
    private function game($variant = 1) {
        $game = new UtilitariansUT();
        $game->init();
        $game->doAdjustMaterial(2, $variant);
        $game->gamestate->jumpToState(14); // civAbility, args = argCivAbility
        return $game;
    }

    private function queueCivBenefit(GameUT $game, string $data): void {
        $game->benefits->insert("civ", CIV_UTILITARIENS, 1, 1, $data, 1);
    }

    /**
     * The "triggered" branch of saction_civTokenAdvance moves the cube with
     * dbSetStructureLocation($id, $spot, 0), leaving $player_id null, and that runs after
     * benefitCashed() has emptied the stack but before nextState(). The null player_id sends
     * notifyWithName() to getMostlyActivePlayerId(); before the fix that reloaded the current
     * state args, i.e. argCivAbility(), which asserts on a benefit that no longer exists.
     */
    function testUtilitariansTriggeredNotificationDoesNotReloadStateArgs() {
        $game = $this->game();
        $this->queueCivBenefit($game, "triggered::10");
        $game->addCubeAt(1, "civ_39_1"); // the cube the landmark trigger advances

        $game->action_civTokenAdvance(CIV_UTILITARIENS, 1, "");

        // the structure move notification still looks up the active player on a drained stack
        $this->assertEquals(1, $game->active_player_lookups);
        $this->assertNull($game->benefit_at_lookup);
        // but the lookup no longer re-enters argCivAbility
        $this->assertEquals(0, $game->civ_arg_reloads);
    }

    /**
     * Same path through the $is_midgame branch (the third-civilization report on #202072):
     * dbSetStructureLocation($cube_id, $civ_token_string, 0) also omits $player_id.
     */
    function testUtilitariansMidgameNotificationDoesNotReloadStateArgs() {
        $game = $this->game();
        $this->queueCivBenefit($game, "midgame");
        $game->addCubeAt(1, "civ_39_9"); // the midgame branch moves a cube off slot 9 or 10

        $game->action_civTokenAdvance(CIV_UTILITARIENS, 1, "");

        $this->assertEquals(1, $game->active_player_lookups);
        $this->assertNull($game->benefit_at_lookup);
        $this->assertEquals(0, $game->civ_arg_reloads);
    }

    /**
     * Pins the framework behavior the stub models: deprecated gamestate->state() reloads the
     * current state's args, while state(true) and isMultiactiveState() do not. If the stub goes
     * inert again, this fails before the tests above can silently stop covering the bug.
     */
    function testStateReloadsArgsUnlessSkipped() {
        $game = $this->game();
        $this->queueCivBenefit($game, "triggered::10");

        $game->gamestate->state(true);
        $game->gamestate->isMultiactiveState();
        $this->assertEquals(0, $game->civ_arg_reloads);

        $game->gamestate->state();
        $this->assertEquals(1, $game->civ_arg_reloads);
    }

    /**
     * The original #202072 crash mechanism: a bare state() reload on a drained stack re-enters
     * argCivAbility, which asserts. This is what getMostlyActivePlayerId() used to do from the
     * structure move notification.
     */
    function testStateReloadOnDrainedStackAsserts() {
        $game = $this->game();

        ob_start(); // systemAssertTrue echoes the server log through the final error()
        try {
            $game->gamestate->state();
            $this->fail("expected the missing-benefit assert");
        } catch (BgaUserException $e) {
            $this->assertStringContainsString("missing benefit", $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * The "triggered" branch is not gated on the Civilization Adjustments option: the slot
     * layout that drives it is identical for every adjustment value.
     */
    function testUtilitariansLandmarkSlotsAreAdjustmentIndependent() {
        foreach ([1, 2, 4, 8, 9] as $variant) {
            $game = $this->game($variant);
            $this->assertEquals("civ_39_1", $game->getCivSlotWithValue(CIV_UTILITARIENS, "lm", 10));
        }
    }

    /** Only the Lighthouse slot is marked once per turn in material; other civ slots and spots are not. */
    function testOncePerTurnSlot() {
        $game = $this->game(8);
        $this->assertTrue($game->isOncePerTurnSlot("civ_39_5"));
        $this->assertFalse($game->isOncePerTurnSlot("civ_39_4"));
        $this->assertFalse($game->isOncePerTurnSlot("civilization_25"));
        $this->assertFalse($game->isOncePerTurnSlot("tech_spot_1_1"));
        $this->assertFalse($game->isOncePerTurnSlot("hand"));
    }

    /** Player 1 on their 3rd turn with an advance to take, Lighthouse active, 4 territory tiles in hand. */
    private function lighthouseGame(): UtilitariansUT {
        $game = $this->game(8);
        $game->giveCiv(1, CIV_UTILITARIENS);
        $game->addCubeAt(1, "civ_39_5");
        for ($i = 0; $i < 4; $i++) {
            $game->addCard(CARD_TERRITORY, "hand", 1);
        }
        $game->addCubeAt(1, "tech_spot_1_0");
        $game->setResources(1, 5, 5, 5, 5);
        $game->setStat(3, "turns_total", 1);
        $game->gamestate->jumpToState(13);
        return $game;
    }

    /** Use the Lighthouse and resolve its bonus by hand: discard 2 tiles, leaving the resume row. */
    private function useLighthouse(GameUT $game): void {
        $game->action_activatedAbility("civ_39_5", null);
        $bonus = $game->benefitQueue()[0];
        $this->assertEquals("bonus", $bonus["benefit_category"]);
        foreach (array_slice(array_keys($game->getCardsInHand(1, CARD_TERRITORY)), 0, 2) as $card_id) {
            $game->effect_discardCard($card_id, 1);
        }
        $game->benefitCashed($bonus["benefit_id"]);
    }

    /** BGA #252057: the Lighthouse is used once per turn, the confirm screen included. */
    function testLighthouseOncePerTurn() {
        $game = $this->lighthouseGame();
        $this->useLighthouse($game);
        $this->assertFalse($game->ownsLighthouseAndCanPlayIt(1));

        $spot_marker = $game->addMarker(1, "tech_spot_1_1");
        $game->effect_endOfTurn(1);
        $this->assertEquals("hand", $game->getStructureInfoById($spot_marker)["card_location"]);
        $this->assertFalse($game->ownsLighthouseAndCanPlayIt(1));

        $game->effect_startOfTurn(1);
        $this->assertTrue($game->ownsLighthouseAndCanPlayIt(1));
    }

    /** BGA #252057: the resume row re-enters playerTurn, which is not the start of a new turn. */
    function testLighthouseResumeDoesNotStartTheTurnAgain() {
        $game = $this->lighthouseGame();
        $this->useLighthouse($game);
        $game->stBenefitManager();
        $this->assertEquals("playerTurn", $game->gamestate->state()["name"]);
        $game->stPlayerTurn();
        $this->assertFalse($game->ownsLighthouseAndCanPlayIt(1));
        $this->assertEquals(3, $game->getPlayerTurn(1));
    }

    /** A table past stFinishSetup before it started the first turn gets it started by the setup resume row. */
    function testResumeStartsAnUnstartedFirstTurn() {
        $game = $this->lighthouseGame();
        $game->setStat(0, "turns_total", 1);
        $game->queueBenefitNormal(BE_RESUME, 1);
        $game->stBenefitManager();
        $this->assertEquals("playerTurn", $game->gamestate->state()["name"]);
        $this->assertEquals(1, $game->getPlayerTurn(1));
    }

    /** BGA #252057: the turn starts once, in the transition to the player, which frees the Lighthouse. */
    function testTransitionStartsTheNextTurnOnce() {
        $game = $this->lighthouseGame();
        $this->useLighthouse($game);
        $game->setGameStateValue("current_player_turn", 2);
        $game->stTransition();
        $this->assertEquals(1, $game->getActivePlayerId());
        $this->assertEquals(4, $game->getPlayerTurn(1));
        $this->assertTrue($game->ownsLighthouseAndCanPlayIt(1));

        $game->stPlayerTurn();
        $this->assertEquals(4, $game->getPlayerTurn(1));
    }
}
