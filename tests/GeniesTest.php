<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Genies, the third Fantasies and Futures civilization and the first whose ability is answered by
 * an opponent.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename.
 */
class GeniesUT extends GameUT {
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
        $this->giveCiv(self::OWNER, CIV_GENIES);
        $this->genies()->setupCiv(self::OWNER, "start");
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(2);
    }

    function genies(): Genies {
        return $this->getCivilizationInstance(CIV_GENIES, true);
    }

    function getCurrentEra($player_id) {
        return $this->eras[$player_id] ?? parent::getCurrentEra($player_id);
    }

    function makeZombie(int $player_id): void {
        $players = $this->loadPlayersBasicInfos();
        $players[$player_id]["player_zombie"] = 1;
        $this->_setPlayerBasicInfo($players);
    }

    function useCivAbility(int $player_id): void {
        $this->civTokenAdvance(CIV_GENIES, $player_id, Genies::CHOICE_USE);
    }

    function tokenLocation(int $player_id): string {
        $token = $this->genies()->getTokenOf($player_id);
        return $token ? $token["card_location"] : "";
    }

    function moveToken(int $player_id, int $spot): void {
        $this->dbSetStructureLocation((int) $this->genies()->getTokenOf($player_id)["card_id"], $this->genies()->getCivSlot($spot));
    }
}

final class GeniesTest extends TestCase {
    private GeniesUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): GeniesUT {
        $game = new GeniesUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    private function slot(int $spot): string {
        return "civ_" . CIV_GENIES . "_" . $spot;
    }

    private function circles(): string {
        return "o," . implode(",", [BE_VP_CAPITAL, BE_VP_TILES, BE_VP_TERRITORY, BE_VP_TECH]);
    }

    /**
     * Run the turn 2-4 ability up to the point where it waits on the drawn opponent. The rolls are
     * the token draw first, then whatever the ability rolls next.
     */
    private function summon(array $rolls = [0], int $turn = 2, ?GeniesUT $game = null): void {
        $game = $game ?? $this->game;
        $game->seedRand(...$rolls);
        $game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, $turn);
        $game->useCivAbility(GeniesUT::OWNER);
        $game->resolveBenefit(BE_GENIES_WISH, GeniesUT::OWNER);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_GENIES];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertEquals(range(0, Genies::RING_SPOTS), array_keys($civ["slots"]), "the token pile plus the ring");
    }

    /** A wish needs an opponent to grant it, so the civ is kept out of the solo deck. */
    function testCivIsNotDealtInSoloGames() {
        $this->assertFalse($this->game->civilizations[CIV_GENIES]["automa"]);
    }

    /** Clockwise from the top: odd spots are squared (gain), even spots circled (score). */
    function testRingAlternatesSquaredAndCircledBenefits() {
        $ring = [];
        foreach (range(1, Genies::RING_SPOTS) as $spot) {
            $ring[$spot] = $this->game->genies()->getSlotBenefit($spot);
        }
        $this->assertEquals(
            [
                1 => BE_RESEARCH_NB,
                2 => BE_VP_CAPITAL,
                3 => BE_INVENT,
                4 => BE_VP_TILES,
                5 => BE_GAIN_CULTURE,
                6 => BE_VP_TERRITORY,
                7 => BE_EXPLORE,
                8 => BE_VP_TECH,
            ],
            $ring
        );
        $this->assertEquals([2, 4, 6, 8], $this->game->genies()->getCircleSpots());
    }

    function testSetupCollectsAPlayerTokenFromEachOpponent() {
        $game = $this->newGame(3);
        $owners = [];
        foreach ($game->getStructuresOnCiv(CIV_GENIES) as $token) {
            $owners[] = (int) $token["card_location_arg"];
            $this->assertEquals($this->slot(Genies::PILE), $token["card_location"]);
            $this->assertEquals(CUBE_CIV, (int) $token["card_type_arg"]);
        }
        sort($owners);
        $this->assertEquals([GeniesUT::OPPONENT, GeniesUT::OPPONENT + 1], $owners);
    }

    function testAbilityFiresOnIncomeTurns2To5() {
        foreach ([2, 3, 4, 5] as $turn) {
            $game = $this->newGame();
            $game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, $turn);
            $this->assertEquals(["civ"], $game->benefitLabels(), "income turn $turn");
        }
    }

    function testAbilityDoesNotFireOnIncomeTurn1() {
        $this->game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, 1);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /**
     * Nothing on the mat is picked, the opponent does the picking. The single button still goes
     * through the civ ability state: that is the only place a player holding two income civs is
     * offered the order.
     */
    function testEveryIncomeTurnOffersOneButtonAndNoDecline() {
        foreach ([2, 5] as $era) {
            $this->game->era = $era;
            $args = $this->game->argCivAbilitySingle(GeniesUT::OWNER, CIV_GENIES, ["benefit_data" => ""]);
            $this->assertFalse($args["decline"], "era $era");
            $this->assertEquals([Genies::CHOICE_USE], array_keys($args["slots_choice"]), "era $era");
        }
    }

    function testUsingTheAbilityQueuesTheWish() {
        $this->game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, 2);
        $this->game->useCivAbility(GeniesUT::OWNER);
        $this->assertEquals([(string) BE_GENIES_WISH], $this->game->benefitLabels());
    }

    /** The drawn opponent is handed a plain choice of the 4 circles, tagged so the pick can be told apart. */
    function testWishOffersTheDrawnOpponentTheFourCircles() {
        $this->summon();
        $rows = $this->game->benefitQueue();
        $this->assertCount(1, $rows);
        $this->assertEquals($this->circles(), $rows[0]["benefit_category"]);
        $this->assertEquals(GeniesUT::OPPONENT, (int) $rows[0]["benefit_player_id"]);
        $this->assertEquals(reason_civ(CIV_GENIES, Genies::WISH), $rows[0]["benefit_data"]);
        $this->assertContains('${player_name} draws the player token of ${player_name2}', $this->game->notificationTexts());
    }

    /** The draw is a uniform pick among the tokens in the pile, one index per seeded roll. */
    function testDrawPicksTheSeededToken() {
        foreach ([0 => GeniesUT::OPPONENT, 1 => GeniesUT::OPPONENT + 1] as $roll => $expected) {
            $game = $this->newGame(3);
            $this->summon([$roll], 2, $game);
            $this->assertEquals($expected, (int) $game->benefitQueue()[0]["benefit_player_id"], "roll $roll");
        }
    }

    /** An opponent past income turn 5 cannot answer, so their token is skipped (FORMAL_RULES 5.6). */
    function testFinishedOpponentIsNotDrawn() {
        $game = $this->newGame(3);
        $game->eras[GeniesUT::OPPONENT] = 6;
        $this->summon([0], 2, $game);
        $this->assertEquals(GeniesUT::OPPONENT + 1, (int) $game->benefitQueue()[0]["benefit_player_id"]);
    }

    function testNoOpponentLeftSkipsTheAbility() {
        $this->game->eras[GeniesUT::OPPONENT] = 6;
        $this->summon();
        $this->assertEquals([], $this->game->benefitLabels());
        $this->assertContains('${player_name} has nobody left to grant a wish, the ability is skipped', $this->game->notificationTexts());
    }

    /** A zombie stays in the pile, answers with a random circle and scores nothing (FORMAL_RULES 5.6). */
    function testZombieOpponentDrawnAnswersWithARandomCircle() {
        $this->game->makeZombie(GeniesUT::OPPONENT);
        $this->summon([0, 1]); // draw the only token, then the second circle

        $this->assertEquals($this->slot(4), $this->game->tokenLocation(GeniesUT::OPPONENT));
        $rows = $this->game->benefitQueue();
        $this->assertEquals(
            ["a," . BE_VP_TILES . "," . BE_GENIES_SQUARE, (string) BE_CIV_END],
            $this->game->benefitLabels(),
            "only the owner scores"
        );
        $this->assertEquals(GeniesUT::OWNER, (int) $rows[0]["benefit_player_id"]);
        $this->assertContains('${player_name} is zombie, a random circled benefit is chosen for them', $this->game->notificationTexts());
    }

    /**
     * The owner's other pending rows wait behind the wish, the opponent answers first. The plain row
     * is queued after the civ row is cashed, so it sits at the same level as the wish and only an
     * interrupt puts the wish ahead of it.
     */
    function testWishGoesAheadOfTheOwnersOtherPendingRows() {
        $this->game->giveCiv(GeniesUT::OWNER, CIV_FAEFOLK);
        $this->game->queueEraCivAbility(CIV_FAEFOLK, GeniesUT::OWNER, 2);
        $this->game->seedRand(0);
        $this->game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, 2);
        $this->game->useCivAbility(GeniesUT::OWNER);
        $this->game->queueBenefitNormal(BE_GAIN_CULTURE, GeniesUT::OWNER);
        $this->game->resolveBenefit(BE_GENIES_WISH, GeniesUT::OWNER);

        $this->assertEquals([$this->circles(), (string) BE_GAIN_CULTURE, "civ"], $this->game->benefitLabels());
    }

    /** The opponent's pick moves their token onto the circle, they score it, the owner gets the mirror row. */
    function testOpponentChoicePutsTheirTokenOnTheCircleAndScoresForBoth() {
        $this->summon();
        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OPPONENT);

        $this->assertEquals($this->slot(4), $this->game->tokenLocation(GeniesUT::OPPONENT));
        $this->assertContains('${player_name} places their player token on the chosen circled benefit', $this->game->notificationTexts());
        $rows = $this->game->benefitQueue();
        $this->assertEquals(
            ["a," . BE_VP_TILES . "," . BE_GENIES_SQUARE, (string) BE_CIV_END],
            $this->game->benefitLabels(),
            "the opponent's own score is already resolved"
        );
        $this->assertEquals(GeniesUT::OWNER, (int) $rows[0]["benefit_player_id"]);
        $this->assertEquals(reason_civ(CIV_GENIES), $rows[0]["benefit_data"], "the wish tag does not leak into the mirror");
    }

    /** The token stays on the circle while the owner is still choosing and returns to the pile after. */
    function testTokenReturnsToThePileOnceTheOwnerIsPaid() {
        $this->summon();
        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OPPONENT);
        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OWNER);
        $this->game->resolveBenefit(BE_GENIES_SQUARE, GeniesUT::OWNER);
        $this->game->chooseOption(BE_GAIN_CULTURE, GeniesUT::OWNER);
        $this->assertEquals($this->slot(4), $this->game->tokenLocation(GeniesUT::OPPONENT));

        $this->game->resolveBenefit(BE_CIV_END, GeniesUT::OWNER);
        $this->assertEquals($this->slot(Genies::PILE), $this->game->tokenLocation(GeniesUT::OPPONENT));
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** The mirror is one "either order" row, so the square can come before the score. */
    function testOwnerMayTakeTheSquaredBenefitFirst() {
        $this->summon();
        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OPPONENT);

        $this->game->chooseOption(BE_GENIES_SQUARE, GeniesUT::OWNER);
        $this->assertEquals(
            ["o," . BE_INVENT . "," . BE_GAIN_CULTURE, (string) BE_VP_TILES, (string) BE_CIV_END],
            $this->game->benefitLabels()
        );
    }

    /** The owner's own circle row carries the civ reason too and must not be mistaken for a wish. */
    function testOwnerMayScoreTheCircledBenefitFirst() {
        $this->summon();
        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OPPONENT);

        $this->game->chooseOption(BE_VP_TILES, GeniesUT::OWNER);
        $this->assertEquals([(string) BE_GENIES_SQUARE, (string) BE_CIV_END], $this->game->benefitLabels());

        $this->game->resolveBenefit(BE_GENIES_SQUARE, GeniesUT::OWNER);
        $this->assertEquals(["o," . BE_INVENT . "," . BE_GAIN_CULTURE, (string) BE_CIV_END], $this->game->benefitLabels());
    }

    /** The square is read off the token's spot on the ring, not carried in a reason. */
    function testSquaredBenefitIsChosenBetweenTheTwoNeighboursOfTheCircle() {
        $expected = [
            2 => [BE_RESEARCH_NB, BE_INVENT],
            4 => [BE_INVENT, BE_GAIN_CULTURE],
            6 => [BE_GAIN_CULTURE, BE_EXPLORE],
            8 => [BE_EXPLORE, BE_RESEARCH_NB], // the ring wraps back to the top spot
        ];
        foreach ($expected as $spot => $squares) {
            $game = $this->newGame();
            $game->moveToken(GeniesUT::OPPONENT, $spot);
            $game->queueBenefitNormal(BE_GENIES_SQUARE, GeniesUT::OWNER, reason_civ(CIV_GENIES));
            $game->resolveBenefit(BE_GENIES_SQUARE, GeniesUT::OWNER);
            $this->assertEquals(["o," . implode(",", $squares)], $game->benefitLabels(), "circle $spot");
        }
    }

    function testSquaredBenefitWithNoTokenOnTheRingIsAnError() {
        $this->game->queueBenefitNormal(BE_GENIES_SQUARE, GeniesUT::OWNER, reason_civ(CIV_GENIES));
        $this->expectOutputRegex("/Internal Error during move 0: ERR:Genies:20/");
        $this->expectException(BgaUserException::class);
        $this->expectExceptionMessage("ERR:Genies:20");
        $this->game->resolveBenefit(BE_GENIES_SQUARE, GeniesUT::OWNER);
    }

    /** Drawing with replacement: the token of the previous wish is back in the pile before the draw. */
    function testTokensReturnToThePileBeforeTheNextDraw() {
        $this->game->moveToken(GeniesUT::OPPONENT, 6);
        $this->summon();
        $this->assertEquals($this->slot(Genies::PILE), $this->game->tokenLocation(GeniesUT::OPPONENT));
    }

    /**
     * zombieTurn drops every row of the quitter, and the wish row is theirs. The civ answers for
     * them first, so the owner still gets the mirror (FORMAL_RULES 5.6).
     */
    function testOpponentQuittingAtThePromptGetsARandomCircle() {
        $this->summon();
        $this->game->makeZombie(GeniesUT::OPPONENT);
        $this->game->seedRand(2); // the third circle
        $this->game->gamestate->jumpToState(19);

        $this->game->zombieTurn(null, GeniesUT::OPPONENT);

        $this->assertEquals($this->slot(6), $this->game->tokenLocation(GeniesUT::OPPONENT));
        $rows = $this->game->benefitQueue();
        $this->assertEquals(
            ["a," . BE_VP_TERRITORY . "," . BE_GENIES_SQUARE, (string) BE_CIV_END],
            $this->game->benefitLabels(),
            "only the owner's row survives"
        );
        $this->assertEquals(GeniesUT::OWNER, (int) $rows[0]["benefit_player_id"]);
        $this->assertEquals(18, $this->game->gamestate->state()["id"]);
    }

    function testIncomeTurn5ScoresTwoDifferentCircles() {
        $this->game->era = 5;
        $this->game->queueEraCivAbility(CIV_GENIES, GeniesUT::OWNER, 5);
        $this->game->useCivAbility(GeniesUT::OWNER);
        $rows = $this->game->benefitQueue();
        $this->assertEquals([$this->circles()], $this->game->benefitLabels());
        $this->assertEquals([2, GeniesUT::OWNER], [(int) $rows[0]["benefit_quantity"], (int) $rows[0]["benefit_player_id"]]);

        $this->game->chooseOption(BE_VP_TERRITORY, GeniesUT::OWNER);
        $this->assertEquals(
            ["o," . BE_VP_CAPITAL . "," . BE_VP_TILES . "," . BE_VP_TECH],
            $this->game->benefitLabels(),
            "the circle already scored is not offered again"
        );
    }
}
