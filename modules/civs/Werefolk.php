<?php

declare(strict_types=1);

class Werefolk extends AbsCivilization {
    const FACE_DOWN = 0;
    const FACE_UP = 1;
    const VP_NO_REGRESS = 504;
    const CHOICE_FLIP = 1;

    public function __construct(object $game) {
        parent::__construct(CIV_WEREFOLK, $game);
    }

    /**
     * The mat holds no cube and the flip has nothing to choose between, but the ability still goes
     * through the civ ability state: that is what puts it in the pool a player picks the order from
     * when another income civ is pending. A single button stands in for the missing slots.
     */
    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        $data["title"] = clienttranslate("You must draw a space tile and flip it");
        $data["slots_choice"] = [
            self::CHOICE_FLIP => [
                "title" => clienttranslate("Flip the space tile"),
                "tooltip" => clienttranslate("Discard the tile on the mat, gain a random space tile and flip it like a coin"),
            ],
        ];
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Werefolk:14", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Werefolk:15", $game->hasCiv($player_id, $this->civ));
        $this->systemAssertTrue("ERR:Werefolk:16", $spot == self::CHOICE_FLIP);

        $game->queueBenefitNormal(BE_WEREFOLK_FLIP, $player_id, reason_civ($this->civ));
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
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} cannot gain a space tile, the ability is skipped'),
                [],
                $player_id
            );
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
            $choice = [
                "or" => [
                    BE_ADVANCE_EXPLORATION_BENEFIT_FREEBONUS,
                    BE_ADVANCE_SCIENCE_BENEFIT_FREEBONUS,
                    BE_ADVANCE_MILITARY_BENEFIT_FREEBONUS,
                    BE_ADVANCE_TECHNOLOGY_BENEFIT_FREEBONUS,
                ],
            ];
        } else {
            $choice = count($this->regressableTracks($player_id))
                ? ["or" => [BE_WEREFOLK_REGRESS, self::VP_NO_REGRESS]]
                : self::VP_NO_REGRESS;
        }
        $game->queueBenefitInterrupt($choice, $player_id, $reason);
        $game->queueBenefitNormal(BE_CIV_END, $player_id, $reason);
        return true;
    }

    /** Only the explore this ability may queue consumes the marker; the other branches leave it behind. */
    function endCivAbility(int $player_id): void {
        $this->game->setGameStateValue("selected_space_tile", 0);
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
