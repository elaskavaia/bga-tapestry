<?php

declare(strict_types=1);

/**
 * Psionics, a Fantasies and Futures civilization that samples a second reality: every random gain
 * its owner takes draws or rolls one more and keeps one. All of that behaviour lives in the engine
 * seams (see hasExtraOption, awardRandomCard and rollDieFaces), so this class only answers "is it
 * on" for the mat's own income table: turns 2-5 each gain one random thing, itself then sampled.
 */
class Psionics extends AbsCivilization {
    /** Income turn to the row the mat pays; each is a random gain the seams intercept in turn. */
    const INCOME_ROWS = [2 => BE_TERRITORY, 3 => BE_TAPESTRY, 4 => BE_INVENT, 5 => BE_RESEARCH];

    public function __construct(object $game) {
        parent::__construct(CIV_PSIONICS, $game);
    }

    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        $game = $this->game;
        // the parent signature is untyped and getActivePlayerId returns a string, which the typed
        // helpers below would reject outright under strict_types
        $player_id = (int) $player_id;
        $incomeTurn = (int) ($incomeTurn ?: $game->getCurrentEra($player_id));
        $income_trigger = $this->getRules("income_trigger", []);
        if (!in_range($incomeTurn, array_get($income_trigger, "from", 0), array_get($income_trigger, "to", 0))) {
            parent::queueEraCivAbility($player_id, $incomeTurn);
            return;
        }
        $game->queueBenefitNormal(self::INCOME_ROWS[$incomeTurn], $player_id, reason_civ($this->civ));
    }
}
