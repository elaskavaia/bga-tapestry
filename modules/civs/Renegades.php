<?php

declare(strict_types=1);

class Renegades extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_RENEGADES, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ_id;
        $reason = reason_civ($civ);
        $token = $this->game->addCivToken($player_id, 0, $civ);
        if ($start) {
        } else { // midgame setup
            if ($this->game->isAdjustments8()) {
                // If you gain this civilization in the middle of the game, place a player token in a tier that matches a tier on the board containing one of your player tokens. Gain the benefit immediately.

                $this->game->benefitCivEntry($civ, $player_id, 'midgame');
            }
        }
        $tokens = [$token];
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance) {
        $this->systemAssertTrue("not applicable", $this->game->hasCiv($player_id, CIV_RENEGADES));
        if ($advance) {
            $benefit_available = ($flags & FLAG_GAIN_BENFIT) != 0 ? 1 : 0;
            if (!$benefit_available)
                return true;
            $tier = 1 + floor(($spot - 1) / 3);
            //             $this->queueBenefitNormal([ 'p' => 0,'g' => [ BE_RENEGADES_ADV,'h' => 603 ] ],
            //                     $player_id, reason(CARD_CIVILIZATION, CIV_RENEGADES, $tier));
            if (!$this->isRenegadeAdvancePossible($tier)) {
                $this->game->notifyWithName('message', clienttranslate('RENEGADES: not possible to use ability for tier ${tier}, skipping'), [
                    'tier' => $tier
                ], $player_id);
                return true;
            }
            $this->game->queueBenefitNormal(['or' => [BE_RENEGADES_ADV, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, CIV_RENEGADES, $tier));
        }
        return true;
    }

    function isRenegadeAdvancePossible($tier) {
        $cube_tier = $this->getCurrentTier();
        if ($cube_tier < $tier) return true;
        return false;
    }

    function getCurrentTier($cube = null) {
        if (!$cube) $cube = $this->getSingleCube();
        $cube_tier = getPart($cube['card_location'], 2);
        return $cube_tier;
    }


    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ_id;
        $this->systemAssertTrue("ERR:Renegades:01", $ben == 310); //BE_RENEGADES_ADV
        $tier = (int) getReasonPart($reason, 3);
        $cube = $this->getSingleCube();
        $cube_tier = $this->getCurrentTier($cube);
        if ($cube_tier >= $tier) {
            throw new BgaUserException($this->_('RENEGADES advance is not possible to this tier'));
        }
        $cube_tier++;

        $this->game->clearCurrentBenefit($ben);
        $next_benefit = $this->game->getCurrentBenefit(BE_SPOT);
        if ($next_benefit) $this->game->benefitCashed($next_benefit); // next was spot benefit, we cancelled it
        // renegade advance



        $civ_token_string = "civ_{$civ}_$cube_tier";
        $income_turn = $this->game->getCurrentEra($player_id);
        $cube_id = $cube['card_id'];
        $this->game->dbSetStructureLocation($cube_id, $civ_token_string, $income_turn, clienttranslate('${player_name} advances on RENEGADES'), $player_id);

        //$this->systemAssertTrue("moving $cube_id on civ_${civ}_${cube_tier}");
        $slots = $this->getRules('slots');
        $benefits = $slots[$cube_tier]['benefit'];
        $this->game->queueBenefitNormal($benefits);
        if ($cube_tier == 4 &&  $this->game->getAdjustmentVariant()<8){
            $this->game->achievementEOT($player_id);
        }
        $this->game->nextStateBenefitManager();
        return false; // no cleanup
    }

    function moveCivCube(int $player_id, bool $is_midgame, int $spot, $extra) {
        if ($this->game->isAdjustments8() && $is_midgame) {
            $civ = $this->civ_id;
            $cube = $this->getSingleCube();
            $valid = $this->getCurrentBoardTiers($player_id);
            if (array_search($spot, $valid) === false) {
                $this->systemAssertTrue("Unathorized move");
            }

            $civ_token_string = "civ_{$civ}_$spot";
            $income_turn = $this->game->getCurrentEra($player_id);
            $cube_id = $cube['card_id'];
            $this->game->dbSetStructureLocation($cube_id, $civ_token_string, $income_turn, clienttranslate('${player_name} advances on RENEGADES'), $player_id);

            if ($spot != 0) {
                $slots = $this->getRules('slots');
                $benefits = $slots[$spot]['benefit'];
                $this->game->queueBenefitNormal($benefits);
            }
            return;
        }
    }

    function getCurrentBoardTiers($player_id) {
        $slots = [];
        $cubes = $this->game->getCubeInfoWithFlags($player_id, FLAG_SELF);
        foreach ($cubes as $cube) {
            $loc = $cube['card_location'];
            $spot = getPart($loc, 3); // tech_spot_1_11
            $tier = 1 + floor(($spot - 1) / 3);
            $slots[$tier] = 1;
        }
        unset ($slots[0]);
        return array_keys($slots);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ_id;
        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $is_midgame = $condition == 'midgame';
        if ($this->game->isAdjustments8() && $is_midgame) {
            $data['slots'] = $this->getCurrentBoardTiers($player_id);
        }
        return $data;
    }
}
