<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Elder Ones, the fifth Fantasies and Futures civilization: a choose one gain on income turns 2-4,
 * a tapestry for resources trade on income turn 5, and extended play afterwards.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename.
 */
class ElderOnesUT extends GameUT {
    const OWNER = 11;
    const OPPONENT = 12;

    /** What getPossibleAdvances() answers, playerextra and the track cubes are not modelled. */
    public array $advances = [];
    /** Players finalGameScoring() ran for, in order. */
    public array $finalScored = [];

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::OWNER, self::OWNER + $players - 1), []));
        $this->curid = self::OWNER;
        $this->_setCurrentPlayerId($this->curid);
        $this->eras[self::OWNER] = 2;
        $this->eras[self::OPPONENT] = 2;
        $this->giveCiv(self::OWNER, CIV_ELDER_ONES);
        $this->getCivilizationInstance(CIV_ELDER_ONES, true); // includes civs/ElderOnes.php
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(2);
    }

    function getPossibleAdvances($onlyValid = true) {
        return $this->advances;
    }

    function finalGameScoring($player_id) {
        $this->finalScored[] = (int) $player_id;
    }

    function useCivAbility(int $player_id, int $spot): void {
        $this->civTokenAdvance(CIV_ELDER_ONES, $player_id, $spot);
    }

    /** The income row and the button press that answers it, the way a real turn reaches the civ. */
    function useIncomeAbility(int $player_id, int $turn, int $spot): void {
        $this->queueEraCivAbility(CIV_ELDER_ONES, $player_id, $turn);
        $this->useCivAbility($player_id, $spot);
    }

    function acceptBonus(int $player_id, array $card_ids): void {
        $this->gamestate->changeActivePlayer($player_id);
        $this->gamestate->jumpToState(35);
        $this->action_acceptBonus($card_ids, 0);
    }

    function declineTheBonus(int $player_id): void {
        $this->gamestate->changeActivePlayer($player_id);
        $this->gamestate->jumpToState(35);
        $this->declineBonus();
    }

    function playerTurn(int $player_id): void {
        $this->startPlayerTurn($player_id);
        $this->gamestate->jumpToState(13);
        $this->stPlayerTurn();
    }

    function giveTapestryCards(int $player_id, int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->addCard(CARD_TAPESTRY, "hand", $player_id, 1);
        }
        return $ids;
    }
}

final class ElderOnesTest extends TestCase {
    private ElderOnesUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): ElderOnesUT {
        $game = new ElderOnesUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    private function elderOnes(): ElderOnes {
        return $this->game->getCivilizationInstance(CIV_ELDER_ONES, true);
    }

    /** The state where the player has finished income turn 5 and keeps taking advance turns. */
    private function enterExtendedPlay(int $player_id = ElderOnesUT::OWNER): void {
        $this->game->eras[$player_id] = 5;
        $this->game->startPlayerTurn($player_id);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_ELDER_ONES];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertArrayNotHasKey("slots", $civ, "nothing on the mat is clicked");
    }

    /** Extended play is advance turns, which the bot does not take. */
    function testCivIsNotDealtInSoloGames() {
        $this->assertFalse($this->game->civilizations[CIV_ELDER_ONES]["automa"]);
    }

    // -------------------------------------------------------- income turns 2-4

    function testIncomeTurns2To4OfferBothOptions() {
        foreach ([2, 3, 4] as $turn) {
            $game = $this->newGame();
            $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, $turn);
            $this->assertEquals(["civ"], $game->benefitLabels(), "income turn $turn");

            $args = $game->argCivAbilitySingle(ElderOnesUT::OWNER, CIV_ELDER_ONES, $game->getCurrentBenefit(CIV_ELDER_ONES, "civ"));
            $this->assertEquals([ElderOnes::CHOICE_RESOURCE, ElderOnes::CHOICE_TAPESTRY], array_keys($args["slots_choice"]));
            $this->assertEquals([BE_ANYRES], $args["slots_choice"][ElderOnes::CHOICE_RESOURCE]["benefit"]);
            $this->assertEquals([BE_TAPESTRY, BE_TAPESTRY], $args["slots_choice"][ElderOnes::CHOICE_TAPESTRY]["benefit"]);
            $this->assertFalse($args["decline"], "the ability is mandatory");
        }
    }

    function testResourceChoiceQueuesOneAnyResource() {
        $this->game->useIncomeAbility(ElderOnesUT::OWNER, 2, ElderOnes::CHOICE_RESOURCE);
        $this->assertEquals([(string) BE_ANYRES], $this->game->benefitLabels());
    }

    function testTapestryChoiceQueuesTwoCards() {
        $this->game->useIncomeAbility(ElderOnesUT::OWNER, 3, ElderOnes::CHOICE_TAPESTRY);
        $this->assertEquals([(string) BE_TAPESTRY, (string) BE_TAPESTRY], $this->game->benefitLabels());
    }

    function testAbilityIsNotOfferedOutsideItsIncomeTurns() {
        $this->game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 1);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    // ---------------------------------------------------------- income turn 5

    function testIncomeTurn5QueuesTheTradeAsABonusRow() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);
        $row = $game->benefitQueue()[0];
        $this->assertEquals("bonus", $row["benefit_category"]);
        $this->assertEquals(BE_TAPESTRY, $row["benefit_type"]);
        $this->assertEquals(-ElderOnes::TRADE_MAX, $row["benefit_quantity"], "an upper bound of six cards");
        $this->assertEquals(BE_ANYRES . "," . BE_ANYRES, $row["benefit_data"]);
    }

    function testTradeGivesTwoResourcesPerCardDiscarded() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $cards = $game->giveTapestryCards(ElderOnesUT::OWNER, 4);
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);

        $game->acceptBonus(ElderOnesUT::OWNER, array_slice($cards, 0, 3));

        $this->assertEquals(array_fill(0, 6, (string) BE_ANYRES), $game->benefitLabels());
        $this->assertEquals(1, $game->getCardCountInHand(ElderOnesUT::OWNER, CARD_TAPESTRY), "3 of 4 discarded");
    }

    function testTradeAcceptsTheSixCardMaximum() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $cards = $game->giveTapestryCards(ElderOnesUT::OWNER, 6);
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);

        $game->acceptBonus(ElderOnesUT::OWNER, $cards);

        $this->assertEquals(array_fill(0, 12, (string) BE_ANYRES), $game->benefitLabels());
    }

    function testTradeRefusesASeventhCard() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $cards = $game->giveTapestryCards(ElderOnesUT::OWNER, 7);
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);

        $this->expectException(BgaUserException::class);
        $game->acceptBonus(ElderOnesUT::OWNER, $cards);
    }

    function testTradeIsSkippedWithAnEmptyHand() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);

        $game->gamestate->jumpToState(35);
        $game->stBonus();

        $this->assertEquals([], $game->benefitLabels(), "no card to discard, the row is dropped");
    }

    function testTradeCanBeDeclined() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $game->giveTapestryCards(ElderOnesUT::OWNER, 2);
        $game->queueEraCivAbility(CIV_ELDER_ONES, ElderOnesUT::OWNER, 5);

        $game->declineTheBonus(ElderOnesUT::OWNER);

        $this->assertEquals([], $game->benefitLabels());
        $this->assertEquals(2, $game->getCardCountInHand(ElderOnesUT::OWNER, CARD_TAPESTRY), "nothing paid");
    }

    // ------------------------------------------------------- end of income 5

    function testIncomeTurn5DoesNotFinishThePlayer() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);

        $game->effect_endOfIncome(ElderOnesUT::OWNER);

        $this->assertEquals(5, $game->getCurrentEra(ElderOnesUT::OWNER), "the era stays 5");
        $this->assertEquals([], $game->finalScored, "final scoring is deferred");
        $this->assertTrue($game->isPlayerAlive(ElderOnesUT::OWNER));
    }

    function testIncomeTurn5FinishesAPlayerWithoutTheCiv() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OPPONENT, 5);

        $game->effect_endOfIncome(ElderOnesUT::OPPONENT);

        $this->assertEquals(6, $game->getCurrentEra(ElderOnesUT::OPPONENT));
        $this->assertEquals([ElderOnesUT::OPPONENT], $game->finalScored);
    }

    function testExtendedPlayStartsOnlyAfterIncomeTurn5() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 4);
        $this->assertFalse($game->isExtendedPlay(ElderOnesUT::OWNER), "era 4");

        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $this->assertFalse($game->isExtendedPlay(ElderOnesUT::OWNER), "during income turn 5");

        $game->startPlayerTurn(ElderOnesUT::OWNER);
        $this->assertTrue($game->isExtendedPlay(ElderOnesUT::OWNER));

        $game->startPlayerTurn(ElderOnesUT::OPPONENT);
        $this->assertTrue($game->isExtendedPlay(ElderOnesUT::OWNER), "still, on an opponent's turn");
        $this->assertFalse($game->isExtendedPlay(ElderOnesUT::OPPONENT), "the opponent has no such civ");
    }

    // -------------------------------------------------------- extended turns

    function testTurnWithAnAffordableAdvanceContinues() {
        $game = $this->game;
        $game->eras[ElderOnesUT::OWNER] = 5;
        $game->advances = ["1_5" => 1];

        $game->playerTurn(ElderOnesUT::OWNER);

        $this->assertEquals([], $game->finalScored);
        $this->assertTrue($game->isPlayerAlive(ElderOnesUT::OWNER));
    }

    function testTurnWithNoAffordableAdvanceFinishesThePlayerOnce() {
        $game = $this->game;
        $game->eras[ElderOnesUT::OWNER] = 5;
        $game->advances = [];

        $game->playerTurn(ElderOnesUT::OWNER);

        $this->assertEquals([ElderOnesUT::OWNER], $game->finalScored);
        $this->assertEquals(6, $game->getCurrentEra(ElderOnesUT::OWNER));
        $this->assertFalse($game->isExtendedPlay(ElderOnesUT::OWNER));

        $game->playerTurn(ElderOnesUT::OWNER);
        $this->assertEquals([ElderOnesUT::OWNER], $game->finalScored, "not scored a second time");
    }

    /** The civ does not block the income turn that reaches era 5, and that turn is not extended play. */
    function testTakingIncomeTurn5IsAllowedAndDoesNotStartExtendedPlayYet() {
        $game = $this->game;
        $game->eras[ElderOnesUT::OWNER] = 4;
        $game->startPlayerTurn(ElderOnesUT::OWNER);
        $game->gamestate->jumpToState(13);

        $game->takeIncome();

        $this->assertEquals(5, $game->getCurrentEra(ElderOnesUT::OWNER));
        $this->assertFalse($game->isExtendedPlay(ElderOnesUT::OWNER), "income turn 5 is not over yet");
    }

    function testIncomeIsRefusedInExtendedPlay() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->gamestate->jumpToState(13);

        $this->expectException(BgaUserException::class);
        $game->takeIncome();
    }

    function testEndingVoluntarilyFinishesThePlayer() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->advances = ["1_5" => 1];
        $game->gamestate->jumpToState(13);

        $game->action_endMyGame();

        $this->assertEquals([ElderOnesUT::OWNER], $game->finalScored);
        $this->assertEquals(6, $game->getCurrentEra(ElderOnesUT::OWNER));
    }

    function testEndingIsRefusedBeforeIncomeTurn5IsOver() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $game->gamestate->jumpToState(13);

        $this->expectException(BgaUserException::class);
        $game->action_endMyGame();
    }

    function testExtendedPlayFlagReachesTheClient() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $this->assertTrue($game->argPlayerTurn()["extended_play"]);

        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $this->assertFalse($game->argPlayerTurn()["extended_play"]);
    }

    // ------------------------------------------------------- response cards

    function testTrapRowIsDroppedInExtendedPlay() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->startPlayerTurn(ElderOnesUT::OPPONENT);

        $game->queueTrapResponse(ElderOnesUT::OWNER, reason("str", "conquer"));

        $this->assertEquals([], $game->benefitLabels());
    }

    function testTrapRowIsQueuedBeforeIncomeTurn5IsOver() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);

        $game->queueTrapResponse(ElderOnesUT::OWNER, reason("str", "conquer"));

        $this->assertEquals(["140"], $game->benefitLabels());
    }

    // ------------------------------------------------------------ landmarks

    function testLandmarkScores10VPOnlyInExtendedPlay() {
        $game = $this->game;
        $game->startIncomeTurn(ElderOnesUT::OWNER, 4);
        $game->gainLandmarkTriggers(ElderOnesUT::OWNER, 1);
        $this->assertEquals(0, $game->dbGetScore(ElderOnesUT::OWNER), "before income turn 5");

        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $game->gainLandmarkTriggers(ElderOnesUT::OWNER, 2);
        $this->assertEquals(0, $game->dbGetScore(ElderOnesUT::OWNER), "during income turn 5");

        $game->startPlayerTurn(ElderOnesUT::OWNER);
        $game->gainLandmarkTriggers(ElderOnesUT::OWNER, 3);
        $this->assertEquals(ElderOnes::LANDMARK_VP, $game->dbGetScore(ElderOnesUT::OWNER));
    }

    /** Whoever's turn it is: a landmark pushed onto the player by an opponent still scores. */
    function testLandmarkScoresOnAnOpponentsTurn() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->startPlayerTurn(ElderOnesUT::OPPONENT);

        $game->gainLandmarkTriggers(ElderOnesUT::OWNER, 4);

        $this->assertEquals(ElderOnes::LANDMARK_VP, $game->dbGetScore(ElderOnesUT::OWNER));
    }

    function testLandmarkOfAnotherPlayerScoresNothing() {
        $game = $this->game;
        $this->enterExtendedPlay();

        $game->gainLandmarkTriggers(ElderOnesUT::OPPONENT, 5);

        $this->assertEquals(0, $game->dbGetScore(ElderOnesUT::OPPONENT));
    }

    // ------------------------------------------------------------- tapestry

    function testEra4TapestryIsInactiveDuringIncomeTurn5AndActiveAfter() {
        $game = $this->game;
        $card_id = $game->addCard(CARD_TAPESTRY, "era4", ElderOnesUT::OWNER, TAP_ACADEMIA);

        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $this->assertNull($game->isTapestryActive(ElderOnesUT::OWNER, TAP_ACADEMIA), "1.3 applies as for everyone");

        $game->startPlayerTurn(ElderOnesUT::OWNER);
        $active = $game->isTapestryActive(ElderOnesUT::OWNER, TAP_ACADEMIA);
        $this->assertEquals($card_id, $active["card_id"]);
    }

    function testEra4TapestryStaysInactiveForAPlayerWithoutTheCiv() {
        $game = $this->game;
        $game->addCard(CARD_TAPESTRY, "era4", ElderOnesUT::OPPONENT, TAP_ACADEMIA);
        $game->eras[ElderOnesUT::OPPONENT] = 5;
        $game->startPlayerTurn(ElderOnesUT::OPPONENT);

        $this->assertNull($game->isTapestryActive(ElderOnesUT::OPPONENT, TAP_ACADEMIA));
    }

    function testOverplayInExtendedPlayLandsOnEra4AndCoversTheOldCard() {
        $game = $this->game;
        $old_id = $game->addCard(CARD_TAPESTRY, "era4", ElderOnesUT::OWNER, TAP_ACADEMIA);
        $new_id = $game->addCard(CARD_TAPESTRY, "hand", ElderOnesUT::OWNER, TAP_ACADEMIA);
        $this->enterExtendedPlay();
        $game->queueBenefitNormal(64, ElderOnesUT::OWNER, reason("str", "overplay"));

        $game->playTapestryCard($new_id, ElderOnesUT::OWNER);

        $this->assertEquals("era4", $game->getCardInfoById($new_id)["card_location"]);
        $this->assertEquals("era_6", $game->getCardInfoById($old_id)["card_location"]);
        $this->assertEquals($new_id, $game->getLatestTapestry(ElderOnesUT::OWNER, 4)["card_id"]);
    }

    function testOverplayIsStillRefusedDuringIncomeTurn5() {
        $game = $this->game;
        $game->addCard(CARD_TAPESTRY, "era4", ElderOnesUT::OWNER, TAP_ACADEMIA);
        $game->startIncomeTurn(ElderOnesUT::OWNER, 5);
        $game->queueBenefitNormal(64, ElderOnesUT::OWNER, reason("str", "overplay"));

        $game->gamestate->jumpToState(15);
        $game->stTapestryCard();

        $this->assertEquals([], $game->benefitLabels(), "the row is dropped, no card is played");
    }

    // ---------------------------------------------------------- end of game

    function testTheTableWaitsForTheExtendedPlayer() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->eras[ElderOnesUT::OPPONENT] = 6;

        $this->assertEquals([ElderOnesUT::OWNER], array_keys($game->getPlayersInGame()));

        $game->advances = [];
        $game->playerTurn(ElderOnesUT::OWNER);

        $this->assertEquals([], $game->getPlayersInGame(), "the game ends once the last one stops");
    }
}
