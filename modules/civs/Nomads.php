<?php

declare(strict_types=1);

class Nomads extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_NOMADS, $game);
    }


    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $game = $this->game;
        if (!$start) {
            if ($game->isAdjustments8()) {
                // gain income building min

                $player_income_data =  $game->getPlayerIncomeData($player_id);
                $min = 10;
                for ($track = 1; $track <= 4; $track++) {
                    $field = $game->income_tracks[$track]['field'];
                    $limit = (int) ($player_income_data[$field]);
                    if ($limit < $min) $min = $limit;
                }
                $choice = [];
                for ($track = 1; $track <= 4; $track++) {
                    $field = $game->income_tracks[$track]['field'];
                    $limit = (int) ($player_income_data[$field]);
                    if ($limit == $min) $choice[] = $track + BE_MARKET - 1;
                }
                if (count($choice) == 0) {
                    return;
                }
                if (count($choice) == 1) {
                    $game->queueBenefitInterrupt($choice[0], $player_id, reason(CARD_CIVILIZATION, $civ));
                } else {
                    $game->queueBenefitInterrupt(['or'=>$choice], $player_id, reason(CARD_CIVILIZATION, $civ));
                }
            }
        }

        return array('tokens' => [], 'outposts' => []);
    }
}
