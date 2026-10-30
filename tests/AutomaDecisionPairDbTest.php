<?php

declare(strict_types=1);

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * The Automa decision pair draw. It numbers the two new cards with raw SQL, so each card row goes to
 * both stores.
 */
final class AutomaDecisionPairDbTest extends TestWithRealDb {
    private GameUT $game;

    private function addCard(int $type, string $location, int $location_arg, int $type_arg): int {
        $id = $this->game->addCard($type, $location, $location_arg, $type_arg);
        $this->game->DbQuery(
            "INSERT INTO card (card_id, card_type, card_type_arg, card_location, card_location_arg) VALUES ($id, '$type', $type_arg, '$location', $location_arg)"
        );
        return $id;
    }

    /**
     * Studio auto-error "Invalid territory tile": the pick result is a list, so the draw numbered
     * cards 0 and 1 by key and silently moved card id 1, a player's territory tile, to player 1.
     */
    function testDecisionDrawOnlyNumbersTheDrawnCards() {
        $this->game = new GameUT();
        $this->game->init();
        $tile = $this->addCard(CARD_TERRITORY, "hand", 2, 4);
        $this->assertEquals(1, $tile, "the old key bug only hits card id 1");
        $first = $this->addCard(CARD_DECISION, "deck_decision", 2, 5);
        $second = $this->addCard(CARD_DECISION, "deck_decision", 1, 6);

        $this->assertEquals(2, $this->game->effect_drawDecisionPair(PLAYER_AUTOMA));

        $this->assertEquals(
            [$tile => 2, $first => 0, $second => 1],
            array_map("intval", $this->game->getCollectionFromDB("SELECT card_id, card_location_arg FROM card", true))
        );
    }
}
