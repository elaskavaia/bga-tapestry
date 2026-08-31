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
     * Documents current buggy behavior for BGA #183142 (5 players, Civilization Adjustments = 8);
     * both "BUG:" assertions flip when the ordering is fixed.
     *
     * stFinishSetup calls setupCiv() per player, and every call starts with interruptBenefit(),
     * so each player's start_benefit rows are pushed one prerequisite further back. The BE_RESUME
     * row queued at the end of stFinishSetup lands at prerequisite 0, and awardBenefits case 201
     * jumps to the player turn state - so the setup drain ends with the start_benefit rows of
     * every player except the last one processed still on the stack. When such a player is the
     * (randomly picked) starting player and opens with an income turn, queueIncomeTurn inserts the
     * income turn 1 civ trigger at prerequisite 0, ahead of their own stranded start tiles.
     */
    function testIslandersIncomeTurn1TriggerJumpsAheadOfStartTiles() {
        $game = $this->game;
        $game->doAdjustMaterial(5, 8);

        // stFinishSetup: setupCiv per player, Islanders processed before another player
        $game->setupCiv(CIV_ISLANDERS, 1, true);
        $game->setupCiv(CIV_TRADERS, 2, true);
        $game->queueBenefitNormal(BE_RESUME, 1);

        $tiles = $game->benefitPosition("standard", BE_TERRITORY, 1);
        $resume = $game->benefitPosition("standard", BE_RESUME, 1);
        $this->assertTrue($tiles >= 0, "start tiles are queued at setup");
        $this->assertTrue($resume >= 0, "BE_RESUME is queued at setup");
        // BUG: BE_RESUME pops first and ends the drain, stranding the start tiles.
        // Fixed version: $resume > $tiles.
        $this->assertTrue($resume < $tiles, "BGA #183142: setup drain stops before the Islanders start tiles");

        // First income turn of that player: queueIncomeTurn() -> queueEraCivAbilities()
        $game->queueEraCivAbility(CIV_ISLANDERS, 1, 1);

        $tiles = $game->benefitPosition("standard", BE_TERRITORY, 1);
        $trigger = $game->benefitPosition("civ", CIV_ISLANDERS, 1);
        $this->assertTrue($trigger >= 0, "income turn 1 civ trigger is queued");
        // BUG: the explore prompt is resolved before the 4 tiles are granted.
        // Fixed version: $trigger > $tiles.
        $this->assertTrue($trigger < $tiles, "BGA #183142: explore prompt precedes the start tiles");
    }
}
