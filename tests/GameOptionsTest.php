<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * The "Remove" game options (101 Marriage of State, 102 Renaissance) park the matching tapestry
 * card in limbo during setup, driven by the option_removals map in material.
 */
final class GameOptionsTest extends TestCase {
    private GameUT $game;
    private int $marriage;
    private int $renaissance;

    protected function setUp(): void {
        $this->game = new GameUT(2);
        $this->game->init();
        $this->marriage = $this->game->addCard(CARD_TAPESTRY, "deck_tapestry", 0, 23);
        $this->renaissance = $this->game->addCard(CARD_TAPESTRY, "deck_tapestry", 0, 33);
    }

    private function location(int $card_id): string {
        return $this->game->getCardInfoById($card_id, true)["location"];
    }

    public function testNoOptionsKeepsEveryCard(): void {
        $this->game->gamestate->table_globals = [];
        $this->game->removeSomeComponents();
        $this->assertSame("deck_tapestry", $this->location($this->marriage));
        $this->assertSame("deck_tapestry", $this->location($this->renaissance));
        $this->assertSame([], $this->game->notifications());
    }

    public function testKeepValueLeavesCardInDeck(): void {
        $this->game->gamestate->table_globals = [101 => "0", 102 => "0"];
        $this->game->removeSomeComponents();
        $this->assertSame("deck_tapestry", $this->location($this->marriage));
        $this->assertSame("deck_tapestry", $this->location($this->renaissance));
    }

    public function testRemoveMarriageOfStateOnly(): void {
        $this->game->gamestate->table_globals = [101 => "1"];
        $this->game->removeSomeComponents();
        $this->assertSame("limbo", $this->location($this->marriage));
        $this->assertSame("deck_tapestry", $this->location($this->renaissance));
        $notif = $this->game->notificationLike("removed from the game");
        $this->assertSame("MARRIAGE OF STATE", $notif["args"]["card_name"]);
    }

    public function testRemoveBothCards(): void {
        $this->game->gamestate->table_globals = [101 => "1", 102 => "1"];
        $this->game->removeSomeComponents();
        $this->assertSame("limbo", $this->location($this->marriage));
        $this->assertSame("limbo", $this->location($this->renaissance));
        $this->assertCount(2, $this->game->notificationsOfType("message"));
    }
}
