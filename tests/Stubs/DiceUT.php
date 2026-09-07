<?php

declare(strict_types=1);

require_once __DIR__ . "/GameUT.php";

/**
 * Harness for the flows that roll a die: player ROLLER is active in the benefit manager state,
 * and the raw SQL the roll flows read (tile benefit, active tapestries) is scripted by the test.
 */
class DiceUT extends GameUT {
    const ROLLER = 1;
    const OTHER = 2;

    /** getTileBenefit is raw SQL over the map table, which the harness does not model. */
    public array $tileBenefit = [];
    /** Tapestry ids isTapestryActive() answers true for, the card table is not modelled here. */
    public array $tapestries = [];

    function init() {
        parent::init();
        $this->gamestate->changeActivePlayer(self::ROLLER);
        $this->gamestate->jumpToState(18);
    }

    function getTileBenefit() {
        return $this->tileBenefit;
    }

    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        return in_array($tapestry_id, $this->tapestries) ? ["card_type_arg" => $tapestry_id] : null;
    }

    /** The conquer flow reads the map and the outpost pool with raw SQL, neither is modelled. */
    function getMapHexData($xcoords, $map = null) {
        return ["map_owners" => []];
    }

    /** Same again: the real one reads the structure table with raw SQL. */
    function getOutpostsInHand($player_id) {
        return $this->getStructuresSearch(BUILDING_OUTPOST, null, "hand", $player_id);
    }

    /** Same again, without the notification: the real one moves the row with raw SQL. */
    function effect_placeOnMap($player_id, $structure_id, $location, $notif = "*", $ownership = true) {
        $this->structures->setLocation((int) $structure_id, $location);
    }

    function stateName(): string {
        return $this->gamestate->state()["name"];
    }

    /** Flags action_research_decision reads off the row that is on the stack in the research state. */
    function pendingFlags(): int {
        $row = $this->getCurrentBenefit();
        return (int) array_get_def($this->benefit_types, (int) $row["benefit_type"], "flags", 0);
    }

    /** Queue a row for the roller and hand it to awardBenefits once, the way one stBenefitManager pop does. */
    function resolveRow(int $ben, int $count = 1): bool {
        $this->queueBenefitNormal($ben, self::ROLLER);
        $row = $this->benefitQueue()[0];
        $done = (bool) $this->awardBenefits(self::ROLLER, $ben, $count, $row["benefit_data"]);
        if ($done) {
            $this->benefitCashed($row["benefit_id"]);
        }
        return $done;
    }

    /** Pop standard rows the way stBenefitManager does, until one changes state or the stack ends. */
    function runManager(int $limit = 10): void {
        for ($i = 0; $i < $limit; $i++) {
            $row = $this->benefitQueue()[0] ?? null;
            if (!$row || $row["benefit_category"] != "standard") {
                return;
            }
            $done = $this->awardBenefits(
                (int) $row["benefit_player_id"],
                (int) $row["benefit_type"],
                (int) $row["benefit_quantity"],
                $row["benefit_data"]
            );
            if (!$done) {
                return;
            }
            $this->benefitCashed($row["benefit_id"]);
        }
        throw new Exception("benefit stack did not settle: " . implode(",", $this->benefitLabels()));
    }
}
