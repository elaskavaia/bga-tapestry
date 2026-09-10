<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/IncomeMatUT.php";

/**
 * Artificers, the civilization the income mat seams were built for. The mat is the real structure
 * table of the harness and the prompt goes through action_civTokenAdvance, so what is asserted
 * here is the layout a real table would end up with and the income it then pays.
 */
class ArtificersUT extends IncomeMatUT {
    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->giveCiv(self::OWNER, CIV_ARTIFICERS);
        $this->getCivilizationInstance(CIV_ARTIFICERS, true); // includes civs/Artificers.php, so Artificers::ACTION_* resolve
    }

    function artificers(): Artificers {
        return $this->getCivilizationInstance(CIV_ARTIFICERS, true);
    }

    /** The income row of one income turn, as queueEraCivAbilities queues it. */
    function startAbility(int $incomeTurn): void {
        $this->eras[self::OWNER] = $incomeTurn;
        $this->queueEraCivAbility(CIV_ARTIFICERS, self::OWNER, $incomeTurn);
    }

    /** The whole ability: the income row, then the button the owner presses. */
    function useCivAbility(int $incomeTurn, int $choice): void {
        $this->startAbility($incomeTurn);
        $this->civTokenAdvance(CIV_ARTIFICERS, self::OWNER, $choice);
    }

    function declineCivAbility(int $incomeTurn): void {
        $this->startAbility($incomeTurn);
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(14);
        $this->action_civDecline(CIV_ARTIFICERS);
    }

    /** The entries the prompt offers, without pressing anything. */
    function getOfferedChoices(int $incomeTurn): array {
        $this->eras[self::OWNER] = $incomeTurn;
        return array_keys($this->artificers()->getChoices(self::OWNER, $incomeTurn));
    }

    function getSpotsOnTrack(int $type): array {
        return array_map("intval", array_column($this->getIncomeBuildingsOnTrack(self::OWNER, $type), "card_location_arg2"));
    }
}

final class ArtificersTest extends TestCase {
    private ArtificersUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(int $players = 2): ArtificersUT {
        $game = new ArtificersUT($players);
        $game->init();
        $game->doAdjustMaterial($players, 8);
        return $game;
    }

    /** What effect_IncomeBenefits pays for the given uncovered spots of each track. */
    private function expectedIncome(array $spots_per_track): array {
        $expected = [];
        foreach ($spots_per_track as $track => $spots) {
            foreach ($spots as $spot) {
                foreach ($this->game->income_tracks[$track][$spot]["benefit"] as $ben) {
                    $expected[] = [ArtificersUT::OWNER, $ben, 1, reason("inspot", "{$track}_{$spot}")];
                }
            }
        }
        return $expected;
    }

    private function getChoiceKey(int $track, int $action): int {
        return $track * 10 + $action;
    }

    // ---------------------------------------------------------- material

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_ARTIFICERS];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5], $civ["income_trigger"]);
        $this->assertTrue($civ["automa"]);
        $this->assertTrue(array_get($civ["income_trigger"], "decline", true), "both abilities say you may");
        $this->assertArrayNotHasKey("slots", $civ, "the choice is made on the income mat, not on the civ mat");
        $this->assertInstanceOf(Artificers::class, $this->game->artificers());
    }

    // --------------------------------------------------------- set aside

    /** The building leaves the mat for the tableau and the track uncovers the spot it sat on. */
    function testSetAsideAtEveryMutationTurn() {
        foreach ([2, 3, 4] as $incomeTurn) {
            $game = $this->newGame();
            $game->layoutPrefixMat(ArtificersUT::OWNER);
            $leftmost = (int) array_key_first($game->getIncomeBuildingsOnTrack(ArtificersUT::OWNER, BUILDING_MARKET));

            $game->useCivAbility($incomeTurn, $this->getChoiceKey(BUILDING_MARKET, Artificers::ACTION_SET_ASIDE));

            $row = $game->getStructureInfoById($leftmost);
            $this->assertEquals("hand", $row["card_location"], "turn $incomeTurn");
            $this->assertEquals(0, $row["card_location_arg2"], "turn $incomeTurn");
            $this->assertEquals(2, $game->dbGetIncomeTrackLevel(BUILDING_MARKET, ArtificersUT::OWNER), "turn $incomeTurn");
            $this->assertEquals([1, 2], $game->getIncomeUncoveredSpots(ArtificersUT::OWNER, BUILDING_MARKET), "turn $incomeTurn");
            $this->assertEquals([], $game->benefitLabels(), "turn $incomeTurn");
        }
    }

    function testSetAsidePaysTheUncoveredSpotTheSameTurn() {
        $this->game->layoutPrefixMat(ArtificersUT::OWNER);
        $this->game->useCivAbility(2, $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SET_ASIDE));
        $this->game->effect_IncomeBenefits(null, ArtificersUT::OWNER);
        $this->assertEquals($this->expectedIncome([1 => [1], 2 => [1], 3 => [1, 2], 4 => [1]]), $this->game->awarded);
    }

    /** Setting aside is not gaining a building (CIV.ARTIFICERS.6), so the gain hooks stay silent. */
    function testSetAsideIsNotGainingABuilding() {
        $this->game->tapestries[ArtificersUT::OWNER] = [8]; // CAPITALISM, 1 coin per market claimed
        $this->game->giveCiv(ArtificersUT::OWNER, CIV_RELENTLESS);
        $this->game->layoutPrefixMat(ArtificersUT::OWNER);

        $this->game->useCivAbility(2, $this->getChoiceKey(BUILDING_MARKET, Artificers::ACTION_SET_ASIDE));

        $this->assertEquals(0, $this->game->dbGetScore(ArtificersUT::OWNER));
        $this->assertEquals([], $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE), "RELENTLESS placed no cube");
        $gains = array_filter($this->game->notificationTexts(), fn($text) => str_contains($text, "res_type"));
        $this->assertEquals([], $gains, "CAPITALISM paid no coin");
    }

    // ------------------------------------------------------- slide left

    /** One space left, never to the leftmost empty space: the vacated spot is uncovered, the target is covered. */
    function testSlideLeftMovesOneSpaceAndLeavesTheLevelAlone() {
        $this->game->layoutTrack(ArtificersUT::OWNER, BUILDING_FARM, [4, 5, 6]);
        $this->game->layoutPrefix(ArtificersUT::OWNER, BUILDING_MARKET, 1);
        $this->game->layoutPrefix(ArtificersUT::OWNER, BUILDING_HOUSE, 1);
        $this->game->layoutPrefix(ArtificersUT::OWNER, BUILDING_ARMORY, 1);

        $this->game->useCivAbility(2, $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SLIDE));

        $this->assertEquals([3, 5, 6], $this->game->getSpotsOnTrack(BUILDING_FARM));
        $this->assertEquals(3, $this->game->dbGetIncomeTrackLevel(BUILDING_FARM, ArtificersUT::OWNER));
        $this->assertEquals([1, 2, 4], $this->game->getIncomeUncoveredSpots(ArtificersUT::OWNER, BUILDING_FARM));

        $this->game->effect_IncomeBenefits(null, ArtificersUT::OWNER);
        $this->assertEquals($this->expectedIncome([1 => [1], 2 => [1], 3 => [1, 2, 4], 4 => [1]]), $this->game->awarded);
    }

    /** The leftmost building carries the smallest spot, so spot 1 is the only thing that blocks a slide. */
    function testSlideIsNotOfferedWithNoEmptySpaceToTheLeft() {
        $this->game->layoutTrack(ArtificersUT::OWNER, BUILDING_HOUSE, [1, 4, 6]);
        $this->assertEquals([$this->getChoiceKey(BUILDING_HOUSE, Artificers::ACTION_SET_ASIDE)], $this->game->getOfferedChoices(2));
    }

    function testAnEmptyTrackOffersNothing() {
        $this->game->layoutTrack(ArtificersUT::OWNER, BUILDING_ARMORY, []);
        $this->game->layoutTrack(ArtificersUT::OWNER, BUILDING_FARM, [6]);
        $this->assertEquals(
            [
                $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SET_ASIDE),
                $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SLIDE),
            ],
            $this->game->getOfferedChoices(2)
        );
    }

    function testEveryTrackWithABuildingOffersBothActions() {
        $this->game->layoutPrefixMat(ArtificersUT::OWNER);
        $expected = [];
        for ($track = 1; $track <= 4; $track++) {
            $expected[] = $this->getChoiceKey($track, Artificers::ACTION_SET_ASIDE);
            $expected[] = $this->getChoiceKey($track, Artificers::ACTION_SLIDE);
        }
        $this->assertEquals($expected, $this->game->getOfferedChoices(2));
    }

    // --------------------------------------------------------- income turn 5

    /** 1 VP per building off the mat: claimed, set aside or wherever it went, and a slid track pays nothing. */
    function testFinalTurnScoresEveryBuildingThatLeftTheMat() {
        $owner = ArtificersUT::OWNER;
        $this->game->layoutTrack($owner, BUILDING_MARKET, [3, 4, 5, 6]); // one claimed
        $this->game->layoutTrack($owner, BUILDING_HOUSE, [1, 3, 4, 5, 6]); // slid, never claimed
        $this->game->layoutTrack($owner, BUILDING_FARM, [4, 5, 6]); // two claimed
        $this->game->layoutTrack($owner, BUILDING_ARMORY, [3, 4, 5, 6]);
        $this->game->dbAddStructure($owner, BUILDING_ARMORY, 0, "hand"); // set aside earlier

        $this->game->useCivAbility(5, Artificers::ACTION_SCORE);

        $this->assertEquals(4, $this->game->dbGetScore($owner));
    }

    function testFinalTurnSlideAllPacksTheTrackToTheLeft() {
        $owner = ArtificersUT::OWNER;
        $this->game->layoutTrack($owner, BUILDING_FARM, [3, 5, 6]);
        $this->game->layoutPrefix($owner, BUILDING_MARKET, 1);
        $this->game->layoutPrefix($owner, BUILDING_HOUSE, 1);
        $this->game->layoutPrefix($owner, BUILDING_ARMORY, 1);

        $this->game->useCivAbility(5, $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SLIDE_ALL));

        $this->assertEquals([1, 2, 3], $this->game->getSpotsOnTrack(BUILDING_FARM));
        $this->assertEquals(3, $this->game->dbGetIncomeTrackLevel(BUILDING_FARM, $owner));
        $this->assertEquals([4, 5, 6], $this->game->getIncomeUncoveredSpots($owner, BUILDING_FARM));

        $this->game->effect_IncomeBenefits(null, $owner);
        $this->assertEquals($this->expectedIncome([1 => [1], 2 => [1], 3 => [4, 5, 6], 4 => [1]]), $this->game->awarded);
    }

    /** Packing a track that is already flush left, or empty, moves nothing, so it is not offered. */
    function testFinalTurnOffersScoringAndOnlyTheTracksThatWouldMove() {
        $owner = ArtificersUT::OWNER;
        $this->game->layoutTrack($owner, BUILDING_MARKET, [1, 2, 3]); // packed
        $this->game->layoutTrack($owner, BUILDING_HOUSE, []); // empty
        $this->game->layoutTrack($owner, BUILDING_FARM, [2, 3]);
        $this->game->layoutTrack($owner, BUILDING_ARMORY, [1, 3]);

        $this->assertEquals(
            [
                Artificers::ACTION_SCORE,
                $this->getChoiceKey(BUILDING_FARM, Artificers::ACTION_SLIDE_ALL),
                $this->getChoiceKey(BUILDING_ARMORY, Artificers::ACTION_SLIDE_ALL),
            ],
            $this->game->getOfferedChoices(5)
        );
    }

    // ------------------------------------------------------------ no ability

    function testDeclineChangesNothing() {
        $this->game->layoutPrefixMat(ArtificersUT::OWNER);
        $this->game->declineCivAbility(3);

        $this->assertEquals([2, 3, 4, 5, 6], $this->game->getSpotsOnTrack(BUILDING_MARKET));
        $this->assertEquals(1, $this->game->dbGetIncomeTrackLevel(BUILDING_MARKET, ArtificersUT::OWNER));
        $this->assertEquals([], $this->game->benefitLabels());
    }

    function testIncomeTurns1And6QueueNothing() {
        foreach ([1, 6] as $incomeTurn) {
            $game = $this->newGame();
            $game->layoutPrefixMat(ArtificersUT::OWNER);
            $game->startAbility($incomeTurn);
            $this->assertEquals([], $game->benefitLabels(), "turn $incomeTurn");
            $this->assertNotEmpty($game->notificationLike("is not applicable in era"), "turn $incomeTurn");
        }
    }

    /** Every track empty is no legal answer at all, so the prompt is skipped instead of shown with no buttons. */
    function testNothingLeftOnTheMatQueuesNothing() {
        for ($track = 1; $track <= 4; $track++) {
            $this->game->layoutTrack(ArtificersUT::OWNER, $track, []);
        }
        $this->game->startAbility(3);
        $this->assertEquals([], $this->game->benefitLabels());
        $this->assertNotEmpty($this->game->notificationLike("no income building is left"));
    }

    /** Income turn 5 always has the scoring option, even with nothing anywhere to slide. */
    function testFinalTurnAlwaysHasTheScoringOption() {
        for ($track = 1; $track <= 4; $track++) {
            $this->game->layoutTrack(ArtificersUT::OWNER, $track, []);
        }
        $this->game->startAbility(5);
        $this->assertEquals(["civ"], $this->game->benefitLabels());
        $this->assertEquals([Artificers::ACTION_SCORE], $this->game->getOfferedChoices(5));
    }

    // ---------------------------------------------------- other civs on a mutated mat

    /** A claim of any kind still takes the leftmost building and uncovers the spot it sat on. */
    function testAClaimOnAMutatedTrackTakesTheLeftmostBuilding() {
        $owner = ArtificersUT::OWNER;
        $ids = $this->game->layoutTrack($owner, BUILDING_HOUSE, [2, 4, 5]);
        $this->game->layoutPrefix($owner, BUILDING_MARKET, 1);
        $this->game->layoutPrefix($owner, BUILDING_FARM, 1);
        $this->game->layoutPrefix($owner, BUILDING_ARMORY, 1);

        $this->assertFalse($this->game->claimIncomeStructure(BUILDING_HOUSE, null));

        $this->assertEquals("capital_structure", $this->game->getStructureInfoById($ids[2])["card_location"]);
        $this->assertEquals([1, 2, 3, 6], $this->game->getIncomeUncoveredSpots($owner, BUILDING_HOUSE));
    }
}
