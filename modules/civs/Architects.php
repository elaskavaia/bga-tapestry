<?php

declare(strict_types=1);

class Architects extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_ARCHITECTS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $reason = reason_civ($civ);
        $tokens = [];
        if (!$start) {
            //$this->error("setting up $civ_id");
            //$civ1= $this->getCollectionFromDB("SELECT card_type_arg FROM card WHERE card_type='5' AND card_location='hand' AND card_location_arg='$player_id'");
            //$this->error(json_encode($civ1, JSON_PRETTY_PRINT));
            // add 3 tokens to track possible swaps of buldings
            $loc = "civilization_" . CIV_ARCHITECTS;
            $tokens[] = $this->game->addCube($player_id, $loc, CUBE_CIV, $reason);
            $tokens[] = $this->game->addCube($player_id, $loc, CUBE_CIV, $reason);
            $tokens[] = $this->game->addCube($player_id, $loc, CUBE_CIV, $reason);
            $this->game->benefitCivEntry($civ, $player_id, 'midgame');
            // $this->error("after");
            //$this->error(json_encode($civ1, JSON_PRETTY_PRINT));
        } else {
            if ($this->game->isAdjustments4or8()) {
                $pnum = $this->game->getPlayersNumberWithBots() - 1;
                for ($i = 0; $i < $pnum; $i++) {
                    $this->game->benefitCivEntry($civ, $player_id);
                }
                $this->game->queueBenefitNormal(BE_CONFIRM, $player_id);
            }
        }
        return array('tokens' => $tokens, 'outposts' => []);
    }

    function moveCivCube(int $player_id, int $spot, string $extra, array $civ_args) {
        $condition = array_get($civ_args,'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
        if ($this->game->isAdjustments4or8()) {
            if (!$is_midgame) {
                // place cube in the city
                $structure_id = $this->game->addCube($player_id, 'capital_structure');
                $district_info = $this->game->getDistrictInfo($spot, $extra, null, $player_id);
                $this->game->userAssertTrue(totranslate("Cannot place cube in the same district"), array_get_def($district_info, 'build_types', BUILDING_CUBE, 0) == 0);
                $this->game->effect_placeOnCapitalMat($structure_id, $spot, $extra);
                return;
            }
        }
        $this->game->systemAssertTrue('cannot use ARCHITECTS civ now', $is_midgame);
        $structure1 = $spot;
        $structure2 = $extra;
        $info1 = $this->game->getStructureInfoById($structure1, true);
        $this->game->systemAssertTrue("cannot find $structure1", $info1);
        $this->game->userAssertTrue(totranslate('Can only swap income buildings'), $this->game->checkValidIncomeType($info1['type']));
        $info2 = $this->game->getStructureInfoById($structure2, true);
        $this->game->systemAssertTrue("cannot find $structure2", $info2);
        $this->game->userAssertTrue(totranslate('Can only swap income buildings'), $this->game->checkValidIncomeType($info2['type']));
        $this->game->dbSetStructureLocation($structure1, $info2['location'], null, '', $player_id);
        $this->game->dbSetStructureLocation($structure2, $info1['location'], null, clienttranslate('${player_name} swapped two income buildings'), $player_id);
        $cubes = $this->game->getStructuresSearch(BUILDING_CUBE, null , "civilization_$civ");
        if (count($cubes) > 0) {
            $cube0 = array_key_first($cubes);
            $this->game->dbSetStructureLocation($cube0, 'hand', null, '', $player_id);
            if (count($cubes) > 1)
                $this->game->benefitCivEntry($civ, $player_id, 'midgame');
        }
        $this->game->checkPrivateAchievement(5, $player_id);
        return;
    }
}
