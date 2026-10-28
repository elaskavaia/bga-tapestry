<?php

declare(strict_types=1);

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * removeSomeComponents on a real MySQL. GameUT moves cards in memory, so it never builds the
 * IN () list that MySQL rejects when an option removes a card the deck does not have.
 */
final class GameOptionsDbTest extends TestWithRealDb {
    /** BGA #252974: solo leaves Marriage of State out of the deck, removing it by option crashed setup. */
    function testRemovingCardAbsentFromDeckDoesNotCrashSetup(): void {
        $game = new class extends GameUT {
            function dbMoveCards(array $card_ids, string $location, int $location_arg): void {
                PGameXBody::dbMoveCards($card_ids, $location, $location_arg);
            }
        };
        $game->init();
        $game->gamestate->table_globals = [101 => "1"];

        $game->removeSomeComponents();

        $this->assertNotNull($game->notificationLike("removed from the game"));
    }
}
