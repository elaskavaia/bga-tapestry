<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Werefolk, the second Fantasies and Futures civilization.
 *
 * The coin flip is the first use of the seeded bgaRand in GameUT, so every test states the face it
 * wants. An unseeded roll writes a warning to stderr rather than passing by luck.
 */
class WerefolkUT extends GameUT {
    function __construct() {
        parent::__construct();
        $this->era = 2;
        $this->giveCiv(1, CIV_WEREFOLK);
    }

    function putCubeOnTrack(int $track, int $spot, int $type_arg = CUBE_NORMAL): int {
        return $this->addCubeAt(1, "tech_spot_{$track}_{$spot}", $type_arg);
    }

    function putTileOnMat(): int {
        return $this->addCard(CARD_SPACE, "civilization_" . CIV_WEREFOLK);
    }

    function useCivAbility(int $spot, int $player_id = 1): void {
        $this->civTokenAdvance(CIV_WEREFOLK, $player_id, $spot);
    }
}

final class WerefolkTest extends TestCase {
    private WerefolkUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(): WerefolkUT {
        $game = new WerefolkUT();
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $game->getCivilizationInstance(CIV_WEREFOLK, true); // load the class so Werefolk:: constants resolve in single-method runs
        return $game;
    }

    private function werefolk(): Werefolk {
        return $this->game->getCivilizationInstance(CIV_WEREFOLK, true);
    }

    /** Run the ability up to the point where it waits on the player. */
    private function useAbility(int $face, int $turn = 2, ?WerefolkUT $game = null): void {
        $game = $game ?? $this->game;
        $game->addCard(CARD_SPACE, "deck_space");
        $game->seedRand($face);
        $game->queueEraCivAbility(CIV_WEREFOLK, 1, $turn);
        $game->useCivAbility(Werefolk::CHOICE_FLIP);
        $game->resolveBenefit(BE_WEREFOLK_FLIP);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_WEREFOLK];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertArrayNotHasKey("slots", $civ, "the mat holds a space tile, not cubes on slots");
    }

    function testTheFourNewAdvanceRowsGrantBenefitAndFreeBonus() {
        $expected = [
            BE_ADVANCE_EXPLORATION_BENEFIT_FREEBONUS => 1,
            BE_ADVANCE_SCIENCE_BENEFIT_FREEBONUS => 2,
            BE_ADVANCE_MILITARY_BENEFIT_FREEBONUS => 3,
            BE_ADVANCE_TECHNOLOGY_BENEFIT_FREEBONUS => 4,
        ];
        foreach ($expected as $ben => $track) {
            $row = $this->game->benefit_types[$ben];
            $this->assertEquals("t", $row["r"], "benefit $ben");
            $this->assertEquals($track, $row["t"], "benefit $ben");
            $this->assertEquals(FLAG_GAIN_BENFIT | FLAG_FREE_BONUS, $row["flags"], "benefit $ben");
        }
    }

    function testExploreRowCarriesTheCivSoTheTileLandsOnTheMat() {
        $this->assertEquals("explore_space", $this->game->benefit_types[BE_WEREFOLK_EXPLORE]["state"]);
        $this->assertEquals(CIV_WEREFOLK, $this->game->benefit_types[BE_WEREFOLK_EXPLORE]["civ"]);
        $this->assertEquals(CIV_ALIENS, $this->game->benefit_types[BE_EXPLORE_SPACE_ALIEN]["civ"]);
        $this->assertArrayNotHasKey("civ", $this->game->benefit_types[BE_EXPLORE_SPACE], "the plain explore stays in the supply");
    }

    function testAbilityFiresOnIncomeTurns2To5() {
        foreach ([2, 3, 4, 5] as $turn) {
            $game = $this->newGame();
            $game->queueEraCivAbility(CIV_WEREFOLK, 1, $turn);
            $this->assertEquals(["civ"], $game->benefitLabels(), "income turn $turn");
        }
    }

    /**
     * The flip has nothing to pick, but it still queues as a civ row: that is the only thing the
     * civ ability state offers a player the order of when a second income civ is pending.
     */
    function testAbilityOffersOneButtonAndNoDecline() {
        $args = $this->game->argCivAbilitySingle(1, CIV_WEREFOLK, ["benefit_data" => ""]);
        $this->assertFalse($args["decline"]);
        $this->assertEquals([Werefolk::CHOICE_FLIP], array_keys($args["slots_choice"]));
    }

    function testUsingTheAbilityQueuesTheFlip() {
        $this->game->queueEraCivAbility(CIV_WEREFOLK, 1, 2);
        $this->game->useCivAbility(Werefolk::CHOICE_FLIP);
        $this->assertEquals([(string) BE_WEREFOLK_FLIP], $this->game->benefitLabels());
    }

    /**
     * argCivAbility groups every pending civ row sharing a prerequisite, and the client turns the
     * ones the player is not on into "switch civilization" buttons. Queueing the flip outside that
     * pool silently took the ordering choice away from a player holding two income civs.
     */
    function testSecondIncomeCivSharesTheSameChoicePool() {
        $this->game->giveCiv(1, CIV_FAEFOLK);
        $this->game->queueEraCivAbility(CIV_FAEFOLK, 1, 2);
        $this->game->queueEraCivAbility(CIV_WEREFOLK, 1, 2);

        $rows = $this->game->benefitQueue();
        $this->assertEquals(["civ", "civ"], $this->game->benefitLabels());
        $this->assertEquals([CIV_FAEFOLK, CIV_WEREFOLK], [(int) $rows[0]["benefit_type"], (int) $rows[1]["benefit_type"]]);
        $this->assertEquals($rows[0]["benefit_prerequisite"], $rows[1]["benefit_prerequisite"]);
    }

    function testAbilityDoesNotFireOnIncomeTurn1() {
        $this->game->queueEraCivAbility(CIV_WEREFOLK, 1, 1);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** The tile drawn this turn is the one the explore state must insist on. */
    function testDrawnTileIsMarkedAsTheOneToExploreWith() {
        $tile = $this->game->addCard(CARD_SPACE, "deck_space");
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertEquals($tile, $this->game->getGameStateValue("selected_space_tile"));
        $this->game->queueBenefitInterrupt(BE_WEREFOLK_EXPLORE, 1, "");
        $this->assertEquals($tile, $this->game->argSpaceExploration()["selected_space_tile"]["card_id"]);
    }

    /** A tile held from an earlier turn is not offered, the marker is replaced every flip. */
    function testStockpiledTileIsNotTheMarkedOne() {
        $old = $this->game->addCard(CARD_SPACE, "hand");
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertNotEquals($old, $this->game->getGameStateValue("selected_space_tile"));
    }

    /** The client only highlights the marked tile, the action refuses any other one anyway. */
    function testExploringWithAnotherTileIsRefused() {
        $old = $this->game->addCard(CARD_SPACE, "hand", 1, 17);
        $this->useAbility(Werefolk::FACE_UP);
        $marked = $this->game->getCardInfoById($this->game->getGameStateValue("selected_space_tile"));
        $this->assertNotEquals(17, $marked["card_type_arg"], "the fixture must differ from the drawn tile");

        $this->game->queueBenefitInterrupt(BE_WEREFOLK_EXPLORE, 1, "");
        $this->expectException(BgaUserException::class);
        $this->expectExceptionMessage("You must explore with the space tile you just drew");
        $this->game->action_exploreSpace(17);
    }

    /**
     * Nothing consumes the marker on the face-down branch, so a plain explore later the same turn
     * (INTERSTELLAR TRAVEL or WARPGATES, both reachable from the face-down advance) must treat
     * every hand tile as ordinary per FORMAL_RULES 5.3. TODO.md "Werefolk BUG" entry.
     */
    function testPlainExploreAfterFaceDownIsNotRestricted() {
        $this->game->addCard(CARD_SPACE, "hand");
        $this->useAbility(Werefolk::FACE_DOWN);
        $this->game->queueBenefitInterrupt(BE_EXPLORE_SPACE, 1, "");
        $this->assertNull($this->game->argSpaceExploration()["selected_space_tile"]);
    }

    /** The face-down and VP branches never explore, so the ability clears its own marker at the end. */
    function testMarkerIsClearedWhenTheAbilityEnds() {
        $this->useAbility(Werefolk::FACE_DOWN);
        $this->assertNotEquals(0, $this->game->getGameStateValue("selected_space_tile"));
        $this->game->benefitCashed($this->game->benefitQueue()[0]); // the advance the flip queued, answered
        $this->game->resolveBenefit(BE_CIV_END);
        $this->assertEquals(0, $this->game->getGameStateValue("selected_space_tile"));
    }

    function testNoMarkerLeavesEveryTileSelectable() {
        $this->game->setGameStateValue("selected_space_tile", 0);
        $this->assertNull($this->game->argSpaceExploration()["selected_space_tile"]);
    }

    /** No tile gained means nothing to flip: the whole ability is skipped (FORMAL_RULES 5.3). */
    function testEmptyDeckAndDiscardSkipsTheFlip() {
        $this->game->seedRand(Werefolk::FACE_DOWN);
        $this->game->queueEraCivAbility(CIV_WEREFOLK, 1, 2);
        $this->game->useCivAbility(Werefolk::CHOICE_FLIP);
        $this->game->resolveBenefit(BE_WEREFOLK_FLIP);
        $this->assertEquals([], $this->game->benefitLabels(), "no advance or regress choice from a ghost flip");
    }

    function testDiscardsTheTileAlreadyOnTheMat() {
        $tile = $this->game->putTileOnMat();
        $this->useAbility(Werefolk::FACE_DOWN);
        $this->assertEquals("discard", $this->game->getCardInfoById($tile)["card_location"]);
    }

    function testFaceDownAdvancesOnAnyTrackWithBenefitAndFreeBonus() {
        $this->useAbility(Werefolk::FACE_DOWN);
        $this->assertEquals(
            [
                "o," .
                BE_ADVANCE_EXPLORATION_BENEFIT_FREEBONUS .
                "," .
                BE_ADVANCE_SCIENCE_BENEFIT_FREEBONUS .
                "," .
                BE_ADVANCE_MILITARY_BENEFIT_FREEBONUS .
                "," .
                BE_ADVANCE_TECHNOLOGY_BENEFIT_FREEBONUS,
                (string) BE_CIV_END,
            ],
            $this->game->benefitLabels()
        );
    }

    function testFaceUpOffersRegressOrTheFourVP() {
        $this->game->putCubeOnTrack(2, 3);
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertEquals(
            ["o," . BE_WEREFOLK_REGRESS . "," . Werefolk::VP_NO_REGRESS, (string) BE_CIV_END],
            $this->game->benefitLabels()
        );
    }

    /** Nothing to regress means the player did not regress, so the VP is taken with no prompt. */
    function testFaceUpWithNothingToRegressPaysTheVPDirectly() {
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertEquals([(string) Werefolk::VP_NO_REGRESS, (string) BE_CIV_END], $this->game->benefitLabels());
    }

    function testCubeAtSpotZeroCannotRegress() {
        $this->game->putCubeOnTrack(3, 0);
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertEquals([(string) Werefolk::VP_NO_REGRESS, (string) BE_CIV_END], $this->game->benefitLabels());
    }

    function testVirtualCubeCannotRegress() {
        $this->game->putCubeOnTrack(1, 5, CUBE_AI);
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertEquals([(string) Werefolk::VP_NO_REGRESS, (string) BE_CIV_END], $this->game->benefitLabels());
    }

    /** Only the tracks that can actually move back are offered, and the explore follows the choice. */
    function testRegressOffersOnlyLegalTracksThenExplores() {
        $this->game->putCubeOnTrack(1, 4);
        $this->game->putCubeOnTrack(3, 2);
        $this->game->putCubeOnTrack(4, 0);
        $this->useAbility(Werefolk::FACE_UP);
        $this->game->chooseOption(BE_WEREFOLK_REGRESS);

        $this->assertEquals(
            [
                "o," . BE_REGRESS_EXPLORATION_NOBENEFIT . "," . BE_REGRESS_MILITARY_NOBENEFIT,
                (string) BE_WEREFOLK_EXPLORE,
                (string) BE_CIV_END,
            ],
            $this->game->benefitLabels()
        );
    }

    /** A single legal track collapses to the bare regress, the engine drops one option choices. */
    function testRegressWithOneLegalTrackSkipsTheChoice() {
        $this->game->putCubeOnTrack(4, 1);
        $this->useAbility(Werefolk::FACE_UP);
        $this->game->chooseOption(BE_WEREFOLK_REGRESS);
        $this->assertEquals(
            [(string) BE_REGRESS_TECHNOLOGY_NOBENEFIT, (string) BE_WEREFOLK_EXPLORE, (string) BE_CIV_END],
            $this->game->benefitLabels()
        );
    }

    function testFlipIsReportedInTheLog() {
        $this->useAbility(Werefolk::FACE_UP);
        $this->assertContains('${player_name} flips the space tile face-up', $this->game->notificationTexts());

        $game = $this->newGame();
        $this->useAbility(Werefolk::FACE_DOWN, 2, $game);
        $this->assertContains('${player_name} flips the space tile face-down', $game->notificationTexts());
    }

    function testBothFacesAreReachable() {
        $this->assertEquals(0, Werefolk::FACE_DOWN);
        $this->assertEquals(1, Werefolk::FACE_UP);
    }

    /**
     * GameUT serves getStructuresSearch from the in memory model, so the production query builder
     * never runs in these tests. It used to reject backslashes, which made the escaped underscore
     * throw in a real game and pass here. Guard the exact pattern regressableTracks passes.
     */
    function testTrackCubePatternSurvivesTheProductionQueryBuilder() {
        $this->assertEquals(
            " AND card_location LIKE 'tech\\_spot\\_%'",
            $this->game->queryExpression("card_location", "tech\\_spot\\_%", 2)
        );
    }

    function testAssertsOnABenefitItDoesNotOwn() {
        $this->assertAsserts("ERR:Werefolk:13", fn() => $this->werefolk()->awardBenefits(1, BE_SPACE));
    }

    function testAssertsWhenAskedToRegressWithNoLegalTrack() {
        $this->assertAsserts("ERR:Werefolk:20", fn() => $this->werefolk()->awardBenefits(1, BE_WEREFOLK_REGRESS));
    }

    function testAssertsForAPlayerWithoutTheCiv() {
        $this->assertAsserts("ERR:Werefolk:12", fn() => $this->werefolk()->awardBenefits(2, BE_WEREFOLK_FLIP));
    }

    function testAssertsOnASpotItDoesNotHave() {
        $this->assertAsserts("ERR:Werefolk:16", fn() => $this->werefolk()->moveCivCube(1, 2, "", []));
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
