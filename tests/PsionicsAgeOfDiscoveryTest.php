<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/PsionicsUT.php";

class PsionicsAgeOfDiscoveryUT extends PsionicsUT {
    /** Tracks the roller has a token on, at the start; the cube lookup is raw SQL the harness does not model. */
    public array $tokenTracks = [1, 2, 3, 4];

    function dbGetCubesOnTrack($player_id, $track = null, $spot = null, $type_arg = null) {
        $cubes = [];
        foreach ($this->tokenTracks as $t) {
            if ((!$track || $t == $track) && !$spot) {
                $cubes[] = [
                    "card_id" => $t,
                    "card_location" => "tech_spot_{$t}_0",
                    "card_type_arg" => 0,
                    "card_location_arg" => $player_id,
                ];
            }
        }
        return $cubes;
    }

    /** Stands in for the roller's advance: the row it would resolve, on top of the stack. */
    function trackMovementProper($track, $spot, $change, $flags, $mandatory, $player_id) {
        $this->queueBenefitNormal(21 + $track, $player_id);
    }
}

/** BGA #248665: PSIONICS playing AGE OF DISCOVERY. */
final class PsionicsAgeOfDiscoveryTest extends TestCase {
    private PsionicsAgeOfDiscoveryUT $game;

    protected function setUp(): void {
        $this->game = new PsionicsAgeOfDiscoveryUT();
        $this->game->init();
        $this->game->sample();
    }

    private function playAgeOfDiscovery(int ...$faces): void {
        $this->game->seedRand(...$faces);
        $this->game->effect_ageOfDiscovery(PsionicsUT::ROLLER);
    }

    /** BGA #248665: the roller chooses between the two faces before anyone advances. */
    function testAgeOfDiscoveryOffersTheSampledTrack() {
        $this->playAgeOfDiscovery(3, 1);

        $this->assertEquals([[PsionicsUT::ROLLER, BE_ADVANCE_EXPLORATION_BENEFIT_OPT + 2]], $this->game->rows());
        $this->assertFalse($this->game->argResearch()["decline"]);
    }

    /** BGA #248665: everyone advances on the picked track, the opponents after the roller. */
    function testOpponentsAdvanceOnTheTrackPicked() {
        $game = $this->game;
        $this->playAgeOfDiscovery(3, 1);
        $game->runManager();
        $this->assertEquals("research", $game->stateName());

        $game->action_research_decision(1, 1);

        $this->assertEquals([[PsionicsUT::ROLLER, BE_ADVANCE_E], [PsionicsUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT]], $game->rows());
    }

    function testAgeOfDiscoveryCannotBeDeclined() {
        $game = $this->game;
        $this->playAgeOfDiscovery(3, 1);
        $game->runManager();

        $this->expectException(BgaUserException::class);
        $this->expectExceptionMessage("AGE OF DISCOVERY");
        $game->action_research_decision(0, 0);
    }

    /** A roller who quits at the pick still sends the opponents along the die's track. */
    function testZombieRollerStillAdvancesTheOpponents() {
        $game = $this->game;
        $this->playAgeOfDiscovery(3, 1);

        $game->effect_zombieBenefits(PsionicsUT::ROLLER);

        $this->assertEquals([[PsionicsUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT + 2]], $game->rows());
    }

    function testEmpiricismAlsoOffersTheChoice() {
        $game = $this->game;
        $game->stopSampling();
        $game->tapestries = [TAP_EMPIRICISM];
        $this->playAgeOfDiscovery(3, 1);

        $this->assertEquals([[PsionicsUT::ROLLER, BE_ADVANCE_EXPLORATION_BENEFIT_OPT + 2]], $game->rows());
    }

    /** With no token to move on either track the roller declines, and the opponents still advance on the die. */
    function testAgeOfDiscoveryDeclinesWithNowhereToAdvance() {
        $game = $this->game;
        $game->tokenTracks = [2, 4];
        $this->playAgeOfDiscovery(3, 1);
        $game->runManager();
        $this->assertTrue($game->argResearch()["decline"]);

        $game->action_research_decision(0, 0);

        $this->assertEquals([[PsionicsUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT + 2]], $game->rows());
    }

    function testTheSameFaceTwiceIsNoChoice() {
        $this->playAgeOfDiscovery(3, 3);

        $this->assertEquals(
            [[PsionicsUT::ROLLER, BE_ADVANCE_M], [PsionicsUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT + 2]],
            $this->game->rows()
        );
    }
}
