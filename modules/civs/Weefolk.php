<?php

declare(strict_types=1);

class Weefolk extends AbsCivilization {
    /** The optional territory-for-building trade, the only slot of the build prompt. */
    const CHOICE_BUILD = 1;
    /** Reason arg telling the build prompt apart from the token gift, both civ rows of this civ. */
    const PHASE_BUILD = "build";
    const BUILD_LAST_TURN = 4;

    public function __construct(object $game) {
        parent::__construct(CIV_WEEFOLK, $game);
    }

    /**
     * Two prompts per income turn, queued one after the other rather than side by side: the client
     * tells civ rows apart by their civilization, so two pending rows of this civ would collide.
     * The build prompt is queued once the token is given, or here when nobody can take one.
     */
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
        if ($this->getEligibleOpponents($player_id)) {
            // the income turn rides along, the trade it unlocks is queued once the token is given
            $game->benefitCivEntry($this->civ, $player_id, reason_civ($this->civ, (string) $incomeTurn));
        } else {
            $this->notifyNobodyEligible($player_id);
            $this->queueBuildPhase($player_id, $incomeTurn);
        }
        if ($incomeTurn == 5) {
            $game->queueBenefitNormal(BE_WEEFOLK_SCORE, $player_id, reason_civ($this->civ));
        }
    }

    /** Nobody to receive the mid game token means no prompt at all, or the owner has no button to press. */
    function setupCiv(int $player_id, string $start) {
        if (!$start && !$this->getEligibleOpponents($player_id)) {
            $this->notifyNobodyEligible($player_id);
            return ["tokens" => [], "outposts" => []];
        }
        return parent::setupCiv($player_id, $start);
    }

    function notifyNobodyEligible(int $player_id): void {
        $this->game->notifyWithName(
            "message",
            clienttranslate('${player_name} has nobody to give a player token to, the token is skipped'),
            [],
            $player_id
        );
    }

    /** The mid game gift carries no income turn, and turn 5 is past the trade, so both queue nothing. */
    function queueBuildPhase(int $player_id, int $incomeTurn): void {
        $game = $this->game;
        if (!in_range($incomeTurn, 2, self::BUILD_LAST_TURN) || $game->getCardCountInHand($player_id, CARD_TERRITORY) == 0) {
            return;
        }
        $game->benefitCivEntry($this->civ, $player_id, reason_civ($this->civ, self::PHASE_BUILD));
    }

    /** Opponents who can still be given a token: real players who have not finished (FORMAL_RULES CIV.WEEFOLK.4). */
    function getEligibleOpponents(int $player_id): array {
        $game = $this->game;
        $opponents = [];
        foreach ($game->getOpponentsStartingFromLeft($player_id) as $opponent_id) {
            if ($game->isRealPlayer($opponent_id) && $game->getCurrentEra($opponent_id) <= 5) {
                $opponents[] = (int) $opponent_id;
            }
        }
        return $opponents;
    }

    function isBuildPhase(array $benefit): bool {
        return $this->game->getReasonArg(array_get($benefit, "benefit_data", ""), 3) === self::PHASE_BUILD;
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $game = $this->game;
        $data = $benefit;
        $data["reason"] = $game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        if ($this->isBuildPhase($benefit)) {
            $data["decline"] = true;
            $data["build"] = true; // the client picks the territory tile for this prompt only
            $data["title"] = clienttranslate("You may spend a territory tile to gain an income building");
            $data["slots_choice"] = [
                self::CHOICE_BUILD => [
                    "benefit" => [BE_GAIN_ANY_INCOME_BUILDING],
                    "title" => clienttranslate("Spend a territory tile"),
                    "tooltip" => clienttranslate("Discard a territory tile from your supply, then gain any income building"),
                ],
            ];
            return $data;
        }
        $data["title"] = clienttranslate("Give a player token to an opponent, who plants it in their capital");
        $data["slots_choice"] = [];
        foreach ($this->getEligibleOpponents((int) $player_id) as $index => $opponent_id) {
            $data["slots_choice"][$index + 1] = [
                "player_id" => $opponent_id,
                "player_name" => $game->customGetPlayerNameById($opponent_id),
                "title" => clienttranslate('Give token to ${player_name}'),
                "tooltip" => clienttranslate("They choose the plot of their capital city the token is placed in"),
            ];
        }
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Weefolk:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Weefolk:12", $game->hasCiv($player_id, $this->civ));

        if ($this->isBuildPhase($civ_args)) {
            $this->systemAssertTrue("ERR:Weefolk:13", $spot == self::CHOICE_BUILD);
            $this->spendTerritory($player_id, (int) $extra);
            return;
        }
        $choice = array_get(array_get($civ_args, "slots_choice", []), $spot, []);
        $opponent_id = (int) array_get($choice, "player_id", 0);
        $this->systemAssertTrue("ERR:Weefolk:14", $opponent_id > 0 && $opponent_id != $player_id);
        $this->giveToken($player_id, $opponent_id);
        $this->queueBuildPhase($player_id, (int) $game->getReasonArg(array_get($civ_args, "benefit_data", ""), 3));
    }

    /** Historians style: the tile the owner picked in their supply travels in the extra argument. */
    function spendTerritory(int $player_id, int $tile_type): void {
        $game = $this->game;
        $tile = $game->getCardInfoSearch(CARD_TERRITORY, $tile_type, "hand", $player_id);
        $game->userAssertTrue(clienttranslate("Invalid territory tile"), $tile !== null);
        $game->effect_discardCard($tile["card_id"], $player_id);
        $game->notifyWithName(
            "message",
            clienttranslate('${player_name} spends a territory tile to gain an income building'),
            [],
            $player_id
        );
        $game->queueBenefitNormal(BE_GAIN_ANY_INCOME_BUILDING, $player_id, reason_civ($this->civ));
    }

    /** The token is a cube of the owner, but the row that plants it belongs to the opponent. */
    function giveToken(int $player_id, int $opponent_id): void {
        $game = $this->game;
        $game
            ->notif("message", $player_id)
            ->withPlayer2($opponent_id)
            ->notifyAll(clienttranslate('${player_name} gives a player token to ${player_name2}'));
        if ($game->isZombiePlayer($opponent_id)) {
            // a zombie counts as finished, so their benefit row would be dropped before it reaches
            // the civ; the token is planted for them here instead (FORMAL_RULES CIV.WEEFOLK.4)
            $this->plantForZombie($opponent_id);
            // nobody else becomes active, so the roll needs its own savepoint or undo re-rolls it
            $game->prepareUndoSavepoint();
            return;
        }
        $game->queueBenefitNormal(BE_WEEFOLK_PLOT, $opponent_id, reason_civ($this->civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Weefolk:15", $game->isRealPlayer($player_id));

        switch ($ben) {
            case BE_WEEFOLK_PLOT:
                return $this->plantToken($player_id);
            case BE_WEEFOLK_SCORE:
                $this->systemAssertTrue("ERR:Weefolk:16", $game->hasCiv($player_id, $this->civ));
                return $this->scoreTokens($player_id, $count, $reason);
        }
        $this->systemAssertTrue("ERR:Weefolk:17", false);
        return true;
    }

    /**
     * The opponent plants the token themselves, through the ordinary structure placement state: the
     * cube waits in capital_structure the way a claimed income building does.
     */
    function plantToken(int $opponent_id): bool {
        $game = $this->game;
        $owner = $this->getOwner();
        $this->systemAssertTrue("ERR:Weefolk:18", $owner != $opponent_id);
        $this->systemAssertTrue("ERR:Weefolk:19", $game->getPendingStructure() === null);
        $token_id = $game->addCube($owner, "capital_structure");
        $game->notifyMoveStructure(
            clienttranslate('${player_name} must plant the player token in their capital'),
            $token_id,
            [],
            $opponent_id
        );
        $game->gamestate->nextState("structure");
        return false;
    }

    /**
     * Every planted token scores its row and its column, so a building in two tokens' rows scores
     * twice, and so does one in a token's row that is also in its column (FORMAL_RULES CIV.WEEFOLK.1).
     */
    function scoreTokens(int $player_id, int $count, string $reason): bool {
        $game = $this->game;
        $vp = 0;
        foreach ($game->getStructuresSearch(BUILDING_CUBE, null, "capital\\_cell\\_%", $player_id) as $token) {
            $capital_owner = (int) getPart($token["card_location"], 2);
            if ($capital_owner == $player_id) {
                continue; // a cube of their own is not a planted token (FORMAL_RULES CIV.WEEFOLK.5)
            }
            $vp += $this->countInLine($capital_owner, true, (int) getPart($token["card_location"], 3));
            $vp += $this->countInLine($capital_owner, false, (int) getPart($token["card_location"], 4));
        }
        if ($vp) {
            $game->awardVP($player_id, $vp * $count, $reason);
        }
        return true;
    }

    /** Income buildings and landmarks of one capital touching a single row or column of it. */
    function countInLine(int $capital_owner, bool $is_row, int $line): int {
        $game = $this->game;
        $count = 0;
        foreach ($game->getStructuresSearch(null, null, "capital\\_cell\\_{$capital_owner}\\_%", $capital_owner) as $structure) {
            $type = (int) $structure["card_type"];
            $x = (int) getPart($structure["card_location"], 3);
            $y = (int) getPart($structure["card_location"], 4);
            if ($game->checkValidIncomeType($type)) {
                $count += ($is_row ? $x : $y) == $line ? 1 : 0;
                continue;
            }
            if ($type != BUILDING_LANDMARK) {
                continue;
            }
            $cells = $game->getStructureCells($type, $structure["card_location_arg2"], $x, $y, (int) $structure["card_type_arg"]);
            foreach ($cells as [$cx, $cy]) {
                if (($is_row ? $cx : $cy) == $line) {
                    $count++;
                    break; // a landmark spanning the line is still just one landmark
                }
            }
        }
        return $count;
    }

    /** A quitter cannot be asked where the token goes, so it lands on a random plot (FORMAL_RULES CIV.WEEFOLK.4). */
    function zombieBenefit(array $benefit): void {
        if ((int) $benefit["benefit_type"] == BE_WEEFOLK_PLOT) {
            $this->plantForZombie((int) $benefit["benefit_player_id"]);
        }
    }

    /**
     * A quitter who was already at the placement prompt has the token waiting in capital_structure:
     * that cube is the one to plant, or to put back, or it strands there and every later placement
     * of any player picks it up instead of their own structure.
     */
    function plantForZombie(int $opponent_id): void {
        $game = $this->game;
        $owner = $this->getOwner();
        $pending = $game->getPendingStructure();
        $token_id = $pending && $game->isForeignToken($pending, $opponent_id) ? (int) $pending["card_id"] : 0;
        $plots = $game->getCapitalEmptyPlots($opponent_id);
        if (!$plots) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} is zombie and their city is full, the player token is not placed'),
                [],
                $opponent_id
            );
            $game->dbSetStructureLocation($token_id ?: null, "hand", null, "", $owner);
            return;
        }
        $game->notifyWithName(
            "message",
            clienttranslate('${player_name} is zombie, a random plot is chosen for the player token'),
            [],
            $opponent_id
        );
        [$x, $y] = $plots[$game->bgaRand(0, count($plots) - 1)];
        $game->effect_placeOnCapitalMat($token_id ?: $game->addCube($owner, "hand"), $x, $y, 0, $opponent_id);
    }

    function getOwner(): int {
        $owner = (int) $this->game->getCivOwner($this->civ);
        $this->systemAssertTrue("ERR:Weefolk:20", $owner > 0);
        return $owner;
    }
}
