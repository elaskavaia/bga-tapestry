<?php

declare(strict_types=1);

abstract class AbsCivilization  {
    protected int $civ; // numeric civ type
    public PGameXBody $game; // game ref

    public function __construct(int $civ, object $game) {
        $this->game = $game;
        if ($civ == 0) throw new feException("Invalid civ id 0");
        $this->civ = $civ;
    }

    function getType() {
        return $this->civ;
    }

    function getCivInfo() {
        $civ = $this->civ;
        return $this->game->civilizations[$civ];
    }

    function getRules($field = 'benefit', $def = null) {
        return array_get($this->getCivInfo(), $field, $def);
    }

    function finalScoring($player_id) {
        // nothing by default
    }

    function hasActivatedAbilities($player_id) {
        return false;
    }

    function action_activatedAbility($player_id, $ability, $arg, &$state) {
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        if (!$start) {
            $midgame_setup = $this->getRules('midgame_setup', false);
            if ($midgame_setup)
                $this->game->benefitCivEntry($civ, $player_id, 'midgame');
        }
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function moveCivCube(int $player_id, bool $is_midgame, int $spot, $extra) {
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
    function systemAssertTrue($log, $cond = false) {
        $this->game->systemAssertTrue($log, $cond);
    }

    function getSingleCube() {
        $cubes = $this->game->getStructuresOnCiv($this->civ, BUILDING_CUBE);
        $cube = array_shift($cubes);
        $this->systemAssertTrue("ERR:AbsCivilization:01", $cube);
        return $cube;
    }

    function getCivSlot(int $slot) {
        $civ = $this->civ;
        return "civ_{$civ}_$slot";
    }

    function argCivAbilitySingle($player_id, $benefit) {
        return [];
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        return true;  // cleanup
    }

    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        $civ = $this->civ;
        if ($civ == CIV_UTILITARIENS && $this->game->isAdjustments8() && $incomeTurn == 2) {
            $this->game->benefitCivEntry($civ, $player_id);
            return;
        }
        if (!$incomeTurn)
            $incomeTurn = $this->game->getCurrentEra($player_id);
        $income_trigger = array_get_def($this->game->civilizations, $civ, 'income_trigger', null);
        if (!$income_trigger)
            return; // no income trigger
        $from = array_get($income_trigger, 'from', 0);
        $to = array_get($income_trigger, 'to', 0);
        if ($to == 0)
            return;
        switch ($civ) {
            case CIV_HISTORIANS: // HISTORIANS Discard territory tile to give token (when they gain landmark, you gain the exposed beneftits).
                $cubes = $this->game->getCollectionFromDB("SELECT card_location FROM structure WHERE card_location_arg='$player_id' AND  card_location LIKE 'civ_{$civ}\\_%'");
                if (count($cubes) == 0)
                    return;
                break;
            case CIV_MILITANTS: // MILITANTS Gain exposed benefits at start of income turns
                if (in_range($incomeTurn, $from, $to))
                    $this->game->militantBenefits();
                return;
            default:
                break;
        }
        if (in_range($incomeTurn, $from, $to))
            $this->game->benefitCivEntry($civ, $player_id);
        else {
            $notactive = clienttranslate('${player_name}: ability of ${civ_name} is not applicable in era ${era}');
            $this->game->notifyWithName('message', $notactive, [
                'civ_name' => $this->game->getTokenName(CARD_CIVILIZATION, $civ),
                'era' => $incomeTurn
            ], $player_id);
        }
    }
}
