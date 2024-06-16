<?php

declare(strict_types=1);

class Craftsmen extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_CRAFTSMEN, $game);
    }


    function moveCivCube(int $player_id, int $spot,  $extra, array $civ_args) {
        $condition = array_get($civ_args,'benefit_data');
        $is_midgame = ($condition == 'midgame');
        $civ = $this->civ;
        $game = $this->game;
        if (!$is_midgame) {
            $game->clearCurrentBenefit();
            // normal move is place structure on craftment mat
            $this->systemAssertTrue("No civ CRAFTSMEN", $game->hasCiv($player_id, CIV_CRAFTSMEN));
            $slots = $this->getCraftsmenSlots($player_id);
            $game->userAssertTrue(totranslate('Invalid slot'), in_array($spot, $slots));
            $civ_location = "civ_3_" . $spot;
            $curr = $game->getStructureInfoSearch(null, null, 'capital_structure');
            $structure_id = $curr['card_id'];
            $type = $curr['card_type'];

            $game->dbSetStructureLocation($structure_id, $civ_location, null, clienttranslate('${player_name} places an income building on Craftsmen mat'), $player_id);


            if (($game->isTapestryActive($player_id, 27)) && ($type <= 5)) { // MONARCHY
                $game->awardVP($player_id, 3, reason_tapestry(27));
            }
            $slots = $this->getRules('slots');
            $benefits = $slots[$spot]['benefit'];
            $game->interruptBenefit();
            $game->queueBenefitNormal($benefits, $player_id, reason_civ(CIV_CRAFTSMEN));
            return;
        }
        $civ_token_string = "civ_{$civ}_$spot";
        $income_turn = $game->getCurrentEra($player_id);

        $first = false;
        $slots = $this->getCraftsmenSlotsMidgame($player_id, $first);
        $game->userAssertTrue(totranslate('Invalid slot'), in_array($spot, $slots));

        // find free cube
        $cube = $game->addCube($player_id, 'hand');
        // UPDATE cube
        $game->dbSetStructureLocation($cube, $civ_token_string, $income_turn, clienttranslate('${player_name} advances on their civilization mat'), $player_id);

        if ($first) {
            // gain benefit
            $slots = $this->getRules('slots');
            $benefits = $slots[$spot]['benefit'];
            $game->interruptBenefit();
            $game->queueBenefitNormal($benefits, $player_id, reason_civ(CIV_CRAFTSMEN));
        }

        $slots = $this->getCraftsmenSlotsMidgame($player_id, $first);
        if (count($slots) > 0) {
            // re-schedule benefit again
            $game->benefitCivEntry($civ, $player_id, 'midgame');
        }
    }


    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        if ($benefit === null) {
            $benefit = $game->getCurrentBenefit($civ, 'civ');
        }

        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];

        $is_midgame = $condition == 'midgame';
        if (!$is_midgame) return $data;

        $first = false;
        $data['slots'] = $this->getCraftsmenSlotsMidgame($player_id, $first);
        if ($first)
            $data['title'] = clienttranslate('You can place a player token on highlighed slots and gain benefit (only first placement will gain benefit)');
        else
            $data['title'] = clienttranslate('You can place a player token on highlighed slots, do not gain benefit');
        return $data;
    }


    function inspectColumnMidgame($from, $to, $curera, $used, &$slots) {
        $size = $to - $from + 1;
        for ($i = 1; $i <= $size; $i++) {
            $a = $from + $i - 1;
            if (array_get($used, $a, 0) == 1) return 0;
        }
        for ($i = 1; $i <= $size; $i++) {
            $a = $from + $i - 1;
            $era = $i;
            if ($curera >= $era) {
                array_push($slots, $a);
            }
        }
        return 1;
    }


    function getCraftsmenSlotsMidgame($player_id, &$first) {
        $civ = $this->civ;
        $game = $this->game;

        if (!$game->hasCiv($player_id, $civ)) return [];

        $curera = $game->getCurrentEra($player_id);

        $used = [];
        $structures = $this->game->getStructuresOnCiv($civ, null);
        foreach ($structures as $token) {
            $slot = getPart($token['card_location'], 2);
            $used[$slot] = 1;
        }

        $slots = [];
        $column = 0;
        $column += $this->inspectColumnMidgame(1, 3, $curera, $used, $slots);

        $column += $this->inspectColumnMidgame(4, 7, $curera, $used, $slots);
        $column += $this->inspectColumnMidgame(8, 12, $curera, $used, $slots);
        if ($column == 3) $first = true;
        else $first = false;
        return $slots;
    }

    function inspectColumnNormal($from, $to, $used, &$slots) {
        $size = $to - $from + 1;
        $last = 0;
        for ($i = 1; $i <= $size; $i++) {
            $a = $from + $i - 1;
            if (array_get($used, $a, 0) == 1) $last = $a;
        }
        for ($i = 1; $i <= $size; $i++) {
            $a = $from + $i - 1;
            if ($a > $last) {
                array_push($slots, $a);
                break;
            }
        }
    }

    function getCraftsmenSlots($player_id) {
        $civ = $this->civ;
        $game = $this->game;

        if (!$game->hasCiv($player_id, $civ)) return [];
        $civ_type = $game->getUniqueValueFromDB("SELECT card_type FROM structure WHERE card_location='capital_structure' LIMIT 1");
        if ($civ_type > 4)
            return []; // only income structures can be put on mat.
        // Need to check which slots are available. 1-3, 4-7, 8-12
        $used = [];
        $structures = $this->game->getStructuresOnCiv($civ, null);
        foreach ($structures as $token) {
            $slot = getPart($token['card_location'], 2);
            $used[$slot] = 1;
        }
        $slots = [];
        $this->inspectColumnNormal(1, 3,  $used, $slots);
        $this->inspectColumnNormal(4, 7,  $used, $slots);
        $this->inspectColumnNormal(8, 12,  $used, $slots);
        return $slots;
    }
}
