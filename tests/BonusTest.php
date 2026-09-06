<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

class BonusUT extends GameUT {
    const PLAYER = 11;

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::PLAYER, self::PLAYER + $players - 1), []));
        $this->curid = self::PLAYER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::PLAYER);
        $this->gamestate->jumpToState(2);
    }

    /** The real one counts the hand with raw SQL, which the in memory card model never sees. */
    function getPayResourceCount($pay_benefit_type, $player_id) {
        if ($pay_benefit_type == BE_TAPESTRY) {
            return $this->getCardCountInHand($player_id, CARD_TAPESTRY);
        }
        return parent::getPayResourceCount($pay_benefit_type, $player_id);
    }

    function giveTapestryCards(int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->addCard(CARD_TAPESTRY, "hand", self::PLAYER, 1);
        }
    }

    /** DEMOCRACY: after drawing 3 tapestry cards, discard any number of them for 2 VP each (PGameXBody::democracy). */
    function queueDemocracyBonus(): void {
        $this->queueBonus(BE_TAPESTRY, -1, "15,15", 0, self::PLAYER);
    }

    function enterBonusState(): void {
        $this->gamestate->jumpToState(35);
        $this->stBonus();
    }
}

/**
 * stBonus, the gate in front of the bonus state. Changed with the Elder Ones trade: a row the
 * player holds nothing to pay with is now dropped whatever its quantity, where before only a
 * positive quantity the player could not cover was. DEMOCRACY's unlimited row is the one
 * pre-existing producer that sees the difference.
 */
final class BonusTest extends TestCase {
    private BonusUT $game;

    protected function setUp(): void {
        $this->game = new BonusUT();
        $this->game->init();
    }

    /** Before the change this opened the bonus state with nothing but a Decline button. */
    function testUnlimitedRowIsDroppedWithNothingToPay() {
        $this->game->queueDemocracyBonus();
        $this->game->enterBonusState();

        $this->assertEquals([], $this->game->benefitLabels());
        $this->assertContains('${player_name} cannot pay for bonus ${reason}', $this->game->notificationTexts());
    }

    function testUnlimitedRowWaitsForThePlayerWithOneCard() {
        $this->game->giveTapestryCards(1);
        $this->game->queueDemocracyBonus();
        $this->game->enterBonusState();

        $this->assertEquals(["bonus"], $this->game->benefitLabels());
        $this->assertNotContains('${player_name} cannot pay for bonus ${reason}', $this->game->notificationTexts());
    }

    function testFixedRowIsDroppedWhenThePlayerIsShort() {
        $this->game->giveTapestryCards(1);
        $this->game->queueBonus(BE_TAPESTRY, 2, (string) BE_ANYRES, 0, BonusUT::PLAYER);
        $this->game->enterBonusState();

        $this->assertEquals([], $this->game->benefitLabels());
    }

    function testFixedRowWaitsWhenThePlayerCanPay() {
        $this->game->giveTapestryCards(2);
        $this->game->queueBonus(BE_TAPESTRY, 2, (string) BE_ANYRES, 0, BonusUT::PLAYER);
        $this->game->enterBonusState();

        $this->assertEquals(["bonus"], $this->game->benefitLabels());
    }
}
