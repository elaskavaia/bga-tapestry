<?php

declare(strict_types=1);

class Templates extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_TEMPLATES, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
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

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ;
        $this->systemAssertTrue("ERR:AbsCivilization:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:AbsCivilization:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case 0:
                $this->systemAssertTrue("not implemented");
                return true;  
            default:
                $this->systemAssertTrue("ERR:AbsCivilization:10");
                return true;
        }
        return true;
    }

    function moveCivCube(int $player_id, int $spot, string $extra, array $civ_args) {
        $condition = array_get($civ_args,'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
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
        $civ = $this->civ;
        $data = $benefit;
        $condition = array_get($benefit, 'benefit_data');
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $slots = $this->getRules('slots');
        $slot_choice = $this->getRules('slot_choice');
        $is_midgame = $condition == 'midgame';
        return $data;
    }
}
