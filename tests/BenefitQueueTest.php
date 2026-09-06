<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

class BenefitQueueUT extends GameUT {
    const OWNER = 11;
    const OPPONENT = 12;

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::OWNER, self::OWNER + $players - 1), []));
        $this->curid = self::OWNER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(2);
    }

    /** [player_id, type] of every pending row, in pop order. */
    function rows(): array {
        return array_map(fn($row) => [(int) $row["benefit_player_id"], (int) $row["benefit_type"]], $this->benefitQueue());
    }
}

/**
 * The two civ hooks the Fantasies and Futures work added around the benefit table, seen from a
 * game without one of those civs: effect_onQueueBenefit asks the civ a reason names before queuing
 * a row for a player who does not own it, and zombieTurn hands such rows to that civ before
 * dropping the quitter's rows. Neither may change anything for a civ that does not override them.
 */
final class BenefitQueueTest extends TestCase {
    private BenefitQueueUT $game;

    protected function setUp(): void {
        $this->game = new BenefitQueueUT();
        $this->game->init();
        $this->game->giveCiv(BenefitQueueUT::OWNER, CIV_HERALDS);
    }

    function testRowAnotherPlayersCivHandsToAnOpponentIsQueued() {
        $this->game->queueBenefitNormal(BE_GAIN_COIN, BenefitQueueUT::OPPONENT, reason_civ(CIV_HERALDS));

        $this->assertEquals([[BenefitQueueUT::OPPONENT, BE_GAIN_COIN]], $this->game->rows());
    }

    function testRowsWithoutACivReasonAreQueued() {
        $this->game->queueBenefitNormal(BE_GAIN_COIN, BenefitQueueUT::OPPONENT, reason_tapestry(12));
        $this->game->queueBenefitNormal(BE_GAIN_CULTURE, BenefitQueueUT::OPPONENT);

        $this->assertEquals([[BenefitQueueUT::OPPONENT, BE_GAIN_COIN], [BenefitQueueUT::OPPONENT, BE_GAIN_CULTURE]], $this->game->rows());
    }

    function testQuitterLosesOnlyTheirOwnRows() {
        $this->game->queueBenefitNormal(BE_GAIN_COIN, BenefitQueueUT::OWNER);
        $this->game->queueBenefitNormal(BE_GAIN_COIN, BenefitQueueUT::OPPONENT, reason_civ(CIV_HERALDS));
        $this->game->queueBenefitNormal(BE_GAIN_CULTURE, BenefitQueueUT::OPPONENT);

        $this->game->effect_zombieBenefits(BenefitQueueUT::OPPONENT);

        $this->assertEquals([[BenefitQueueUT::OWNER, BE_GAIN_COIN]], $this->game->rows());
    }
}
