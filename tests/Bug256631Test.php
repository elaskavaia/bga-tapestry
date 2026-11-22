<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/MapUT.php";

/** BGA #256631: TANKS conquer offered only an empty neighbour, not one held by a single opponent outpost. */
final class Bug256631Test extends TestCase {
    function testTanksOffersAnAdjacentSingleOpponentOutpostInAFourPlayerGame() {
        $game = new MapUT(4);
        $game->doAdjustMaterial(4, 8);
        $game->init();
        $fourth = MapUT::OTHER + 1;
        $game->addOutpostAt(MapUT::OWNER, "2_0");
        $game->addOutpostAt(MapUT::OPPONENT, "3_0");
        $game->allyWith(MapUT::OTHER, $fourth);

        $found = $game->getConquerTargets(false, false, MapUT::OWNER);

        $this->assertContains("3_0", $found);
    }
}
