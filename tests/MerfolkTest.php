<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Merfolk, the sixth Fantasies and Futures civilization: a card and a submerge on income turns 2-4,
 * a card, the return of the submerged ones and a cull on income turn 5, then extended play where
 * every turn spends tapestry cards until the hand is empty.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename.
 */
class MerfolkUT extends GameUT {
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
        $this->giveCiv(self::OWNER, CIV_MERFOLK);
        $this->getCivilizationInstance(CIV_MERFOLK, true); // includes civs/Merfolk.php
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

    /** Cards for awardCard to draw; with an empty deck the draw is announced as void instead. */
    function fillDeck(int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->addCard(CARD_TAPESTRY, "deck_tapestry", 0, TAP_ACADEMIA);
        }
    }

    function giveTapestryCards(int $player_id, int $count): array {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->addCard(CARD_TAPESTRY, "hand", $player_id, TAP_ACADEMIA);
        }
        return $ids;
    }

    function handIds(int $player_id): array {
        return array_keys($this->getCardsInHand($player_id, CARD_TAPESTRY));
    }

    function submergedIds(int $player_id): array {
        return array_keys($this->getCardsSearch(CARD_TAPESTRY, null, "submerged", $player_id));
    }

    /** The income row and the benefit manager resolving it, the way a real income turn reaches it. */
    function useIncomeAbility(int $player_id, int $turn): void {
        $this->startIncomeTurn($player_id, $turn);
        $this->queueEraCivAbility(CIV_MERFOLK, $player_id, $turn);
        $this->resolveBenefit($turn == 5 ? BE_MERFOLK_SURFACE : BE_MERFOLK_DIVE, $player_id);
    }

    function useCivAbility(int $player_id, int $spot, $extra = ""): void {
        $this->civTokenAdvance(CIV_MERFOLK, $player_id, $spot, $extra);
    }

    function playerTurn(int $player_id): void {
        $this->startPlayerTurn($player_id);
        $this->gamestate->jumpToState(13);
        $this->stPlayerTurn();
    }

    /** The pending civ row as the civ ability state hands it to the client. */
    function civArgs(int $player_id): array {
        $benefit = $this->getCurrentBenefit(CIV_MERFOLK, "civ");
        return $this->argCivAbilitySingle($player_id, CIV_MERFOLK, $benefit);
    }

    function hiddenNotifications(string $channel): array {
        return array_values(array_filter($this->notificationsOfType("moveCardsHidden"), fn($notif) => $notif["channel"] == $channel));
    }
}

final class MerfolkTest extends TestCase {
    private MerfolkUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): MerfolkUT {
        $game = new MerfolkUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    /** The state where the player has finished income turn 5 and keeps taking turns. */
    private function enterExtendedPlay(int $player_id = MerfolkUT::OWNER): void {
        $this->game->eras[$player_id] = 5;
        $this->game->startPlayerTurn($player_id);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_MERFOLK];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertFalse($civ["automa"], "no bot plays a hidden hand");
        $this->assertArrayNotHasKey("slots", $civ, "nothing on the mat is clicked");
    }

    // -------------------------------------------------------- income turns 2-4

    function testIncomeTurnQueuesTheDiveRow() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_MERFOLK, MerfolkUT::OWNER, 3);
        $this->assertEquals([(string) BE_MERFOLK_DIVE], $game->benefitLabels());
    }

    function testAbilityIsNotOfferedOutsideItsIncomeTurns() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_MERFOLK, MerfolkUT::OWNER, 1);
        $this->assertEquals([], $game->benefitLabels());
    }

    function testDiveGainsACardThenPromptsToSubmerge() {
        $game = $this->game;
        $game->fillDeck(1);
        $game->giveTapestryCards(MerfolkUT::OWNER, 4);

        $game->useIncomeAbility(MerfolkUT::OWNER, 2);

        $this->assertEquals(5, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY), "the drawn card joins the hand");
        $this->assertEquals(Merfolk::PHASE_SUBMERGE, $game->civArgs(MerfolkUT::OWNER)["phase"]);
    }

    function testDiveWithTwoCardsLeftPromptsNothing() {
        $game = $this->game;
        $game->fillDeck(1);
        $game->giveTapestryCards(MerfolkUT::OWNER, 1);

        $game->useIncomeAbility(MerfolkUT::OWNER, 2);

        $this->assertEquals(2, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY));
        $this->assertEquals([], $game->benefitLabels(), "nothing to submerge, no prompt");
    }

    function testSubmergeKeepsTheTwoSelectedCards() {
        $game = $this->game;
        $game->fillDeck(1);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->useIncomeAbility(MerfolkUT::OWNER, 2);

        $keep = array_slice($cards, 0, 2);
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_SELECT, $keep);

        $this->assertEquals($keep, $game->handIds(MerfolkUT::OWNER));
        $this->assertEquals(3, count($game->submergedIds(MerfolkUT::OWNER)), "the other 2 plus the drawn one");
    }

    /** The prompt answers the row being resolved, so it goes ahead of the rest of the income turn. */
    function testSubmergePromptJumpsTheQueue() {
        $game = $this->game;
        $game->fillDeck(1);
        $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->startIncomeTurn(MerfolkUT::OWNER, 2);
        $game->queueEraCivAbility(CIV_MERFOLK, MerfolkUT::OWNER, 2);
        $game->queueBenefitNormal(BE_CONFIRM, MerfolkUT::OWNER, reason("str", "income"));

        $game->resolveBenefit(BE_MERFOLK_DIVE, MerfolkUT::OWNER);

        $this->assertEquals(["civ", (string) BE_CONFIRM], $game->benefitLabels());
    }

    function testSubmergeRefusesAWrongNumberOfCards() {
        $game = $this->game;
        $game->fillDeck(1);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->useIncomeAbility(MerfolkUT::OWNER, 2);

        $this->expectException(BgaUserException::class);
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_SELECT, array_slice($cards, 0, 3));
    }

    function testSubmergeRefusesACardOutsideTheHand() {
        $game = $this->game;
        $game->fillDeck(1);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $other = $game->giveTapestryCards(MerfolkUT::OPPONENT, 1);
        $game->useIncomeAbility(MerfolkUT::OWNER, 2);

        $this->expectException(BgaUserException::class);
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_SELECT, [$cards[0], $other[0]]);
    }

    /** The whole point of the zone: nothing that reads the hand can see what is under the mat. */
    function testSubmergedCardsAreNotInHand() {
        $game = $this->game;
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 5);
        $game->moveCardsHidden(array_slice($cards, 2), MerfolkUT::OWNER, "submerged", "");

        $this->assertEquals(2, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY));
        $this->assertEquals(array_slice($cards, 0, 2), $game->handIds(MerfolkUT::OWNER));
        $this->assertEquals([], $game->getCardsInHand(MerfolkUT::OWNER, CARD_TAPESTRY, null, array_slice($cards, 2)));
    }

    function testSubmergedCardsReachOpponentsFaceDown() {
        $game = $this->game;
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 3);
        $game->moveCardsHidden($cards, MerfolkUT::OWNER, "submerged", "");

        $public = $game->hiddenNotifications("broadcast");
        $this->assertCount(1, $public);
        $this->assertEquals(3, $public[0]["args"]["count"]);
        foreach ($public[0]["args"]["cards"] as $card) {
            $this->assertEquals(0, $card["card_type_arg"], "the face is stripped off");
            $this->assertEquals("submerged", $card["card_location"]);
        }

        $private = $game->hiddenNotifications("player");
        $this->assertCount(1, $private);
        $this->assertEquals(MerfolkUT::OWNER, $private[0]["player_id"]);
        foreach ($private[0]["args"]["cards"] as $card) {
            $this->assertEquals(TAP_ACADEMIA, $card["card_type_arg"], "the owner sees the faces");
        }
    }

    // ---------------------------------------------------------- income turn 5

    function testIncomeTurn5QueuesTheSurfaceRow() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_MERFOLK, MerfolkUT::OWNER, 5);
        $this->assertEquals([(string) BE_MERFOLK_SURFACE], $game->benefitLabels());
    }

    function testSurfaceGainsACardAndReturnsTheSubmergedOnes() {
        $game = $this->game;
        $game->fillDeck(1);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->moveCardsHidden(array_slice($cards, 2), MerfolkUT::OWNER, "submerged", "");

        $game->useIncomeAbility(MerfolkUT::OWNER, 5);

        $this->assertEquals(5, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY), "4 held plus the drawn one");
        $this->assertEquals([], $game->submergedIds(MerfolkUT::OWNER));
    }

    /** Returning is not gaining, so only the single drawn card announces a gain (FORMAL_RULES 5.19). */
    function testReturningTheSubmergedCardsIsNotAGain() {
        $game = $this->game;
        $game->fillDeck(1);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->moveCardsHidden(array_slice($cards, 2), MerfolkUT::OWNER, "submerged", "");

        $game->useIncomeAbility(MerfolkUT::OWNER, 5);

        $this->assertCount(1, $game->notificationsOfType("newCards"), "one draw, not three returns");
    }

    function testSurfaceWithNothingSubmergedStillGainsACard() {
        $game = $this->game;
        $game->fillDeck(1);

        $game->useIncomeAbility(MerfolkUT::OWNER, 5);

        $this->assertEquals(1, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY));
        $this->assertEquals([], $game->benefitLabels());
    }

    function testEndOfIncomeQueuesTheCullOnTurn5Only() {
        $game = $this->game;
        $game->queueEndOfIncomeCivAbilities(MerfolkUT::OWNER, 4);
        $this->assertEquals([], $game->benefitLabels());

        $game->queueEndOfIncomeCivAbilities(MerfolkUT::OWNER, 5);
        $this->assertEquals([(string) BE_MERFOLK_CULL], $game->benefitLabels());
    }

    /** The cull has to land before the confirm row, so it stays inside the undo window. */
    function testCullIsQueuedBeforeTheConfirmRow() {
        $game = $this->game;
        $game->startIncomeTurn(MerfolkUT::OWNER, 5);

        $game->queueIncomeTurn();

        $labels = $game->benefitLabels();
        $cull = array_search((string) BE_MERFOLK_CULL, $labels, true);
        $confirm = array_search((string) BE_CONFIRM, $labels, true);
        $this->assertNotFalse($cull);
        $this->assertNotFalse($confirm);
        $this->assertLessThan($confirm, $cull);
    }

    function testCullPromptsAboveFiveCards() {
        $game = $this->game;
        $game->startIncomeTurn(MerfolkUT::OWNER, 5);
        $game->giveTapestryCards(MerfolkUT::OWNER, 7);
        $game->queueEndOfIncomeCivAbilities(MerfolkUT::OWNER, 5);

        $game->resolveBenefit(BE_MERFOLK_CULL, MerfolkUT::OWNER);

        $this->assertEquals(Merfolk::PHASE_KEEP, $game->civArgs(MerfolkUT::OWNER)["phase"]);
    }

    function testCullDoesNotPromptAtFiveCards() {
        $game = $this->game;
        $game->startIncomeTurn(MerfolkUT::OWNER, 5);
        $game->giveTapestryCards(MerfolkUT::OWNER, 5);
        $game->queueEndOfIncomeCivAbilities(MerfolkUT::OWNER, 5);

        $game->resolveBenefit(BE_MERFOLK_CULL, MerfolkUT::OWNER);

        $this->assertEquals([], $game->benefitLabels());
    }

    function testCullDiscardsEverythingBeyondTheFiveKept() {
        $game = $this->game;
        $game->startIncomeTurn(MerfolkUT::OWNER, 5);
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 7);
        $game->queueEndOfIncomeCivAbilities(MerfolkUT::OWNER, 5);
        $game->resolveBenefit(BE_MERFOLK_CULL, MerfolkUT::OWNER);

        $keep = array_slice($cards, 0, 5);
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_SELECT, $keep);

        $this->assertEquals($keep, $game->handIds(MerfolkUT::OWNER));
        $this->assertCount(2, $game->getCardsSearch(CARD_TAPESTRY, null, "discard"));
    }

    function testIncomeTurn5DoesNotFinishThePlayer() {
        $game = $this->game;
        $game->startIncomeTurn(MerfolkUT::OWNER, 5);

        $game->effect_endOfIncome(MerfolkUT::OWNER);

        $this->assertEquals(5, $game->getCurrentEra(MerfolkUT::OWNER), "the era stays 5");
        $this->assertEquals([], $game->finalScored, "final scoring is deferred");
        $this->assertTrue($game->isPlayerAlive(MerfolkUT::OWNER));
    }

    // --------------------------------------------------------- extended turns

    function testExtendedTurnPromptsForTheTurnChoice() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 3);
        $game->eras[MerfolkUT::OWNER] = 5;

        $game->playerTurn(MerfolkUT::OWNER);

        $this->assertEquals(Merfolk::PHASE_TURN, $game->civArgs(MerfolkUT::OWNER)["phase"]);
        $this->assertEquals([], $game->finalScored);
    }

    function testExtendedTurnDiscardScoresFiveVPPerCard() {
        $game = $this->game;
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 4);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);

        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_DISCARD, array_slice($cards, 0, 3));

        $this->assertEquals(3 * Merfolk::DISCARD_VP, $game->dbGetScore(MerfolkUT::OWNER));
        $this->assertEquals(1, $game->getCardCountInHand(MerfolkUT::OWNER, CARD_TAPESTRY));
    }

    function testExtendedTurnRefusesAnEmptyDiscard() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 2);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);

        $this->expectException(BgaUserException::class);
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_DISCARD, "");
    }

    function testPlayOptionIsOfferedOnlyOverAnEra4Card() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 2);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);
        $this->assertArrayNotHasKey(Merfolk::CHOICE_PLAY, $game->civArgs(MerfolkUT::OWNER)["slots_choice"], "no card to cover");

        $game->addCard(CARD_TAPESTRY, "era4", MerfolkUT::OWNER, TAP_ACADEMIA);
        $this->assertArrayHasKey(Merfolk::CHOICE_PLAY, $game->civArgs(MerfolkUT::OWNER)["slots_choice"]);
    }

    function testPlayOptionQueuesTheOverplay() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 2);
        $game->addCard(CARD_TAPESTRY, "era4", MerfolkUT::OWNER, TAP_ACADEMIA);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);

        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_PLAY);

        $this->assertEquals(["64"], $game->benefitLabels());
        $this->assertEquals(4, $game->getTapestryEra(MerfolkUT::OWNER), "the overplay lands on era 4");
    }

    function testEmptyHandFinishesThePlayerExactlyOnce() {
        $game = $this->game;
        $game->eras[MerfolkUT::OWNER] = 5;

        $game->playerTurn(MerfolkUT::OWNER);

        $this->assertEquals([MerfolkUT::OWNER], $game->finalScored);
        $this->assertEquals(6, $game->getCurrentEra(MerfolkUT::OWNER));
        $this->assertFalse($game->isExtendedPlay(MerfolkUT::OWNER));

        $game->playerTurn(MerfolkUT::OWNER);
        $this->assertEquals([MerfolkUT::OWNER], $game->finalScored, "not scored a second time");
    }

    /** Advance turns are gone: the civ takes every extended turn over, affordable advance or not. */
    function testAnAffordableAdvanceDoesNotKeepTheTurnOpen() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 1);
        $game->advances = ["1_5" => 1];
        $game->eras[MerfolkUT::OWNER] = 5;

        $game->playerTurn(MerfolkUT::OWNER);

        $this->assertEquals(Merfolk::PHASE_TURN, $game->civArgs(MerfolkUT::OWNER)["phase"]);
    }

    // -------------------------------------------------------- response cards

    /** The card says the defender keeps their response cards (FORMAL_RULES 5.23). */
    function testTrapRowIsStillOfferedInExtendedPlay() {
        $game = $this->game;
        $this->enterExtendedPlay();
        $game->startPlayerTurn(MerfolkUT::OPPONENT);

        $game->queueTrapResponse(MerfolkUT::OWNER, reason("str", "conquer"));

        $this->assertEquals(["140"], $game->benefitLabels());
    }

    function testTrapDiscardCanEmptyTheHandAndEndTheGameNextTurn() {
        $game = $this->game;
        $cards = $game->giveTapestryCards(MerfolkUT::OWNER, 1);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->effect_discardCard($cards, MerfolkUT::OWNER);

        $game->playerTurn(MerfolkUT::OWNER);

        $this->assertEquals([MerfolkUT::OWNER], $game->finalScored);
    }

    // -------------------------------------------------------- other civs

    function testTwoExtendedPlayCivsAtOneTableEndIndependently() {
        $game = $this->game;
        $game->giveCiv(MerfolkUT::OPPONENT, CIV_ELDER_ONES);
        $game->getCivilizationInstance(CIV_ELDER_ONES, true);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->eras[MerfolkUT::OPPONENT] = 5;
        $game->advances = ["1_5" => 1];

        $game->playerTurn(MerfolkUT::OWNER);
        $this->assertEquals([MerfolkUT::OWNER], $game->finalScored, "the empty hand ends the Merfolk game");

        $game->playerTurn(MerfolkUT::OPPONENT);
        $this->assertEquals([MerfolkUT::OWNER], $game->finalScored, "the Elder Ones player can still advance");

        $game->advances = [];
        $game->playerTurn(MerfolkUT::OPPONENT);
        $this->assertEquals([MerfolkUT::OWNER, MerfolkUT::OPPONENT], $game->finalScored);
    }

    /** Not supported, but a read on every getTapestryEra cannot throw: the first civ found decides. */
    function testTwoExtendedPlayCivsOnOnePlayerPickTheFirst() {
        $game = $this->game;
        $game->giveCiv(MerfolkUT::OWNER, CIV_ELDER_ONES);
        $game->getCivilizationInstance(CIV_ELDER_ONES, true);

        $this->expectOutputRegex("/ERR:game:03 two extended play civs of player 11, 46 decides/");
        $this->assertEquals(CIV_MERFOLK, $game->getExtendedPlayCiv(MerfolkUT::OWNER)->getType());
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->startPlayerTurn(MerfolkUT::OWNER);
        $this->assertEquals(4, $game->getTapestryEra(MerfolkUT::OWNER), "still readable");
    }

    function testMandatoryAbilityCannotBeDeclined() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 3);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);
        $game->gamestate->jumpToState(14);

        $this->expectException(BgaUserException::class);
        $game->action_civDecline(CIV_MERFOLK);
    }

    /** The overplay is void with no era 4 card, and it would burn the mandatory turn. */
    function testPlayOptionIsRefusedWithoutAnEra4Card() {
        $game = $this->game;
        $game->giveTapestryCards(MerfolkUT::OWNER, 2);
        $game->eras[MerfolkUT::OWNER] = 5;
        $game->playerTurn(MerfolkUT::OWNER);

        $this->expectOutputRegex("/Internal Error during move 0: ERR:Merfolk:21/");
        $this->expectExceptionMessage("ERR:Merfolk:21");
        $game->useCivAbility(MerfolkUT::OWNER, Merfolk::CHOICE_PLAY);
    }
}
