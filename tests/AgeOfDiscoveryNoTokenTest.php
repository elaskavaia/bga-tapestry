<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/DiceUT.php";

/** BGA #58486 (comment by ukcody34, table 910048653): AGE OF DISCOVERY rolls a track an opponent has no token on. */
final class AgeOfDiscoveryNoTokenTest extends TestCase {
    private DiceUT $game;

    protected function setUp(): void {
        $this->game = new DiceUT();
        $this->game->init();
        // OTHER completed technology and moved the token to the start of exploration
        $this->game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_1_0");
        $this->game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_2_3");
        $this->game->dbAddStructure(DiceUT::OTHER, BUILDING_CUBE, 0, "tech_spot_3_3");
    }

    /** Documents buggy behavior: the opponent's advance throws instead of being void; flips when the fix lands. */
    function testOpponentWithNoTokenOnRolledTrackIsNotSkipped() {
        $game = $this->game;
        $game->queueAgeOfDiscoveryOpponents(DiceUT::ROLLER, 4);
        $row = $game->benefitQueue()[0];
        $this->assertEquals([DiceUT::OTHER, BE_ADVANCE_EXPLORATION_NOBENEFIT + 3], [(int) $row["benefit_player_id"], (int) $row["benefit_type"]]);

        $this->expectException(BgaUserException::class);
        $this->expectExceptionMessage("Has to be cubes on track 4");
        $this->expectOutputRegex("/Internal Error during move 0: Has to be cubes on track 4/");
        $game->runManager();
    }
}
