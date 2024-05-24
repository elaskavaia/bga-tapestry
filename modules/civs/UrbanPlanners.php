<?php

declare(strict_types=1);

define("BE_URBANPLANNERS_GRAB", 316);


class UrbanPlanners extends AbsCivilization {
    // TODO: not all rules are finish need to triggel placing landmark on mat and out of turn action to place in the city
    public function __construct(object $game) {
        parent::__construct(CIV_URBAN_PLANNERS, $game);
    }

    function finalScoring($player_id) {
        $civ = $this->civ_id;
        if (!$this->game->hasCiv($player_id, $civ)) {
            return;
        }
        $num = $this->getMaxAdjacentLandmarks($player_id); // calculate connected landmarks
        if ($num > 7) $num = 7;
        $vp = $num * $num; // square of number

        $this->game->awardVP($player_id, $vp, reason_civ($civ));
    }

    function getMaxAdjacentLandmarks($player_id) {
        $capital = $this->game->getCapitalData($player_id);
        //      $this->DbQuery("UPDATE structure SET card_location='$cell', card_type_arg='$rot' WHERE card_id='$sid'");
        //        $cell = 'capital_cell_' . $player_id . '_' . $x . '_' . $y;
        $flood_map = [];
        for ($dx = 0; $dx < 15; $dx += 1) {
            for ($dy = 0; $dy < 15; $dy += 1) {
                $flood_map[$dx][$dy] = 0;
            }
        }
        $type = BUILDING_LANDMARK + 1;
        $flood_area = 1;
        for ($dx = 0; $dx < 15; $dx += 1) {
            for ($dy = 0; $dy < 15; $dy += 1) {
                if ($capital[$dx][$dy] == $type) {
                    if ($flood_map[$dx][$dy] == 0) {
                        $this->capitalFlood($flood_area, $dx, $dy, $flood_map, $capital, $type);
                        $flood_area++;
                    }
                }
            }
        }
        $landmarks = $this->game->getStructuresSearch(BUILDING_LANDMARK, null, "capital_cell_${player_id}_%");
        $has_landmark = [];
        for ($dx = 0; $dx < 15; $dx += 1) {
            for ($dy = 0; $dy < 15; $dy += 1) {
                if ($capital[$dx][$dy] == $type) {
                    $flood_mark = $flood_map[$dx][$dy];
                    if ($flood_mark != 0) {

                        foreach ($landmarks as $info) {
                            $cell = $info['card_location'];
                            $lm_x = getPart($cell, 3);
                            $lm_y = getPart($cell, 4);
                            if ($lm_x == $dx && $lm_y == $dy) {
                                $has_landmark[$flood_mark][$info['card_location_arg2']] = 1;
                            }
                        }

                        $this->capitalFlood($flood_area, $dx, $dy, $flood_map, $capital, $type);
                        $flood_area++;
                    }
                }
            }
        }
        $max = 0;
        foreach ($has_landmark as $flood_mark => $list) {
            $num = count($list);
            if ($max < $num) $max = $num;
            $this->game->notifyWithName("message", clienttranslate('${player_name} has area ${area} with ${num} landmarks'), ['area' => $flood_mark, 'num' => $num], $player_id);
        }
        $this->game->notifyWithName("message", clienttranslate('${player_name} has ${max} connected landmarks for scoring purposes'), ['max' => $max], $player_id);
        return $max;
    }
    private function  capitalFlood($flood_area, $dx, $dy, &$flood_map, $capital, $type) {
        if ($dx < 0 || $dx >= 15) return;
        if ($dy < 0 || $dy >= 15) return;
        if ($flood_map[$dx][$dy] != 0) return;
        if ($capital[$dx][$dy] != $type) return;

        $flood_map[$dx][$dy] = $flood_area;
        $this->capitalFlood($flood_area, $dx + 1, $dy, $flood_map, $capital, $type);
        $this->capitalFlood($flood_area, $dx, $dy + 1, $flood_map, $capital, $type);
        $this->capitalFlood($flood_area, $dx, $dy - 1, $flood_map, $capital, $type);
        $this->capitalFlood($flood_area, $dx - 1, $dy, $flood_map, $capital, $type);
    }

    function triggerPreGainStructure($player_id, $type) {
        if (!$this->game->isRealPlayer($player_id))
            return false;
        $civ = $this->civ_id;
        if (!$this->game->hasCiv($player_id, $civ)) {
            return false;
        }
        $this->game->queueBenefitNormal(['or' => [BE_URBANPLANNERS_GRAB, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, $civ));
        return true;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = '') {
        $civ = $this->civ_id;
        $this->systemAssertTrue("ERR:UrbanPlanners:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:UrbanPlanners:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case BE_URBANPLANNERS_GRAB:
                $this->game->clearCurrentBenefit($ben);
                $next_benefit = $this->game->getCurrentBenefit();
                $this->systemAssertTrue("ERR:UrbanPlanners:01",  $next_benefit);
                $landmark_type = $this->game->getRulesBenefit($next_benefit['benefit_type'], 'lm');
                $this->systemAssertTrue("ERR:UrbanPlanners:02",  $landmark_type);
                $this->game->benefitCashed($next_benefit); // next was landmark benefit, we cancelled it
                $civ_token_string = "civilization_{$civ}";

                $landmark = $this->game->getStructureInfoSearch(BUILDING_LANDMARK, null, null, null, $landmark_type);
                $this->systemAssertTrue("ERR:UrbanPlanners:03",  $landmark);
                $sid = $landmark['card_id'];
                $owner = $landmark['card_location_arg'];
                $this->systemAssertTrue("ERR:UrbanPlanners:04",  $owner == 0);
                $this->game->DbQuery("UPDATE structure SET card_location_arg='$player_id' WHERE card_id='$sid'");
                $this->game->dbSetStructureLocation($sid, $civ_token_string, null, clienttranslate('${player_name} places landmark on URBAN PLANNERS'), $player_id);
                $this->game->gainLandmarkTriggers($player_id, $landmark_type);
                $this->game->nextStateBenefitManager();
                return false;  // no cleanup
            default:
                $this->systemAssertTrue("ERR:UrbanPlanners:10");
                return true;
        }
        return false;  // no cleanup
    }

    function hasActivatedAbilities($player_id) {
        if (!$this->game->isRealPlayer($player_id))
            return false;
        $civ = $this->civ_id;
        if (!$this->game->hasCiv($player_id, $civ)) {
            return false;
        }
        $landmark = $this->game->getStructureInfoSearch(BUILDING_LANDMARK, null, "civilization_$civ");
        return $landmark != null;
    }

    function action_activatedAbility($player_id, $ability, $arg, &$state) {
        $civ = $this->civ_id;
        if ($ability != "civ_25") return false;
        if (!$this->hasActivatedAbilities($player_id)) return false;
        $sid = $arg;
        $this->systemAssertTrue("ERR:UrbanPlanners:20", $sid);
        $info = $this->game->getStructureInfoById($sid);
        $this->systemAssertTrue("ERR:UrbanPlanners:21", $info);
        $this->systemAssertTrue("ERR:UrbanPlanners:22", $info['card_type'] == BUILDING_LANDMARK);
        $this->systemAssertTrue("ERR:UrbanPlanners:23", $info['card_location'] == "civilization_$civ");
        $landmark_type =  $info['card_location_arg2'];

        // activate claimLandmark

        $this->game->DbQuery("UPDATE structure SET card_location='capital_structure', card_location_arg='$player_id' WHERE card_id='$sid'");
        $this->game->notifyMoveStructure('', $sid, [], $player_id);

        $landmark_be = $this->game->landmark_data[$landmark_type]['benefit'];
        $landmark_type2 = $this->game->getRulesBenefit($landmark_be, 'lm');
        $this->systemAssertTrue("ERR:UrbanPlanners:24", $landmark_type == $landmark_type2);

        $this->game->interruptBenefit();
        $this->game->benefitSingleEntry('standard', $landmark_be, $player_id, 1, reason_civ($civ));
        $state = 26; // place structure

        if ($this->game->isAdjustments4or8()) {
            //If you place 2+ landmarks from this mat at the same time, also gain [ANY RESOURCE] and [TAPESTRY]
            $cube_id = $this->game->addCube($player_id, $info['card_location']);

            $cubes = $this->game->getStructuresSearch(BUILDING_CUBE, null, $info['card_location']);
            $num = count($cubes);
            if ($num == 2) {
                $this->game->queueBenefitNormal([BE_ANYRES, BE_TAPESTRY], $player_id, reason_civ($civ));
            }
            $this->game->notifyMoveStructure(clienttranslate('${player_name} placed landmark from their civilization mat x ${num}'), $cube_id, ['num' => $num], $player_id);
        }


        return true;
    }
}
