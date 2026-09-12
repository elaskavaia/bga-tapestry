<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

/**
 * Stage 2 of the PSIONICS plan: the civilization itself. Almost all of its behaviour is the engine
 * seam PsionicsSeamTest already pins; this file covers the thin civ class - the mat's fixed income
 * table - and the material entry, and that each income gain is itself sampled by the seam.
 */
final class PsionicsCivTest extends TestCase {
    private PsionicsUT $game;

    protected function setUp(): void {
        $this->game = $this->newGame();
    }

    private function newGame(): PsionicsUT {
        $game = new PsionicsUT();
        $game->init();
        $game->doAdjustMaterial(2, 8);
        $game->getCivilizationInstance(CIV_PSIONICS, true); // includes civs/Psionics.php
        return $game;
    }

    // ------------------------------------------------------------- material

    function testMaterialEntry() {
        $info = $this->game->civilizations[CIV_PSIONICS];

        $this->assertEquals("FF", $info["exp"]);
        $this->assertTrue($info["automa"]);
        $this->assertEquals(["from" => 2, "to" => 5, "decline" => false], $info["income_trigger"]);
        $this->assertEquals(Psionics::INCOME_ROWS, array_map(fn($slot) => $slot["benefit"][0], $info["slots"]));
    }

    // ------------------------------------------------------------- income

    /** The mat pays a fixed table, and every gain on it is itself a random gain the seam samples. */
    function testEachIncomeTurnQueuesItsOneRowWithTheCivReason() {
        foreach ([2 => BE_TERRITORY, 3 => BE_TAPESTRY, 4 => BE_INVENT, 5 => BE_RESEARCH] as $turn => $row) {
            $game = $this->newGame();
            $game->startIncomeTurn(PsionicsUT::ROLLER, $turn);
            $game->queueEraCivAbility(CIV_PSIONICS, PsionicsUT::ROLLER, $turn);

            $this->assertEquals([[PsionicsUT::ROLLER, $row]], $game->rows(), "turn $turn");
            $this->assertEquals(reason_civ(CIV_PSIONICS), $game->benefitQueue()[0]["benefit_data"], "turn $turn");
        }
    }

    function testIncomeTurnOneQueuesNothingAndPrintsNotApplicable() {
        $game = $this->game;
        $game->startIncomeTurn(PsionicsUT::ROLLER, 1);
        $game->queueEraCivAbility(CIV_PSIONICS, PsionicsUT::ROLLER, 1);

        $this->assertEquals([], $game->rows());
        $this->assertNotEmpty($game->notificationLike("not applicable"));
    }

    /** Income turn 2 is a territory gain, which the seam turns into a keep over two tiles. */
    function testIncomeTurnTwoEndsInAKeepCardOverTwoTerritoryTiles() {
        $game = $this->game;
        $game->sample();
        $game->fillDeck(CARD_TERRITORY, 4);
        $game->useIncomeAbility(2);

        $this->assertEquals("keepCard", $game->stateName());
        $this->assertCount(2, $game->getCardsSearch(CARD_TERRITORY, null, "draw", PsionicsUT::ROLLER));
        $this->assertCount(0, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
    }

    // --------------------------------------------------- finished or zombie

    /**
     * FORMAL_RULES CIV.PSIONICS.4: a finished owner generates no extra option. The income row is
     * dropped by checkAliveForBenefit before it is awarded, so no draw is ever queued.
     */
    function testAFinishedOwnersIncomeRowIsDroppedAndDrawsNothing() {
        $game = $this->game;
        $game->sample();
        $game->fillDeck(CARD_TERRITORY, 4);
        $game->startIncomeTurn(PsionicsUT::ROLLER, 2);
        $game->queueEraCivAbility(CIV_PSIONICS, PsionicsUT::ROLLER, 2);
        $this->assertEquals([[PsionicsUT::ROLLER, BE_TERRITORY]], $game->rows());

        $game->eras[PsionicsUT::ROLLER] = 6; // finished

        // the gate stBenefitManager applies before awarding: drop the row, never draw
        foreach ($game->benefitQueue() as $row) {
            $alive = $game->checkAliveForBenefit((int) $row["benefit_player_id"], (int) $row["benefit_type"], $row["benefit_category"]);
            $this->assertFalse($alive);
            $game->benefitCashed($row["benefit_id"]);
        }

        $this->assertEquals([], $game->rows());
        $this->assertCount(0, $game->getCardsSearch(CARD_TERRITORY, null, "draw", PsionicsUT::ROLLER));
    }

    // ------------------------------------------------------------- datas

    function testGetAllDatasCarriesTheSampledDiceFieldsAtZeroWithoutASampler() {
        $dice = $this->game->getAllDatas()["dice"];

        $this->assertEquals(0, (int) $dice["psionics"]);
        $this->assertEquals(0, (int) $dice["red2"]);
        $this->assertEquals(0, (int) $dice["black2"]);
    }
}
