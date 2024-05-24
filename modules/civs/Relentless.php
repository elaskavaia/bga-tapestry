<?php

declare(strict_types=1);

class Relentless extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_RELENTLESS, $game);
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
        // nothing
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

    function relentlessBenefitOnGainBuilding($player_id, $force = 0) {
        if (!$player_id)
            $player_id = $this->game->getActivePlayerId();
        if ($this->game->hasCiv($player_id, CIV_RELENTLESS)) {
            $turn = $this->game->getPlayerTurn($player_id);
            $cubes_cur = $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE, $turn);
            if (count($cubes_cur) == 0 || $force) {
                $cubes = $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE);
                $num = count($cubes) + 1;
                $structure_id = $this->game->addCube($player_id, "civ_23_$num", 0, $turn);
                $this->game->notifyMoveStructure(clienttranslate('${player_name} adds cube #${num} to RELENTLESS mat'), $structure_id, ['num'=>$num], $player_id);
            } else {
                $this->game->notifyWithName('${player_name} does not add cube to RELENTLESS mat (same turn)');
            }
        }
    }

    function relentlessBenefitOnEndOfTurn($player_id) {
        if (!$player_id)
            $player_id = $this->game->getActivePlayerId();
        if (!$this->game->hasCiv($player_id, CIV_RELENTLESS))
            return;
        if ($this->game->getGameStateValue('income_turn'))
            return;
        $turn = (int) $this->game->getPlayerTurn($player_id);
        $cubes_cur = $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE, $turn);
        if (count($cubes_cur) == 0) { // nothing was placed this turn
            $cubes = $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE);
            $num = count($cubes);
            if ($num == 0)
                return;
            if ($num>=5) $num = 5;
            $benefit = $this->game->getCivBenefit(CIV_RELENTLESS, $num);
            $this->game->queueBenefitInterrupt($benefit, $player_id, reason_civ(CIV_RELENTLESS));
            if (count($cubes) > 0) {
                foreach ($cubes as $key => $value) {
                    $this->game->dbSetStructureLocation($key, 'hand', null, '', $player_id);
                }
            }
        }
    }

}
