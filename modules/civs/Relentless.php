<?php

declare(strict_types=1);
define("BE_RELENTLESS_AB", 318);
class Relentless extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_RELENTLESS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $reason = reason_civ($civ);
        $tokens = [];
        if ($start) {
        } else { // midgame setup
            //v8 If you gain this civilization in the middle of the game, place 4 player tokens on this mat. You may discard them now to gain the benefit.
            if ($this->game->isAdjustments8()) {
                $this->game->interruptBenefit();
                $turn = $this->game->getPlayerTurn($player_id);
                for ($i = 0; $i < 4; $i++) {
                    $num = $i + 1;
                    $structure_id = $this->game->addCube($player_id, "civ_23_$num", 0, $turn);
                    $this->game->notifyMoveStructure(clienttranslate('${player_name} adds cube #${num} to RELENTLESS mat'), $structure_id, ['num' => $num], $player_id);
                }
                $this->game->queueBenefitNormal(['or' => [BE_RELENTLESS_AB, BE_DECLINE]], $player_id, $reason);
            }
        }
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance) {
        return true;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $slots = $this->getRules('slots');
        $slot_choice = $this->getRules('slot_choice');
        $is_midgame = $condition == 'midgame';
        return $data;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ;
        $this->systemAssertTrue("ERR:Relentless:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Relentless:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case BE_RELENTLESS_AB:
                $this->spentCubesToGainBenefits($player_id);
                return true;
            default:
                $this->systemAssertTrue("ERR:Relentless:10");
                return true;
        }
        return true;
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
                $this->game->notifyMoveStructure(clienttranslate('${player_name} adds cube #${num} to RELENTLESS mat'), $structure_id, ['num' => $num], $player_id);
            } else {
                $this->game->notifyWithName('${player_name} does not add cube to RELENTLESS mat (same turn)');
            }
        }
    }

    function spentCubesToGainBenefits($player_id) {
        $cubes = $this->game->getStructuresOnCiv(CIV_RELENTLESS, BUILDING_CUBE);
        $num = count($cubes);
        if ($num == 0)
            return;
        if ($num >= 5) $num = 5;
        $benefit = $this->game->getCivBenefit(CIV_RELENTLESS, $num);
        $this->game->queueBenefitInterrupt($benefit, $player_id, reason_civ(CIV_RELENTLESS));
        if (count($cubes) > 0) {
            foreach ($cubes as $key => $value) {
                $this->game->dbSetStructureLocation($key, 'hand', null, '', $player_id);
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
            $this->spentCubesToGainBenefits($player_id);
        }
    }
}
