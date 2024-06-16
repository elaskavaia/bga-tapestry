<?php

declare(strict_types=1);

class Collectors extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_COLLECTORS, $game);
    }


    function finalScoring($player_id) {
        $civ = $this->civ;
        $game = $this->game;

        if (!$game->hasCiv($player_id, $civ)) {
            return;
        }
        $num = 0;
        $vp = 0;

        for ($i = 1; $i <= 6; $i++) {
            $cube = $this->game->getStructureOnCivSlot($civ, $i);
            if ($cube) {
                $num++;
            }
            $cube =  $game->getCardInfoSearch(null, null, $this->getCivSlot($i));
            if ($cube) {
                $num++;
            }
        }
        // 1/3/6/10/15/21
        for ($i = 1; $i <= $num; $i++) {
            $vp = $vp + $i;
        }

        $game->awardVP($player_id, $vp, reason_civ($civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ;
        $game = $this->game;
        $game->systemAssertTrue("ERR:Collectors:11", $game->isRealPlayer($player_id));
        $game->systemAssertTrue("ERR:Collectors:12", $game->hasCiv($player_id, $civ));
        switch ($ben) {
            case 312: // BE_COLLECTORS_GRAB  structure              
                $bene = $game->clearCurrentBenefit($ben);
                $next_benefit = $game->getCurrentBenefit();
                if ($this->effect_collectorsGrab($player_id, $next_benefit)) {
                    $game->benefitCashed($next_benefit);
                }


                $game->nextStateBenefitManager();
                return false;

            case 313: // BE_COLLECTORS_CARD  card
                $bene = $game->clearCurrentBenefit($ben);
                $next_benefit = $game->getCurrentBenefit();
                if ($this->effect_collectorsGrabCard($player_id, $bene)) {
                    $game->benefitCashed($next_benefit);
                }

                $game->nextStateBenefitManager();
                return false;
            default:
                $game->systemAssertTrue("ERR:AbsCivilization:10");
                return true;
        }
        return true;
    }

    function effect_collectorsGrabCard($player_id, $bene) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $bene['benefit_data'];
        $card_id = $game->getReasonArg($data, 3);
        $card_type  = $this->doCollectCard($player_id, $card_id);
        if (!$card_type) return false;
        if ($card_type == CARD_TECHNOLOGY && $game->isAdjustments8()) {
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ($civ), 2);
        } else {
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ($civ));
        }
        return true;
    }

    function doCollectCard($player_id, $card_id) {
        $game = $this->game;
        $card = $game->getCardInfoById($card_id);
        $card_type = $card['card_type'];
        $slot = $game->getCivSlotNumberForGain($this->civ, 'card', $card_type);
        $game->systemAssertTrue("cannot find card $card_id $slot", $slot > 0);
        $loc = $this->getCivSlot($slot);
        $cube =  $game->getCardInfoSearch(null, null, $loc);

        if ($cube) // already there
            return 0;
        $game->dbSetCardLocation($card_id, $loc, 0, clienttranslate('${player_name} collected ${card_type_name} on COLLECTORS mat'), $player_id);
        return (int) $card_type;
    }

    function effect_collectorsGrab($player_id, $bene) {
        $civ = $this->civ;
        $game = $this->game;
        $ben = $bene['benefit_type'];
        $message = clienttranslate('${player_name} collected ${structure_name} on COLLECTORS mat');

        $bencount2 =  1;
        if ($game->isAdjustments8()) {
            $bencount2 = 2;
        }

        if ($ben == BE_HOUSE) {
            // gain house
            if ($game->claimIncomeStructure(BUILDING_HOUSE, null)) {
                // no more houses
                return false;
            }
            $structure_data = $game->getPendingStructure();
            $structure_id = $structure_data['card_id'];
            $game->dbSetStructureLocation($structure_id, 'civ_21_2', null,  $message, $player_id);
            $game->checkPrivateAchievement(5, $player_id);
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ($civ), 1);
            return true;
        }
        if ($ben == BE_CONQUER) {
            $structure_id = $game->getOutpostId(null, $player_id, false); // XXX Militants
            if (!$structure_id) return false;
            $game->dbSetStructureLocation($structure_id, 'civ_21_1', null,  $message, $player_id);
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ($civ), $bencount2);
            return true;
        }
        $landmark_id = $game->getRulesBenefit($ben, 'lm');
        if ($landmark_id) {
            // landmark benefit
            if ($game->claimLandmark($landmark_id, $player_id, null)) {
                // no landmark?
                return false;
            }
            $structure_data = $game->getPendingStructure();
            $structure_id = $structure_data['card_id'];
            $game->dbSetStructureLocation($structure_id, 'civ_21_3', null,  $message, $player_id);
            $game->checkPrivateAchievement(6, $player_id);
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ($civ), $bencount2);
            return true;
        }
        $game->systemAssertTrue("unexpected benefit $ben");
    }



    function triggerPreGainStructure($player_id, $type, $ben) {
        $civ = $this->civ;
        $game = $this->game;
        if (!$game->hasCiv($player_id, $civ)) {
            return false;
        }
        if ($game->isIncomeTurn()) return false;
        $table = 'structure';
        $slot = $game->getCivSlotNumberForGain($civ, $table, $type);
        if ($slot <= 0)
            return false;
        $cube = $game->getStructureOnCivSlot($civ, $slot);
        if ($cube) // already there
            return false;
        $game->queueBenefitNormal(['or' => [BE_COLLECTORS_GRAB, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS, "$table:$type"));
        return true;
    }

    function triggerPreKeepCard($player_id, $card_id, $type) {
        $civ = $this->civ;
        $game = $this->game;
        if (!$game->hasCiv($player_id, $civ)) {
            return false;
        }
        if ($game->isIncomeTurn()) return false;
        $table = 'card';
        $slot = $game->getCivSlotNumberForGain($civ, $table, $type);
        if ($slot <= 0)
            return false;
        $loc = $this->getCivSlot($slot);
        $cube =  $game->getCardInfoSearch(null, null, $loc);
        if ($cube) // already there 
            return false;

        $game->queueBenefitInterrupt(['or' => [BE_COLLECTORS_CARD, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS, $card_id));
        return true;
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $game = $this->game;
        if (!$start) {
            $midgame_setup = $this->getRules('midgame_setup', false);
            if ($midgame_setup) {
                $taps = $game->getCardsInHand($player_id, CARD_TAPESTRY);
                $ters = $game->getCardsInHand($player_id, CARD_TERRITORY);
                if (count($taps) + count($ters) > 0)
                    $this->game->benefitCivEntry($civ, $player_id, 'midgame');
            }
        }
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $is_midgame = $condition == 'midgame';
        if ($is_midgame) {
            $cards = $game->getCardsInHand($player_id, CARD_TAPESTRY);
            $data['targets'] = array_keys($cards);
            $cards = $game->getCardsInHand($player_id, CARD_TERRITORY);
            $data['targets'] =  array_merge($data['targets'], array_keys($cards));
            $data['title'] = clienttranslate('You may place 1 [TAPESTRY] and/or 1 [TILE] from your supply on this mat; do not gain any immediate benefit');
        }
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, string $extra, array $civ_args) {
        $condition = array_get($civ_args,'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
        if (!$is_midgame) {
            return;
        }

        if ($spot) {
            $this->doCollectCard($player_id, $spot);
        }

        if ($extra) {
            $this->doCollectCard($player_id, $extra);
        }
    }
}
