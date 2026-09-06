<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Weefolk, the fourth Fantasies and Futures civilization: a player token planted in an opponent's
 * capital, an optional territory-for-building trade, and row and column scoring at income turn 5.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename.
 */
class WeefolkUT extends GameUT {
    const OWNER = 11;
    const OPPONENT = 12;

    /** getCurrentEra() per player, on top of the single era GameUT models. */
    public array $eras = [];

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::OWNER, self::OWNER + $players - 1), []));
        $this->curid = self::OWNER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
        $this->giveCiv(self::OWNER, CIV_WEEFOLK);
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(2);
    }

    function weefolk(): Weefolk {
        return $this->getCivilizationInstance(CIV_WEEFOLK, true);
    }

    function getCurrentEra($player_id) {
        return $this->eras[$player_id] ?? parent::getCurrentEra($player_id);
    }

    /** isTapestryActive() reads the card table with raw SQL, which the in memory model never sees. */
    public array $tapestries = [];

    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries[$player_id] ?? []);
    }

    function makeZombie(int $player_id): void {
        $players = $this->loadPlayersBasicInfos();
        $players[$player_id]["player_zombie"] = 1;
        $this->_setPlayerBasicInfo($players);
    }

    function useCivAbility(int $player_id, int $spot, $extra = ""): void {
        $this->civTokenAdvance(CIV_WEEFOLK, $player_id, $spot, $extra);
    }

    /** The real pop of the plot row: stBenefitManager switches to the opponent and resolves it. */
    function offerPlot(int $opponent_id): void {
        $this->gamestate->jumpToState(18);
        $this->stBenefitManager();
        if ($this->getActivePlayerId() != $opponent_id) {
            throw new BgaSystemException("the plot row did not make $opponent_id active");
        }
    }

    function plotOptions(): array {
        return $this->argPlaceStructure()["options"][0];
    }

    /** Put a structure straight into a capital cell, grid included. */
    function putStructure(int $capital_owner, int $type, int $x, int $y, int $landmark = 0, int $rot = 0): int {
        $id = $this->dbAddStructure($capital_owner, $type, $rot, "capital_cell_{$capital_owner}_{$x}_{$y}", $landmark);
        $this->dbSetCapitalCell($capital_owner, $x, $y, $type + 1);
        return $id;
    }

    /** Every plot occupied, so the only way in for a token is to replace a building. */
    function fillCity(int $capital_owner): void {
        for ($x = 3; $x < 12; $x++) {
            for ($y = 3; $y < 12; $y++) {
                $this->dbSetCapitalCell($capital_owner, $x, $y, BUILDING_HOUSE + 1);
            }
        }
    }

    function plantedTokens(int $player_id): array {
        return $this->getStructuresSearch(BUILDING_CUBE, null, "capital\\_cell\\_%", $player_id);
    }
}

final class WeefolkTest extends TestCase {
    private WeefolkUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): WeefolkUT {
        $game = new WeefolkUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    /** The give prompt with the token handed to the first eligible opponent. */
    private function giveToken(int $turn = 2, ?WeefolkUT $game = null): void {
        $game = $game ?? $this->game;
        $game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, $turn);
        $game->useCivAbility(WeefolkUT::OWNER, 1);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_WEEFOLK];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertTrue($civ["midgame_setup"]);
        $this->assertArrayNotHasKey("slots", $civ, "nothing on the mat is clicked");
    }

    /** The token needs an opponent to take it, so the civ is kept out of the solo deck. */
    function testCivIsNotDealtInSoloGames() {
        $this->assertFalse($this->game->civilizations[CIV_WEEFOLK]["automa"]);
    }

    function testAbilityFiresOnIncomeTurns2To5() {
        foreach ([2, 3, 4, 5] as $turn) {
            $game = $this->newGame();
            $game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, $turn);
            $expected = $turn == 5 ? ["civ", (string) BE_WEEFOLK_SCORE] : ["civ"];
            $this->assertEquals($expected, $game->benefitLabels(), "income turn $turn");
        }
    }

    /** getActivePlayerId() is declared to return a string, and this class is under strict_types. */
    function testAbilityAcceptsAStringPlayerId() {
        $this->game->queueEraCivAbility(CIV_WEEFOLK, (string) WeefolkUT::OWNER, 2);
        $this->assertEquals(["civ"], $this->game->benefitLabels());
    }

    /** Nobody to receive it means no prompt: the gift has no decline, so an empty one would stall. */
    function testMidgameGainWithNoEligibleOpponentQueuesNothing() {
        $this->game->eras[WeefolkUT::OPPONENT] = 6;
        $this->game->weefolk()->setupCiv(WeefolkUT::OWNER, "");

        $this->assertEquals([], $this->game->benefitLabels());
        $this->assertContains(
            '${player_name} has nobody to give a player token to, the token is skipped',
            $this->game->notificationTexts()
        );
    }

    function testAbilityDoesNotFireOnIncomeTurn1() {
        $this->game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, 1);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    function testGivePromptOffersOneMandatoryButtonPerOpponent() {
        $game = $this->newGame(3);
        $game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, 2);
        $args = $game->argCivAbilitySingle(WeefolkUT::OWNER, CIV_WEEFOLK, $game->getCurrentBenefit(CIV_WEEFOLK, "civ"));

        $this->assertFalse($args["decline"]);
        $this->assertEquals([1, 2], array_keys($args["slots_choice"]));
        $this->assertEquals([WeefolkUT::OPPONENT, WeefolkUT::OPPONENT + 1], array_column($args["slots_choice"], "player_id"));
    }

    /** An opponent past income turn 5 can no longer place, so they are not offered (FORMAL_RULES 5.10). */
    function testFinishedOpponentIsNotOffered() {
        $game = $this->newGame(3);
        $game->eras[WeefolkUT::OPPONENT] = 6;
        $game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, 2);
        $args = $game->argCivAbilitySingle(WeefolkUT::OWNER, CIV_WEEFOLK, $game->getCurrentBenefit(CIV_WEEFOLK, "civ"));

        $this->assertEquals([WeefolkUT::OPPONENT + 1], array_column($args["slots_choice"], "player_id"));
    }

    /** With nobody left to take a token the gift is skipped, but the trade is still offered. */
    function testNobodyEligibleSkipsTheTokenAndStillOffersTheTrade() {
        $this->game->eras[WeefolkUT::OPPONENT] = 6;
        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, 2);

        $this->assertEquals(["civ"], $this->game->benefitLabels());
        $this->assertTrue($this->game->weefolk()->isBuildPhase($this->game->getCurrentBenefit(CIV_WEEFOLK, "civ")));
        $this->assertContains(
            '${player_name} has nobody to give a player token to, the token is skipped',
            $this->game->notificationTexts()
        );
    }

    function testNobodyEligibleAndNoTileLeavesNothingPending() {
        $this->game->eras[WeefolkUT::OPPONENT] = 6;
        $this->game->queueEraCivAbility(CIV_WEEFOLK, WeefolkUT::OWNER, 2);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** The row that plants the token belongs to the opponent, which is what makes them active. */
    function testGivingATokenQueuesThePlotRowForTheOpponent() {
        $this->giveToken();
        $rows = $this->game->benefitQueue();

        $this->assertEquals([(string) BE_WEEFOLK_PLOT], $this->game->benefitLabels());
        $this->assertEquals(WeefolkUT::OPPONENT, (int) $rows[0]["benefit_player_id"]);
        $this->assertEquals(reason_civ(CIV_WEEFOLK), $rows[0]["benefit_data"]);
        $this->assertContains('${player_name} gives a player token to ${player_name2}', $this->game->notificationTexts());
    }

    /** The trade is a second prompt queued behind the token, never a second civ row at the same time. */
    function testTradeIsOfferedAfterTheTokenOnIncomeTurns2To4() {
        foreach ([2, 3, 4] as $turn) {
            $game = $this->newGame();
            $game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
            $this->giveToken($turn, $game);
            $this->assertEquals([(string) BE_WEEFOLK_PLOT, "civ"], $game->benefitLabels(), "income turn $turn");
        }
    }

    function testTradeIsNotOfferedWithoutATerritoryTile() {
        $this->giveToken(3);
        $this->assertEquals([(string) BE_WEEFOLK_PLOT], $this->game->benefitLabels());
    }

    function testTradeIsNotOfferedOnIncomeTurn5() {
        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->giveToken(5);
        $this->assertEquals([(string) BE_WEEFOLK_PLOT, (string) BE_WEEFOLK_SCORE], $this->game->benefitLabels());
    }

    /** The fifth token is planted before the scoring row is reached, so it counts (FORMAL_RULES 5.7). */
    function testIncomeTurn5ScoresBehindTheLastPlot() {
        $this->giveToken(5);
        $this->assertEquals(
            [BE_WEEFOLK_PLOT, BE_WEEFOLK_SCORE],
            array_map(fn($row) => (int) $row["benefit_type"], $this->game->benefitQueue())
        );
    }

    function testMidgameGainGivesOneTokenAndNothingElse() {
        $this->game->weefolk()->setupCiv(WeefolkUT::OWNER, "");
        $this->assertEquals(["civ"], $this->game->benefitLabels());

        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->game->useCivAbility(WeefolkUT::OWNER, 1);
        $this->assertEquals([(string) BE_WEEFOLK_PLOT], $this->game->benefitLabels());
    }

    // ------------------------------------------------------------- placement

    function testPlotRowHandsTheOpponentAStructureToPlace() {
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $pending = $this->game->getPendingStructure();
        $this->assertEquals(BUILDING_CUBE, (int) $pending["card_type"]);
        $this->assertEquals(WeefolkUT::OWNER, (int) $pending["card_location_arg"], "the token stays the owner's cube");
        $this->assertEquals(26, $this->game->gamestate->state()["id"]);
    }

    function testPlantedTokenFillsTheCellAndKeepsItsOwner() {
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $this->game->place_structure(0, 5, 7);

        $tokens = $this->game->plantedTokens(WeefolkUT::OWNER);
        $this->assertCount(1, $tokens);
        $this->assertEquals("capital_cell_" . WeefolkUT::OPPONENT . "_5_7", reset($tokens)["card_location"]);
        $this->assertEquals(BUILDING_CUBE + 1, $this->game->getCapitalData(WeefolkUT::OPPONENT)[5][7]);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** The filled plot counts for the opponent's capital, so it can complete their district. */
    function testPlantedTokenCompletesTheOpponentsDistrict() {
        for ($x = 3; $x <= 5; $x++) {
            for ($y = 3; $y <= 5; $y++) {
                if ($x != 4 || $y != 4) {
                    $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_HOUSE, $x, $y);
                }
            }
        }
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $this->game->place_structure(0, 4, 4);

        $rows = $this->game->benefitQueue();
        $this->assertCount(1, $rows);
        $this->assertEquals([RES_ANY, WeefolkUT::OPPONENT], [(int) $rows[0]["benefit_type"], (int) $rows[0]["benefit_player_id"]]);
        $this->assertContains('${player_name} completes district #${dn}', $this->game->notificationTexts());
    }

    /** Impassable cells are not plots, the token is not a building (FORMAL_RULES 5.8). */
    function testImpassableCellsAreNotOffered() {
        $this->game->setCapitalMat(WeefolkUT::OPPONENT, 1);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->assertNotContains("4_3", $this->game->plotOptions(), "the mountain mat is impassable at 4_3");
        $this->assertContains("3_3", $this->game->plotOptions());
    }

    /** Even TERRAFORMING does not open an impassable cell to someone else's token (FORMAL_RULES 5.8). */
    function testTerraformingDoesNotOpenImpassableCellsToAForeignToken() {
        $this->game->setCapitalMat(WeefolkUT::OPPONENT, 1);
        $this->game->tapestries[WeefolkUT::OPPONENT] = [39];
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->assertNotContains("4_3", $this->game->plotOptions());
    }

    /** A full city offers the income building cells instead, landmarks are never offered (FORMAL_RULES 5.9). */
    function testFullCityOffersIncomeBuildingCellsOnly() {
        $this->game->fillCity(WeefolkUT::OPPONENT);
        $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 6, 8);
        $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_LANDMARK, 3, 3, 1);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->assertEquals(["6_8"], $this->game->plotOptions());
    }

    function testReplacedBuildingIsSetAsideAndTheCellStaysFilled() {
        $this->game->fillCity(WeefolkUT::OPPONENT);
        $market = $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 6, 8);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $this->game->place_structure(0, 6, 8);

        $this->assertEquals("hand", $this->game->structureLocation($market));
        $this->assertEquals(BUILDING_CUBE + 1, $this->game->getCapitalData(WeefolkUT::OPPONENT)[6][8]);
        $this->assertContains(
            '${player_name} sets aside their ${structure_name}, replaced by a player token',
            $this->game->notificationTexts()
        );
    }

    /** With a plot still free the token has no business evicting anyone (FORMAL_RULES 5.9). */
    function testReplacementIsRefusedWhileTheCityHasAnEmptyPlot() {
        $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 6, 8);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->expectException(BgaUserException::class);
        $this->game->place_structure(0, 6, 8);
    }

    /** Only the plot token may take an occupied cell, an ordinary building still cannot. */
    function testOwnBuildingCannotBePlacedOnAnOccupiedCell() {
        $this->game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 6, 8);
        $house = $this->game->dbAddStructure(WeefolkUT::OPPONENT, BUILDING_HOUSE, 0, "capital_structure");

        $this->expectException(BgaUserException::class);
        $this->game->effect_placeOnCapitalMat($house, 6, 8, 0, WeefolkUT::OPPONENT);
    }

    /** The plot token belongs to the WEEFOLK player, so the Craftsmen mat must not take it. */
    function testForeignTokenCannotBeDivertedToTheCraftsmenMat() {
        $this->game->giveCiv(WeefolkUT::OPPONENT, CIV_CRAFTSMEN);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->expectException(BgaUserException::class);
        $this->game->placeCraftsmen(1);
    }

    /** Nor may Nomads put it on the map: the guard sits ahead of the conquer target check. */
    function testForeignTokenCannotBeDivertedToTheMapByNomads() {
        $this->game->giveCiv(WeefolkUT::OPPONENT, CIV_NOMADS);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);

        $this->expectException(BgaUserException::class);
        $this->game->conquer_structure(3, 3);
    }

    // ------------------------------------------------------------------ undo

    /**
     * The plot row makes the opponent active inside the owner's income turn. Without a savepoint at
     * that switch the opponent's undo would rewind into the owner's turn and replay the gift.
     */
    function testMakingTheOpponentActiveTakesAnUndoSavepoint() {
        $this->giveToken();
        $this->assertEquals([], $this->game->undoSavepoints, "the gift alone makes nobody else active");

        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $this->assertEquals([WeefolkUT::OPPONENT], $this->game->undoSavepoints);
        $this->assertEquals(WeefolkUT::OPPONENT, (int) $this->game->getActivePlayerId());
    }

    /** Nobody becomes active on the zombie branch, so the civ has to take the savepoint itself. */
    function testPlantingForAZombieTakesItsOwnUndoSavepoint() {
        $this->game->makeZombie(WeefolkUT::OPPONENT);
        $this->game->seedRand(0);
        $this->giveToken();

        $this->assertEquals([WeefolkUT::OWNER], $this->game->undoSavepoints);
    }

    /** The trade prompt stays with the owner, so it adds no savepoint of its own. */
    function testTradePromptTakesNoSavepoint() {
        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $this->game->place_structure(0, 5, 7);
        $savepoints = $this->game->undoSavepoints;

        $this->game->useCivAbility(WeefolkUT::OWNER, Weefolk::CHOICE_BUILD, 3);
        $this->assertEquals($savepoints, $this->game->undoSavepoints);
    }

    // ----------------------------------------------------------------- trade

    function testTradeDiscardsTheTileAndQueuesTheIncomeBuilding() {
        $tile = $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->giveToken();
        $this->game->benefitCashed($this->game->getCurrentBenefit(BE_WEEFOLK_PLOT));
        $this->game->useCivAbility(WeefolkUT::OWNER, Weefolk::CHOICE_BUILD, 3);

        $this->assertEquals("discard", $this->game->getCardInfoById($tile)["card_location"]);
        $this->assertEquals([(string) BE_GAIN_ANY_INCOME_BUILDING], $this->game->benefitLabels());
    }

    function testTradeWithATileTheOwnerDoesNotHaveIsRejected() {
        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->giveToken();
        $this->game->benefitCashed($this->game->getCurrentBenefit(BE_WEEFOLK_PLOT));

        $this->expectException(BgaUserException::class);
        $this->game->useCivAbility(WeefolkUT::OWNER, Weefolk::CHOICE_BUILD, 7);
    }

    function testTradePromptCanBeDeclined() {
        $this->game->addCard(CARD_TERRITORY, "hand", WeefolkUT::OWNER, 3);
        $this->giveToken();
        $args = $this->game->argCivAbilitySingle(WeefolkUT::OWNER, CIV_WEEFOLK, $this->game->getCurrentBenefit(CIV_WEEFOLK, "civ"));

        $this->assertTrue($args["decline"]);
        $this->assertTrue($args["build"]);
        $this->assertEquals([Weefolk::CHOICE_BUILD], array_keys($args["slots_choice"]));
    }

    // --------------------------------------------------------------- scoring

    /** One VP per income building in the token's row plus one per building in its column. */
    function testScoreCountsTheRowAndTheColumnOfEachToken() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 5, 3);
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_FARM, 5, 4);
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_HOUSE, 9, 7);
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_ARMORY, 8, 8); // neither row 5 nor column 7
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");

        $this->assertEquals(
            3,
            $game->weefolk()->countInLine(WeefolkUT::OPPONENT, true, 5) + $game->weefolk()->countInLine(WeefolkUT::OPPONENT, false, 7)
        );
    }

    /** A building in two tokens' rows is scored twice (FORMAL_RULES 5.7). */
    function testABuildingInTwoTokenRowsScoresTwice() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 5, 5);
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_9");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(2, $this->scoredVP());
    }

    /** In a token's row and also in its column, so it scores once for each (FORMAL_RULES 5.7). */
    function testABuildingInBothTheRowAndTheColumnScoresTwice() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 5, 9);
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_HOUSE, 9, 7);
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_9_9");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(4, $this->scoredVP());
    }

    /** A landmark spanning the line is one landmark, but it can touch a row and a column. */
    function testLandmarkCountsOncePerLineItTouches() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_LANDMARK, 5, 6, 2); // Apothecary, 2x2 over 5-6 by 6-7

        $this->assertEquals(1, $game->weefolk()->countInLine(WeefolkUT::OPPONENT, true, 5), "row 5 holds it once");
        $this->assertEquals(1, $game->weefolk()->countInLine(WeefolkUT::OPPONENT, true, 6), "and so does row 6");
        $this->assertEquals(1, $game->weefolk()->countInLine(WeefolkUT::OPPONENT, false, 7), "and column 7");
        $this->assertEquals(0, $game->weefolk()->countInLine(WeefolkUT::OPPONENT, false, 9), "column 9 does not touch it");
    }

    /** A landmark in the token's row and its column is two separate lines, so it scores twice. */
    function testLandmarkInTheRowAndTheColumnScoresTwice() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_LANDMARK, 5, 6, 2);
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(2, $this->scoredVP());
    }

    /** A cube of the owner in their own capital is not a planted token (FORMAL_RULES 5.11). */
    function testOwnCubeInOwnCapitalDoesNotScore() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OWNER, BUILDING_MARKET, 5, 5);
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OWNER . "_5_7");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(0, $this->scoredVP());
    }

    function testScoringWithNoPlantedTokenIsZero() {
        $this->game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $this->game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);
        $this->assertEquals(0, $this->scoredVP());
    }

    // --------------------------------------------------- other civilizations

    /**
     * INFILTRATORS is the other civ that puts a cube of its owner into someone else's space, on the
     * opponent's start hex rather than in their capital. Only what sits in a capital cell is a
     * planted token, so the two cube stacks are scored apart (FORMAL_RULES 5.11).
     */
    function testInfiltratorsCubesOnTheStartHexDoNotScore() {
        $game = $this->game;
        $game->putStructure(WeefolkUT::OPPONENT, BUILDING_MARKET, 5, 5);
        $game->addCubeAt(WeefolkUT::OWNER, $this->startHex(WeefolkUT::OPPONENT));
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(1, $this->scoredVP(), "the market in the planted token's row, and nothing else");
    }

    /**
     * The other direction: INFILTRATORS counts its own cubes on the start hex to decide the third
     * cube civilization bonus, and a token planted in that same opponent's capital is not one.
     */
    function testPlantedTokenDoesNotCountTowardTheInfiltratorsBonus() {
        $game = $this->game;
        $game->giveCiv(WeefolkUT::OWNER, CIV_INFILTRATORS);
        $start = $this->startHex(WeefolkUT::OPPONENT);
        $game->addCubeAt(WeefolkUT::OWNER, $start);
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");

        $args = $game->argCivAbilitySingle(WeefolkUT::OWNER, CIV_INFILTRATORS, ["benefit_data" => ""]);
        $choices = array_filter($args["slots_choice"], fn($slot) => array_get($slot, "player_id") == WeefolkUT::OPPONENT);
        $this->assertCount(1, $choices);
        $choice = reset($choices);
        $this->assertEquals(1, $choice["cubes"], "one cube on the hex, the planted token is not on it");
        $this->assertEquals([171], $choice["benefit"], "no civilization bonus, that needs a third hex cube");
    }

    /** A foreign cube fills a plot but is no building of that capital, so tokens never score each other. */
    function testPlantedTokensDoNotScoreEachOther() {
        $game = $this->game;
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_7");
        $game->addCubeAt(WeefolkUT::OWNER, "capital_cell_" . WeefolkUT::OPPONENT . "_5_9");
        $game->queueBenefitNormal(BE_WEEFOLK_SCORE, WeefolkUT::OWNER, reason_civ(CIV_WEEFOLK));
        $game->resolveBenefit(BE_WEEFOLK_SCORE, WeefolkUT::OWNER);

        $this->assertEquals(0, $this->scoredVP());
    }

    // ---------------------------------------------------------------- zombie

    /**
     * An opponent who is already a zombie counts as finished, so a benefit row of theirs would be
     * dropped before the civ ever saw it. The token is planted for them instead (FORMAL_RULES 5.10).
     */
    function testZombieOpponentIsPlantedForRatherThanQueued() {
        $this->game->makeZombie(WeefolkUT::OPPONENT);
        $this->game->seedRand(0); // the first empty plot, 3_3
        $this->giveToken();

        $this->assertEquals([], $this->game->benefitLabels(), "no row is left for the quitter to swallow");
        $tokens = $this->game->plantedTokens(WeefolkUT::OWNER);
        $this->assertCount(1, $tokens);
        $this->assertEquals("capital_cell_" . WeefolkUT::OPPONENT . "_3_3", reset($tokens)["card_location"]);
        $this->assertContains('${player_name} is zombie, a random plot is chosen for the player token', $this->game->notificationTexts());
    }

    function testZombieOpponentWithAFullCityKeepsTheToken() {
        $this->game->fillCity(WeefolkUT::OPPONENT);
        $this->game->makeZombie(WeefolkUT::OPPONENT);
        $this->giveToken();

        $this->assertCount(0, $this->game->plantedTokens(WeefolkUT::OWNER));
        $this->assertContains(
            '${player_name} is zombie and their city is full, the player token is not placed',
            $this->game->notificationTexts()
        );
    }

    /**
     * Quitting at the placement prompt leaves the token in capital_structure. It has to be the cube
     * that gets planted, or it strands there and the next placement of any player picks it up.
     */
    function testOpponentQuittingAtThePromptPlantsTheWaitingToken() {
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $pending = (int) $this->game->getPendingStructure()["card_id"];
        $this->game->makeZombie(WeefolkUT::OPPONENT);
        $this->game->seedRand(0);

        $this->game->zombieTurn(null, WeefolkUT::OPPONENT);

        $this->assertNull($this->game->getPendingStructure(), "capital_structure is emptied");
        $this->assertEquals("capital_cell_" . WeefolkUT::OPPONENT . "_3_3", $this->game->structureLocation($pending));
        $this->assertCount(1, $this->game->plantedTokens(WeefolkUT::OWNER), "no second cube is created");
    }

    function testOpponentQuittingAtThePromptWithAFullCityReturnsTheToken() {
        $this->giveToken();
        $this->game->offerPlot(WeefolkUT::OPPONENT);
        $pending = (int) $this->game->getPendingStructure()["card_id"];
        $this->game->fillCity(WeefolkUT::OPPONENT);
        $this->game->makeZombie(WeefolkUT::OPPONENT);

        $this->game->zombieTurn(null, WeefolkUT::OPPONENT);

        $this->assertNull($this->game->getPendingStructure());
        $this->assertEquals("hand", $this->game->structureLocation($pending));
    }

    /** The opponent's start hex, where INFILTRATORS parks a cube. */
    private function startHex(int $player_id): string {
        $this->game->addCard(CARD_CAPITAL, "hand", $player_id, 1);
        return $this->game->getStartingPosition($player_id)["location"];
    }

    private function scoredVP(): int {
        $vp = 0;
        foreach ($this->game->notificationsOfType("VP") as $notif) {
            $vp += (int) $notif["args"]["increase"];
        }
        return $vp;
    }
}
