<?php

declare(strict_types=1);

class Isolationists extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_ISOLATIONISTS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $reason = reason_civ($civ);
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        if ($this->game->isAdjustments4or8()) {
            //"Start with 4 player tokens here (add 1 extra in 4-5 player games) and 2 territory tiles.
            if ($this->game->getPlayersNumber() >= 4) {
                $tokens[] = $this->game->addCivToken($player_id, 5, $civ);
            }
            $this->game->awardCard($player_id, 2, CARD_TERRITORY, false, $reason);
        }

        return ["tokens" => $tokens, "outposts" => []];
    }

    function finalScoring($player_id) {
        $this->finalIsolationistScoring($player_id);
    }

    function finalIsolationistScoring($player_id, $score = true) {
        $map_control = $this->game->getControlHexes($player_id);
        // Create an array to hold each vertex. Default to zero and update with mass_id as we progress.
        $land_mass_overview = []; // this is map of tile+verex to graph connected terrain id
        $valid_coords = [];
        $ladmass_terrain = [];
        foreach ($map_control as $tile) {
            $coord = $tile["map_coords"];
            $land_mass_overview[$coord] = [0, 0, 0, 0, 0, 0];
            $valid_coords[$coord] = $tile;
            if (!$score) {
                $this->debugConsole("to check $coord", ["tile" => $tile], true);
            }
        }
        // Go through each vertex to assign it a landmass.
        $land_mass_id = 1;
        foreach ($map_control as $tile) {
            $coord = $tile["map_coords"];
            $tile_id = $tile["map_tile_id"];
            if (!$score) {
                $this->debugConsole("tile coords $coord tile $tile_id", [], true);
            }
            for ($side = 0; $side < 6; $side++) {
                // for each vertex
                $index = $land_mass_overview[$coord][$side];
                if ($index != 0) {
                    if (!$score) {
                        $this->debugConsole("tile coords $coord:$side - done mass=$index", [], true);
                    }
                    continue;
                }
                $land_mass_overview[$coord][$side] = -1; // this is not connected
                // hasn't been connected yet.
                // Need to identify any neighbours with same terrain..
                $terrain = $this->game->territory_tiles[$tile_id]["x"][$side];
                $tername = $this->game->terrain_types[$terrain]["name"];
                if ($terrain == 1) {
                    // sea
                    if (!$score) {
                        $this->debugConsole("tile coords $coord:$side - sea", [], true);
                    }
                    continue;
                }
                $ladmass_terrain[$land_mass_id] = $terrain;
                // all land on the same tile assigned same number
                $to_check = [];
                $to_check[] = [$coord, $side];
                // Check each neighbour for the same terrain.
                while (count($to_check) > 0) {
                    list($m_coord, $m_vertex) = array_shift($to_check);
                    $vn = $this->getVertexNeighbours($m_coord, $m_vertex, $valid_coords);
                    $land_mass_overview[$m_coord][$m_vertex] = $land_mass_id;
                    $m_tile_id = $valid_coords[$m_coord]["map_tile_id"];
                    for ($x = 0; $x < 6; $x++) {
                        if ($this->game->territory_tiles[$m_tile_id]["x"][$x] == $terrain) {
                            if ($x != $m_vertex) {
                                $vn[] = [$m_coord, $x];
                            }
                        }
                    }
                    //if (!$score)$this->debugConsole("rec nei $m_coord:$m_vertex",$vn, true);
                    foreach ($vn as $nei) {
                        list($n_coord, $n_vertex) = $nei;
                        $n_tile_id = $valid_coords[$n_coord]["map_tile_id"];
                        $n_terrain = $this->game->territory_tiles[$n_tile_id]["x"][$n_vertex];
                        $n_land = $land_mass_overview[$n_coord][$n_vertex];
                        if ($n_terrain == $terrain && $n_land == 0) {
                            $land_mass_overview[$n_coord][$n_vertex] = $land_mass_id;
                            if (!$score) {
                                $this->debugConsole(
                                    "rec coords $m_coord:$m_vertex $tername pass $n_coord:$n_vertex => mass=$land_mass_id ",
                                    [],
                                    true
                                );
                            }
                            if (!in_array($nei, $to_check)) {
                                $to_check[] = $nei;
                                if (!$score) {
                                    $this->debugConsole("pushed $n_coord:$n_vertex", [], true);
                                }
                            }
                        } else {
                            $n_tername = $this->game->terrain_types[$n_terrain]["name"];
                            if (!$score) {
                                $this->debugConsole(
                                    "coords $m_coord:$m_vertex $tername pass $n_coord:$n_vertex $n_tername => mass=$n_land ",
                                    [],
                                    true
                                );
                            }
                        }
                    }
                }
                $land_mass_id++;
            }
        }
        // Now run through the land_masses and count how many tiles they each appear on for the max!
        $highest = 0;
        $highest_terrain = 1;
        for ($index = 1; $index < $land_mass_id; $index++) {
            $count = 0;
            foreach ($land_mass_overview as $lmid => $lm) {
                $found = false;
                for ($b = 0; $b < 6; $b++) {
                    if ($lm[$b] == $index) {
                        $found = true;
                    }
                }
                $count += $found ? 1 : 0;
            }
            if ($count > $highest) {
                $highest = $count;
                $highest_terrain = $ladmass_terrain[$index];
            }
        }
        // Cross check against table for points.
        switch ($highest) {
            case 0:
            case 1:
                $points = 5;
                break;
            case 2:
                $points = 10;
                break;
            case 3:
                $points = 16;
                break;
            case 4:
                $points = 23;
                break;
            case 5:
                $points = 31;
                break;
            default:
                $points = 40;
                break;
        }
        $name = $this->game->terrain_types[$highest_terrain]["name"];
        $this->game->notifyWithName(
            "message",
            clienttranslate('${player_name} (ISOLATIONISTS) has ${count} connected ladnmass tiles of ${tername}'),
            [
                "count" => $highest,
                "tername" => $name,
                "i18n" => ["tername"],
            ]
        );
        if ($score) {
            $this->game->awardVP($player_id, $points, reason_civ(CIV_ISOLATIONISTS));
        }
    }

    function debug_getVN($x, $y, $v) {
        $player_id = $this->game->getActivePlayerId();
        $map_control = $this->game->getControlHexes($player_id);
        $res = $this->getVertexNeighbours($x . "_" . $y, $v, $map_control);
        return $res;
    }

    /**
     *
     * @param string $coords
     * @param int $vertex
     * @param array $valid_coords
     * @return array [$coords, $vertex, $tile_id]
     */
    function getVertexNeighbours($coords, $vertex, $valid_coords) {
        // need to use map_tile_orient and $vertex to know which relative position we are in.
        $neighbours = [];
        if (!array_key_exists($coords, $valid_coords)) {
            return $neighbours;
        }
        $c = explode("_", $coords);
        $x = $c[0];
        $y = $c[1];
        $current_orient = $valid_coords[$coords]["map_tile_orient"];
        $relative = $this->game->getTileRotation($vertex, $current_orient, -1); // reverse rotation
        $relatives = [];
        switch ($relative) {
            case 0:
                array_push($relatives, [$x . "_" . ($y - 1), 2]);
                array_push($relatives, [$x - 1 . "_" . ($y - 1), 4]);
                break;
            case 1:
                array_push($relatives, [$x - 1 . "_" . $y, 5]);
                array_push($relatives, [$x - 1 . "_" . ($y - 1), 3]);
                break;
            case 2:
                array_push($relatives, [$x - 1 . "_" . $y, 4]);
                array_push($relatives, [$x . "_" . ($y + 1), 0]);
                break;
            case 3:
                array_push($relatives, [$x + 1 . "_" . ($y + 1), 1]);
                array_push($relatives, [$x . "_" . ($y + 1), 5]);
                break;
            case 4:
                array_push($relatives, [$x + 1 . "_" . ($y + 1), 0]);
                array_push($relatives, [$x + 1 . "_" . $y, 2]);
                break;
            case 5:
                array_push($relatives, [$x . "_" . ($y - 1), 3]);
                array_push($relatives, [$x + 1 . "_" . $y, 1]);
                break;
            default:
                $this->game->systemAssertTrue("invalid tile rotation $relative");
                break;
        }
        foreach ($relatives as $r) {
            $coord = $r[0];
            $v = $r[1];
            if (array_key_exists($coord, $valid_coords)) {
                $rel_vertex = $this->game->getTileRotation($v, $valid_coords[$coord]["map_tile_orient"]);
                array_push($neighbours, [$coord, $rel_vertex]);
            }
        }
        return $neighbours;
    }
}
