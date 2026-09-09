<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/**
 * Celestials, the second Fantasies and Futures civilization: the floating capital replaces one
 * starting outpost, drifts one territory per income turn 2-4 rolling both conquer dice, and income
 * turn 5 scores the landmarks of the capital city by whether they hang off the side of the mat.
 *
 * What the token does to the map is pinned in MapTest, which covers an inert token whoever placed
 * it. These are the civ's own paths: setup, the income rows, the move and the scoring.
 */
class CelestialsUT extends MapUT {
    const CAPITAL = 1; // small map start -2_-2, three explored neighbours

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->doAdjustMaterial($players, 2);
        $this->giveCiv(self::OWNER, CIV_CELESTIALS);
        $this->setCapitalMat(self::OWNER, 0);
    }

    function celestials(): Celestials {
        return $this->getCivilizationInstance(CIV_CELESTIALS, true);
    }

    /** Setup as stFinishSetup reaches it: two outposts on the starting territory, then the civ. */
    function setupCelestials($start = true): string {
        $this->addCard(CARD_CAPITAL, "hand", self::OWNER, self::CAPITAL);
        $coord = $this->getStartingPosition(self::OWNER)["coords"];
        $this->addOutpostAt(self::OWNER, $coord);
        $this->addOutpostAt(self::OWNER, $coord);
        $this->setupCiv(CIV_CELESTIALS, self::OWNER, $start);
        return $coord;
    }

    function useIncomeAbility(int $turn): void {
        $this->startIncomeTurn(self::OWNER, $turn);
        $this->queueEraCivAbility(CIV_CELESTIALS, self::OWNER, $turn);
    }

    /** The income row popped by the benefit manager, which is what opens the move state. */
    function openMovePrompt(int $turn = 2): void {
        $this->useIncomeAbility($turn);
        $this->gamestate->jumpToState(18);
        $this->stBenefitManager();
    }

    /** red then black, the order rollConquerDice consumes them in. */
    function moveToken(int $token_id, string $coord, int $red = 0, int $black = 0): void {
        $this->seedRand($red, $black);
        $this->action_celestialMove($token_id, (int) getPart($coord, 0), (int) getPart($coord, 1));
    }

    /** A landmark as placed: the anchor in the location, the landmark in arg2, the rotation in type_arg. */
    function placeLandmark(int $landmark, int $x, int $y, int $rot = 0): int {
        $cell = "capital_cell_" . self::OWNER . "_{$x}_{$y}";
        return $this->dbAddStructure(self::OWNER, BUILDING_LANDMARK, $rot, $cell, $landmark);
    }
}

final class CelestialsTest extends TestCase {
    const TECH_HUB = 1; // 2 wide and 3 high at rotation 0, 3 wide and 2 high at rotation 1
    const START = "-2_-2";

    private CelestialsUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): CelestialsUT {
        $game = new CelestialsUT($players);
        $game->init();
        return $game;
    }

    private function tokens(int $player_id = CelestialsUT::OWNER): array {
        return array_keys($this->game->getStructuresSearch(BUILDING_CUBE, 1, "land\\_%", $player_id));
    }

    // ------------------------------------------------------------- material

    function testTheCivilizationIsAnFFIncomeCivWithNoMatSlots() {
        $info = $this->game->civilizations[CIV_CELESTIALS];

        $this->assertEquals("FF", $info["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5], $info["income_trigger"]);
        $this->assertEquals(["or" => [174, 173]], $info["midgame_ben"]);
        $this->assertTrue($info["automa"]);
        $this->assertArrayNotHasKey("slots", $info, "the token lives on the map, not on the mat");
        $this->assertArrayNotHasKey("tokens_count", $info);
    }

    // ---------------------------------------------------------------- setup

    function testSetupLeavesOneOutpostAndTheTokenOnTheStartingTerritory() {
        $game = $this->game;

        $coord = $game->setupCelestials();

        $this->assertEquals([CelestialsUT::OWNER], $game->hexOwners($coord), "the token controls nothing");
        $this->assertEquals(2, $game->hexOccupancy($coord));
        $this->assertCount(1, $game->getStructuresSearch(BUILDING_OUTPOST, null, "land_$coord", CelestialsUT::OWNER));
        $this->assertCount(1, $game->getOutpostsInHand(CelestialsUT::OWNER), "the replaced outpost is back in the supply");
        $this->assertEquals(["land_$coord"], array_map(fn($id) => $game->structureLocation($id), $this->tokens()));
        $this->assertEquals(1, $game->toppleFlag($this->tokens()[0]), "an item, not a structure that stands");
    }

    function testTheTokenIsAPlainPlayerTokenWithNoCivilizationMarker() {
        $game = $this->game;
        $coord = $game->setupCelestials();
        $token = $game->getStructureInfoById($this->tokens()[0]);

        $this->assertEquals(BUILDING_CUBE, $token["card_type"]);
        $this->assertEquals(CelestialsUT::OWNER, $token["card_location_arg"]);
        $this->assertEquals(0, $token["card_location_arg2"], "tokens are indistinguishable once placed");
        $this->assertFalse($game->isControllingStructure($token));
        $this->assertEquals([$coord], array_keys($game->getControlHexes(CelestialsUT::OWNER)));
    }

    function testGainingTheCivilizationMidGameReplacesAnOutpostTheSameWay() {
        $game = $this->game;

        $coord = $game->setupCelestials(false);

        $this->assertCount(1, $game->getStructuresSearch(BUILDING_OUTPOST, null, "land_$coord", CelestialsUT::OWNER));
        $this->assertCount(1, $this->tokens());
        $this->assertCount(1, $game->getOutpostsInHand(CelestialsUT::OWNER));
    }

    // --------------------------------------------------------- income rows

    function testTheMoveIsQueuedOnIncomeTurns2To4AndTheScoreOnTurn5() {
        foreach ([2, 3, 4] as $turn) {
            $game = $this->newGame();
            $game->useIncomeAbility($turn);
            $this->assertEquals([BE_CELESTIALS_MOVE], $game->benefitLabels(), "income turn $turn");
        }
        $game = $this->newGame();
        $game->useIncomeAbility(5);
        $this->assertEquals([BE_CELESTIALS_SCORE], $game->benefitLabels());
    }

    function testIncomeTurn1QueuesNothing() {
        $game = $this->game;

        $game->useIncomeAbility(1);

        $this->assertEquals([], $game->benefitLabels());
        $this->assertStringContainsString("not applicable", $game->notificationTexts()[0]);
    }

    // ----------------------------------------------------------- the prompt

    function testThePromptOffersTheExploredNeighboursOfTheToken() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $game->addOutpostAt(CelestialsUT::OPPONENT, "-2_-1");
        $game->addOutpostAt(CelestialsUT::OPPONENT, "-2_-1", 1);

        $game->openMovePrompt();
        $args = $game->argCelestialMove(CelestialsUT::OWNER);

        $this->assertEquals("celestialMove", $game->getStateName());
        $this->assertEquals(["-2_-1", "-1_-2", "-3_-3"], $args["targets"][$token], "a full territory is still a target");
        $this->assertTrue($args["decline"], "the move is optional");
    }

    function testEveryInertTokenOfTheOwnerIsOfferedAndAStandingCubeIsNot() {
        $game = $this->game;
        $celestial = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $infiltrator = $game->addTokenAt(CelestialsUT::OWNER, "2_0");
        $militant = $game->addCubeAt(CelestialsUT::OWNER, "land_0_0");
        $isolationist = $game->addTokenAt(CelestialsUT::OWNER, "1_-1");
        $game->addTokenAt(CelestialsUT::OPPONENT, "1_2");

        $targets = $game->celestials()->getMoveTargets(CelestialsUT::OWNER);

        $this->assertEquals([$celestial, $infiltrator, $isolationist], array_keys($targets), "tokens are indistinguishable");
        $this->assertArrayNotHasKey($militant, $targets, "a cube placed as an outpost is an outpost");
    }

    function testATokenWithNoExploredNeighbourOffersDeclineOnly() {
        $game = $this->game;
        $game->addTokenAt(CelestialsUT::OWNER, "0_0");

        $game->openMovePrompt();
        $args = $game->argCelestialMove(CelestialsUT::OWNER);

        $this->assertEquals([], $args["targets"]);
        $this->assertTrue($args["decline"]);
    }

    // ------------------------------------------------------------- the move

    function testMovingRollsBothDiceAndGainsBothBenefits() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $game->setTile("-2_-1", 6); // a worker on the black die face 1

        $game->openMovePrompt();
        $game->moveToken($token, "-2_-1", 0, 2);

        $this->assertEquals("land_-2_-1", $game->structureLocation($token));
        $this->assertEquals(1, $game->toppleFlag($token));
        $this->assertEquals([505, BE_GAIN_WORKER], $game->benefitLabels(), "red first, then black");
        $this->assertEquals(-1, $game->benefitPosition("standard", 141, CelestialsUT::OWNER), "no die pick, this is not a conquer");
        $this->assertEquals(0, $game->getGameStateValue("toppled_player"));
    }

    function testTheMoveTakesNoControlOfAnything() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $game->addOutpostAt(CelestialsUT::OPPONENT, "-2_-1");

        $game->openMovePrompt();
        $game->moveToken($token, "-2_-1", 0, 2);

        $this->assertEquals([CelestialsUT::OPPONENT], $game->hexOwners("-2_-1"));
        $this->assertEquals([], array_keys($game->getControlHexes(CelestialsUT::OWNER)));
        $this->assertEquals(2, $game->hexOccupancy("-2_-1"), "and the territory is now blocked at two items");
    }

    function testTheBlackDieFace1PaysTheTerritoryMovedInto() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $game->setTile(self::START, 6); // a worker, the territory left behind
        $game->setTile("-2_-1", 4); // culture, the territory moved into

        $game->openMovePrompt();
        $game->moveToken($token, "-2_-1", 0, 1);

        $this->assertEquals([505, BE_GAIN_CULTURE], $game->benefitLabels());
    }

    function testAFaceWithNoBenefitPaysNothingAndSaysSo() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);

        $game->openMovePrompt();
        $game->moveToken($token, "-2_-1", 0, 1); // the starting tiles have no benefit

        $this->assertEquals([505], $game->benefitLabels());
        $game->notificationLike("gains nothing");
    }

    function testDecliningMovesNothingAndRollsNothing() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);

        $game->openMovePrompt();
        $game->action_decline();

        $this->assertEquals("land_" . self::START, $game->structureLocation($token));
        $this->assertEquals([], $game->benefitLabels());
        $this->assertEquals(0, $game->getGameStateValue("conquer_die_red"));
        $game->notificationLike("does not move their floating capital");
    }

    /** The roll is a roll from the table, so an ILLUMINATI opponent is paid first (FORMAL_RULES CIV.CELESTIALS.5). */
    function testAnIlluminatiOpponentTakesTheDiceAndIsPaidBeforeTheMover() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);
        $game->giveCiv(CelestialsUT::OPPONENT, CIV_ILLUMINATI);
        $game->getCivilizationInstance(CIV_ILLUMINATI, true); // includes civs/Illuminati.php
        $game->setGameStateValue(Illuminati::GLOBAL_DICE, Illuminati::ALL_DICE);

        $game->openMovePrompt();
        $game->moveToken($token, "-2_-1", 0, 2);

        $this->assertEquals(
            [
                [CelestialsUT::OPPONENT, 505],
                [CelestialsUT::OPPONENT, BE_GAIN_WORKER],
                [CelestialsUT::OWNER, 505],
                [CelestialsUT::OWNER, BE_GAIN_WORKER],
            ],
            array_map(fn($row) => [(int) $row["benefit_player_id"], (int) $row["benefit_type"]], $game->benefitQueue())
        );
    }

    /** The state has to declare a transition zombieTurn can take, or a quitter throws in it. */
    function testAZombieInThePromptSkipsTheMove() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);

        $game->openMovePrompt();
        $game->zombieTurn();

        $this->assertEquals("land_" . self::START, $game->structureLocation($token));
        $this->assertEquals([], $game->benefitLabels());
    }

    function testMovingToATerritoryThatWasNotOfferedIsRejected() {
        $game = $this->game;
        $token = $game->addTokenAt(CelestialsUT::OWNER, self::START);

        $game->openMovePrompt();

        $this->expectException(BgaUserException::class);
        $game->moveToken($token, "2_0");
    }

    // ---------------------------------------------------------- the scoring

    private function score(): int {
        $game = $this->game;
        $game->useIncomeAbility(5);
        $game->resolveBenefit(BE_CELESTIALS_SCORE, CelestialsUT::OWNER);
        return $game->dbGetScore(CelestialsUT::OWNER);
    }

    function testACapitalWithNoLandmarksScoresNothing() {
        $this->assertEquals(0, $this->score());
    }

    function testEveryLandmarkOnTheMatScores5() {
        $game = $this->game;
        $game->placeLandmark(self::TECH_HUB, 3, 3);
        $game->placeLandmark(self::TECH_HUB, 6, 3);
        $game->placeLandmark(self::TECH_HUB, 9, 3);

        $this->assertEquals(15, $this->score());
    }

    function testALandmarkHangingOffAnySideCosts2() {
        foreach ([[2, 3, 0], [3, 2, 0], [11, 3, 0], [3, 10, 0]] as [$x, $y, $rot]) {
            $game = $this->newGame();
            $this->game = $game;
            $game->placeLandmark(self::TECH_HUB, $x, $y, $rot);

            $this->assertEquals(-2, $this->score(), "anchor $x,$y");
        }
    }

    function testOnlyTheRotationCanMakeALandmarkHang() {
        $game = $this->game;
        $game->placeLandmark(self::TECH_HUB, 10, 3, 0);

        $this->assertEquals(5, $this->score(), "2 wide fits at x 10");

        $game = $this->newGame();
        $this->game = $game;
        $game->placeLandmark(self::TECH_HUB, 10, 3, 1);

        $this->assertEquals(-2, $this->score(), "3 wide does not");
    }

    function testAMixedCapitalNetsTheTwoRates() {
        $game = $this->game;
        $game->placeLandmark(self::TECH_HUB, 3, 3);
        $game->placeLandmark(self::TECH_HUB, 6, 3);
        $game->placeLandmark(self::TECH_HUB, 3, 10);

        $this->assertEquals(8, $this->score());
    }

    function testALandmarkOutsideTheCityScoresNeither() {
        $game = $this->game;
        $game->dbAddStructure(CelestialsUT::OWNER, BUILDING_LANDMARK, 0, "hand", self::TECH_HUB);
        $game->dbAddStructure(CelestialsUT::OWNER, BUILDING_LANDMARK, 0, "civ_" . CIV_CRAFTSMEN . "_1", self::TECH_HUB);

        $this->assertEquals(0, $this->score());
    }

    function testALandmarkOfAnotherPlayerIsNotCounted() {
        $game = $this->game;
        $game->dbAddStructure(
            CelestialsUT::OPPONENT,
            BUILDING_LANDMARK,
            0,
            "capital_cell_" . CelestialsUT::OPPONENT . "_3_3",
            self::TECH_HUB
        );

        $this->assertEquals(0, $this->score());
    }
}
