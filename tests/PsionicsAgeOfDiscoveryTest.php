<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

final class PsionicsAgeOfDiscoveryTest extends TestCase {
    /**
     * Pins buggy behavior for BGA #248665: the sampled face is rolled and announced, but the roller is
     * queued a forced advance on the first face only, so the choice is never offered. Flips when the fix lands.
     */
    function testAgeOfDiscoveryIgnoresTheSampledTrack() {
        $game = new PsionicsUT();
        $game->init();
        $game->sample();
        $game->seedRand(3, 1);

        $game->effect_ageOfDiscovery(PsionicsUT::ROLLER);

        $this->assertEquals(3, $game->getGameStateValue("science_die"));
        $this->assertEquals(1, $game->getGameStateValue("science_die_psionics"));
        $this->assertEquals([[PsionicsUT::ROLLER, BE_ADVANCE_M], [PsionicsUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT + 2]], $game->rows());
    }
}
