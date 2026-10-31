<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/TrackMoveUT.php";

/** BGA #58486 (dup #62514): two player tokens on one track (AI SINGULARITY), the owner picks which one moves. */
final class TwoTokensOnTrackTest extends TestCase {
    private TrackMoveUT $game;
    private int $startToken;

    protected function setUp(): void {
        $this->game = new TrackMoveUT();
        $this->game->init();
        $this->game->setGameStateValue("cube_choice", -1);
        $this->startToken = (int) $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_0");
        $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_5");
        $this->game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_SCIENCE . "_2");
        $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_EXPLORATION . "_1");
        $this->game->dbAddStructure(DiceUT::ROLLER, BUILDING_CUBE, 0, "tech_spot_" . TRACK_EXPLORATION . "_5");
    }

    private function playAgeOfDiscoveryRollingScience(): void {
        $this->game->seedRand(TRACK_SCIENCE);
        $this->game->effect_ageOfDiscovery(DiceUT::ROLLER);
        $this->game->runManager();
    }

    /** benefitChoice: the client sends the button's destination spot, button_benefit_<ben>_<track>_<spot>. */
    private function chooseFirst(int $ben, int $other, int $spot): void {
        $game = $this->game;
        $game->benefitSingleEntry("a,$ben,$other", 0, DiceUT::ROLLER);
        $game->gamestate->jumpToState(29);
        $game->action_first_benefit($ben, $spot);
        $game->gamestate->jumpToState(18);
        $game->runManager();
    }

    /** benefitOption: same button ids as benefitChoice. */
    private function chooseOne(int $ben, int $other, int $spot): void {
        $game = $this->game;
        $game->benefitSingleEntry("o,$ben,$other", 0, DiceUT::ROLLER);
        $game->gamestate->jumpToState(19);
        $game->action_choose_benefit($ben, $spot);
        $game->gamestate->jumpToState(18);
        $game->runManager();
    }

    function testRollerChoosesTheToken() {
        $this->playAgeOfDiscoveryRollingScience();

        $this->assertEquals("trackSelect", $this->game->stateName());
        $this->assertEquals([], $this->game->moved);
    }

    /** BGA #58486: a non-track pick in benefitChoice sends spot 0, which must not choose the token for a later advance. */
    function testNonTrackFirstBenefitDoesNotChooseTheTokenOfALaterAdvance() {
        $this->chooseFirst(BE_GAIN_COIN, BE_GAIN_WORKER, 0);
        $this->assertEquals([], $this->game->benefitQueue());

        $this->playAgeOfDiscoveryRollingScience();

        $this->assertEquals("trackSelect", $this->game->stateName());
        $this->assertEquals([], $this->game->moved);
    }

    /** BGA #58486: same through benefitOption. */
    function testNonTrackChooseBenefitDoesNotChooseTheTokenOfALaterAdvance() {
        $this->chooseOne(BE_GAIN_COIN, BE_GAIN_WORKER, 0);
        $this->assertEquals([], $this->game->benefitQueue());

        $this->playAgeOfDiscoveryRollingScience();

        $this->assertEquals("trackSelect", $this->game->stateName());
        $this->assertEquals([], $this->game->moved);
    }

    function testFirstBenefitTrackButtonMovesTheChosenToken() {
        $this->chooseFirst(BE_ADVANCE_S, BE_GAIN_COIN, 6);

        $this->assertEquals([[TRACK_SCIENCE, 5, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    function testFirstBenefitTrackButtonMovesTheStartToken() {
        $this->chooseFirst(BE_ADVANCE_S, BE_GAIN_COIN, 1);

        $this->assertEquals([[TRACK_SCIENCE, 0, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    /** Spot 0 is a real choice here: the regress button onto the start space. */
    function testFirstBenefitRegressButtonOntoTheStartSpace() {
        $this->chooseFirst(BE_REGRESS_E, BE_GAIN_COIN, 0);

        $this->assertEquals([[TRACK_EXPLORATION, 1, ACTION_REGRESS, DiceUT::ROLLER]], $this->game->moved);
    }

    function testChooseBenefitTrackButtonMovesTheChosenToken() {
        $this->chooseOne(BE_ADVANCE_S, BE_GAIN_COIN, 6);

        $this->assertEquals([[TRACK_SCIENCE, 5, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    /** The button to spot 1 stores the start token's spot 0 as the choice. */
    function testChooseBenefitTrackButtonMovesTheStartToken() {
        $this->chooseOne(BE_ADVANCE_S, BE_GAIN_COIN, 1);

        $this->assertEquals([[TRACK_SCIENCE, 0, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    function testChooseBenefitRegressButtonOntoTheStartSpace() {
        $this->chooseOne(BE_REGRESS_E, BE_GAIN_COIN, 0);

        $this->assertEquals([[TRACK_EXPLORATION, 1, ACTION_REGRESS, DiceUT::ROLLER]], $this->game->moved);
    }

    function testTrackSelectSpotMovesTheChosenToken() {
        $this->playAgeOfDiscoveryRollingScience();

        $this->game->action_selectTrackSpot(TRACK_SCIENCE, 6);

        $this->assertEquals([[TRACK_SCIENCE, 5, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    function testTrackSelectCubeMovesTheStartToken() {
        $this->playAgeOfDiscoveryRollingScience();

        $this->game->action_selectCube($this->startToken);

        $this->assertEquals([[TRACK_SCIENCE, 0, ACTION_ADVANCE, DiceUT::ROLLER]], $this->game->moved);
    }

    function testResearchDecisionMovesTheChosenToken() {
        $game = $this->game;
        $game->queueBenefitNormal(BE_ADVANCE_SCIENCE_BENEFIT_OPT, DiceUT::ROLLER);
        $game->runManager();
        $this->assertEquals("research", $game->stateName());

        $game->action_research_decision(TRACK_SCIENCE, 6);

        $this->assertEquals([[TRACK_SCIENCE, 5, ACTION_ADVANCE, DiceUT::ROLLER]], $game->moved);
    }
}
