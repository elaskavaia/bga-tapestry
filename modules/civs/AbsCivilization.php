<?php

declare(strict_types=1);

abstract class AbsCivilization {
    protected int $civ; // numeric civ type
    public PGameXBody $game; // game ref

    public function __construct(int $civ, object $game) {
        $this->game = $game;
        if ($civ == 0) {
            throw new feException("Invalid civ id 0");
        }
        $this->civ = $civ;
    }

    function getType() {
        return $this->civ;
    }

    function getCivInfo() {
        $civ = $this->civ;
        return $this->game->civilizations[$civ];
    }

    function getRules($field = "benefit", $def = null) {
        return array_get($this->getCivInfo(), $field, $def);
    }

    function finalScoring($player_id) {
        // nothing by default
    }

    function hasActivatedAbilities($player_id) {
        return false;
    }

    function action_activatedAbility($player_id, $ability, $arg, &$state) {}

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        if (!$start) {
            $midgame_setup = $this->getRules("midgame_setup", false);
            if ($midgame_setup) {
                $this->game->benefitCivEntry($civ, $player_id, "midgame");
            }
        }
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        return ["tokens" => $tokens, "outposts" => []];
    }

    function hasCiv($player_id) {
        return $this->game->hasCiv($player_id, $this->civ);
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $this->systemAssertTrue("ERR:AbsCivilization:11");
    }

    function triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance) {
        return true;
    }

    /**
     * Copy of translation function for conviniene, this can only be user for error message in exceptions
     */
    function _($str) {
        return $this->game->_($str);
    }

    function debugConsole($info, $args = [], $silent = false) {
        $this->game->debugConsole($info, $args, $silent);
    }
    function systemAssertTrue($log, $cond = false, $logonly = "") {
        $this->game->systemAssertTrue($log, $cond, $logonly);
    }

    function getSingleCube(int $player_id = 0) {
        $cubes = $this->game->getStructuresOnCiv($this->civ, BUILDING_CUBE);
        $keys = array_keys($cubes);
        $cube = array_shift($keys);
        if ($cube) {
            return $cube;
        }
        if ($player_id) {
            return $this->game->addCube($player_id, "hand");
        }
        $this->systemAssertTrue("ERR:AbsCivilization:01", $cube);
        return $cube;
    }

    function getAllCubesOnCiv() {
        $civ = $this->civ;
        return $this->game->getStructuresOnCiv($civ, BUILDING_CUBE);
    }

    function getCivSlot(int $slot) {
        $civ = $this->civ;
        return "civ_{$civ}_$slot";
    }

    function placeCivCube($player_id, int $spot, int $cube_id = 0, int $state = null, string $message = "*") {
        $civ = $this->civ;
        $civ_token_string = "civ_{$civ}_$spot";

        $this->systemAssertTrue("Civ slot occupied $civ_token_string", $this->game->getStructureOnCivSlot($civ, $spot) == null);

        if ($state === null) {
            $state = $this->game->getCurrentEra($player_id);
        }

        // find free cube
        if ($cube_id === 0) {
            $cube_id = (int) $this->game->addCube($player_id, "hand");
        }

        if ($message == "*") {
            $message = clienttranslate('${player_name} advances on their civilization mat');
        }

        // UPDATE cube
        $this->game->dbSetStructureLocation($cube_id, $civ_token_string, $state, $message, $player_id);

        return $cube_id;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        return [];
    }

    function populateSlotChoiceForArgs(array &$data) {
        $civ = $this->civ;
        $game = $this->game;
        $slots = $this->getRules("slots");
        $slot_choice = $this->getRules("slot_choice");
        $data["slots_choice"] = [];
        if ($slots) {
            $only = [];
            if ($slot_choice == "unoccupied") {
                $token_data = $game->getStructuresOnCiv($civ, BUILDING_CUBE);
                foreach ($token_data as $token) {
                    $slot = getPart($token["card_location"], 2);
                    unset($slots[$slot]);
                }
            }
            if ($slot_choice == "occupied") {
                $token_data = $game->getStructuresOnCiv($civ, BUILDING_CUBE);
                foreach ($token_data as $token) {
                    $slot = getPart($token["card_location"], 2);
                    $only[$slot] = 1;
                }
            }
            if ($slot_choice) {
                foreach ($slots as $i => $info) {
                    if (count($only) == 0 || isset($only[$i])) {
                        $data["slots_choice"][$i]["benefit"] = $info["benefit"];
                    }
                }
                if (count($slots) > 1) {
                    $data["title"] = clienttranslate("Choose one of these options");
                }
            }
        }
        $income_trigger = $this->getRules("income_trigger", null);
        $decline = array_get($income_trigger, "decline", true);
        $data["decline"] = $decline;
        return $slots;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        return true; // cleanup
    }

    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        $civ = $this->civ;
        if ($civ == CIV_UTILITARIENS && $this->game->isAdjustments8() && $incomeTurn == 2) {
            $this->game->benefitCivEntry($civ, $player_id);
            return;
        }
        if (!$incomeTurn) {
            $incomeTurn = $this->game->getCurrentEra($player_id);
        }
        $income_trigger = array_get_def($this->game->civilizations, $civ, "income_trigger", null);
        if (!$income_trigger) {
            return;
        } // no income trigger
        $from = array_get($income_trigger, "from", 0);
        $to = array_get($income_trigger, "to", 0);
        if ($to == 0) {
            return;
        }
        switch ($civ) {
            case CIV_HISTORIANS: // HISTORIANS Discard territory tile to give token (when they gain landmark, you gain the exposed beneftits).
                $cubes = $this->game->getCollectionFromDB(
                    "SELECT card_location FROM structure WHERE card_location_arg='$player_id' AND  card_location LIKE 'civ_{$civ}\\_%'"
                );
                if (count($cubes) == 0) {
                    return;
                }
                break;
            case CIV_MILITANTS: // MILITANTS Gain exposed benefits at start of income turns
                if (in_range($incomeTurn, $from, $to)) {
                    $this->game->militantBenefits();
                }
                return;
            default:
                break;
        }
        if (in_range($incomeTurn, $from, $to)) {
            $this->game->benefitCivEntry($civ, $player_id);
        } else {
            $notactive = clienttranslate('${player_name}: ability of ${civ_name} is not applicable in era ${era}');
            $this->game->notifyWithName(
                "message",
                $notactive,
                [
                    "civ_name" => $this->game->getTokenName(CARD_CIVILIZATION, $civ),
                    "era" => $incomeTurn,
                ],
                $player_id
            );
        }
    }
}
