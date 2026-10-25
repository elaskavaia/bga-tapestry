<?php

declare(strict_types=1);

require_once __DIR__ . "/Stubs/GameUT.php";
require_once __DIR__ . "/Stubs/TestWithRealDb.php";

/**
 * queueFirstTurn on a real MySQL. GameUT keeps the benefit stack in memory, so the other setup tests
 * never run the prerequisite column of the INSERT nor the ORDER BY that finds the deepest setup row.
 */
final class SetupFirstTurnDbTest extends TestWithRealDb {
    function testResumeQueuedBelowTheDeepestSetupRow() {
        $game = new class extends GameUT {
            function benefitSingleEntry($cat, $type, $player_id, $quantity = 1, $data = "", $prerequisite = 0) {
                PGameXBody::benefitSingleEntry($cat, $type, $player_id, $quantity, $data, $prerequisite);
            }

            function dbGetBenefits() {
                return PGameXBody::dbGetBenefits();
            }
        };
        $game->init();
        // start rows of a civ set up earlier, pushed down by the next civ's interruptBenefit
        $game->DbQuery(
            "INSERT INTO benefit (benefit_category, benefit_type, benefit_prerequisite, benefit_player_id) VALUES ('standard', '" .
                BE_TERRITORY .
                "', 2, 1), ('standard', '" .
                BE_TAPESTRY .
                "', 0, 2)"
        );

        $game->queueFirstTurn(1);

        $this->assertEquals(
            [BE_TAPESTRY, BE_TERRITORY, BE_RESUME],
            array_map("intval", array_column($game->dbGetBenefits(), "benefit_type"))
        );
    }
}
