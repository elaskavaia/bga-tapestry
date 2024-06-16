<?php

declare(strict_types=1);

class Infiltrators extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_INFILTRATORS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $game = $this->game;
        if ($game->isAdjustments8()) {
            $this->effect_placeTokenOnAllOpponentCapitalTerritory($player_id);
        }
        if (!$start) {
            $midgame_setup = $this->getRules('midgame_setup', false);
            if ($midgame_setup)
                $this->game->benefitCivEntry($civ, $player_id, 'midgame');
        }
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function moveCivCube(int $player_id, int $slot,  $extra, array $civ_args) {
        $civ = $this->civ;
        $game = $this->game;
        $condition = array_get($civ_args, 'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $slots_choice_arr = array_get($civ_args, 'slots_choice', []);
        $slots_choice = array_get($slots_choice_arr, $slot, []);
        $this->systemAssertTrue("ERR:Infiltrators:01", count($slots_choice) > 0);
        $benefit = array_get($slots_choice, "benefit", null);
        $this->systemAssertTrue("ERR:Infiltrators:02", $benefit !== null);
        $opp_id = array_get($slots_choice, "player_id", "");
        if ($is_midgame) {
            // just place token and do not gain benefit
            $this->effect_placeTokenOnOpponentCapitalTerritory($player_id, $opp_id);
        } else {
            $game->queueBenefitNormal($benefit, $player_id, reason_civ($civ, $opp_id));
        }
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $benefit;
        $condition = array_get($benefit, 'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $this->populateSlotChoiceForArgs($data);
        if ($is_midgame) {
            $data['title'] = clienttranslate('Chose an opponent to give player token to');
        } else {
            $data['title'] = clienttranslate('Chose to place an outpost or gain vp');
        }

        unset($data['slots_choice'][1]); // remove this it will be replace with choices with players
        if ($is_midgame) {
            unset($data['slots_choice'][0]);
        }
        $no = 0;
        $players = $game->loadPlayersBasicInfosWithBots();
        foreach ($players as $opponent_id => $player_info) {
            if ($opponent_id == $player_id)
                continue;
            if ($opponent_id == PLAYER_SHADOW)
                continue;
            $no = $no + 1;
            $o_count = count($game->getOutpostsInHand($opponent_id));
            $start_info = $game->getStartingPosition($opponent_id);
            $location = $start_info['location'];
            $cubes = $game->getStructuresSearch(BUILDING_CUBE, null, $location, $player_id, null, false);
            $prev_cubes = count($cubes);
            $data['slots_choice'][$no]['benefit'] = [171];
            $data['slots_choice'][$no]['player_id'] = $opponent_id;
            $data['slots_choice'][$no]['count'] = $o_count;
            $data['slots_choice'][$no]['player_name'] = $game->getPlayerNameById($opponent_id);
            if ($is_midgame) {
                $data['slots_choice'][$no]['title'] = clienttranslate('Give token to ${player_name}');
            } else {
                $data['slots_choice'][$no]['title'] = clienttranslate('Give token to ${player_name}: ${count} VP');
            }
            $data['slots_choice'][$no]['cubes'] = $prev_cubes;
            if ($prev_cubes == 2) { // 3rd cube is about to be added
                $data['slots_choice'][$no]['benefit'][] = [BE_GAIN_CIV];
                $data['slots_choice'][$no]['title'] = clienttranslate('Give token to ${player_name}: ${count} VP + Civilization');
            }
        }
        return $data;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ;
        $game = $this->game;
        $this->systemAssertTrue("ERR:Infiltrators:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Infiltrators:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case 170:
                $state = $game->getRulesBenefit($ben, 'state', null);
                $game->gamestate->nextState($state);
                return false;
            case 171:
                // place token in opponent capital territory
                $bene = $game->getCurrentBenefit($ben);
                $opponent_id = (int) getReasonPart($bene['benefit_data'], 3);
                $this->effect_placeTokenOnOpponentCapitalTerritory($player_id, $opponent_id);
                $o_count = count($game->getOutpostsInHand($opponent_id));
                $game->awardVP($player_id, $o_count * $count, $reason, null, $ben);
                return true;
            default:
                $this->systemAssertTrue("ERR:Infiltrators:10");
                return true;
        }
        return true;
    }

    function effect_placeTokenOnAllOpponentCapitalTerritory(int $player_id) {
        $civ = $this->civ;
        $game = $this->game;
        $players = $game->loadPlayersBasicInfosWithBots();
        foreach ($players as $opponent_id => $player_info) {
            if ($opponent_id == $player_id)
                continue;
            if ($opponent_id == PLAYER_SHADOW)
                continue;
            $this->effect_placeTokenOnOpponentCapitalTerritory($player_id, $opponent_id);
        }
    }

    function effect_placeTokenOnOpponentCapitalTerritory(int $player_id, int $opponent_id) {
        $game = $this->game;
        $start_info = $game->getStartingPosition($opponent_id);
        $location = $start_info['location'];

        $structure_id = $game->addCube($player_id, 'hand');
        $game->effect_placeOnMap($player_id, $structure_id, $location, '*', false);
    }
}
