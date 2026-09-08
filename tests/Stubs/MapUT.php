<?php

declare(strict_types=1);

require_once __DIR__ . "/GameUT.php";

/**
 * Harness for the territory map. getMap and everything that reads it - conquest targeting,
 * control, stand up, the end of conquer awards - now go through the card and structure models
 * rather than raw SQL, so the engine runs here unmodified and needs no override at all.
 *
 * Player ids start at 11 so they stay clear of PLAYER_AUTOMA (1) and PLAYER_SHADOW (2), which
 * customGetPlayerNameById would otherwise rename. Two players means the small map, which material
 * already tiles at 0_0 and at twelve hexes around it.
 */
class MapUT extends GameUT {
    const OWNER = 11;
    const OPPONENT = 12;
    const OTHER = 13;

    function __construct(int $players = 2) {
        parent::__construct($players);
        $this->_setPlayerBasicInfo(array_fill_keys(range(self::OWNER, self::OWNER + $players - 1), []));
        $this->curid = self::OWNER;
        $this->_setCurrentPlayerId($this->curid);
        $this->era = 2;
    }

    function init() {
        $this->gamestate->changeActivePlayer(self::OWNER);
        $this->gamestate->jumpToState(18);
    }

    /** An explored territory, as the tile card the map reader picks up for that coord. */
    function setTile(string $coord, int $tile_id = 1, int $rot = 0): int {
        return $this->cards->addRow(CARD_TERRITORY, $tile_id, "map", $rot, $coord);
    }

    function addOutpostAt(int $player_id, string $coord, int $toppled = 0): int {
        return $this->dbAddStructure($player_id, BUILDING_OUTPOST, $toppled, "land_$coord");
    }

    /** A player token on a territory: a cube carrying the inert flag, the Infiltrators shape. */
    function addTokenAt(int $player_id, string $coord): int {
        return $this->addCubeAt($player_id, "land_$coord", 1);
    }

    function giveOutposts(int $player_id, int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $this->dbAddStructure($player_id, BUILDING_OUTPOST, 0, "hand");
        }
    }

    /** Read off the whole map rather than one hex, which is how every decision reads it. */
    function hexOwners(string $coord): array {
        return $this->getMap()[$coord]["map_owners"];
    }

    function hexOccupants(string $coord): array {
        return $this->getMap()[$coord]["map_occupants"];
    }

    function hexOccupancy(string $coord): int {
        return (int) $this->getMap()[$coord]["occupancy"];
    }

    /** The topple flag of one structure: 0 stands and controls, 1 is toppled or inert. */
    function toppleFlag(int $structure_id): int {
        return (int) $this->getStructureInfoById($structure_id)["card_type_arg"];
    }

    /** An alliance tapestry between two players, which conquest targeting reads through isAlly. */
    function allyWith(int $player_id, int $ally_id): void {
        $this->cards->addRow(CARD_TAPESTRY, 5, "era" . $this->era, $player_id, $ally_id);
    }
}
