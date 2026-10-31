<?php

declare(strict_types=1);

require_once __DIR__ . "/DiceUT.php";

class TrackMoveUT extends DiceUT {
    public array $moved = [];
    /** flags and mandatory of each move, in the order of $moved */
    public array $movedWith = [];

    /** Records which token moved; the real move cascades into spot benefits the harness does not model. */
    function trackMovementProper($track, $spot, $change, $flags, $mandatory, $player_id) {
        $this->moved[] = [(int) $track, (int) $spot, (int) $change, (int) $player_id];
        $this->movedWith[] = [(int) $flags, (bool) $mandatory];
        return true;
    }
}
