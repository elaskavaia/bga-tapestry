<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Faefolk, the first Fantasies and Futures civilization.
 *
 * The card table, the single mat token and the benefit stack all live in the GameUT in memory
 * models, so the tests drive the real engine: array expansion, the benefit 342 alias and the
 * income turn 5 doubling are all exercised rather than stubbed.
 */
class FaefolkUT extends GameUT {
    function __construct() {
        parent::__construct();
        $this->era = 2;
        $this->giveCiv(1, CIV_FAEFOLK);
    }

    /** Replaces whatever sits on the ellipse, no argument leaves the player without a token. */
    function setToken(string ...$locations): void {
        foreach (array_keys($this->getStructuresOnCiv(CIV_FAEFOLK)) as $id) {
            $this->structures->setLocation($id, "hand");
        }
        foreach ($locations as $location) {
            $this->addCubeAt(1, $location, CUBE_CIV);
        }
    }

    function tokenLocation(): string {
        $tokens = $this->getStructuresOnCiv(CIV_FAEFOLK);
        return $tokens ? reset($tokens)["card_location"] : "";
    }

    function setTapestryOnMat(int $count) {
        for ($i = 1; $i <= $count; $i++) {
            $this->addCard(CARD_TAPESTRY, "era$i");
        }
    }

    /** What stBenefitManager does with the pending flicker row: resolve it, then cash it on true. */
    function resolveFlicker(int $player_id = 1): void {
        $row = $this->benefits->first(["benefit_category" => "standard", "benefit_type" => BE_FAEFOLK_FLICKER]);
        if (!$row) {
            throw new BgaSystemException("no pending flicker row");
        }
        if ($this->awardBenefits($player_id, BE_FAEFOLK_FLICKER)) {
            $this->benefitCashed($row["benefit_id"]);
        }
    }
}

final class FaefolkTest extends TestCase {
    private FaefolkUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
        $this->game->setToken("civ_43_1");
    }

    /** A table with the civ in hand and nothing on the ellipse yet. */
    private function newGame(): FaefolkUT {
        $game = new FaefolkUT();
        $game->init();
        $game->doAdjustMaterial(2, 8);
        return $game;
    }

    private function faefolk(): Faefolk {
        return $this->game->getCivilizationInstance(CIV_FAEFOLK, true);
    }

    /** Resolve the whole ability the way stBenefitManager would, stopping at non-Faefolk rows. */
    private function useAbility(int $choice): void {
        $this->faefolk()->moveCivCube(1, $choice, "", []);
        $this->game->resolveFlicker();
    }

    function testConstantsAreTheOnesTheSpriteExpects() {
        // tapestry.css positions .civilization_40 .. .civilization_49 from the alphabetical sprite
        $this->assertEquals(40, CIV_ARTIFICERS);
        $this->assertEquals(43, CIV_FAEFOLK);
        $this->assertEquals(49, CIV_WEREFOLK);
        $this->assertEquals(0b1000, EXP_FF_FLAG);
    }

    function testMaterialEntry() {
        $civ = $this->game->civilizations[CIV_FAEFOLK];
        $this->assertEquals("FF", $civ["exp"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $civ["income_trigger"]);
        $this->assertEquals(1, $civ["tokens_count"]);
        $this->assertEquals(7, count($civ["slots"]));
    }

    /**
     * The seven spots, asserted through the engine: land the token on each in turn and check the
     * rows that actually reach the benefit stack, not the material rows they were read from.
     * Multi icon spots are gain-all, so each icon becomes its own standard row.
     */
    function testEachSpotQueuesItsPrintedBenefits() {
        $expected = [
            1 => [(string) BE_VP_TAPESTY],
            2 => [(string) BE_TAPESTRY, (string) BE_TERRITORY, (string) BE_TECH_CARD, (string) BE_VP_ANY_BUILDING],
            3 => [(string) BE_GAIN_FOOD, (string) BE_VP_TILES],
            4 => [(string) BE_GAIN_COIN, (string) BE_VP_TECH],
            5 => [(string) BE_GAIN_CULTURE, (string) BE_VP_TERRITORY],
            6 => [(string) BE_GAIN_WORKER, (string) BE_VP_CAPITAL],
            7 => [(string) BE_SPACE, (string) BE_EXPLORE_SPACE],
        ];
        foreach ($expected as $spot => $rows) {
            $game = $this->newGame();
            $game->setToken("civ_43_$spot");
            $game->getCivilizationInstance(CIV_FAEFOLK, true)->moveCivCube(1, Faefolk::CHOICE_FLICKER_ONLY, "", []);
            $game->resolveFlicker();

            $this->assertEquals("civ_43_$spot", $game->tokenLocation(), "spot $spot, 0 visible tapestry, token stays");
            $this->assertEquals($rows, $game->benefitLabels(), "spot $spot");
        }
    }

    /** SCORE ANY BUILDING resolves into a single four way choice, it is not four separate scores. */
    function testScoreAnyBuildingResolvesToAChooseOne() {
        $this->game->awardBenefits(1, BE_VP_ANY_BUILDING);
        $this->assertEquals(["o," . BE_VP_FARM . "," . BE_VP_ARMORY . "," . BE_VP_HOUSE . ",54"], $this->game->benefitLabels());
    }

    function testSetupPlacesTokenOnSpotOne() {
        $game = $this->newGame();
        $game->setupCiv(CIV_FAEFOLK, 1, "1");
        $this->assertEquals("civ_43_1", $game->tokenLocation());
    }

    function testMidgameSetupAlsoPlacesTheToken() {
        $game = $this->newGame();
        $game->setupCiv(CIV_FAEFOLK, 1, "");
        $this->assertEquals("civ_43_1", $game->tokenLocation());
    }

    function testAbilityFiresOnIncomeTurns2To5() {
        foreach ([2, 3, 4, 5] as $turn) {
            $game = $this->newGame();
            $game->queueEraCivAbility(CIV_FAEFOLK, 1, $turn);
            $this->assertEquals(["civ"], $game->benefitLabels(), "income turn $turn");
        }
    }

    function testAbilityDoesNotFireOnIncomeTurn1() {
        $this->game->queueEraCivAbility(CIV_FAEFOLK, 1, 1);
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** The whole ability is mandatory, only the tapestry card is a choice, so no Decline button. */
    function testAbilityOffersTwoChoicesAndNoDecline() {
        $args = $this->faefolk()->argCivAbilitySingle(1, ["benefit_data" => ""]);
        $this->assertFalse($args["decline"]);
        $this->assertEquals([1, 2], array_keys($args["slots_choice"]));
        $this->assertEquals([BE_TAPESTRY], $args["slots_choice"][1]["benefit"]);
    }

    /**
     * The printed order is gain the card first, count second, so the flicker is queued behind the
     * tapestry card rather than resolved with it.
     */
    function testTapestryCardIsQueuedAheadOfTheFlicker() {
        $this->faefolk()->moveCivCube(1, Faefolk::CHOICE_GAIN_TAPESTRY, "", []);
        $this->assertEquals([(string) BE_TAPESTRY, (string) BE_FAEFOLK_FLICKER], $this->game->benefitLabels());
    }

    function testFlickerOnlyQueuesNoTapestryCard() {
        $this->faefolk()->moveCivCube(1, Faefolk::CHOICE_FLICKER_ONLY, "", []);
        $this->assertEquals([(string) BE_FAEFOLK_FLICKER], $this->game->benefitLabels());
    }

    /**
     * Gaining a tapestry card can put one on the income mat before the count happens: with HERALDS,
     * isTapestryActive() reads the clone on civilization_6, so a TYRANNY there makes
     * effect_cardComesInPlayTriggerResolve queue benefit 64 and the card lands in era{N}. The
     * flicker must see that card. Resolving the two queued rows in stack order is what makes this
     * work, so the test resolves them in order with the mat changing in between.
     */
    function testCardPlayedWhileGainingIsCounted() {
        $this->game->setTapestryOnMat(1);
        $this->faefolk()->moveCivCube(1, Faefolk::CHOICE_GAIN_TAPESTRY, "", []);

        // BE_TAPESTRY resolves first; TYRANNY plays the drawn card onto the mat
        $this->game->addCard(CARD_TAPESTRY, "era2");
        $this->game->resolveFlicker();

        // 2 visible now, not 1: spot 1 + 2 = spot 3, and the spot 2 benefits are never queued
        $this->assertEquals("civ_43_3", $this->game->tokenLocation());
        $this->assertContains((string) BE_VP_TILES, $this->game->benefitLabels());
    }

    /**
     * Visible means played on the income mat. Cards in hand are hidden, and a card in era_6 has
     * been covered by the one played over it, so neither is counted.
     */
    function testHandAndCoveredCardsAreNotVisible() {
        $this->game->setTapestryOnMat(2);
        $this->game->addCard(CARD_TAPESTRY, "hand");
        $this->game->addCard(CARD_TAPESTRY, "hand");
        $this->game->addCard(CARD_TAPESTRY, "era_6");
        $this->game->addCard(CARD_TAPESTRY, "era1", 2); // an opponent's card
        $this->game->addCard(CARD_TAPESTRY, "deck_tapestry");

        $this->assertEquals(2, $this->faefolk()->countVisibleTapestry(1));
    }

    function testClockwiseMoveWrapsPastSpotSeven() {
        $this->game->setToken("civ_43_5");
        $this->game->setTapestryOnMat(3);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_1", $this->game->tokenLocation());
        $this->assertEquals([(string) BE_VP_TAPESTY], $this->game->benefitLabels());
    }

    function testFullLapLandsOnTheSameSpot() {
        $this->game->setToken("civ_43_3");
        $this->game->setTapestryOnMat(7);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->tokenLocation());
    }

    /**
     * Zero visible tapestry cards is a legal state (a player who never played one): the token
     * moves 0 spots and the benefit of the spot it is already on is gained again.
     */
    function testZeroVisibleTapestryStaysOnTheCurrentSpot() {
        $this->game->setToken("civ_43_6");
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_6", $this->game->tokenLocation());
        $this->assertEquals([(string) BE_GAIN_WORKER, (string) BE_VP_CAPITAL], $this->game->benefitLabels());
    }

    function testIncomeTurn4DoesNotDoubleTheBenefit() {
        $this->game->era = 4;
        $this->game->setTapestryOnMat(2);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->tokenLocation());
        $this->assertEquals([(string) BE_GAIN_FOOD, (string) BE_VP_TILES], $this->game->benefitLabels());
    }

    /** Income turn 5 doubles the benefit, not the move: one flicker, two resolutions of one spot. */
    function testIncomeTurn5GainsTheBenefitTwice() {
        $this->game->era = 5;
        $this->game->setTapestryOnMat(2);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->tokenLocation());
        $this->assertEquals(
            [(string) BE_GAIN_FOOD, (string) BE_VP_TILES, (string) BE_GAIN_FOOD, (string) BE_VP_TILES],
            $this->game->benefitLabels()
        );
    }

    /** The four icon spot doubled: eight rows, and each copy of 342 stays its own choice. */
    function testIncomeTurn5OnTheFourIconSpot() {
        $this->game->era = 5;
        $this->game->setTapestryOnMat(1);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_2", $this->game->tokenLocation());
        $labels = $this->game->benefitLabels();
        $this->assertEquals(8, count($labels));
        $this->assertEquals(2, count(array_keys($labels, (string) BE_VP_ANY_BUILDING)));
    }

    function testFlickerNotificationReportsTheCountAndTheNewSpot() {
        $this->game->setToken("civ_43_7");
        $this->game->setTapestryOnMat(3);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $notif = $this->game->notificationsOfType("message")[0];
        $this->assertEquals("message", $notif["type"]);
        $this->assertStringContainsString('${count}', $notif["log"]);
        $this->assertStringContainsString('${spot}', $notif["log"]);
        $this->assertEquals(3, $notif["args"]["count"]);
        $this->assertEquals(3, $notif["args"]["spot"]);
    }

    function testUnknownChoiceIsRejected() {
        $this->assertAsserts("ERR:Faefolk:13", fn() => $this->faefolk()->moveCivCube(1, 9, "", []));
    }

    function testMissingTokenIsRejected() {
        $this->game->setToken();
        $this->assertAsserts("ERR:Faefolk:20", fn() => $this->faefolk()->flicker(1, 1));
    }

    function testDuplicateTokensAreRejected() {
        $this->game->setToken("civ_43_1", "civ_43_4");
        $this->assertAsserts("ERR:Faefolk:20", fn() => $this->faefolk()->flicker(1, 1));
    }

    function testTokenOutsideTheEllipseIsRejected() {
        $this->game->setToken("civ_43_9");
        $this->assertAsserts("ERR:Faefolk:21", fn() => $this->faefolk()->flicker(1, 1));
    }

    function testFlickerBenefitRejectsAnotherBenefitId() {
        $this->assertAsserts("ERR:Faefolk:15", fn() => $this->faefolk()->awardBenefits(1, BE_TAPESTRY));
    }

    private function assertAsserts(string $code, callable $fn): void {
        ob_start(); // systemAssertTrue echoes the server log before throwing
        try {
            $fn();
            $this->fail("expected $code");
        } catch (BgaUserException $e) {
            $this->assertStringContainsString($code, $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }
}
