<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Infiltrators. On income turns 2-5 the player either shares a territory with a lone opponent
 * outpost (benefit 170) or plants a player token on an opponent's capital city territory for a VP
 * per outpost that opponent has not placed (benefit 171), plus a civilization for the third token
 * on the same territory.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename.
 */
class InfiltratorsUT extends GameUT {
    const OWNER = 11;
    const OPPONENT = 12;
    const OTHER = 13;

    /** A different capital per player, so every start hex is a different territory. */
    const CAPITALS = [self::OWNER => 2, self::OPPONENT => 1, self::OTHER => 3];

    function __construct(int $players = 3) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::OWNER, self::OWNER + $players - 1), []));
        $this->curid = self::OWNER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
        $this->giveCiv(self::OWNER, CIV_INFILTRATORS);
        foreach (self::CAPITALS as $player_id => $capital) {
            if ($player_id < self::OWNER + $players) {
                $this->addCard(CARD_CAPITAL, "hand", $player_id, $capital);
            }
        }
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(2);
    }

    function infiltrators(): Infiltrators {
        return $this->getCivilizationInstance(CIV_INFILTRATORS, true);
    }

    function startHex(int $player_id): string {
        return $this->getStartingPosition($player_id)["location"];
    }

    function giveOutposts(int $player_id, int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->dbAddStructure($player_id, BUILDING_OUTPOST, 0, "hand");
        }
    }

    function useCivAbility(int $spot, int $player_id = self::OWNER): void {
        $this->civTokenAdvance(CIV_INFILTRATORS, $player_id, $spot);
    }

    /** Cubes of $player_id sitting on a territory, which is what a planted token looks like. */
    function tokensOn(int $player_id, string $location): array {
        return $this->getStructuresSearch(BUILDING_CUBE, null, $location, $player_id);
    }
}

final class InfiltratorsTest extends TestCase {
    private InfiltratorsUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 3, int $variant = 8): InfiltratorsUT {
        $game = new InfiltratorsUT($players);
        $game->init();
        $game->doAdjustMaterial($players, $variant);
        $game->setGameStateValue("variant_adjustments", $variant); // isAdjustments8() reads it back
        return $game;
    }

    /** What the client is offered, through the game wide dispatch rather than the civ directly. */
    private function args(string $condition = ""): array {
        return $this->game->argCivAbilitySingle(InfiltratorsUT::OWNER, CIV_INFILTRATORS, ["benefit_data" => $condition]);
    }

    private function choiceFor(int $opponent_id, string $condition = ""): array {
        $slots = $this->args($condition)["slots_choice"];
        $found = array_filter($slots, fn($slot) => array_get($slot, "player_id") == $opponent_id);
        $this->assertCount(1, $found, "exactly one choice targets $opponent_id");
        return reset($found);
    }

    private function scoredVP(): int {
        $vp = 0;
        foreach ($this->game->notificationsOfType("VP") as $notif) {
            $vp += (int) $notif["args"]["increase"];
        }
        return $vp;
    }

    // ------------------------------------------------------------- the offer

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_INFILTRATORS];
        $this->assertEquals("PP", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5], $civ["income_trigger"]);
        $this->assertEquals([["benefit" => [170]], ["benefit" => [171]]], $civ["slots"]);
    }

    /** Slot 0 stays the outpost option, and every opponent is appended as a token target. */
    function testOneTokenChoicePerOpponentPlusTheOutpostOption() {
        $slots = $this->args()["slots_choice"];

        $this->assertEquals([0, 1, 2], array_keys($slots));
        $this->assertEquals([170], $slots[0]["benefit"]);
        $this->assertEquals([InfiltratorsUT::OPPONENT, InfiltratorsUT::OTHER], [$slots[1]["player_id"], $slots[2]["player_id"]]);
    }

    /** The VP on offer is one per outpost the opponent still has off the board. */
    function testTheOfferCountsTheOpponentsUnplacedOutposts() {
        $this->game->giveOutposts(InfiltratorsUT::OPPONENT, 4);
        $this->game->giveOutposts(InfiltratorsUT::OTHER, 1);

        $this->assertEquals(4, $this->choiceFor(InfiltratorsUT::OPPONENT)["count"]);
        $this->assertEquals(1, $this->choiceFor(InfiltratorsUT::OTHER)["count"]);
    }

    function testTheThirdTokenOnACapitalAlsoGainsACivilization() {
        $hex = $this->game->startHex(InfiltratorsUT::OPPONENT);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);

        $choice = $this->choiceFor(InfiltratorsUT::OPPONENT);
        $this->assertEquals(2, $choice["cubes"]);
        $this->assertEquals([171, [BE_GAIN_CIV]], $choice["benefit"]);
        $this->assertStringContainsString("Civilization", $choice["title"]);
    }

    function testAFourthTokenGainsNoFurtherCivilization() {
        $hex = $this->game->startHex(InfiltratorsUT::OPPONENT);
        for ($i = 0; $i < 3; $i++) {
            $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);
        }

        $this->assertEquals([171], $this->choiceFor(InfiltratorsUT::OPPONENT)["benefit"]);
    }

    /**
     * Player tokens are indistinguishable on the table, so one of yours that another civilization
     * left on that territory counts toward the third as well (FORMAL_RULES CIV.INFILTRATORS.1). An ISOLATIONISTS
     * token is a CUBE_CIV of yours sitting on a territory you conquered.
     */
    function testATokenLeftByAnotherCivilizationCountsTowardTheThird() {
        $hex = $this->game->startHex(InfiltratorsUT::OPPONENT);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex, CUBE_CIV);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);

        $choice = $this->choiceFor(InfiltratorsUT::OPPONENT);
        $this->assertEquals(2, $choice["cubes"]);
        $this->assertEquals([171, [BE_GAIN_CIV]], $choice["benefit"]);
    }

    /**
     * The tokens are counted per territory. Two on one opponent's capital say nothing about the
     * next opponent's, whose own count is still zero.
     */
    function testTokensOnOneCapitalDoNotCountTowardAnother() {
        $hex = $this->game->startHex(InfiltratorsUT::OPPONENT);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);

        $this->assertEquals(0, $this->choiceFor(InfiltratorsUT::OTHER)["cubes"]);
        $this->assertEquals([171], $this->choiceFor(InfiltratorsUT::OTHER)["benefit"]);
    }

    /**
     * Only the Infiltrators player's own cubes are tokens of theirs. Cubes another player put on
     * that same territory must not push the count to the third token bonus.
     */
    function testCubesOfOtherPlayersOnTheCapitalDoNotCount() {
        $hex = $this->game->startHex(InfiltratorsUT::OPPONENT);
        $this->game->addCubeAt(InfiltratorsUT::OPPONENT, $hex);
        $this->game->addCubeAt(InfiltratorsUT::OTHER, $hex);
        $this->game->addCubeAt(InfiltratorsUT::OWNER, $hex);

        $choice = $this->choiceFor(InfiltratorsUT::OPPONENT);
        $this->assertEquals(1, $choice["cubes"]);
        $this->assertEquals([171], $choice["benefit"]);
    }

    /** Midgame entry only hands out a token, so the outpost option is dropped and no VP is named. */
    function testMidgameOffersOnlyTheOpponents() {
        $slots = $this->args("midgame")["slots_choice"];

        $this->assertEquals([1, 2], array_keys($slots));
        $this->assertStringNotContainsString("VP", $slots[1]["title"]);
    }

    // ---------------------------------------------------------- the ability

    /** The whole income turn: choose an opponent, plant the token, score the outposts they hold. */
    function testPlantingATokenScoresTheOpponentsUnplacedOutposts() {
        $game = $this->game;
        $game->giveOutposts(InfiltratorsUT::OPPONENT, 3);
        $game->queueEraCivAbility(CIV_INFILTRATORS, InfiltratorsUT::OWNER, 2);
        $game->useCivAbility(1);
        $game->resolveBenefit(171, InfiltratorsUT::OWNER);

        $tokens = $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OPPONENT));
        $this->assertCount(1, $tokens);
        $this->assertEquals(1, reset($tokens)["card_type_arg"], "placed without taking the territory");
        $this->assertEquals(3, $this->scoredVP());
    }

    function testPlantingATokenTakesNoOutpostFromTheOpponent() {
        $game = $this->game;
        $game->giveOutposts(InfiltratorsUT::OPPONENT, 2);
        $game->queueEraCivAbility(CIV_INFILTRATORS, InfiltratorsUT::OWNER, 2);
        $game->useCivAbility(1);
        $game->resolveBenefit(171, InfiltratorsUT::OWNER);

        $this->assertCount(2, $game->getOutpostsInHand(InfiltratorsUT::OPPONENT));
    }

    /** The outpost option hands off to the structure placement state and stays on the stack. */
    function testTheOutpostOptionWaitsForAPlacement() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_INFILTRATORS, InfiltratorsUT::OWNER, 2);
        $game->useCivAbility(0);

        $this->assertEquals(["170"], $game->benefitLabels());
        $game->resolveBenefit(170, InfiltratorsUT::OWNER);
        $this->assertEquals(38, $game->gamestate->state()["id"], "moveStructureOnto");
        $this->assertEquals(["170"], $game->benefitLabels(), "not cashed until the outpost is placed");
    }

    /** Midgame is the token alone: it is placed, and nothing is queued to score for it. */
    function testMidgamePlacementScoresNothing() {
        $game = $this->game;
        $game->giveOutposts(InfiltratorsUT::OPPONENT, 3);
        $game->benefitCivEntry(CIV_INFILTRATORS, InfiltratorsUT::OWNER, "midgame");
        $game->useCivAbility(1);

        $this->assertCount(1, $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OPPONENT)));
        $this->assertEquals([], $game->benefitLabels());
        $this->assertEquals(0, $this->scoredVP());
    }

    /** The ability is not offered outside income turns 2-5. */
    function testTheAbilityIsNotOfferedInEraSix() {
        $game = $this->game;
        $game->queueEraCivAbility(CIV_INFILTRATORS, InfiltratorsUT::OWNER, 6);

        $this->assertEquals([], $game->benefitLabels());
    }

    // ------------------------------------------------------------ setup and gain

    /** Adjustment pack 8 starts the civ with a token already on every opponent's capital. */
    function testAdjustments8SetupPlantsATokenOnEveryOpponent() {
        $game = $this->game;
        $game->infiltrators()->setupCiv(InfiltratorsUT::OWNER, "start");

        $this->assertCount(1, $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OPPONENT)));
        $this->assertCount(1, $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OTHER)));
        $this->assertCount(0, $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OWNER)));
    }

    /** Gained mid game under pack 8, the starting tokens come with one more to give away. */
    function testGainingTheCivMidGameQueuesAnExtraToken() {
        $game = $this->game;
        $game->infiltrators()->setupCiv(InfiltratorsUT::OWNER, "");

        $this->assertEquals(["civ"], $game->benefitLabels());
        $this->assertEquals("midgame", $game->benefitQueue()[0]["benefit_data"]);
    }

    function testSetupPlantsNothingWithoutTheAdjustmentPack() {
        $game = $this->newGame(3, 2);
        $game->infiltrators()->setupCiv(InfiltratorsUT::OWNER, "start");

        $this->assertCount(0, $game->tokensOn(InfiltratorsUT::OWNER, $game->startHex(InfiltratorsUT::OPPONENT)));
        $this->assertEquals([], $game->benefitLabels());
    }

    /** Every civilization the player gains is drawn 3, kept 1 instead of dealt off the top. */
    function testGainingACivilizationDrawsThreeAndKeepsOne() {
        $game = $this->game;
        $game->awardBenefits(InfiltratorsUT::OWNER, BE_GAIN_CIV);

        $this->assertEquals(["172"], $game->benefitLabels());
    }

    function testAnotherPlayerGainsACivilizationTheNormalWay() {
        $game = $this->game;
        $game->awardBenefits(InfiltratorsUT::OPPONENT, BE_GAIN_CIV);

        $this->assertEquals([], $game->benefitLabels());
    }

    // ------------------------------------------------------------- assertions

    function testAssertsForAPlayerWithoutTheCiv() {
        $this->assertAsserts("ERR:Infiltrators:12", fn() => $this->game->infiltrators()->awardBenefits(InfiltratorsUT::OPPONENT, 171));
    }

    function testAssertsOnABenefitItDoesNotOwn() {
        $this->assertAsserts("ERR:Infiltrators:10", fn() => $this->game->infiltrators()->awardBenefits(InfiltratorsUT::OWNER, 1));
    }

    function testAssertsOnASlotItWasNotOffered() {
        $this->assertAsserts("ERR:Infiltrators:01", fn() => $this->game->infiltrators()->moveCivCube(InfiltratorsUT::OWNER, 9, "", []));
    }

    /** systemAssertTrue echoes the diagnostic before throwing, so swallow the output. */
    private function assertAsserts(string $code, callable $fn): void {
        try {
            ob_start();
            $fn();
            ob_end_clean();
            $this->fail("expected $code");
        } catch (BgaUserException $e) {
            ob_end_clean();
            $this->assertStringContainsString($code, $e->getMessage());
        }
    }
}
