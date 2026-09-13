<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * TYRANNY: "THIS ERA: If you gain a Tapestry, you may immediately play it on top of this card."
 *
 * Every gain queues its own benefit 64 row, so a gain of two cards leaves two rows on a stack that
 * pops last in first out. Playing one of them covers TYRANNY, which is what makes the offer of the
 * other one meaningless.
 */
class TyrannyUT extends GameUT {
    const GAINED_FIRST = 8; // CAPITALISM, an era card with no when played benefit
    const GAINED_SECOND = 44; // WARTIME ECONOMY, same
    const IN_HAND = 26; // MILITARISM, in hand before the gain and never a TYRANNY target

    function __construct() {
        parent::__construct();
        $this->era = 2;
    }

    /** TYRANNY played into the current era slot, which is all isTapestryActive() reads. */
    function playTyranny(): int {
        return $this->addCard(CARD_TAPESTRY, "era2", 1, TAP_TYRANNY);
    }

    /** A tapestry card arriving in hand, the way awardCard does before it runs the trigger. */
    function gainTapestry(int $type): int {
        $card_id = $this->addCard(CARD_TAPESTRY, "hand", 1, $type);
        $this->effect_cardComesInPlay($card_id, 1, "");
        return $card_id;
    }
}

final class TyrannyTest extends TestCase {
    private TyrannyUT $game;

    protected function setUp(): void {
        $this->game = new TyrannyUT();
        $this->game->init();
        $this->game->doAdjustMaterial(2, 8);
        $this->game->playTyranny();
        $this->game->addCard(CARD_TAPESTRY, "hand", 1, TyrannyUT::IN_HAND);
    }

    private function tapestryOnTop(): int {
        return (int) $this->game->getLatestTapestry(1, 2)["card_type_arg"];
    }

    function testOneGainedCardIsPlayedOnTop() {
        $card_id = $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $this->game->stTapestryCard();
        $this->game->playTapestryCard($card_id, 1);

        $this->assertEquals(TyrannyUT::GAINED_FIRST, $this->tapestryOnTop());
        $this->assertEquals(5, $this->game->dbGetScore(1));
        $this->assertEquals([], $this->game->benefitLabels());
    }

    /** A card that was already in hand is not a gained card, so TYRANNY cannot take it. */
    function testACardFromHandIsRefused() {
        $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $in_hand = array_key_first($this->game->getCardsSearch(CARD_TAPESTRY, TyrannyUT::IN_HAND, "hand", 1));
        $this->game->stTapestryCard();

        $this->expectException(BgaUserException::class);
        $this->expectExceptionMessage("You can only play a just drawn card");
        $this->game->playTapestryCard($in_hand, 1);
    }

    /** Both cards of one gain are offered, not only the one whose row happens to pop first. */
    function testBothCardsOfADoubleGainAreOffered() {
        $first = $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $second = $this->game->gainTapestry(TyrannyUT::GAINED_SECOND);
        $this->game->stTapestryCard();

        $args = $this->game->argTapestryCard();

        $this->assertEquals([$second => TyrannyUT::GAINED_SECOND, $first => TyrannyUT::GAINED_FIRST], $args["tyranny_cards"]);
        $this->assertTrue($args["decline"]);
    }

    /**
     * The rows pop last in first out, so the row the player is asked about first is the one for the
     * card gained second. Playing the other one leaves that row behind with nothing to offer.
     */
    function testEitherCardOfADoubleGainCanBePlayedOnTop() {
        $first = $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $this->game->gainTapestry(TyrannyUT::GAINED_SECOND);
        $this->game->stTapestryCard();

        $this->game->playTapestryCard($first, 1);
        $this->game->stTapestryCard();

        $this->assertEquals(TyrannyUT::GAINED_FIRST, $this->tapestryOnTop());
        $this->assertEquals(5, $this->game->dbGetScore(1));
        $this->assertEquals([], $this->game->benefitLabels(), "a covered TYRANNY offers nothing, the row is void");
    }

    /**
     * HERALDS and MERFOLK queue the same benefit 64 with a reason instead of a card id, and a
     * TYRANNY row queued behind such a row must not turn it into a TYRANNY offer.
     */
    function testAnotherProducerOfTheSameRowIsNotATyrannyOffer() {
        $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $this->game->queueBenefitInterrupt(64, 1, reason_civ(CIV_HERALDS));
        $this->game->stTapestryCard();

        $args = $this->game->argTapestryCard();

        $this->assertArrayNotHasKey("tyranny_cards", $args);
        $in_hand = array_key_first($this->game->getCardsSearch(CARD_TAPESTRY, TyrannyUT::IN_HAND, "hand", 1));
        $this->game->playTapestryCard($in_hand, 1);
        $this->assertEquals(TyrannyUT::IN_HAND, $this->tapestryOnTop());
        $this->assertEquals(0, $this->game->dbGetScore(1), "no TYRANNY bonus for a row TYRANNY did not queue");
    }

    /** Playing on top of TYRANNY covers it, so the other row of the same gain has nothing to offer. */
    function testTheOtherRowOfADoubleGainIsDroppedOnceTyrannyIsCovered() {
        $this->game->gainTapestry(TyrannyUT::GAINED_FIRST);
        $second = $this->game->gainTapestry(TyrannyUT::GAINED_SECOND);
        $this->game->stTapestryCard();
        $this->game->playTapestryCard($second, 1);

        $this->assertEquals([(string) 64], $this->game->benefitLabels(), "the row for the card gained first is still queued");
        $this->game->stTapestryCard();

        $this->assertEquals([], $this->game->benefitLabels(), "a covered TYRANNY offers nothing, the row is void");
        $this->assertEquals(TyrannyUT::GAINED_SECOND, $this->tapestryOnTop());
        $this->assertEquals(5, $this->game->dbGetScore(1));
    }
}
