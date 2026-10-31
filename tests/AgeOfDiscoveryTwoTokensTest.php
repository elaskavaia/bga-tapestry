<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/DiceUT.php";

class AgeOfDiscoveryTwoTokensUT extends DiceUT {
    public array $moved = [];

    /** Records which token moved; the real move cascades into spot benefits the harness does not model. */
    function trackMovementProper($track, $spot, $change, $flags, $mandatory, $player_id) {
        $this->moved[] = [(int) $track, (int) $spot, (int) $change, (int) $player_id];
        return true;
    }
}

/** BGA #58486 (dup #62514): two player tokens on the track AGE OF DISCOVERY rolls. */
final class AgeOfDiscoveryTwoTokensTest extends TestCase {
    private AgeOfDiscoveryTwoTokensUT $game;

    protected function setUp(): void {
        $this->game = new AgeOfDiscoveryTwoTokensUT();
        $this->game->init();
        $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_0");
        $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_5");
        $this->game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_2");
    }

    private function playAgeOfDiscoveryRollingScience(): void {
        $this->game->seedRand(TRACK_SCIENCE);
        $this->game->effect_ageOfDiscovery(DiceUT::ROLLER);
        $this->game->runManager();
    }

    function testRollerChoosesTheToken() {
        $this->game->setGameStateValue("cube_choice", -1);

        $this->playAgeOfDiscoveryRollingScience();

        $this->assertEquals("trackSelect", $this->game->stateName());
        $this->assertEquals([], $this->game->moved);
    }

    /**
     * Documents buggy behavior for BGA #58486: picking a non-track option first sends spot 0 (button_benefit_<ben>_0_0),
     * action_first_benefit stores it in cube_choice, and the next advance silently takes the token on spot 0.
     * Flips to trackSelect when the fix lands.
     */
    function testStaleCubeChoiceMovesTheStartTokenWithoutAsking() {
        $game = $this->game;
        $game->setGameStateValue("cube_choice", -1);
        $game->benefitSingleEntry("a," . BE_GAIN_COIN . "," . BE_GAIN_WORKER, 0, DiceUT::ROLLER);
        $game->gamestate->jumpToState(29);
        $game->action_first_benefit(BE_GAIN_COIN, 0);
        $game->gamestate->jumpToState(18);
        $game->runManager();
        $this->assertEquals([], $game->benefitQueue());

        $this->playAgeOfDiscoveryRollingScience();

        $this->assertNotEquals("trackSelect", $game->stateName());
        $this->assertEquals([TRACK_SCIENCE, 0, ACTION_ADVANCE, DiceUT::ROLLER], $game->moved[0]);
    }
}
