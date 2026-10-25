<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

final class IslandersTest extends TestCase {
    public $game;

    protected function setUp(): void {
        $this->game = new GameUT(5);
        $this->game->init();
    }

    function testIslandersMaterialUnderAdjustmentPack() {
        $game = $this->game;
        $game->doAdjustMaterial(5, 8);
        $islanders = $game->civilizations[CIV_ISLANDERS];
        $this->assertEquals(["m" => 4, "g" => BE_TERRITORY], $islanders["start_benefit"]);
        $this->assertEquals(["from" => 1, "to" => 5], $islanders["income_trigger"]);
    }

    /**
     * Verifies the fix for BGA #183142 (5 players, Civilization Adjustments = 8).
     *
     * stFinishSetup calls setupCiv() per player, and every call starts with interruptBenefit(),
     * so each player's start_benefit rows are pushed one prerequisite further back. The BE_RESUME
     * row that jumps to the first turn is queued last, so the setup drain grants the start tiles of
     * every player before income turn 1 queues the Islanders explore prompt.
     */
    function testIslandersStartTilesArriveBeforeIncomeTurn1Trigger() {
        $game = $this->game;
        $game->doAdjustMaterial(5, 8);
        // 4 for the Islanders, 2 for the Traders
        for ($i = 1; $i <= 6; $i++) {
            $game->addCard(CARD_TERRITORY, $game->card_types[CARD_TERRITORY]["deck"], 0, $i);
        }

        // stFinishSetup: setupCiv per player, Islanders processed before another player
        $game->setupCiv(CIV_ISLANDERS, 1, true);
        $game->setupCiv(CIV_TRADERS, 2, true);
        $game->queueFirstTurn(1);

        $tiles = $game->benefitPosition("standard", BE_TERRITORY, 1);
        $resume = $game->benefitPosition("standard", BE_RESUME, 1);
        $this->assertTrue($tiles >= 0, "start tiles are queued at setup");
        $this->assertTrue($resume > $tiles, "BGA #183142: setup drain reaches the Islanders start tiles");

        $game->gamestate->jumpToState(18);
        $game->stBenefitManager();
        $this->assertEquals("playerTurn", $game->gamestate->state()["name"]);
        $this->assertEquals(4, $game->getCardCountInHand(1, CARD_TERRITORY));
        $this->assertEquals(2, $game->getCardCountInHand(2, CARD_TERRITORY));

        // First income turn of that player: queueIncomeTurn() -> queueEraCivAbilities()
        $game->queueEraCivAbility(CIV_ISLANDERS, 1, 1);
        $this->assertEquals(0, $game->benefitPosition("civ", CIV_ISLANDERS, 1));
        $this->assertEquals(-1, $game->benefitPosition("standard", BE_TERRITORY, 1));
    }
}
