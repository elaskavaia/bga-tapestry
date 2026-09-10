<?php

declare(strict_types=1);

require_once __DIR__ . "/MapUT.php";

/**
 * Harness for the income mat: a track laid out spot by spot, and a recorder in place of
 * awardBenefits so an income phase can be asserted as the list of tuples it pays out.
 */
class IncomeMatUT extends MapUT {
    /** [player_id, benefit, count, reason] per awardBenefits call: the fingerprint of an income phase. */
    public array $awarded = [];

    function awardBenefits($player_id, $ben, $count = 1, $reason = "") {
        $this->awarded[] = [(int) $player_id, (int) $ben, (int) $count, $reason];
        return true;
    }

    /** isTapestryActive() reads the card table with raw SQL, which the in memory model never sees. */
    public array $tapestries = [];

    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries[$player_id] ?? []);
    }

    /** A track as today's tables lay it out: spots 1..level uncovered, a building on every spot after. */
    function layoutPrefix(int $player_id, int $type, int $level): array {
        return $this->layoutTrack($player_id, $type, $level < 6 ? range($level + 1, 6) : []);
    }

    function layoutPrefixMat(int $player_id, int $markets = 1, int $houses = 1, int $farms = 1, int $armories = 1): void {
        $this->layoutPrefix($player_id, BUILDING_MARKET, $markets);
        $this->layoutPrefix($player_id, BUILDING_HOUSE, $houses);
        $this->layoutPrefix($player_id, BUILDING_FARM, $farms);
        $this->layoutPrefix($player_id, BUILDING_ARMORY, $armories);
    }

    /** An old table: six buildings per type were created and none carries a spot. */
    function layoutUnmigrated(int $player_id, int $type, int $level): array {
        $ids = [];
        for ($i = 0; $i < 7 - $level; $i++) {
            $ids[] = $this->dbAddStructure($player_id, $type, 0, "income", 0);
        }
        $this->income[$player_id][$type] = $level;
        return $ids;
    }

    function spotOf(int $structure_id): int {
        return (int) $this->getStructureInfoById($structure_id)["card_location_arg2"];
    }

    function traders(): Traders {
        return $this->getCivilizationInstance(CIV_TRADERS, true);
    }
}
