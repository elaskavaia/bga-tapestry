<?php

declare(strict_types=1);

class Werefolk extends AbsCivilization {
    const FACE_DOWN = 0;
    const FACE_UP = 1;
    const VP_NO_REGRESS = 504;

    public function __construct(object $game) {
        parent::__construct(CIV_WEREFOLK, $game);
    }

    /**
     * The mat holds no cube and the ability starts with no decision, so it is queued as a plain
     * benefit instead of going through benefitCivEntry, which would stop on the civ ability state
     * and ask for a click that has nothing to choose between.
     */
    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        if (!$incomeTurn) {
            $incomeTurn = $this->game->getCurrentEra($player_id);
        }
        $income_trigger = $this->getRules("income_trigger", []);
        $from = array_get($income_trigger, "from", 0);
        $to = array_get($income_trigger, "to", 0);
        if (!in_range($incomeTurn, $from, $to)) {
            parent::queueEraCivAbility($player_id, $incomeTurn);
            return;
        }
        $this->game->queueBenefitNormal(BE_WEREFOLK_FLIP, $player_id, reason_civ($this->civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Werefolk:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Werefolk:12", $game->hasCiv($player_id, $this->civ));

        switch ($ben) {
            case BE_WEREFOLK_FLIP:
                return $this->flip($player_id, $reason);
            case BE_WEREFOLK_REGRESS:
                return $this->regressThenExplore($player_id, $reason);
        }
        $this->systemAssertTrue("ERR:Werefolk:13", false);
        return true;
    }

    function discardTileOnMat(int $player_id): void {
        $game = $this->game;
        $tiles = $game->getCardsSearch(CARD_SPACE, null, "civilization_" . $this->civ, $player_id);
        foreach ($tiles as $tile) {
            $game->effect_discardCard((int) $tile["card_id"], $player_id);
        }
    }

    /** The drawn tile is the one that must be explored with, the way COAL BARON marks its territory. */
    function drawTile(int $player_id, string $reason): int {
        $game = $this->game;
        $cards = $game->awardCard($player_id, 1, CARD_SPACE, false, $reason);
        $card = reset($cards);
        $tile_id = $card ? (int) $card["id"] : 0;
        $game->setGameStateValue("selected_space_tile", $tile_id);
        return $tile_id;
    }

    /**
     * Face-up leaves the regress optional, and declining it is what pays the 4 VP, so both are
     * offered as one choice. A player who cannot regress anywhere did not regress, so they take
     * the VP with no prompt.
     */
    function flip(int $player_id, string $reason): bool {
        $game = $this->game;
        $this->discardTileOnMat($player_id);
        if (!$this->drawTile($player_id, $reason)) {
            // no tile gained means nothing to flip, the whole ability is skipped (FORMAL_RULES 5.3)
            $game->notifyWithName("message", clienttranslate('${player_name} cannot gain a space tile, the ability is skipped'), [], $player_id);
            return true;
        }
        $face_up = $game->bgaRand(self::FACE_DOWN, self::FACE_UP) == self::FACE_UP;
        $game->notifyWithName(
            "message",
            $face_up
                ? clienttranslate('${player_name} flips the space tile face-up')
                : clienttranslate('${player_name} flips the space tile face-down'),
            [],
            $player_id
        );
        $game->prepareUndoSavepoint();

        if (!$face_up) {
            $game->queueBenefitInterrupt(
                [
                    "or" => [
                        BE_ADVANCE_EXPLORATION_BENEFIT_FREEBONUS,
                        BE_ADVANCE_SCIENCE_BENEFIT_FREEBONUS,
                        BE_ADVANCE_MILITARY_BENEFIT_FREEBONUS,
                        BE_ADVANCE_TECHNOLOGY_BENEFIT_FREEBONUS,
                    ],
                ],
                $player_id,
                $reason
            );
            return true;
        }
        $choice = count($this->regressableTracks($player_id)) ? ["or" => [BE_WEREFOLK_REGRESS, self::VP_NO_REGRESS]] : self::VP_NO_REGRESS;
        $game->queueBenefitInterrupt($choice, $player_id, $reason);
        return true;
    }

    function regressThenExplore(int $player_id, string $reason): bool {
        $game = $this->game;
        $tracks = $this->regressableTracks($player_id);
        $this->systemAssertTrue("ERR:Werefolk:20", count($tracks) > 0);

        $regress = [];
        foreach ($tracks as $track) {
            $regress[] = BE_REGRESS_EXPLORATION_NOBENEFIT - 1 + $track;
        }
        $game->queueBenefitInterrupt([["or" => $regress], BE_WEREFOLK_EXPLORE], $player_id, $reason);
        return true;
    }

    /** Tracks holding a cube that can still move back a spot. Virtual cubes never regress. */
    function regressableTracks(int $player_id): array {
        $tracks = [];
        foreach ($this->game->getStructuresSearch(BUILDING_CUBE, null, "tech\\_spot\\_%", $player_id) as $cube) {
            if ((int) $cube["card_type_arg"] == CUBE_AI) {
                continue;
            }
            $track = (int) getPart($cube["card_location"], 2);
            $spot = (int) getPart($cube["card_location"], 3);
            if ($spot > 0) {
                $tracks[$track] = $track;
            }
        }
        ksort($tracks);
        return array_values($tracks);
    }
}
