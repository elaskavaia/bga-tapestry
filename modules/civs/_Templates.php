<?php

declare(strict_types=1);

class Templates extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_TEMPLATES, $game);
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

    function moveCivCube(int $player_id, int $spot,  $extra, array $civ_args) {
        $condition = array_get($civ_args, 'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
        if ($is_midgame) {
            //...
            return;
        }


        // UPDATE cube
        $this->placeCivCube($player_id, $spot);
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
