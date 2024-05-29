<?php

declare(strict_types=1);

class Heralds extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_HERALDS, $game);
    }

    function finalScoring($player_id) {
        // 
    }
}
