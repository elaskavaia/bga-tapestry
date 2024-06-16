<?php

declare(strict_types=1);

class Gamblers extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_GAMBLERS, $game);
    }

    function moveCivCube(int $player_id, int $spot, string $extra, array $civ_args) {
        $condition = array_get($civ_args,'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
        $slots = $this->getRules('slots');
        $benefit = $slots[0]["benefit"];
        $this->game->queueBenefitNormal($benefit, $player_id, reason_civ($civ));
        return;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $data = $benefit;
        //$condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason_civ($civ), false);
        $data['slots'] = [];
        $slots = $this->getRules('slots');
        $benefit0 = $slots[0]["benefit"];
        //$slot_choice = $this->getRules('slot_choice');
        //$is_midgame = $condition == 'midgame';

        $data['slots_choice'] = [];
        $data['slots_choice'][0] = [];
        $data['slots_choice'][0]['benefit'] = $benefit0;
        $data['title'] = clienttranslate('Gamble!');

        $income_trigger = $this->getRules('income_trigger', []);
        $decline = array_get($income_trigger, 'decline', true);
        $data['decline'] = $decline;
        return $data;
    }
}
