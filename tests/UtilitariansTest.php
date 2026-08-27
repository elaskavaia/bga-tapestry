<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Regression tests for BGA bug #202072.
 *
 * The framework stubs return empty rows for every DB accessor, so the benefit stack and the
 * civilization cube are kept in memory here.
 *
 * The stub gamestate is wired with ->game, so gamestate->state() models what the production
 * framework does: the studio stack trace for table T950560 move 11 shows state() going through
 * loadStateArgs() -> getStateClassArgs() -> LegacyGameState->getArgs() -> PGameXBody
 * ->argCivAbility(). The fix keeps that reload out of the notification path:
 * getMostlyActivePlayerId() asks isMultiactiveState(), which does not reload args.
 */
class UtilitariansUT extends GameUT {
    public $benefit_rows = [];
    public $cube = null;
    public $active_player_lookups = 0;
    public $benefit_at_lookup = "not-called";
    public $civ_arg_reloads = 0;

    function __construct() {
        parent::__construct();
        $this->gamestate->game = $this;
    }

    function getCurrentBenefit($ben = null, $cat = "standard") {
        if ($ben === null) {
            return $this->benefit_rows ? reset($this->benefit_rows) : null;
        }
        if (is_array($ben)) {
            return array_get($this->benefit_rows, $ben["benefit_id"], null);
        }
        foreach ($this->benefit_rows as $row) {
            if ($row["benefit_type"] == $ben && $row["benefit_category"] == $cat) {
                return $row;
            }
        }
        return null;
    }

    function benefitCashed($benefit_table_id) {
        $id = is_array($benefit_table_id) ? $benefit_table_id["benefit_id"] : $benefit_table_id;
        unset($this->benefit_rows[$id]);
    }

    function interruptBenefit() {}

    function getStructureInfoSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        return $this->cube;
    }

    function getStructureInfoById($id, $assert = true) {
        return $this->cube;
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
        $game->cube = [
            "card_id" => 77,
            "card_type" => BUILDING_CUBE,
            "card_type_arg" => 0,
            "card_location" => "civ_39_7",
            "card_location_arg" => 1,
            "card_location_arg2" => 0,
        ];
        return $game;
    }

    private function civBenefit($data) {
        return [
            5 => [
                "benefit_id" => 5,
                "benefit_type" => CIV_UTILITARIENS,
                "benefit_category" => "civ",
                "benefit_player_id" => 1,
                "benefit_prerequisite" => 1,
                "benefit_count" => 1,
                "benefit_data" => $data,
            ],
        ];
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
        $game->benefit_rows = $this->civBenefit("triggered::10");

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
        $game->benefit_rows = $this->civBenefit("midgame");

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
        $game->benefit_rows = $this->civBenefit("triggered::10");

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
        $game->benefit_rows = [];

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
}
