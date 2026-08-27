<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/Stubs/GameUT.php";

/**
 * Landmarks 13-19 share the landmark_mat_slot location prefix with the 12 advancement track
 * landmarks but are never claimable from a track, see BGA bug #203108.
 */
class LandmarksUT extends GameUT {
    public array $mat = [];

    function getStructuresSearch(
        $card_type,
        $card_type_arg = null,
        $card_location = null,
        $card_location_arg = null,
        $card_location_arg2 = null
    ) {
        if ($card_location === "landmark_mat_slot%") {
            return $this->mat;
        }
        return [];
    }

    public array $messages = [];

    function notifyWithName($type, $message = "", $args = null, $player_id = null) {
        $this->messages[] = $message;
    }

    function setMat(array $types) {
        $this->mat = [];
        foreach ($types as $type) {
            $this->mat[100 + $type] = [
                "card_id" => 100 + $type,
                "card_type" => BUILDING_LANDMARK,
                "card_location" => "landmark_mat_slot$type",
                "card_location_arg2" => "$type",
            ];
        }
    }
}

final class LandmarksTest extends TestCase {
    public $game;

    protected function setUp(): void {
        $this->game = new LandmarksUT();
        $this->game->init();
    }

    function testAllNineteenAreSeededButOnlyTwelveAreClaimable() {
        $this->game->setMat(range(1, 19));
        $left = $this->game->getUnclaimedTrackLandmarks();

        $this->assertEquals(range(1, 12), array_map("intval", array_column($left, "card_location_arg2")));
    }

    function testExtraPoolAloneLeavesNothingToClaim() {
        $this->game->setMat([13, 14, 15, 16, 17, 18, 19]);

        $this->assertEquals([], $this->game->getUnclaimedTrackLandmarks());
    }

    function testTierTwoSelectionIsLimitedToItsFourLandmarks() {
        $this->game->setMat(range(1, 19));
        $tier2 = $this->game->getUnclaimedTrackLandmarks([2, 6, 9, 10]);

        $this->assertEquals([2, 6, 9, 10], array_map("intval", array_column($tier2, "card_location_arg2")));
    }

    // argBuildingSelect hands the result straight to choices, which the client indexes by card id
    function testResultStaysKeyedByCardId() {
        $this->game->setMat([1, 13]);
        $left = $this->game->getUnclaimedTrackLandmarks();

        $this->assertEquals([101], array_keys($left));
    }

    /**
     * Benefit 111 used to accept any row on the mat while argBuildingSelect offered only 1-12, so
     * an extras-only mat opened a selection state with nothing to click.
     */
    function testDystopiaSkipsWhenOnlyTheExtraPoolIsLeft() {
        $this->game->setMat([13, 14, 15, 16, 17, 18, 19]);

        $this->assertTrue($this->game->awardBenefits(1, 111));
        $this->assertEquals(["No more landmarks left"], $this->game->messages);
    }
}
