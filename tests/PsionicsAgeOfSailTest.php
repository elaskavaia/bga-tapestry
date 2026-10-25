<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

class PsionicsAgeOfSailUT extends PsionicsUT {
    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries)
            ? ["card_type_arg" => $tapestry_id, "card_id" => 1, "card_location_arg2" => 0]
            : null;
    }

    function getExplorationTargets($anywhere = false, $player_id = null) {
        return ["0_0"];
    }
}

/** BGA #248010: PSIONICS with AGE OF SAIL active on an explore. */
final class PsionicsAgeOfSailTest extends TestCase {
    private PsionicsAgeOfSailUT $game;

    protected function setUp(): void {
        $this->game = new PsionicsAgeOfSailUT();
        $this->game->init();
        $this->game->sample();
        $this->game->tapestries = [TAP_AGE_OF_SAIL];
        $this->game->fillDeck(CARD_TERRITORY, 20);
    }

    private function keepAll(): void {
        $game = $this->game;
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals("keepCard", $game->stateName());
            $game->action_keepCard([$game->drawn(CARD_TERRITORY)[0]], null);
            $game->gamestate->jumpToState(18);
            $game->runManager();
        }
    }

    /** BGA #248010: the three keeps resolve before the explore, which then does not draw again. */
    function testAgeOfSailKeepsResolveBeforeTheExplore() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17));
        $game->runManager();

        $this->assertEquals("keepCard", $game->stateName());
        $roller_rows = array_fill(0, 3, [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY]);
        $this->assertEquals([...$roller_rows, [PsionicsUT::ROLLER, 17]], $game->rows());

        $this->keepAll();

        $this->assertEquals("explore", $game->stateName());
        $this->assertCount(3, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
        $this->assertEquals([[PsionicsUT::ROLLER, 17]], $game->rows());
        $this->assertFalse($game->argExplore(PsionicsUT::ROLLER)["void"]);
    }

    function testAgeOfSailWithoutPsionicsDrawsStraightIntoHand() {
        $game = $this->game;
        $game->stopSampling();
        $game->queueBenefitNormal(46, PsionicsUT::ROLLER, reason("be", 46));
        $game->runManager();

        $this->assertEquals("explore", $game->stateName());
        $this->assertCount(3, $game->getCardsInHand(PsionicsUT::ROLLER, CARD_TERRITORY));
        $this->assertEquals([[PsionicsUT::ROLLER, 46]], $game->rows());
    }

    /** BGA #248010: a second explore on the same row draws its own three. */
    function testAgeOfSailDrawsAgainForTheNextExploreOfTheRow() {
        $game = $this->game;
        $game->queueBenefitNormal(17, PsionicsUT::ROLLER, reason("be", 17), 2);
        $game->runManager();

        $quantities = array_map(fn($row) => [(int) $row["benefit_type"], (int) $row["benefit_quantity"]], $game->benefitQueue());
        $this->assertEquals([...array_fill(0, 3, [BE_PSIONICS_TERRITORY, 1]), [17, 1], [17, 1]], $quantities);

        $this->keepAll();
        $this->assertEquals("explore", $game->stateName());
        $game->benefitCashed($game->getCurrentBenefit()["benefit_id"]);
        $game->gamestate->jumpToState(18);
        $game->runManager();

        $this->assertEquals("keepCard", $game->stateName());
        $this->assertEquals([...array_fill(0, 3, [PsionicsUT::ROLLER, BE_PSIONICS_TERRITORY]), [PsionicsUT::ROLLER, 17]], $game->rows());
    }
}
