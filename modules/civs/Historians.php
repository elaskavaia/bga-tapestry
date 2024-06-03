<?php

declare(strict_types=1);


class Historians extends AbsCivilization {

    // HISTORIANS (7) 
    // The Historians want to witness the achievements of other civilizations. Start with these 4 squares covered with your player tokens and 1 Territory tile. 
    // <b>At the beginning of your income turns (2-5) in 1/2/3 player game or (1-4) in 4-5 player game</b>, you may discard 1 territory tile from your supply to give a token to any opponent (even if they already have a token). This represents a historian you're sending to that civilization.
    // Whenever any opponent with at least one of your 'historians' gains a landmark from an advancement track, you gain all of these exposed benefits.
    // <i>If you gain this civilization in the middle of the game and you are in era 1 or 2, discard it and draw another. Otherwise, immediately give 4 of your player tokens to opponents, leaving the squares exposed on this mat.</i>

    public function __construct(object $game) {
        parent::__construct(CIV_HISTORIANS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $reason = reason_civ($civ);
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        if (!$start) {
            // middle game
            $this->sendHistorianTokensMidGame();
        }
        return array('tokens' => $tokens, 'outposts' => []);
    }


    function sendHistorian($pid, $tid, $token_id) {

        $benefit_data = $this->game->getCurrentBenefit(CIV_HISTORIANS, 'civ');
        $this->systemAssertTrue("cannot find civ HISTORIAN", $benefit_data);
        $this->game->benefitCashed($benefit_data); // the civ benefit.
        $player_id = $this->game->getActivePlayerId();
        // VALIDITY CHECKS
        // 1. Check player owns HISTORIANS and the token is there.
        $token_location = 'civ_7_' . $token_id;
        $historian = $this->game->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location = '$token_location') AND card_location_arg='$player_id' LIMIT 1");
        if ($historian == null) {
            throw new feException('Invalid historian token');
        }
        // 2. Check owns the territory tile.
        $territory_id = $this->game->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_type='1' AND card_type_arg='$tid' AND card_location='hand' AND card_location_arg='$player_id'");
        if ($territory_id == null) {
            throw new feException('Invalid territory tile');
        }
        $this->game->effect_discardCard($territory_id, $player_id);
        $this->sendHistorianToken($historian, $pid);
        // APPLY BENEFITS
        $this->game->gamestate->nextState('benefit');
    }

    function sendHistorianTokensMidGame() {
        $player_id = $this->game->getActivePlayerId();
        $player_data = $this->game->loadPlayersBasicInfosWithBots();
        $num = 1;

        if ($this->game->isAdjustments4or8()) {
            $era = $this->game->getCurrentEra($player_id);
            if ($era <= 2) return;
        }

        if (count($player_data) <= 1) return;

        while ($num < 5) {
            foreach ($player_data as $pid => $player) {
                if ($player_id != $pid) {
                    $token_location = 'civ_7_' . $num;
                    $historian = $this->game->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location = '$token_location') AND card_location_arg='$player_id' LIMIT 1");
                    $num++;
                    if ($historian) {
                        $this->sendHistorianToken($historian, $pid);
                    }
                }
            }
        }

        if ($this->noLandmarksLeft()) {
            $this->activateBenefits($player_id);
        }
    }

    function sendHistorianToken($historian, $otherPlayerId) {
        $player_id = $this->game->getActivePlayerId();
        $token_location = 'pb_' . $otherPlayerId;
        $this->game->DbQuery("UPDATE structure SET card_location='$token_location' WHERE card_id='$historian'");
        $message = clienttranslate('${player_name} gives a Historian token to ${player_name2}');
        $this->game->notif("moveStructure", $player_id)->withStructure($historian)->withPlayer2($otherPlayerId)->notifyAll($message);
    }

    function historianBenefits($player_id, $landmark_id) {
        if (($this->game->isAdjustments4or8()) && $landmark_id > 12) {
            // skip historian
            return;
        }
        // who has the historians?
        $historian = $this->game->getUniqueValueFromDB("SELECT DISTINCT(card_location_arg) FROM structure WHERE card_location LIKE 'pb_$player_id'");
        if (!$historian)
            return;

        $this->activateBenefits($historian);
    }

    function noLandmarksLeft() {
        $lm = $this->game->getObjectFromDB("SELECT card_id FROM structure WHERE card_location LIKE 'landmark_mat_slot%' LIMIT 1");
        return $lm == null; 
    }

    function activateBenefits($player_id) {
        $cubes = $this->game->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'civ_7_%'");
        $covered = array();
        foreach ($cubes as $cube) {
            array_push($covered, getPart($cube['card_location'], 2));
        }

        for ($a = 1; $a <= 4; $a++) {
            if (!in_array($a, $covered)) {
                $benefit = $this->game->civilizations[CIV_HISTORIANS]['slots'][$a]['benefit'];
                $this->game->queueBenefitNormal($benefit, $player_id, reason_civ(CIV_HISTORIANS));
            }
        }
    }
}
