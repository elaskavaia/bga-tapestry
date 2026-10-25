<?php

declare(strict_types=1);

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * The sendHistorian action at the start of an income turn, with the Historians civ benefit as the
 * only row on the stack.
 */
final class HistoriansSendTest extends TestWithRealDb {
    const TERRITORY = 5;

    private GameUT $game;
    private int $historian;

    protected function setUp(): void {
        parent::setUp();
        $this->game = new GameUT(4);
        $this->game->init();
        $this->game->doAdjustMaterial(4, 8);
        $this->game->setGameStateValue("variant_adjustments", 8);
        // each row goes to both stores: the civ reads and moves them with raw SQL, the rest of the game uses the in-memory model
        $historian = $this->game->dbAddStructure(1, CIV_HISTORIANS, 0, "civ_7_1");
        $this->historian = $historian;
        $this->game->DbQuery(
            "INSERT INTO structure (card_id, card_type, card_type_arg, card_location, card_location_arg) VALUES ($historian, '7', 0, 'civ_7_1', 1)"
        );
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
        $this->game->sendHistorian(2, self::TERRITORY, 1);
        $this->assertEquals(18, $this->game->gamestate->getCurrentMainStateId());
        $this->assertEquals([], $this->game->benefitQueue());
        $this->assertEquals(
            "pb_2",
            $this->game->getUniqueValueFromDB("SELECT card_location FROM structure WHERE card_id=$this->historian")
        );
        $this->assertEquals(0, $this->game->getCardCountInHand(1, CARD_TERRITORY));
    }
}
