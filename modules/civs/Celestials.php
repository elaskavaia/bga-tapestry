<?php

declare(strict_types=1);

/**
 * Celestials, the second Fantasies and Futures civilization: one of the two starting outposts is
 * replaced by a player token, the floating capital, which drifts one territory per income turn
 * 2-4 and rolls both conquer dice when it does. Income turn 5 scores the landmarks of the capital
 * city by whether they hang off the side of the mat.
 *
 * The token is an ordinary inert player token on the map: it occupies a territory without
 * controlling it (isControllingStructure), so nothing beyond this class knows the civ is in play.
 * Once placed, tokens are indistinguishable (FORMAL_RULES CIV.INFILTRATORS.1), so the move offers every inert
 * cube of the owner on the map, an INFILTRATORS or ISOLATIONISTS one included.
 */
class Celestials extends AbsCivilization {
    const MOVE_LAST_TURN = 4;
    const VP_ON_MAT = 5;
    const VP_HANGING = -2;

    public function __construct(object $game) {
        parent::__construct(CIV_CELESTIALS, $game);
    }

    /**
     * The token takes the place of one outpost on the starting territory and that outpost goes
     * back to the supply, where getOutpostsInHand finds it for a later conquest. Mid game the
     * territory is the same one; an owner with no outpost left there is not reachable, since an
     * outpost only ever leaves a territory to stand on another one.
     */
    function setupCiv(int $player_id, string $start) {
        $game = $this->game;
        $location = array_get($game->getStartingPosition($player_id), "location", "");
        $this->systemAssertTrue("ERR:Celestials:11", $location != "");
        $outpost = $game->getStructureInfoSearch(BUILDING_OUTPOST, null, $location, $player_id);
        if ($outpost) {
            $game->dbSetStructureLocation(
                (int) $outpost["card_id"],
                "hand",
                null,
                clienttranslate('${player_name} returns an outpost to their supply'),
                $player_id
            );
        }
        $token_id = (int) $game->addCube($player_id, "hand");
        $game->effect_placeOnMap(
            $player_id,
            $token_id,
            $location,
            clienttranslate('${player_name} places their floating capital at ${coord_text}'),
            false
        );
        return ["tokens" => [$token_id], "outposts" => []];
    }

    /** The move is a prompt with a decline button, the scoring is one deterministic row. */
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
        if ($incomeTurn <= self::MOVE_LAST_TURN) {
            $game->queueBenefitNormal(BE_CELESTIALS_MOVE, $player_id, reason_civ($this->civ));
            return;
        }
        $game->queueBenefitNormal(BE_CELESTIALS_SCORE, $player_id, reason_civ($this->civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Celestials:12", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Celestials:13", $game->hasCiv($player_id, $this->civ));
        if ($ben == BE_CELESTIALS_SCORE) {
            return $this->scoreLandmarks($player_id, $count, $reason);
        }
        $this->systemAssertTrue("ERR:Celestials:14", false);
        return true;
    }

    /**
     * A landmark of the capital city scores 5 when its whole footprint is on the mat and -2 when
     * any cell of it is off, judged from the anchor and the mask as placed. Landmarks that are not
     * in the city - one set aside in hand, one on a CRAFTSMEN slot - score neither.
     */
    function scoreLandmarks(int $player_id, int $count, string $reason): bool {
        $game = $this->game;
        $vp = 0;
        foreach ($game->getStructuresSearch(BUILDING_LANDMARK, null, "capital\\_cell\\_{$player_id}\\_%", $player_id) as $landmark) {
            $vp += $game->isLandmarkOverhanging($landmark) ? self::VP_HANGING : self::VP_ON_MAT;
        }
        if ($vp) {
            $game->awardVP($player_id, $vp * $count, $reason);
        }
        return true;
    }

    /**
     * Every inert token of the owner on the map, and the explored territories each of them may
     * drift to. Occupancy is not a filter: the card allows moving onto a full territory.
     */
    function getMoveTargets(int $player_id): array {
        $game = $this->game;
        $map = $game->getMap();
        $targets = [];
        foreach ($game->getStructuresSearch(BUILDING_CUBE, 1, "land\\_%", $player_id) as $token) {
            $coords = substr($token["card_location"], 5); // land_
            $neighbours = array_values(array_filter($game->getNeighbourHexes($coords, $map), fn($hex) => $map[$hex]["map_tile_id"] != 0));
            if ($neighbours) {
                $targets[(int) $token["card_id"]] = $neighbours;
            }
        }
        return $targets;
    }
}
