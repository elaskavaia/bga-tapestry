<?php

declare(strict_types=1);

class Futurists extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_FUTURISTS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $reason = reason_civ($civ);
        $tokens = [];
        if ($this->game->isAdjustments4or8()) {
            // May advance tokens 2 tracks
            $this->game->queueBenefitNormal(["or" => [151, 152, 153, 154, 401]], $player_id, $reason);
            $this->game->queueBenefitNormal(601, $player_id, $reason); // that suppose to remove track selected by first operation
            $this->game->queueBenefitNormal(["or" => [151, 152, 153, 154, 401]], $player_id, $reason);
            $this->game->queueBenefitNormal(BE_CONFIRM, $player_id, $reason);
        } else {
            // Need to advance tokens 4 spaces
            $this->game->queueBenefitNormal([151, 152, 153, 154], $player_id, $reason);
        }
        return array('tokens' => $tokens, 'outposts' => []);
    }
}
