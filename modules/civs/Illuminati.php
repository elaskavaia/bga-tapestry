<?php

declare(strict_types=1);

/**
 * Illuminati, the seventh Fantasies and Futures civilization: three dice sit on the mat, an
 * opponent's roll takes one off it and pays the owner what that roll produced, and income turns
 * 2-5 score 6 VP per die still there before putting all three back.
 *
 * The dice are never physically moved. The mat is one bitmask global and the client badges the
 * dice it names, so "whose mat did this die come from" is "the bit is set" plus getCivOwner.
 */
class Illuminati extends AbsCivilization {
    const DICE = ["black" => 1, "red" => 2, "science" => 4];
    const ALL_DICE = self::DICE["black"] | self::DICE["red"] | self::DICE["science"];
    const VP_PER_DIE = 6;
    const GLOBAL_DICE = "illuminati_dice";

    public function __construct(object $game) {
        parent::__construct(CIV_ILLUMINATI, $game);
    }

    /** Same code at the start of the game and mid game: draw 3 keep 1, then all three dice on the mat. */
    function setupCiv(int $player_id, string $start) {
        $game = $this->game;
        // the card says draw first, but the mat notifies now and the draw only when its row is
        // popped, so the log reads dice then draw. Nothing reads the mask before the first roll.
        $game->queueBenefitNormal(BE_ILLUMINATI_DRAW, $player_id, reason_civ($this->civ));
        $this->setDiceOnMat(self::ALL_DICE, $player_id, clienttranslate('${player_name} places all 3 dice on the ILLUMINATI mat'));
        return parent::setupCiv($player_id, $start);
    }

    /** The income ability is one deterministic row, the Merfolk shape: nothing is chosen. */
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
        $game->queueBenefitNormal(BE_ILLUMINATI_INCOME, $player_id, reason_civ($this->civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Illuminati:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Illuminati:12", $game->hasCiv($player_id, $this->civ));
        $this->systemAssertTrue("ERR:Illuminati:13", $ben == BE_ILLUMINATI_INCOME);

        $game->queueBenefitNormal(BE_VP, $player_id, reason_civ($this->civ), self::VP_PER_DIE * $this->countDiceOnMat());
        $this->setDiceOnMat(self::ALL_DICE, $player_id, clienttranslate('${player_name} returns all 3 dice to the ILLUMINATI mat'));
        return true;
    }

    /**
     * A die left the mat the moment an opponent rolled it, so every later roll of it - Empiricism's
     * second roll, the second roll of rows 301 and 304, an Alchemists reroll - sees a clear bit and
     * pays nothing. The card's "only from the first roll" needs no reroll tracking.
     */
    function onDieRolled(string $die, int $face, int $roller_id): void {
        $game = $this->game;
        $bit = (int) array_get(self::DICE, $die, 0);
        $mask = $this->getDiceOnMat();
        if (!$bit || !($mask & $bit)) {
            return;
        }
        $owner_id = (int) $game->getCivOwner($this->civ);
        if (!$owner_id || $owner_id == $roller_id) {
            return; // the owner taking their own die puts it straight back
        }
        $this->setDiceOnMat(
            $mask & ~$bit,
            $owner_id,
            clienttranslate('${player_name2} takes the ${die_name} from the ILLUMINATI mat of ${player_name}'),
            $roller_id,
            $die
        );
        if (!$game->isPlayerAlive($owner_id)) {
            return; // a finished or zombie owner gains nothing, and their dice never return
        }
        $this->queueDieGain($owner_id, $die, $face);
    }

    /**
     * What the roller would gain from that face, queued Normal so it sits ahead of whatever the
     * roller does with the roll. The science die is instead an optional single advance on the track
     * rolled with no spot benefit and no bonus - rows 76-79 rather than 84-87, since those write
     * the dice globals the roller's research decision is about to read.
     */
    function queueDieGain(int $owner_id, string $die, int $face): void {
        $game = $this->game;
        $reason = reason_civ($this->civ);
        if ($die == "science") {
            $game->queueBenefitNormal(["or" => [BE_ADVANCE_EXPLORATION_NOBENEFIT + $face - 1, BE_DECLINE]], $owner_id, $reason);
            return;
        }
        $benefit = $game->getConquerDieBenefit($die);
        if (!$benefit) {
            $game->notif("message", $owner_id)->notifyAll(clienttranslate('${player_name} gains nothing, that die face has no benefit'));
            return;
        }
        $game->queueBenefitNormal($benefit, $owner_id, $reason);
    }

    function getDiceOnMat(): int {
        return (int) $this->game->getGameStateValue(self::GLOBAL_DICE);
    }

    function countDiceOnMat(): int {
        $mask = $this->getDiceOnMat();
        return count(array_filter(self::DICE, fn($bit) => $mask & $bit));
    }

    /**
     * The global is inside the undo savepoint, so a roller's undo puts the die back on the mat
     * along with dropping the owner's queued gain.
     */
    function setDiceOnMat(int $mask, int $owner_id, string $message, int $roller_id = 0, string $die = ""): void {
        $game = $this->game;
        $game->setGameStateValue(self::GLOBAL_DICE, $mask);
        $notif = $game
            ->notif("illuminatiDice", $owner_id)
            ->withPlayer2($roller_id)
            ->withPreserveArg("on_mat", $mask)
            ->withPreserveArg("mat_owner", $owner_id);
        if ($die) {
            $notif->withArg("die_name", $this->getDieName($die))->withArg("i18n", ["die_name"]);
        }
        $notif->notifyAll($message);
    }

    function getDieName(string $die): string {
        switch ($die) {
            case "black":
                return clienttranslate("black conquer die");
            case "red":
                return clienttranslate("red conquer die");
        }
        return clienttranslate("science die");
    }
}
