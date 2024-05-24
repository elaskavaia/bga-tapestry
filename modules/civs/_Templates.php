<?php

declare(strict_types=1);

class Templates extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_TEMPLATES, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ_id;
        $reason = reason_civ($civ);
        $tokens = [];
        if ($start) {
        } else { // midgame setup

        }
        return array('tokens' => $tokens, 'outposts' => []);
    }


    function finalScoring($player_id) {
        // 
    }

    function moveCivCube(int $player_id, bool $is_midgame, int $spot, $extra) {
        $civ = $this->civ_id;
        if ($is_midgame) {
            //...
            return;
        }
        $civ_token_string = "civ_{$civ}_$spot";
        $income_turn = $this->game->getCurrentEra($player_id);

        // find free cube
        $cubes = $this->game->getStructuresSearch(BUILDING_CUBE, null , "civilization_$civ");
        $cube = array_key_first($cubes);
        
        // UPDATE cube
        $this->game->dbSetStructureLocation($cube['card_id'], $civ_token_string, $income_turn, clienttranslate('${player_name} advances on their civilization mat'), $player_id);
    }

    function triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance) {
        return true;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ_id;
        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $slots = $this->getRules('slots');
        $slot_choice = $this->getRules('slot_choice');
        $is_midgame = $condition == 'midgame';
        return $data;
    }
}