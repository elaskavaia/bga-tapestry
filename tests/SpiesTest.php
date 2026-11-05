<?php

declare(strict_types=1);

use Bga\GameFramework\VisibleSystemException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/**
 * SPIES: "gain the corresponding benefit from a neighboring player of your choice" - the tapestry
 * slot (benefit 193) copies the WHEN PLAYED benefit of a neighbour's tapestry card.
 */
final class SpiesTest extends TestCase {
    private MapUT $game;

    protected function setUp(): void {
        $this->game = new MapUT(2);
        $this->game->doAdjustMaterial(2, 1);
        $this->game->init();
        $this->game->giveCiv(MapUT::OWNER, CIV_SPIES);
    }

    /** BGA #102790, fixed by e74f344 dropping the Revolution check from action_standup. */
    function testSpiesCopyOfRevolutionStandsUpToppledOutposts() {
        $game = $this->game;
        $revolution = $game->addCard(CARD_TAPESTRY, "era2", MapUT::OPPONENT, 35);
        $mine = $game->addOutpostAt(MapUT::OWNER, "2_0", 1);

        $game->queueBenefitNormal(193, MapUT::OWNER);
        $game->effect_keepCard([$revolution], MapUT::OWNER, $game->getCurrentBenefitWithInfo());
        $game->clearCurrentBenefit(193);
        $game->gamestate->jumpToState(19);
        $game->action_choose_benefit(BE_STANDUP_3_OUTPOSTS, 0);
        $game->gamestate->jumpToState(22);
        $game->action_standup([$mine]);

        $this->assertEquals(0, $game->toppleFlag($mine));
        // the guard e74f344 removed raised the exact error of the report
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("expecting tapestry 35 but it was none");
        $game->isTapestryActive(MapUT::OWNER, 35, true);
    }

    /**
     * BGA #254612: on 5 players only the two seats beside the Spies are offered, and a pick from
     * across the table is the "invalid card selected" of the report. The client let it be clicked.
     */
    function testFivePlayersOffersOnlyNeighboursCards() {
        $game = new MapUT(5);
        $game->doAdjustMaterial(5, 1);
        $game->init();
        $game->giveCiv(MapUT::OWNER, CIV_SPIES);
        $left = $game->addCard(CARD_TERRITORY, "hand", MapUT::OPPONENT, 1);
        $right = $game->addCard(CARD_TERRITORY, "hand", MapUT::OWNER + 4, 2);
        $across = $game->addCard(CARD_TERRITORY, "hand", MapUT::OTHER, 3);

        $game->queueBenefitNormal(191, MapUT::OWNER);

        $this->assertEqualsCanonicalizing([$left, $right], array_keys($game->getKeepCards(191, MapUT::OWNER)));
        $this->expectException(VisibleSystemException::class);
        $this->expectExceptionMessage("invalid card selected");
        $game->effect_keepCard([$across], MapUT::OWNER, $game->getCurrentBenefitWithInfo());
    }
}
