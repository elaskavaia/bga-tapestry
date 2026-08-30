<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Faefolk, the first Fantasies and Futures civilization.
 *
 * The stubs run no SQL, so the card table, the single mat token and the benefit stack are modelled
 * in memory. queueBenefitNormal is deliberately NOT stubbed: the tests drive the real engine so
 * that array expansion, the benefit 342 alias and the income turn 5 doubling are all exercised.
 * The stack model is the IslandersUT one - interruptBenefit() bumps the prerequisite of every
 * pending row, benefitSingleEntry() inserts at prerequisite 0, and rows pop by (prerequisite, id).
 */
class FaefolkUT extends GameUT {
    public array $rows = [];
    public array $card_rows = [];
    public array $messages = [];
    public array $added_cubes = [];
    public string $token_location = "civ_43_1";
    public int $token_id = 77;
    public int $era = 2;
    private int $next_id = 1;

    function getCurrentEra($player_id) {
        return $this->era;
    }

    function hasCiv($player_id, $civ_id) {
        return $civ_id == CIV_FAEFOLK;
    }

    /** Honours card_type, card_location (with SQL LIKE wildcards) and card_location_arg. */
    function getCardsSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        $found = [];
        foreach ($this->card_rows as $card) {
            if ($card_type !== null && $card["card_type"] != $card_type) {
                continue;
            }
            if ($card_location_arg !== null && $card["card_location_arg"] != $card_location_arg) {
                continue;
            }
            if ($card_location !== null) {
                $pattern = "/^" . str_replace(["%", "_"], [".*", "."], preg_quote($card_location, "/")) . "$/";
                if (!preg_match($pattern, $card["card_location"])) {
                    continue;
                }
            }
            $found[$card["card_id"]] = $card;
        }
        return $found;
    }

    function addCard(string $location, int $player_id = 1, int $type = CARD_TAPESTRY) {
        $id = 100 + count($this->card_rows);
        $this->card_rows[] = [
            "card_id" => $id,
            "card_type" => $type,
            "card_type_arg" => 1,
            "card_location" => $location,
            "card_location_arg" => $player_id,
        ];
    }

    function setTapestryOnMat(int $count) {
        for ($i = 1; $i <= $count; $i++) {
            $this->addCard("era$i");
        }
    }

    function getStructuresOnCiv($cid, $type = BUILDING_CUBE, $arg2 = null) {
        if ($this->token_location === "") {
            return [];
        }
        $tokens = [];
        foreach (explode(",", $this->token_location) as $i => $location) {
            $tokens[$this->token_id + $i] = [
                "card_id" => $this->token_id + $i,
                "card_type" => BUILDING_CUBE,
                "card_type_arg" => CUBE_CIV,
                "card_location" => $location,
                "card_location_arg" => 1,
                "card_location_arg2" => 0,
            ];
        }
        return $tokens;
    }

    function dbSetStructureLocation($structure_id, $location, $state = null, $message = "", $player_id = null) {
        $this->token_location = $location;
    }

    function addCube($player_id, $destination, $type_arg = 0, $arg2 = 0) {
        $this->added_cubes[] = $destination;
        return $this->token_id;
    }

    function notifyWithName($type, $message = "", $args = null, $player_id = null) {
        $this->messages[] = ["type" => $type, "message" => $message, "args" => $args];
    }

    function interruptBenefit() {
        foreach ($this->rows as &$row) {
            $row["prereq"]++;
        }
    }

    function benefitSingleEntry($cat, $type, $player_id, $quantity = 1, $data = "") {
        $this->rows[] = [
            "id" => $this->next_id++,
            "prereq" => 0,
            "cat" => $cat,
            "type" => (int) $type,
            "player" => (int) $player_id,
            "count" => $quantity,
        ];
    }

    /** The order stBenefitManager pops rows in. */
    function drainOrder(): array {
        $rows = $this->rows;
        usort($rows, fn($a, $b) => [$a["prereq"], $a["id"]] <=> [$b["prereq"], $b["id"]]);
        return $rows;
    }

    /** What stBenefitManager does with the pending flicker row: resolve it, then cash it on true. */
    function resolveFlicker(int $player_id = 1): void {
        foreach ($this->rows as $i => $row) {
            if ($row["cat"] == "standard" && $row["type"] == BE_FAEFOLK_FLICKER) {
                if ($this->awardBenefits($player_id, BE_FAEFOLK_FLICKER)) {
                    unset($this->rows[$i]);
                }
                return;
            }
        }
        throw new BgaSystemException("no pending flicker row");
    }

    /** Pending rows as "cat:type" in pop order, standard rows shown as just the type. */
    function drainLabels(): array {
        return array_map(fn($r) => $r["cat"] == "standard" ? (string) $r["type"] : $r["cat"], $this->drainOrder());
    }
}

final class FaefolkTest extends TestCase {
    private FaefolkUT $game;

    protected function setUp(): void {
        $this->game = new FaefolkUT();
        $this->game->init();
        $this->game->doAdjustMaterial(2, 8);
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
            $game = new FaefolkUT();
            $game->init();
            $game->doAdjustMaterial(2, 8);
            $game->token_location = "civ_43_$spot";
            $game->getCivilizationInstance(CIV_FAEFOLK, true)->moveCivCube(1, Faefolk::CHOICE_FLICKER_ONLY, "", []);
            $game->resolveFlicker();

            $this->assertEquals("civ_43_$spot", $game->token_location, "spot $spot, 0 visible tapestry, token stays");
            $this->assertEquals($rows, $game->drainLabels(), "spot $spot");
        }
    }

    /** SCORE ANY BUILDING resolves into a single four way choice, it is not four separate scores. */
    function testScoreAnyBuildingResolvesToAChooseOne() {
        $this->game->awardBenefits(1, BE_VP_ANY_BUILDING);
        $this->assertEquals(["o," . BE_VP_FARM . "," . BE_VP_ARMORY . "," . BE_VP_HOUSE . ",54"], $this->game->drainLabels());
    }

    function testSetupPlacesTokenOnSpotOne() {
        $this->game->setupCiv(CIV_FAEFOLK, 1, "1");
        $this->assertEquals(["civ_43_1"], $this->game->added_cubes);
    }

    function testMidgameSetupAlsoPlacesTheToken() {
        $this->game->setupCiv(CIV_FAEFOLK, 1, "");
        $this->assertEquals(["civ_43_1"], $this->game->added_cubes);
    }

    function testAbilityFiresOnIncomeTurns2To5() {
        foreach ([2, 3, 4, 5] as $turn) {
            $game = new FaefolkUT();
            $game->init();
            $game->doAdjustMaterial(2, 8);
            $game->queueEraCivAbility(CIV_FAEFOLK, 1, $turn);
            $this->assertEquals(["civ"], $game->drainLabels(), "income turn $turn");
        }
    }

    function testAbilityDoesNotFireOnIncomeTurn1() {
        $this->game->queueEraCivAbility(CIV_FAEFOLK, 1, 1);
        $this->assertEquals([], $this->game->drainLabels());
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
        $this->assertEquals([(string) BE_TAPESTRY, (string) BE_FAEFOLK_FLICKER], $this->game->drainLabels());
    }

    function testFlickerOnlyQueuesNoTapestryCard() {
        $this->faefolk()->moveCivCube(1, Faefolk::CHOICE_FLICKER_ONLY, "", []);
        $this->assertEquals([(string) BE_FAEFOLK_FLICKER], $this->game->drainLabels());
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
        $this->game->addCard("era2");
        $this->game->resolveFlicker();

        // 2 visible now, not 1: spot 1 + 2 = spot 3, and the spot 2 benefits are never queued
        $this->assertEquals("civ_43_3", $this->game->token_location);
        $this->assertContains((string) BE_VP_TILES, $this->game->drainLabels());
    }

    /**
     * Visible means played on the income mat. Cards in hand are hidden, and a card in era_6 has
     * been covered by the one played over it, so neither is counted.
     */
    function testHandAndCoveredCardsAreNotVisible() {
        $this->game->setTapestryOnMat(2);
        $this->game->addCard("hand");
        $this->game->addCard("hand");
        $this->game->addCard("era_6");
        $this->game->addCard("era1", 2); // an opponent's card
        $this->game->addCard("deck_tapestry");

        $this->assertEquals(2, $this->faefolk()->countVisibleTapestry(1));
    }

    function testClockwiseMoveWrapsPastSpotSeven() {
        $this->game->token_location = "civ_43_5";
        $this->game->setTapestryOnMat(3);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_1", $this->game->token_location);
        $this->assertEquals([(string) BE_VP_TAPESTY], $this->game->drainLabels());
    }

    function testFullLapLandsOnTheSameSpot() {
        $this->game->token_location = "civ_43_3";
        $this->game->setTapestryOnMat(7);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->token_location);
    }

    /**
     * Zero visible tapestry cards is a legal state (a player who never played one): the token
     * moves 0 spots and the benefit of the spot it is already on is gained again.
     */
    function testZeroVisibleTapestryStaysOnTheCurrentSpot() {
        $this->game->token_location = "civ_43_6";
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_6", $this->game->token_location);
        $this->assertEquals([(string) BE_GAIN_WORKER, (string) BE_VP_CAPITAL], $this->game->drainLabels());
    }

    function testIncomeTurn4DoesNotDoubleTheBenefit() {
        $this->game->era = 4;
        $this->game->setTapestryOnMat(2);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->token_location);
        $this->assertEquals([(string) BE_GAIN_FOOD, (string) BE_VP_TILES], $this->game->drainLabels());
    }

    /** Income turn 5 doubles the benefit, not the move: one flicker, two resolutions of one spot. */
    function testIncomeTurn5GainsTheBenefitTwice() {
        $this->game->era = 5;
        $this->game->setTapestryOnMat(2);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_3", $this->game->token_location);
        $this->assertEquals(
            [(string) BE_GAIN_FOOD, (string) BE_VP_TILES, (string) BE_GAIN_FOOD, (string) BE_VP_TILES],
            $this->game->drainLabels()
        );
    }

    /** The four icon spot doubled: eight rows, and each copy of 342 stays its own choice. */
    function testIncomeTurn5OnTheFourIconSpot() {
        $this->game->era = 5;
        $this->game->setTapestryOnMat(1);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $this->assertEquals("civ_43_2", $this->game->token_location);
        $labels = $this->game->drainLabels();
        $this->assertEquals(8, count($labels));
        $this->assertEquals(2, count(array_keys($labels, (string) BE_VP_ANY_BUILDING)));
    }

    function testFlickerNotificationReportsTheCountAndTheNewSpot() {
        $this->game->token_location = "civ_43_7";
        $this->game->setTapestryOnMat(3);
        $this->useAbility(Faefolk::CHOICE_FLICKER_ONLY);

        $notif = $this->game->messages[0];
        $this->assertEquals("message", $notif["type"]);
        $this->assertStringContainsString('${count}', $notif["message"]);
        $this->assertStringContainsString('${spot}', $notif["message"]);
        $this->assertEquals(3, $notif["args"]["count"]);
        $this->assertEquals(3, $notif["args"]["spot"]);
    }

    function testUnknownChoiceIsRejected() {
        $this->assertAsserts("ERR:Faefolk:13", fn() => $this->faefolk()->moveCivCube(1, 9, "", []));
    }

    function testMissingTokenIsRejected() {
        $this->game->token_location = "";
        $this->assertAsserts("ERR:Faefolk:20", fn() => $this->faefolk()->flicker(1, 1));
    }

    function testDuplicateTokensAreRejected() {
        $this->game->token_location = "civ_43_1,civ_43_4";
        $this->assertAsserts("ERR:Faefolk:20", fn() => $this->faefolk()->flicker(1, 1));
    }

    function testTokenOutsideTheEllipseIsRejected() {
        $this->game->token_location = "civ_43_9";
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
