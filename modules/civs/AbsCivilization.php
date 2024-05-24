<?php

declare(strict_types=1);

abstract class AbsCivilization {
    protected int $civ_id; // numeric civ type
    public Tapestry $game; // game ref
    protected array $civilization_info = []; // material file info


    public function __construct(int $civ, object $game) {
        $this->game = $game;
        $this->civ_id = $civ;
        $this->civilization_info = &$this->game->civilizations[$civ];
    }

    function getRules($field = 'benefit', $def = null) {
        return array_get($this->civilization_info, $field, $def);
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
        $civ = $this->civ_id;
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
        $cubes = $this->game->getStructuresOnCiv($this->civ_id, BUILDING_CUBE);
        $cube = array_shift($cubes);
        $this->systemAssertTrue("ERR:AbsCivilization:01", $cube);
        return $cube;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        return [];
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ_id;
        return false;  // no cleanup
    }
}
