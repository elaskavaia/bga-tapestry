<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * The Historians send at the start of an income turn. The civ reads its tokens, tiles and the owned
 * civ cards with raw SQL while the rest of the game uses the in-memory model, so each such row goes
 * to both stores.
 */
final class HistoriansSendTest extends TestWithRealDb {
    const TERRITORY = 5;

    private GameUT $game;
    private int $historian;

    private function newGame(int $players, int $adjustments): GameUT {
        $this->game = new GameUT($players);
        $this->game->init();
        $this->game->doAdjustMaterial($players, $adjustments);
        $this->game->setGameStateValue("variant_adjustments", $adjustments);
        return $this->game;
    }

    private function mirrorStructure(int $id, int $player_id): void {
        $location = $this->game->structureLocation($id);
        $this->game->DbQuery(
            "INSERT INTO structure (card_id, card_type, card_type_arg, card_location, card_location_arg) VALUES ($id, '7', 0, '$location', $player_id)"
        );
    }

    /** The send itself, with the Historians civ benefit as the only row on the stack. */
    private function sendAtIncome(): void {
        $this->newGame(4, 8);
        $this->historian = $this->game->dbAddStructure(1, CIV_HISTORIANS, 0, "civ_7_1");
        $this->mirrorStructure($this->historian, 1);
        $territory = $this->game->addCard(CARD_TERRITORY, "hand", 1, self::TERRITORY);
        $this->game->DbQuery(
            "INSERT INTO card (card_id, card_type, card_type_arg, card_location, card_location_arg) VALUES ($territory, '1', " .
                self::TERRITORY .
                ", 'hand', 1)"
        );
        $this->game->benefitSingleEntry("civ", CIV_HISTORIANS, 1);
        $this->game->gamestate->jumpToState(14);
    }

    function testCivSendLeavesTheTransitionToTheAction() {
        $this->sendAtIncome();
        /** @var Historians */
        $historians = $this->game->getCivilizationInstance(CIV_HISTORIANS, true);
        $historians->sendHistorian(2, self::TERRITORY, 1);
        $this->assertEquals(14, $this->game->gamestate->getCurrentMainStateId());
        $this->assertEquals([], $this->game->benefitQueue());
    }

    /**
     * BGA #252235: the action transitioned twice, the second time from playerTurnEnd (36), which has
     * no "benefit" transition, so it threw and rolled back.
     */
    function testSendHistorianTransitionsOnce() {
        $this->sendAtIncome();
        $this->game->sendHistorian(2, self::TERRITORY, 1);
        $this->assertEquals(18, $this->game->gamestate->getCurrentMainStateId());
        $this->assertEquals([], $this->game->benefitQueue());
        $this->assertEquals(
            "pb_2",
            $this->game->getUniqueValueFromDB("SELECT card_location FROM structure WHERE card_id=$this->historian")
        );
        $this->assertEquals(0, $this->game->getCardCountInHand(1, CARD_TERRITORY));
    }

    /** The games where the card both grants the start tile and fires on income turn 1. */
    public static function startTileAndIncomeTurn1Games(): array {
        return [
            "4p pack" => [4, 8],
            "5p pack" => [5, 8],
            "4p pack, new alchemists" => [4, 9],
            "5p pack, new alchemists" => [5, 9],
            "4p experimental" => [4, 4],
            "5p experimental" => [5, 4],
        ];
    }

    /**
     * Verifies the fix for BGA #73556 #131466 #160624 #171977 #177610 #193150 #195672 #196736
     * #214486 #246956.
     *
     * Card text: "Start with 1 Territory tile ... At the beginning of your income turns ... (1-4) in
     * 4/5 player game, you may discard 1 territory tile from your supply to give a token".
     *
     * Same setup ordering as Islanders BGA #183142: every setupCiv() starts with interruptBenefit(),
     * so the start tile of a civ set up before another one sinks below the newest rows. The BE_RESUME
     * row that jumps to the first turn is queued last, so the tile is in hand before income turn 1
     * queues the civ ability.
     */
    #[DataProvider("startTileAndIncomeTurn1Games")]
    function testStartTileArrivesBeforeTheIncomeTurn1Send(int $players, int $adjustments) {
        $game = $this->newGame($players, $adjustments);
        $game->addCard(CARD_TERRITORY, $game->card_types[CARD_TERRITORY]["deck"], 0, self::TERRITORY);
        // stFinishSetup: setupCiv per civ card in hand, HISTORIANS not the last one processed
        $game->giveCiv(1, CIV_HISTORIANS);
        $game->DbQuery(
            "INSERT INTO card (card_type, card_type_arg, card_location, card_location_arg) VALUES ('5', " . CIV_HISTORIANS . ", 'hand', 1)"
        );
        $game->setupCiv(CIV_HISTORIANS, 1, true);
        foreach ($game->getStructuresOnCiv(CIV_HISTORIANS) as $token) {
            $this->mirrorStructure((int) $token["card_id"], 1);
        }
        $game->setupCiv(CIV_ENTERTAINERS, 2, true);
        $game->queueFirstTurn(1);
        $game->gamestate->jumpToState(18);

        $game->stBenefitManager();
        $this->assertEquals("playerTurn", $game->gamestate->state()["name"]);
        $game->stPlayerTurn();

        $this->assertEquals(1, $game->getCurrentEra(1));
        $this->assertEquals(["civ", "16", "129", "602"], $game->benefitLabels());
        $this->assertEquals(0, $game->benefitPosition("civ", CIV_HISTORIANS, 1));
        $this->assertEquals(1, $game->getCardCountInHand(1, CARD_TERRITORY));
    }
}
