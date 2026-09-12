<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

/**
 * FORMAL_RULES CIV.PSIONICS.12: ALCHEMISTS and PSIONICS are mutually exclusive, a player who owns
 * one can never gain the other. dbPickCardsForLocation is the one choke point: on any civilization
 * draw the forbidden partner is set aside and a replacement is drawn (never offered, never kept).
 */
final class IncompatibleCivsTest extends TestCase {
    private const ROLLER = PsionicsUT::ROLLER;

    private function newGame(): PsionicsUT {
        $game = new PsionicsUT();
        $game->init();
        return $game;
    }

    /** Put a civilization card at a known depth so a draw is deterministic (higher arg = drawn first). */
    private function stockCiv(PsionicsUT $game, int $civ_id, int $depth): void {
        $game->addCard(CARD_CIVILIZATION, "deck_civ", $depth, $civ_id);
    }

    private function drawnCivIds(PsionicsUT $game): array {
        return array_map(
            fn($card) => (int) $card["card_type_arg"],
            array_values($game->getCardsSearch(CARD_CIVILIZATION, null, "draw", self::ROLLER))
        );
    }

    private function discardedCivIds(PsionicsUT $game): array {
        return array_map(
            fn($card) => (int) $card["card_type_arg"],
            array_values($game->getCardsSearch(CARD_CIVILIZATION, null, "discard"))
        );
    }

    private function messageCount(PsionicsUT $game, string $fragment): int {
        return count(array_filter($game->notificationsOfType("message"), fn($notif) => str_contains($notif["log"], $fragment)));
    }

    // ------------------------------------------------------ forbidden mapping

    function testForbiddenCivsMirrorTheOwnedPartner() {
        $game = $this->newGame();
        $this->assertEquals([], $game->getForbiddenCivs(self::ROLLER));

        $game->giveCiv(self::ROLLER, CIV_ALCHEMISTS);
        $this->assertEquals([CIV_PSIONICS], $game->getForbiddenCivs(self::ROLLER));

        $other = $this->newGame();
        $other->giveCiv(self::ROLLER, CIV_PSIONICS);
        $this->assertEquals([CIV_ALCHEMISTS], $other->getForbiddenCivs(self::ROLLER));
    }

    // ------------------------------------ random single gain (awardCard path)

    /** An ALCHEMISTS owner is not a sampler, so BE_GAIN_CIV draws one civ; PSIONICS is redrawn past. */
    function testAnAlchemistsOwnerRedrawsPastPsionicsOnARandomGain() {
        $game = $this->newGame();
        $game->giveCiv(self::ROLLER, CIV_ALCHEMISTS);
        $this->stockCiv($game, CIV_PSIONICS, 2); // on top, would be drawn
        $this->stockCiv($game, CIV_ARCHITECTS, 1); // the replacement

        $this->assertTrue($game->resolveRow(BE_GAIN_CIV));

        $this->assertEquals([CIV_ARCHITECTS], $this->drawnCivIds($game));
        $this->assertEquals([CIV_PSIONICS], $this->discardedCivIds($game));
        $this->assertFalse($game->hasCiv(self::ROLLER, CIV_PSIONICS));
    }

    // -------------------------------------- draw and keep (choice path filter)

    /** An ALCHEMISTS owner drawing 3 to keep 1 (INFILTRATORS/row 172) is never offered PSIONICS. */
    function testAnAlchemistsOwnerIsNeverOfferedPsionicsInADrawAndKeep() {
        $game = $this->newGame();
        $game->giveCiv(self::ROLLER, CIV_ALCHEMISTS);
        $this->stockCiv($game, CIV_PSIONICS, 4);
        $this->stockCiv($game, CIV_ARCHITECTS, 3);
        $this->stockCiv($game, CIV_TRADERS, 2);
        $this->stockCiv($game, CIV_CHOSEN, 1); // the replacement drawn instead of PSIONICS

        $this->assertFalse($game->resolveRow(172));

        $drawn = $this->drawnCivIds($game);
        $this->assertCount(3, $drawn);
        $this->assertNotContains(CIV_PSIONICS, $drawn);
        $this->assertEquals([CIV_PSIONICS], $this->discardedCivIds($game));
    }

    /** A PSIONICS owner drawing 2 to keep 1 (BE_PSIONICS_CIV) is never offered ALCHEMISTS. */
    function testAPsionicsOwnerIsNeverOfferedAlchemistsInADrawAndKeep() {
        $game = $this->newGame();
        $game->sample(); // ROLLER owns PSIONICS
        $this->stockCiv($game, CIV_ALCHEMISTS, 3);
        $this->stockCiv($game, CIV_ARCHITECTS, 2);
        $this->stockCiv($game, CIV_TRADERS, 1); // the replacement drawn instead of ALCHEMISTS

        $this->assertFalse($game->resolveRow(BE_PSIONICS_CIV));

        $drawn = $this->drawnCivIds($game);
        $this->assertCount(2, $drawn);
        $this->assertNotContains(CIV_ALCHEMISTS, $drawn);
        $this->assertEquals([CIV_ALCHEMISTS], $this->discardedCivIds($game));
    }

    /** Row 173 discards the drawn civ and gains another, which the same choke point filters. */
    function testGainAnotherCivilizationNeverOffersTheForbiddenPartner() {
        $game = $this->newGame();
        $game->giveCiv(self::ROLLER, CIV_ALCHEMISTS);
        $game->addCard(CARD_CIVILIZATION, "draw", self::ROLLER, CIV_TRADERS); // the one being discarded
        $this->stockCiv($game, CIV_PSIONICS, 2);
        $this->stockCiv($game, CIV_ARCHITECTS, 1);

        $this->assertTrue($game->resolveRow(173));

        $this->assertEquals([CIV_ARCHITECTS], $this->drawnCivIds($game));
        $this->assertContains(CIV_PSIONICS, $this->discardedCivIds($game));
        $this->assertContains(CIV_TRADERS, $this->discardedCivIds($game));
    }

    // -------------------------------------------------------------- regression

    /** A player owning neither of the pair draws the very same card, unfiltered. */
    function testAPlayerOwningNeitherDrawsTheForbiddenCivNormally() {
        $game = $this->newGame();
        $this->stockCiv($game, CIV_PSIONICS, 2);
        $this->stockCiv($game, CIV_ARCHITECTS, 1);

        $this->assertTrue($game->resolveRow(BE_GAIN_CIV));

        $this->assertEquals([CIV_PSIONICS], $this->drawnCivIds($game));
        $this->assertEquals([], $this->discardedCivIds($game));
    }

    // ---------------------------------------------------------- deck exhausted

    /** When the forbidden partner is the only civ left the gain resolves short: no crash, no loop. */
    function testTheGainResolvesShortWhenOnlyTheForbiddenPartnerRemains() {
        $game = $this->newGame();
        $game->giveCiv(self::ROLLER, CIV_ALCHEMISTS);
        $this->stockCiv($game, CIV_PSIONICS, 1); // the only card in the deck

        $this->assertTrue($game->resolveRow(BE_GAIN_CIV));

        $this->assertEquals([], $this->drawnCivIds($game));
        $this->assertFalse($game->hasCiv(self::ROLLER, CIV_PSIONICS));
        $this->assertEquals([CIV_PSIONICS], $this->discardedCivIds($game));
    }

    /**
     * A draw whose first pick is already short and whose incompatible redraw empties the deck too
     * used to log the reshuffle/insufficient message once per pass; the whole draw prints each once.
     */
    function testTheDrainedRedrawLogsReshuffleAndInsufficientOnce() {
        $game = $this->newGame();
        $game->sample(); // ROLLER owns PSIONICS, forbidden partner is ALCHEMISTS
        $this->stockCiv($game, CIV_ALCHEMISTS, 1); // the only card, and it is the forbidden one

        $this->assertTrue($game->resolveRow(BE_PSIONICS_CIV)); // draw 2 keep 1

        $this->assertEquals([], $this->drawnCivIds($game));
        $this->assertEquals(1, $this->messageCount($game, "reshuffled"));
        $this->assertEquals(1, $this->messageCount($game, "Insufficient"));
    }
}
