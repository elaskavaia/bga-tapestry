<?php

declare(strict_types=1);

class Traders extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_TRADERS, $game);
    }
    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $civ = $this->civ;
        $this->systemAssertTrue("ERR:AbsCivilization:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:AbsCivilization:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case 0:
                $this->systemAssertTrue("not implemented");
                return true;
            default:
                $this->systemAssertTrue("ERR:AbsCivilization:10");
                return true;
        }
        return true;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $civ = $this->civ;
        $game = $this->game;
        $game->systemAssertTrue("ERR:Traders:01", $this->game->hasCiv($player_id, $civ));
        $game->systemAssertTrue("ERR:Traders:02", $extra);
        $this->sendTrader($player_id, $extra);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $benefit;
        $condition = array_get($benefit, "benefit_data");
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data["slots"] = [];
        $targets = $this->getTraderTargets($player_id);
        $data["targets"] = $targets;
        if ($game->isAdjustments8()) {
            $cards = $game->getCardsInHand($player_id, CARD_TERRITORY);
            $data["cards"] = array_keys($cards);
        }
        return $data;
    }

    function sendTrader(int $player_id, array $extra) {
        $civ = $this->civ;
        $game = $this->game;
        $reason = reason_civ($civ);

        $land_coords = array_get($extra, "coords", "");
        $building_type = array_get($extra, "bt", 0);
        $givetile = (int) array_get($extra, "tile", 0);

        // VALIDITY check. Check that coords match an empty or single outpost space.
        $coords = getPart($land_coords, 1) . "_" . getPart($land_coords, 2);
        $targets = $this->getTraderTargets($player_id);
        if (array_get($targets, $coords, null) === null) {
            $game->systemAssertTrue("ERR:Traders:03");
        }
        $map_data = $game->getMap();
        $hex = $map_data[$coords];
        $tile_structs = $hex["structures"];

        // adj8 trades a tile away on both opponent options, so the pick is checked before the board moves
        $sharing_with = $tile_structs ? (int) reset($tile_structs)["card_location_arg"] : 0;
        $tile_to_give = [];
        if ($game->isAdjustments8() && $sharing_with && $sharing_with != $player_id) {
            $tile_to_give = $this->assertTileToGive($player_id, $givetile);
        }

        if ($building_type) {
            $game->systemAssertTrue("trader cannot place income building with default rules", $game->isAdjustments4or8());
            //--adj4: Gain an income building and place it on a territory with exactly 1 opponent outpost token and nothing else; the opponent immediately gains the benefit revealed by the income building. The opponent controls the territory.
            //--adj8: ... (toppled) ...: Give a territory tile to that opponent, then both you and your opponent gain the benefit revealed by the income building. The opponent controls the territory.
            $message = clienttranslate('${player_name} places an income building at ${coord_text}');
            $trader = $game->dbGetIncomeBuildingOfType($building_type, true);
            $game->systemAssertTrue("no income building of game type left", $trader);
            $revealed_spot = (int) $game->getStructureInfoById($trader)["card_location_arg2"];
            $game->claimIncomeStructure($building_type, null);
            $building_benefits = $game->income_tracks[$building_type][$revealed_spot]["benefit"];
            $game->effect_placeOnMap($player_id, $trader, $land_coords, $message, false);
            $other_building = array_shift($tile_structs);
            $opponent_id = (int) $other_building["card_location_arg"];
            $game->userAssertTrue(
                clienttranslate('TRADERS can only place income building on opponent\'s territory'),
                $opponent_id != $player_id
            );
            if ($game->isAdjustments8()) {
                $this->giveTerritoryTile($player_id, $opponent_id, $tile_to_give);
                $game->queueBenefitNormal($building_benefits, $player_id, $reason);
            }
            $game->queueBenefitNormal($building_benefits, $opponent_id, $reason);
            return;
        }
        // Check player owns TRADERS and the token is available.
        $message = clienttranslate('${player_name} places a Trader token at ${coord_text}');
        $trader = $this->getSingleCube($player_id);
        $game->effect_placeOnMap($player_id, $trader, $land_coords, $message, false);

        // APPLY BENEFITS
        if (count($tile_structs) == 0) {
            // benefit is 1VP per adjacent opponent territory
            if ($game->isAdjustments4or8()) {
                return $game->userAssertTrue(clienttranslate("Cannot place on empty territory"));
            }

            $neighbours = $game->getNeighbourHexes($coords);
            $count = 0;
            $opponents = $game->getOpponentsStartingFromLeft($player_id);
            foreach ($neighbours as $neighbour) {
                foreach ($opponents as $opponent_id) {
                    if ($game->isHexOwner($opponent_id, $neighbour, $map_data)) {
                        $count++;
                        break;
                    }
                }
            }
            $game->awardVP($player_id, $count, $reason);
            return;
        }
        $other_building = array_shift($tile_structs);
        if ($other_building["card_type"] != "5") {
            return $game->userAssertTrue(clienttranslate("TRADERS can only share hexes with outposts"), false, toJson($other_building));
        }

        $map_tile_id = $hex["map_tile_id"];
        $tile_data = $game->territory_tiles[$map_tile_id];
        $benefits = array_get($tile_data, "benefit");

        $self_control = $other_building["card_location_arg"] == $player_id;

        if (!$game->isAdjustments4or8()) {
            $game->userAssertTrue(clienttranslate('TRADERS can only place a cube on opponent\'s territory'), !$self_control);
            if ($benefits) {
                $game->queueBenefitInterrupt($benefits, $player_id, $reason);
            }
            return;
        }
        //adj4
        //--Gain an income building and place it on a territory with exactly 1 opponent outpost token and nothing else; the opponent immediately gains the benefit revealed by the income building. The opponent controls the territory.
        //--Place a player token on a territory with exactly 1 opponent outpost token and nothing else: Gain the benefit on the territory (if any); you both share control of game territory for scoring purposes.
        //--Place a player token on a territory you control with exactly 1 outpost token and nothing else: Gain the benefit on the territory (if any).

        if ($game->isAdjustments4()) {
            if ($benefits) {
                $game->queueBenefitInterrupt($benefits, $player_id, $reason);
            }
            return;
        }
        //adj8
        // - Place a player token on a territory with exactly 1 opponent outpost and nothing else: Give a territory tile to that opponent, then you gain both the benefit on the traded territory tile and the benefit of the territory (if any) on which you just placed a player token. The opponent controls the territory.
        // - Gain an income building and place it (toppled) on a territory with exactly 1 opponent outpost and nothing else: Give a territory tile to that opponent, then both you and your opponent gain the benefit revealed by the income building. The opponent controls the territory.
        // - Place a player token on a territory you control with exactly 1 outpost and nothing else: Gain [ANY RESOURCE] or [5VP], then roll the black conquer die and gain the result; each opponent gains a territory tile.
        if ($game->isAdjustments8()) {
            if ($self_control) {
                $game->setSelectedMapHex($coords);

                // gain 1 res or 5 vp
                // gain black dice roll for that terr
                $game->interruptBenefit();
                $game->queueBenefitNormal(["or" => [BE_ANYRES, 505]], $player_id, $reason);
                $game->queueBenefitNormal(324, $player_id, $reason); // roll black conquer die
                $game->queueBenefitNormal(340, $player_id, $reason); //BE_OPPONENTS_GAIN_TILE
                return;
            }
            $this->giveTerritoryTile($player_id, (int) $other_building["card_location_arg"], $tile_to_give);
            $benefits2 = array_get($game->territory_tiles[(int) $tile_to_give["card_type_arg"]], "benefit");

            $game->interruptBenefit();
            if ($benefits) {
                $game->queueBenefitNormal($benefits, $player_id, $reason);
            }
            $game->queueBenefitNormal($benefits2, $player_id, $reason);
            return;
        }
    }

    /** The territory tile the player picked to trade away, as a card row. */
    function assertTileToGive(int $player_id, int $card_id): array {
        $game = $this->game;
        $game->userAssertTrue(clienttranslate("You need a territory tile to give"), $card_id > 0);
        $tile = $game->getCardInfoById($card_id);
        $game->userAssertTrue(
            clienttranslate("You can only give away a territory tile from your own hand"),
            $tile &&
                $tile["card_type"] == CARD_TERRITORY &&
                $tile["card_location"] == "hand" &&
                (int) $tile["card_location_arg"] == $player_id
        );
        return $tile;
    }

    /** Hand the traded tile to the opponent sharing the territory. */
    function giveTerritoryTile(int $player_id, int $opponent_id, array $tile): void {
        if ($opponent_id == PLAYER_SHADOW) {
            // the Shadow Empire has no hand to hold it, so the traded tile leaves the game instead
            $this->game->effect_moveCard((int) $tile["card_id"], $player_id, "discard", 0);
            return;
        }
        $this->game->effect_moveCard((int) $tile["card_id"], $player_id, "hand", $opponent_id);
    }

    function getTraderTargets($player_id) {
        $game = $this->game;

        $valid = [];
        $map_data = $game->getMap();
        if (!$game->isAdjustments4or8()) {
            // not owned explored
            foreach ($map_data as $coord => $hex) {
                if ($hex["map_tile_id"] == 0) {
                    continue;
                }
                if ($hex["occupancy"] == 0) {
                    $valid[$coord] = 0;
                } elseif ($hex["occupancy"] == 1 && !$game->isHexOwner($player_id, $coord, $map_data)) {
                    $building = array_shift($hex["structures"]);
                    if ($building["card_type"] == BUILDING_OUTPOST) {
                        $valid[$coord] = 1;
                    }
                }
            }
        } else {
            foreach ($map_data as $coord => $hex) {
                if ($hex["map_tile_id"] == 0) {
                    continue;
                }
                if ($hex["occupancy"] == 1) {
                    $building = array_shift($hex["structures"]);
                    if ($building["card_type"] == BUILDING_OUTPOST) {
                        $owner = $game->isHexOwner($player_id, $coord, $map_data);
                        $valid[$coord] = $owner ? 2 : 1;
                    }
                }
            }
        }
        return $valid;
    }
}
