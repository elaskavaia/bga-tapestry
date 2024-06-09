<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * tapestry implementation : © Adam Dewbery <adam@dewbs.co.uk>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * tapestry.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 *
 */
require_once('tapcommon.php');
require_once "civs/AbsCivilization.php";
require_once "civs/BasicCivilization.php";

abstract class PGameXBody extends tapcommon {
    // material
    public array $dice_names;
    public array $tech_card_data;
    public array $tech_track_data;
    public array $tech_track_types;

    public array $income_tracks;
    public array $tapestry_card_data;
    public array $benefit_types;

    public array $card_types;
    public array $civilizations;

    public array $territory_tiles;
    public array $terrain_types;
    public array $space_tiles;
    public array $landmark_data;
    public array $structure_types;
    public array $capitals;
    public array $map;
    public array $dice;
    public array $automa_civ_cards;
    public array $decision_cards;

    // decks
    protected Deck $cards;
    protected Deck $structures;
    // cache
    protected array $player_bots;
    protected bool $token_types_adjusted;


    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        $this->initGameStateLabels(array(
            "conquer_die_red" => 10, "conquer_die_black" => 11, "science_die" => 12,
            "toppled_player" => 13, //
            "toppled_by" => 14, //
            "map_id" => 15, //
            "benefit_available" => 16, // unused
            "structure_type_arg" => 17, // used for setting selection of structure type
            "target_structure" => 18, // target structure for actions that target a structure
            "tech_refresh" => 19, // not used
            "anywhere" => 20, // for explore set to 1 if can explore anywhere
            "conquer_bonus" => 21, // can pick one die or both
            "income_turn" => 22, // set to 1 for the duration of "income" turn
            "bonus_available" => 23, // for advance set to 1 if bonus is available to buy, unused
            "maxout_bonus" => 24, // set to 1 is maxout bonus is on, unused
            "current_player_turn" => 25, // player_id that is the leader of the turn (who's turn is it)
            "coal_baron" => 26, //
            "science_die_empiricism" => 27, //
            "partial_undo" => 28, // set to 0 when full undo available, 1 partial
            "income_turn_phase" => 29, // set to phase of income turn 10 - civ ability, 15 - first to era bonus, 20 - play tapestry, 30 - upgrade, 35 - gain vp, 40 gain resources
            "invent_face_up" => 30, // 0 can do both, 1 only face up, 2 only face down
            "undo_move" => 31, // set to number of mover where undo will be unrolled
            "cube_choice" => 32, // cube position (in rare case where multiple cubes on the same track)
            "automa_no" => 33, // player_no for automa/shadow pair
            "automa_civ" => 34, // automa civ number
            "target_player" => 35, // target player for actions that target a player
            "map_coords_selected" => 36, //
            "starting_player" => 37,
            // debug
            "soft_block" => 99,
            // variants
            "variant_adjustments" => 100, // adjustment variant
            "expansion_civ" => 140, // expansion (civ only)
            "income_version" => 150, // removed
            "automa_level" => 151, "shadow_level" => 152,
        ));
        $this->cards = $this->getNew("module.common.deck");
        $this->cards->init("card");
        $this->structures = $this->getNew("module.common.deck");
        $this->structures->init("structure");
        $this->token_types_adjusted = false;
    }

    /**
     * This is called before every action, unlike constructor this method has initialized state of the table so it can
     * access db
     *
     *     @Override
     */
    protected function initTable() {
        // this fiddles with material file depending on the extension selected
        $this->adjustMaterial();
    }

    function isAdjustments4() {
        $variant = $this->getAdjustmentVariant();
        return $variant == 4;
    }
    function isAdjustments4or8() {
        $variant = $this->getAdjustmentVariant();
        return $variant == 4 || $variant == 8;
    }
    function isAdjustments8() {
        $variant = $this->getAdjustmentVariant();
        return $variant == 8;
    }

    function getAdjustmentVariant() {
        $variant = $this->getGameStateValue('variant_adjustments');
        if ($variant == 3)
            $variant = 4;
        return $variant;
    }

    function isExpansionIncluded($exp_flag) {
        if (is_string($exp_flag)) {
            $exp = $exp_flag;
            $exp_flag = EXP_BA_FLAG;
            switch ($exp) {
                case 'AA':
                    $exp_flag = EXP_AA_FLAG;
                    break;
                case 'PP':
                    $exp_flag = EXP_PP_FLAG;
                    break;
                case 'BA':
                    $exp_flag = EXP_BA_FLAG;
                    break;
                default:
                    $this->systemAssertTrue("Unknown expantion $exp");
                    break;
            }
        }
        return is_flag_set($this->getGameStateValue('expansion_civ', 0b001), $exp_flag);
    }

    function adjustMaterial($force = false) {
        $all_tables = [&$this->civilizations];
        if (!$force && $this->token_types_adjusted)
            return $all_tables;
        $this->token_types_adjusted = true;
        $adj = $this->getAdjustmentVariant();
        $num = $this->getPlayersNumberWithBots();
        return $this->doAdjustMaterial($num, $adj);
    }

    function doAdjustMaterial(int $num, int $variant) {
        $all_tables = [&$this->civilizations];
        $adj = $variant;
        foreach ($all_tables as &$token_types) {
            foreach ($token_types as $index => &$table) {
                foreach ($table as $key => $civ_info) {
                    $vars = explode('@', $key, 2);
                    if (count($vars) <= 1)
                        continue;
                    $primary = $vars[0];
                    $variant = $vars[1];
                    // if variant matches
                    $orig = $variant;
                    $variant = preg_replace("/p${num}/", "", $variant, 1);
                    if ($orig != $variant) {
                        $variant = preg_replace("/p[0-9]/", "", $variant);
                    }

                    $orig = $variant;
                    $variant = preg_replace("/a${adj}/", "", $variant, 1);
                    if ($orig != $variant) {
                        $variant = preg_replace("/a[0-9]/", "", $variant);
                    }

                    if ($variant !== '') {
                        $table["$primary@$variant"] = $table[$key]; // want not reduces, incompatible with this game
                    } else {
                        // override existing value
                        if (is_array($table[$key])) {
                            $prev = array_get($table, $primary, []);
                            if (!is_array($prev)) {
                                $this->systemAssertTrue("Expecting array for $primary on $index");
                            }
                            $table[$primary] = array_replace_recursive($prev, $table[$key]);
                        } else
                            $table[$primary] = $table[$key];
                        if ($key != $primary)
                            unset($table[$key]);
                    }
                }
            }
        }
        foreach ($this->civilizations as $key => &$civ_info) {
            if (!array_key_exists('automa', $civ_info)) {
                $civ_info['automa'] = true;
            }
            if (!array_key_exists('exp', $civ_info)) {
                $civ_info['exp'] = 'BA';
            }
            if (!array_key_exists('adjustment', $civ_info)) {
                $civ_info['adjustment'] = '';
            }
            $slots = array_get($civ_info, 'slots', null);
            if (!$slots)
                continue;
            $has_benefits = false;
            $i = 0;
            $prev = [0, 0];
            $dir = ['LR', 'TB'];
            foreach ($slots as $index => &$slot_info) {
                $benefit = array_get($slot_info, 'benefit', null);
                if ($benefit)
                    $has_benefits = true;
                $curr = [array_get($slot_info, 'left', 0), array_get($slot_info, 'top', 0)];
                if ($i == 1) {
                    if ($curr[1] < $prev[1])
                        $dir = ['BT', 'LR'];
                    if ($curr[1] > $prev[1])
                        $dir = ['TB', 'LR'];
                }
                if ($i > 0) {
                    if ($dir[1] == 'LR') {
                        if ($curr[0] != $prev[0]) {
                            $civ_info['slots'][$index]['wrap'] = 1;
                        }
                    } else {
                        if ($curr[1] != $prev[1]) {
                            $civ_info['slots'][$index]['wrap'] = 1;
                        }
                    }
                }
                $prev = $curr;
                $i++;
            }
            $civ_info['slots_benefits'] = $has_benefits;
            $civ_info['slots_dir'] = $dir;
            $dir_tr = [];
            foreach ($dir as $one) {
                if ($one === 'LR')
                    $dir_tr[] = clienttranslate('left to right');
                if ($one === 'TB')
                    $dir_tr[] = clienttranslate('top to bottom');
                if ($one === 'BT')
                    $dir_tr[] = clienttranslate('bottom to top');
            }
            if (!isset($civ_info['slots_description']))
                $civ_info['slots_description'] = $dir_tr;
        }
        $this->card_types[CARD_CIVILIZATION]['data'] = $this->civilizations;
        return $all_tables;
    }

    /*
     * setupNewGame:
     *
     * This method is called only once, when a new game is launched.
     * In this method, you must setup the game according to the game rules, so that
     * the game is ready to be played.
     */
    protected function setupNewGame($players, $options = array()) {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        shuffle($default_colors);
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        $this->DbQuery($sql);
        if ($gameinfos['favorite_colors_support'])
            $this->reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        $this->reloadPlayersBasicInfos();
        $this->activeNextPlayer();
        $this->setupNewGameTables();
    }

    function debug_initTables() {
        try {
            $this->DbQuery("DELETE FROM playerextra");
            $this->DbQuery("DELETE FROM card");
            $this->DbQuery("DELETE FROM structure");
            $this->DbQuery("DELETE FROM benefit");
            $this->DbQuery("DELETE FROM map");
            $this->DbQuery("DELETE FROM capital");
            $this->DbQuery("DELETE FROM stats");
            $this->setupNewGameTables();
        } catch (Exception $e) {
            $this->error($e);
        }
        $newGameDatas = $this->getAllTableDatas(); // this is framework function
        $this->notifyPlayer($this->getActivePlayerId(), 'resetInterfaceWithAllDatas', '', $newGameDatas); // this is notification to reset all data
        $this->notifyAllPlayers("message", 'setup called', []);
    }

    protected function setupNewGameTables() {
        // initialize playerextra
        $players = $this->loadPlayersBasicInfos();
        $numPlayers = count($players);
        $automa = $this->isSolo();
        $shadow = $this->isShadowEmpireOnly();
        $playerswithbots = $this->loadPlayersBasicInfosWithBots(true);
        $values = [];
        $sql = "INSERT INTO playerextra (player_id, player_color, player_name, player_avatar) VALUES ";
        $values = array();
        foreach ($playerswithbots as $player_id => $player) {
            $values[] = "('" . $player_id . "','" . $player['player_color'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        $this->DbQuery($sql);
        $this->initStat('table', 'turns_number', 0);
        $this->initStat('player', 'turns_total', 0);
        $this->initStat('player', 'turns_era_1', 0);
        $this->initStat('player', 'turns_era_2', 0);
        $this->initStat('player', 'turns_era_3', 0);
        $this->initStat('player', 'turns_era_4', 0);
        $this->initStat('player', 'bonuses', 0);
        $this->initStat('player', 'track1', 0);
        $this->initStat('player', 'track2', 0);
        $this->initStat('player', 'track3', 0);
        $this->initStat('player', 'track4', 0);
        $this->initStat('player', 'capital', 0);
        $this->initStat('player', 'civ', 0);
        $all_stats = $this->getStatTypes();
        $player_stats = $all_stats['player'];
        // auto-initialize all stats that starts with game_
        // we need a prefix because there is some other system stuff
        foreach ($player_stats as $key => $value) {
            if (startsWith($key, 'game_')) {
                $this->initStat('player', $key, 0);
            }
        }
        $game_stats = $all_stats['table'];
        // auto-initialize all stats that starts with game_
        // we need a prefix because there is some other system stuff
        foreach ($game_stats as $key => $value) {
            if (startsWith($key, 'game_')) {
                $this->initStat('table', $key, 0);
            }
        }
        // TILE / CARD SETUP
        // Territory tiles
        $cards = array();
        foreach ($this->territory_tiles as $ttid => $tt) {
            if ($ttid < 49) { // Higher numbers are printed on the map, not tiles...
                $cards[] = array('type' => CARD_TERRITORY, 'type_arg' => $ttid, 'nbr' => 1);
            }
        }
        $this->cards->createCards($cards, 'deck_territory');
        $this->cards->shuffle('deck_territory');
        // Space tiles
        $cards = array();
        foreach ($this->space_tiles as $stid => $st) {
            $cards[] = array('type' => CARD_SPACE, 'type_arg' => $stid, 'nbr' => 1);
        }
        $this->cards->createCards($cards, 'deck_space');
        $this->cards->shuffle('deck_space');
        // Tapestry Cards
        //Age of Sail*, Alliance, Coal Baron*, Dictatorship,        Diplomacy*, Espionage*, Marriage of State, Oil Magnate, 
        // Olympic Host, Steam Tycoon*, Trade  Economy*, and 2 traps.
        $atoma_tap_exclusions = [
            TAP_AGE_OF_SAIL, 5, TAP_COAL_BARON, TAP_DICTATORSHIP, 14, TAP_ESPIONAGE,
            TAP_MARRIAGE_OF_STATE, 28, 29, TAP_STEAM_TYCOON, 41
        ];
        $traps = 7;
        if ($automa)
            $traps -= 2;
        $cards = array();
        foreach ($this->tapestry_card_data as $tcid => $tc) {
            if ($tcid == 0)
                continue; // face down card should not be in the deck
            if ($automa && in_array($tcid, $atoma_tap_exclusions))
                continue;
            $nbr = ($tcid == TAP_TRAP) ? $traps : 1; // 7 trap cards
            $cards[] = array('type' => CARD_TAPESTRY, 'type_arg' => $tcid, 'nbr' => $nbr);
        }
        $this->cards->createCards($cards, 'deck_tapestry');
        $this->cards->shuffle('deck_tapestry');
        // Technology Cards
        $cards = array();
        foreach ($this->tech_card_data as $tcid => $tc) {
            $cards[] = array('type' => CARD_TECHNOLOGY, 'type_arg' => $tcid, 'nbr' => 1);
        }
        $this->cards->createCards($cards, 'deck_tech');
        $this->cards->shuffle('deck_tech');
        //
        // CIVILIZATIONS
        //
        $adj = $this->getAdjustmentVariant();
        $cards = array();
        foreach ($this->civilizations as $cid => $c) {
            if (array_get($c, 'exclude', false) === true)
                continue;
            if ($automa && !$this->isTestEnv() && array_get($c, 'automa', true) === false)
                continue;
            $exp = array_get($c, 'exp', 'BA');
            if (!$this->isExpansionIncluded($exp)) {
                continue;
            }

            $max_adjustement_level = (int) array_get($c, 'al', 4);
            if ($adj > $max_adjustement_level) continue;
            $cards[] = array('type' => CARD_CIVILIZATION, 'type_arg' => $cid, 'nbr' => 1);
        }
        $this->cards->createCards($cards, 'deck_civ');
        $this->cards->shuffle('deck_civ');
        //$this->debug_insertCard(CARD_CIVILIZATION, CIV_HISTORIANS);
        // Capitals
        $cards = array();
        foreach ($this->capitals as $cid => $c) {
            $cards[] = array('type' => 6, 'type_arg' => $cid, 'nbr' => 1);
        }
        $this->cards->createCards($cards, 'deck_capital');
        // remove components if needed
        $this->removeSomeComponents();
        // Draw technology cards
        $this->cards->pickCardsForLocation(3, 'deck_tech', 'deck_tech_vis', 0, true);
        $automa_civ = 0;
        // PICK STARTING CAPITAL/CIV CHOICES
        // randomize player_no for automa games XXX TODO
        $this->setGameStateValue('automa_no', 3);
        if ($automa) {
            $order = bga_rand(0, 2);
            $no = $order + 1;
            $this->setGameStateValue('automa_no', $no);
            $keys = array_keys($players);
            $index = 0;
            if ($automa && $no == 3)
                $index = 1;
            foreach ($keys as $player_id) {
                $index += 1;
                if ($index == $no) {
                    $index++;
                    if ($automa)
                        $index++;
                }
                $this->DbQuery("UPDATE player SET player_no='$index' WHERE player_id='$player_id'");
            }
            $this->reloadPlayersBasicInfos();
            $playerswithbots = $this->loadPlayersBasicInfosWithBots(); // re-load to sort by player_no
        }
        $mats = $this->random_mats(count($playerswithbots), $numPlayers < 4 ? 3 : 6);
        $pairs = [1 => 6, 2 => 4, 3 => 5];
        $index = 0;
        $this->DbQuery("UPDATE player SET player_no=player_no + 100"); // to avoid duplicate PRIMARY key
        $allcount = $this->cards->countCardsInLocation('deck_civ');
        foreach ($playerswithbots as $player_id => $player) {
            $index += 1;
            $capital = $mats[$index];
            //$this->debugConsole("Player $player_id $index $capital");
            if ($this->isRealPlayer($player_id) && $numPlayers < 4) {
                $second = $pairs[$capital];
                $this->DbQuery("UPDATE card SET card_location='choice', card_location_arg='$player_id' WHERE card_type='6' AND card_type_arg IN ('$capital', '$second')");
            } else {
                $this->DbQuery("UPDATE card SET card_location='hand', card_location_arg='$player_id' WHERE card_type='6' AND card_type_arg='$capital'");
            }
            if ($this->isRealPlayer($player_id)) {
                // civs
                if ($this->isTestEnv()) {
                    $numciv = floor($allcount / $numPlayers);
                } else {
                    $numciv = 2;
                }
                $this->cards->pickCardsForLocation($numciv, 'deck_civ', 'choice', $player_id, false);
                // income buildings
                $structures = array();
                for ($a = 1; $a < 5; $a++) {
                    $structures[] = array('type' => $a, 'type_arg' => 0, 'nbr' => 6);
                }
                $this->structures->createCards($structures, 'income', $player_id);
                $this->DbQuery("UPDATE player SET player_no='$capital' WHERE player_id='$player_id'");
            }
            $structures = array();
            $structures[] = array('type' => BUILDING_OUTPOST, 'type_arg' => 0, 'nbr' => 10);
            $structures[] = array('type' => BUILDING_CUBE, 'type_arg' => 0, 'nbr' => 8);
            $this->structures->createCards($structures, 'hand', $player_id);
            for ($a = 1; $a <= 4; $a++) {
                $this->dbAddCube($player_id, 'tech_spot_' . $a . '_0');
            }
        }
        $this->reloadPlayersBasicInfos();
        // $no = $this->getGameStateValue('automa_no');
        // $playerswithbots = $this->loadPlayersBasicInfosWithBots();
        // $this->debugConsole("Automa player number is $no", [ 'pls' => $playerswithbots ]);
        //
        // landmarks
        foreach ($this->landmark_data as $a => $info) {
            $this->DbQuery("INSERT INTO structure (card_type, card_type_arg, card_location, card_location_arg2) VALUES ('6', '0', 'landmark_mat_slot$a', '$a')");
        }
        //Initialise Map
        $map_data = $this->getInitMapData('map');
        foreach ($map_data as $coords => $info) {
            $tile_id = array_get($info, 'map_tile_id', 0);
            $tile_orient = array_get($info, 'map_tile_orient', 0);
            $this->DbQuery("INSERT INTO map (map_coords, map_tile_id, map_tile_orient) VALUES ('$coords', '$tile_id', '$tile_orient')");
        }
        if ($automa) {
            $cards = [];
            for ($i = 8; $i <= 22; $i++) {
                $cards[] = array('type' => CARD_DECISION, 'type_arg' => $i, 'nbr' => 1);
            }
            $this->cards->createCards($cards, 'deck_progress');
            $this->cards->shuffle('deck_progress');
            $this->cards->pickCardsForLocation(1, 'deck_progress', 'deck_decision', 0, true);
            $cards = [];
            for ($i = 1; $i <= 7; $i++) {
                $cards[] = array('type' => CARD_DECISION, 'type_arg' => $i, 'nbr' => 1);
            }
            $this->cards->createCards($cards, 'deck_decision');
            $this->cards->shuffle('deck_decision');
            // pick civ automa
            $automa_civ = bga_rand(1, 4);
            $this->effect_automaChangeFavoriteTrack(PLAYER_AUTOMA, $automa_civ);
            $this->setGameStateValue('automa_civ', $automa_civ);
            $this->setStat($automa_civ, 'automa_civ');
            $this->setStat(0, 'automa_score');
            // pick fav shadow
            $shadow_fav_track = bga_rand(1, 4);
            while ($shadow_fav_track == $automa_civ) {
                $shadow_fav_track = bga_rand(1, 4);
            }
            $this->effect_automaChangeFavoriteTrack(PLAYER_SHADOW, $shadow_fav_track);
        }
        if ($shadow) {
            $cards = [];
            for ($i = 1; $i <= 12; $i++) {
                $cards[] = array('type' => CARD_DECISION, 'type_arg' => $i, 'nbr' => 1);
            }
            $this->cards->createCards($cards, 'deck_decision');
            $this->cards->shuffle('deck_decision');
            // pick fav
            $shadow_fav_track = bga_rand(1, 4);
            $this->effect_automaChangeFavoriteTrack(PLAYER_SHADOW, $shadow_fav_track);
        }
    }

    function loadPlayersBasicInfosWithBots($new = false) {
        $player_basic = $this->loadPlayersBasicInfos();
        if ($new) {
            if (!$this->isAutoma())
                return $player_basic;
            return $player_basic + $this->addAutomaPlayer();
        }
        if ($this->isAutoma()) {
            if (!isset($this->player_bots)) {
                $no = (int) $this->getGameStateValue('automa_no');
                $sql = "SELECT player_id, player_color,player_name,player_avatar,player_ai,player_score FROM playerextra";
                $this->player_bots = $this->getCollectionFromDb($sql);
                if ($this->isSolo()) {
                    $this->player_bots[PLAYER_AUTOMA]['player_no'] = $no;
                    $this->player_bots[PLAYER_SHADOW]['player_no'] = ($no % 3) + 1;
                } else if ($this->isShadowEmpireOnly()) {
                    $this->player_bots[PLAYER_SHADOW]['player_no'] = $no;
                }
            }
            foreach ($this->player_bots as $player_id => $info) {
                if (!isset($player_basic[$player_id]))
                    $player_basic[$player_id] = $info;
            }
        }
        uksort($player_basic, fn ($x, $y) => ((int) $player_basic[$x]['player_no']) <=> ((int) $player_basic[$y]['player_no']));
        //$this->warn("basci ".toJson($player_basic));
        return $player_basic;
    }

    function addAutomaPlayer() {
        if (!$this->isAutoma())
            return [];
        $playerswithbots = [];
        $numPlayers = $this->getPlayersNumber();
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];
        shuffle($default_colors);
        if ($this->isSolo()) {
            $playerswithbots[PLAYER_AUTOMA] = [
                'player_id' => PLAYER_AUTOMA, 'player_color' => 0,
                'player_name' => clienttranslate('Automa'), 'player_avatar' => '', 'player_no' => $numPlayers,
                'player_ai' => 1, 'player_color' => '69c9c9'
            ];
        }
        $playerswithbots[PLAYER_SHADOW] = [
            'player_id' => PLAYER_SHADOW, 'player_color' => 0,
            'player_name' => clienttranslate('Shadow Empire'), 'player_avatar' => '', 'player_no' => $numPlayers + 1,
            'player_ai' => 1, 'player_color' => '777777'
        ];
        return $playerswithbots;
    }

    function isAutoma() {
        return $this->isSolo() || $this->isShadowEmpireOnly();
    }

    function isSolo() {
        return $this->getPlayersNumber() == 1;
    }

    function isShadowEmpireOnly() {
        return $this->getPlayersNumber() == 2 && $this->getGameStateValue('shadow_level') > 0;
    }

    function getAutomaLevel() {
        if (!$this->isAutoma())
            return 0;
        $level = (int) $this->getGameStateValue('automa_level');
        if ($level == 0)
            $level = 2;
        return $level;
    }

    function getPlayersNumberWithBots() {
        $num = count($this->loadPlayersBasicInfosWithBots());
        return $num;
    }

    function random_mats($n, $all = 6) {
        $players = [];
        $mats = [];
        for ($i = 1; $i <= $all; $i++) {
            $mats[$i] = $i;
        }
        shuffle($mats);
        $picked = array_slice($mats, 0, $n);
        sort($picked);
        for ($i = 1; $i <= $n; $i++) {
            $mat = array_shift($picked);
            $players[$i] = $mat;
        }
        //     foreach ($players as $index => $mat) {
        //         print("$n: player $index => mat $mat\n");
        //     }
        return $players;
    }

    /*
     * getAllDatas:
     *
     * Gather all informations about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, ie:
     * _ when the game starts
     * _ when a player refreshes the game page (F5)
     */
    protected function getAllDatas() {
        $result = array();
        $current_player_id = $this->getCurrentPlayerId(); // !! We must only return informations visible by this player !!
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT  player_id id, player_score score, player_color color, player_name name,
                        player_income_farms income3, player_income_houses income2, player_income_markets income1, player_income_armories income4,
                        player_res_food res3, player_res_coin res1, player_res_culture res4, player_res_worker res2,
                        player_track_technology track4, player_track_science track2, player_track_military track3, player_track_exploration track1,
                        player_income_turns, player_ai is_ai
                        FROM playerextra ";
        $player_basic = $this->loadPlayersBasicInfosWithBots();
        $result['playerorder_withbots'] = array_keys($player_basic);
        $result['starting_player'] = $this->getGameStateValue('starting_player');
        $result['players'] = array();
        $player_data = $this->getCollectionFromDb($sql);
        $setupphase = ($this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='choice'") != 0);
        foreach ($player_data as $player_id => $player) {
            $result['players'][$player_id] = array();
            $result['players'][$player_id]['id'] = $player['id'];
            $result['players'][$player_id]['score'] = $player['score'];
            $result['players'][$player_id]['color'] = $player['color'];
            $result['players'][$player_id]['name'] = $player['name'];
            $result['players'][$player_id]['is_ai'] = $player['is_ai'];
            $result['players'][$player_id]['no'] = $player_basic[$player_id]['player_no'];
            $result['players'][$player_id]['basic'] = $player;
            $result['players'][$player_id]['alive'] = $this->isPlayerAlive($player_id);
            $result['players'][$player_id]['hand'] = array();
            $result['players'][$player_id]['structures'] = $this->getObjectListFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id'");
            $result['players'][$player_id]['capital'] = $this->getUniqueValueFromDB("SELECT card_type_arg FROM card WHERE card_type='6' AND card_location='hand' AND card_location_arg='$player_id'");
            $result['players'][$player_id]['track_fav'] = $this->structures->getCardsInLocation([
                'track_fav_1',
                'track_fav_2', 'track_fav_3', 'track_fav_4'
            ], $player_id);
            if (!$setupphase) {
                $result['players'][$player_id]['civilizations'] = $this->getCardsInHand($player_id, CARD_CIVILIZATION);
                $result['players'][$player_id]['tapestry'] = $this->getCollectionFromDB("SELECT *  FROM card WHERE card_type='3' AND card_location <> 'hand' AND card_location_arg='$player_id'");
                $result['players'][$player_id]['technology'] = $this->getCardsInHand($player_id, 4);
                $result['players'][$player_id]['technology_updates'] = $this->argUpdateCardList($player_id);
                $result['players'][$player_id]['space'] = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='2' AND card_location<>'hand' AND card_location_arg='$player_id'");
                $result['players'][$player_id]['hand']['territory'] = $this->getCardsInHand($player_id, 1);
                $result['players'][$player_id]['hand']['space'] = $this->getCardsInHand($player_id, 2);
                if ($player_id == $current_player_id) {
                    $result['players'][$player_id]['hand']['tapestry'] = $this->getCardsInHand($player_id, 3);
                } else {
                    $result['players'][$player_id]['hand']['tapestry'] = [];
                }
                $result['players'][$player_id]['counters']['tapestry'] = $this->getCardCountInHand($player_id, 3);
            }
        }
        if ($this->isSolo()) {
            $player_id = PLAYER_AUTOMA;
            $civ = $this->getGameStateValue('automa_civ');
            $result['players'][$player_id]['automa_civ'] = $civ;
        }
        if ($this->isAutoma()) {
            $result['decision_pair'] = $this->cards->getCardsInLocation('decision_pair');
        }
        $result['deck_counters'] = $this->getDeckCounters();
        $result['landmarks'] = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type='6' AND card_location_arg=0");
        $result['landmark_data'] = $this->landmark_data;
        $result['tech_track_types'] = $this->tech_track_types;
        $result['tech_track_data'] = $this->tech_track_data;
        $result['income_track_data'] = $this->income_tracks;
        $result['benefit_types'] = $this->benefit_types;
        $result['terrain_types'] = $this->terrain_types;
        $result['dice_names'] = $this->dice_names;
        $result['card_types'] = $this->card_types;
        $result['map'] = $this->getMapData();
        $result['dice'] = array();
        $result['dice']['red'] = $this->getGameStateValue('conquer_die_red');
        $result['dice']['black'] = $this->getGameStateValue('conquer_die_black');
        $result['dice']['science'] = $this->getGameStateValue('science_die');
        $result['dice']['empiricism'] = $this->getGameStateValue('science_die_empiricism');
        $this->addConstants($result);
        $result['tech_deck_visible'] = $this->getCollectionFromDb("SELECT * FROM card WHERE card_location='deck_tech_vis'");
        if ($setupphase) {
            $result['setup'] = $this->getSetupChoice($current_player_id);
        }
        // any other card locations
        $result['cards'] = $this->getCardsSearch(null, null, 'draw', $current_player_id);
        $result['cards'] += $this->getCardsSearch(null, null, 'islanders', null);
        $result['cards'] += $this->getCardsSearch(null, null, 'map', null);
        $result['cards'] += $this->getCardsSearch(null, null, 'civ_21_%', null);
        $inorder = $this->dbGetBenefitQueue();
        $result['benefitQueue'] = $inorder;
        $result['income_turn_phase'] = $this->getIncomeTurnPhase();
        $result['main_player'] = $this->getGameStateValue('current_player_turn');
        // game variants
        $keys = ['automa_level', 'shadow_level'];
        foreach ($keys as $key) {
            $result['variants'][$key] = $this->getGameStateValue($key);
        }
        $result['variants']['variant_adjustments'] = $this->getAdjustmentVariant();
        $result['variants']['automa_in_play'] = $this->isSolo();
        $result['variants']['shadow_in_play'] = $this->isAutoma();
        // undo
        $result += $this->argUndo();
        //  $result['canceledNotifIds'] = $this->getCanceledNotifIds();
        return $result;
    }

    function getInitMapData($location) {
        $map = []; //
        if ($location == 'land' || $location == 'map') {
            $numPlayers = $this->getPlayersNumber();
            $map_size = ($numPlayers > 3) ? 4 : 3;
            $map_data = ($map_size == 4) ? $this->map["large"] : $this->map["small"];
            for ($a = -$map_size; $a <= $map_size; $a++) {
                for ($b = -$map_size; $b <= $map_size; $b++) {
                    if (abs($a - $b) <= $map_size) {
                        $coords = $a . '_' . $b;
                        if (($map_size == 4) || (($coords != '0_-3') && ($coords != '-3_0') && ($coords != '3_3'))) {
                            if (array_key_exists($coords, $map_data)) {
                                $tile_id = array_get($map_data[$coords], 'id', 0);
                                $tile_orient = array_get($map_data[$coords], 'orient', 0);
                            } else {
                                $tile_id = 0;
                                $tile_orient = 0;
                            }
                            $map[$coords] = ['map_tile_id' => $tile_id, 'map_tile_orient' => $tile_orient, 'map_coords' => $coords];
                        }
                    }
                }
            }
        } else {
            $map_data = $this->map[$location];
            foreach ($map_data as $coords => $info) {
                $map[$coords] = $info;
                $map[$coords]['map_tile_id'] = array_get($info, 'id', 0);
                $map[$coords]['map_tile_orient'] = array_get($info, 'orient', 0);
                $map[$coords]['map_coords'] = $coords;
            }
        }
        return $map;
    }

    function getDeckCounters($onedeck = null) {
        $res = [];
        $decks = ['deck_territory', 'deck_space', 'deck_tapestry', 'deck_tech', 'deck_civ', 'deck_decision'];
        foreach ($decks as $deck) {
            if ($onedeck != null && $deck != $onedeck)
                continue;
            $res[$deck] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='$deck' ");
        }
        // 'discard_territory','discard_space','discard_tapestry','discard_tech','discard_civ'

        $res['discard_tech'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard' AND card_type=4");
        $res['discard_territory'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard' AND card_type=1");
        $res['discard_civ'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard' AND card_type=5");
        $res['discard_tapestry'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard' AND card_type=3");
        $res['discard_space'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard' AND card_type=2");
        $res['discard_decision'] = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='discard_decision'");

        if ($onedeck == null) {
            $playerswithbots = $this->loadPlayersBasicInfosWithBots();
            foreach ($playerswithbots as $player_id => $info) {
                $res['players'][$player_id]['tapestry'] = $this->getCardCountInHand($player_id, 3);
            }
        }
        return $res;
    }

    function notifyDeckCounters($deck = null) {
        $this->notifyAllPlayers('deckCounters', '', $this->getDeckCounters($deck));
    }

    private function addConstants(&$result) {
        $cc = get_defined_constants(true)['user'];
        foreach ($cc as $key => $value) {
            $im = explode('_', $key);
            switch ($im[0]) {
                case 'CARD':
                case 'BE':
                case 'TERRAIN':
                case 'CIV':
                case 'TRACK':
                case 'RES':
                case 'INCOME':
                case 'TAP':
                case 'FLAG':
                    $result['constants'][$key] = $value;
                    break;
                default:
                    break;
            }
        }
    }

    /*
     * getGameProgression:
     *
     * Compute and return the current game progression.
     * The number returned must be an integer beween 0 (=the game just started) and
     * 100 (= the game is finished or almost finished).
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true
     * (see states.inc.php)
     */
    function getGameProgression() {
        $player_data = $this->getCollectionFromDB("SELECT player_id, player_income_turns FROM playerextra");
        $sum = 0;
        foreach ($player_data as $player_id => $player) {
            $sum += $player['player_income_turns'];
        }
        return (20 * $sum) / $this->getPlayersNumber();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    
    function __UTILS__() {
    }

    // my anchor for IDE
    function notifArgsAddBen($benefit_data = null, $arr = null) {
        if (!$arr)
            $arr = [];
        if (!$benefit_data) {
            $benefit_data = $this->getCurrentBenefit();
        }
        if ($benefit_data) {
            $bid = $arr['bid'] = $benefit_data['benefit_type'];
            $arr['count'] = $benefit_data['benefit_quantity'];
            $data = $arr['data'] = $benefit_data['benefit_data'];
            if (!$data) {
                $data = ":be:$bid";
            }
            $arr['reason'] = $this->getReasonFullRec($data);
            $this->argAddTrackChoice($bid, $arr);
        }
        return $arr;
    }

    function addBenefitData(&$arr = null, $benefit_data = null) {
        $arr = $this->notifArgsAddBen($benefit_data, $arr);
        return $arr;
    }

    public function getPlayerNameById($player_id) {
        if ($player_id == PLAYER_AUTOMA)
            return clienttranslate('Automa');
        if ($player_id == PLAYER_SHADOW)
            return clienttranslate('Shadow Empire');
        $players = self::loadPlayersBasicInfos();
        if (!isset($players[$player_id]))
            return null;
        return $players[$player_id]['player_name'];
    }

    function notifyWithTokenName($type, $message, $id, $player_id = -1) {
        $this->notifyWithName('message', $message, ['i18n' => ['name'], 'name' => $this->getTokenName($type, $id)], $player_id);
    }

    function notifArgsAddTokenName($type, $id, $args = null) {
        if (!$args)
            $args = [];
        $i18n = array_get($args, 'i18n', []);
        $i18n[] = 'name';
        $args['i18n'] = $i18n;
        $args['name'] = $this->getTokenName($type, $id);
        return $args;
    }

    function notifArgsAddCardInfo($card_id, $args = null) {
        if (!$args)
            $args = [];
        if (is_array($card_id)) {
            $info = $card_id;
            $card_id = $info['card_id'];
        } else {
            $info = $this->getCardInfoById($card_id);
        }
        $args = array_merge($info, $args);
        $args['card_name'] = $this->getTokenName($info['card_type'], $info['card_type_arg']);
        $args['card_type_name'] = $this->card_types[$info['card_type']]['name'];
        $card_location_arg = $info['card_location_arg'];
        $name = $this->getPlayerNameById($card_location_arg);
        if ($name) {
            $args['player_name2'] = $name;
            $args['player_id2'] = $card_location_arg;
        }
        return $args;
    }

    function notifArgsAddTrackSpot($track = null, $spot = null, $args = null) {
        if (is_array($track)) {
            $args = $track;
            $track = null;
        }
        if (!$args)
            $args = [];
        $preserve = array_get($args, 'preserve', []);
        $i18n = array_get($args, 'i18n', []);
        if ($track === null && isset($args['track'])) {
            $track = $args['track'];
        }
        if ($track) {
            $args['track'] = $track;
            $this->checkTrack($track);
            $preserve[] = 'track';
            $i18n[] = 'track_name';
            $track_name = $this->tech_track_types[$track]['description'];
            $args['track_name'] = $track_name;
        }
        if ($spot === null && isset($args['spot'])) {
            $spot = array_get($args, 'spot', null);
        }
        if ($spot !== null) {
            $args['spot'] = $spot;
            $this->checkSpot($spot);
            $preserve[] = 'spot';
            $i18n[] = 'spot_name';
            $spot_name = $this->tech_track_data[$track][$spot]['name'];
            $args['spot_name'] = $spot_name;
        }
        if (isset($args['advance_regress'])) {
            $i18n[] = 'advance_regress';
        } else if (isset($args['adv'])) {
            $change = $args['adv'];
            $advance_regress = ($change > 0) ? clienttranslate('advances') : clienttranslate('regresses');
            $args['advance_regress'] = $advance_regress;
            $i18n[] = 'advance_regress';
        }
        if (count($i18n) > 0) {
            $args['i18n'] = $i18n;
        }
        if (count($preserve) > 0) {
            $args['preserve'] = $preserve;
        }
        return $args;
    }

    function notifyWithTrack($type, $message, $more_args = [], $player_id = -1) {
        $args = $this->notifArgsAddTrackSpot(null, null, $more_args);
        if (isset($args['throw']))
            throw new BgaUserException(varsub(self::_($message), $args));
        else
            $this->notifyWithName($type, $message, $args, $player_id);
    }

    function notifyMoveStructure($message, $id, $args = [], $player_id = null, $reason = null) {
        $this->systemAssertTrue("not array 3rd arg of notifyMoveStructure", is_array($args));
        $this->notif("moveStructure", $player_id)->withStructure($id)->withArgs($args)->withReason($reason)->notifyAll($message);
    }

    function getCardsInHand($player_id, $card_type, $card_type_arg = null, $card_ids = null) {
        $sql = $this->querySelectCardDataFromTable('card', $card_type, $card_type_arg, 'hand', $player_id);
        if ($card_ids)
            $sql .= $this->queryExpression('card_id', $card_ids);
        return $this->getCollectionFromDB($sql);
    }

    function getCardCountInHand($player_id, $type) {
        return $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_type='$type' AND card_location='hand' AND card_location_arg='$player_id'");
    }

    function getStructuresOnCivExceptArg($cid, $type, $income_turn) {
        $location_like = "civ_$cid\\_%";
        $sql = "SELECT * FROM structure WHERE card_type='$type' AND card_location LIKE '$location_like'";
        if ($income_turn !== null)
            $sql .= " AND card_location_arg2 != $income_turn";
        return $this->getCollectionFromDB($sql);
    }

    function getStructuresOnCiv($cid, $type = BUILDING_CUBE, $arg2 = null) {
        $location_like = "civ\\_$cid\\_%";
        $sql = "SELECT * FROM structure WHERE card_location LIKE '$location_like'";
        if ($type !== null) {
            $sql .= " AND card_type = '$type'";
        }
        if ($arg2 !== null)
            $sql .= " AND card_location_arg2 = $arg2";
        return $this->getCollectionFromDB($sql);
    }

    function getStructureOnCivSlot($cid, $slot) {
        $location = "civ_{$cid}_$slot";
        $sql = "SELECT * FROM structure WHERE card_location = '$location'";
        $sql .= " LIMIT 1";
        return $this->getObjectFromDB($sql);
    }

    function querySelectCardDataFromTable($table, $card_type, $card_type_arg = null, $card_location = null, $card_location_arg = null, $card_location_arg2 = null, $selector = '*') {
        $sql = "SELECT $selector FROM $table WHERE 1 ";
        $sql .= $this->queryExpression('card_type', $card_type);
        $sql .= $this->queryExpression('card_type_arg', $card_type_arg);
        $sql .= $this->queryExpression('card_location', $card_location, 2);
        $sql .= $this->queryExpression('card_location_arg', $card_location_arg);
        $sql .= $this->queryExpression('card_location_arg2', $card_location_arg2, 2);
        return $sql;
    }

    function getCardInfoSearch($card_type, $card_type_arg = null, $card_location = null, $card_location_arg = null, $card_location_arg2 = null) {
        $sql = $this->querySelectCardDataFromTable('card', $card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        $sql .= " LIMIT 1";
        return $this->getObjectFromDB($sql);
    }

    function getCardsSearch($card_type, $card_type_arg = null, $card_location = null, $card_location_arg = null, $card_location_arg2 = null) {
        $sql = $this->querySelectCardDataFromTable('card', $card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        return $this->getCollectionFromDB($sql);
    }

    function getCardInfoById($card_id, $aliased = false) {
        if ($aliased)
            $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg, card_location_arg2 location_arg2 ";
        else
            $sql = "SELECT *";
        $sql .= " FROM card";
        $sql .= " WHERE card_id='$card_id' ";
        return $this->getObjectFromDB($sql);
    }

    function getStructureInfoById($structure_id, $aliased = false) {
        if ($aliased)
            $sql = "SELECT card_id id, card_type type, card_type_arg type_arg, card_location location, card_location_arg location_arg, card_location_arg2 location_arg2 ";
        else
            $sql = "SELECT *";
        $sql .= " FROM structure";
        $sql .= " WHERE card_id='$structure_id' ";
        $dbres = self::DbQuery($sql);
        return mysql_fetch_assoc($dbres);
    }

    function getStructureInfoSearch($card_type, $card_type_arg = null, $card_location = null, $card_location_arg = null, $card_location_arg2 = null) {
        $sql = $this->querySelectCardDataFromTable('structure', $card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        $sql .= " LIMIT 1";
        return $this->getObjectFromDB($sql);
    }

    function getStructuresSearch($card_type, $card_type_arg = null, $card_location = null, $card_location_arg = null, $card_location_arg2 = null) {
        $sql = $this->querySelectCardDataFromTable('structure', $card_type, $card_type_arg, $card_location, $card_location_arg, $card_location_arg2);
        return $this->getCollectionFromDB($sql);
    }

    function addStructure($player_id, $destination, $type, $type_arg = 0, $arg2 = 0) {
        $structure = $this->getStructureInfoSearch($type, null, 'hand', $player_id);
        if ($structure) {
            $structure_id = $structure['card_id'];
            $this->DbQuery("UPDATE structure SET card_location='$destination', card_type_arg=$type_arg, card_location_arg2='$arg2'  WHERE card_id='$structure_id'");
            return $structure_id;
        }
        if ($type == 7) {
            return $this->dbAddCube($player_id, $destination, $type_arg, $arg2);
        }
        if ($type == 8) {
            return $this->dbAddMarker($player_id, $destination);
        }
        return null;
    }

    function addCube($player_id, $destination, $type_arg = 0, $arg2 = 0) {
        return $this->addStructure($player_id, $destination, BUILDING_CUBE, $type_arg, $arg2);
    }

    function addMarker($player_id, $destination) {
        return $this->addStructure($player_id, $destination, BUILDING_MARKER);
    }

    function dbAddCube($player_id, $destination, $type_arg = 0, $arg2 = 0) {
        $this->DbQuery("INSERT INTO structure (card_type, card_location, card_location_arg, card_type_arg, card_location_arg2) VALUES ('7', '$destination', '$player_id', '$type_arg', '$arg2')");
        $structure_id = $this->getLastId('structure');
        return $structure_id;
    }

    function dbAddMarker($player_id, $destination, $type_arg = 0, $arg2 = 0) {
        $this->DbQuery("INSERT INTO structure (card_type, card_location, card_location_arg, card_type_arg, card_location_arg2) VALUES ('8', '$destination', '$player_id', '$type_arg', '$arg2')");
        $structure_id = $this->getLastId('structure');
        return $structure_id;
    }

    function addTapestryClone($player_id, $destination, $type_arg, $arg2 = 0) {

        $clone = $this->getCardInfoSearch(CARD_TAPESTRY, null, $destination, null, $arg2);
        if ($clone) {  // remove previous clone
            $this->effect_discardCard($clone['card_id'], $player_id, 'limbo');
        }

        $this->DbQuery("INSERT INTO card (card_type, card_location, card_location_arg, card_type_arg, card_location_arg2) VALUES ('3', '$destination', '$player_id', '$type_arg', '$arg2')");
        $structure_id = $this->getLastId('card');
        return $structure_id;
    }

    function addCivToken($player_id, $slot, $civ) {
        $slot_id = 'civ_' . $civ . "_" . $slot;
        $reason = reason_civ($civ);
        return $this->addCube($player_id, $slot_id, CUBE_CIV, $reason);
    }

    function getSetupChoice($player_id) {
        $fields  = "card_type_arg, card_type, card_location, card_location_arg, card_location_arg2";
        $capitals = $this->getCollectionFromDB("SELECT $fields FROM card WHERE card_type='6' AND card_location IN ('choice', 'hand') AND card_location_arg='$player_id'");
        if (sizeOf($capitals) <= 1)
            $capitals = null; // don't offer a choice if only 1!
        return array(
            'capitals' => $capitals,
            'civilizations' => $this->getCollectionFromDB("SELECT $fields FROM card WHERE card_type='5' AND card_location IN ('choice', 'hand') AND card_location_arg='$player_id'")
        );
    }

    function getMapData() {
        //return $this->getCollectionFromDB("SELECT card_type_arg id, card_location_arg rot, card_location_arg2 coord FROM card WHERE card_type='1' AND card_location='map'");
        return $this->getCollectionFromDB("SELECT map_tile_id id, map_tile_orient rot, map_coords coord FROM map WHERE map_tile_id != 0");
    }

    function awardBenefitsAutoma($player_id, $ben, $count = 1, $reason = '') {
        if ($player_id != PLAYER_AUTOMA)
            return false;
        $action = $this->getRulesBenefit($ben, 'r', 'x');
        if ($action == 'e') {
            $this->effect_automaExplore();
            return true;
        }
        if ($action == 'a') {
            $this->effect_automaConquer();
            return true;
        }
        switch ($ben) {
            case 5:
                return true; // Automa does not gain resources
            case 140: // topple TODO Automa
                $cards = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='3' AND card_location='hand' AND card_location_arg='$player_id'");
                $values = array_values($cards);
                shuffle($values);
                while (count($values) > 0) {
                    $card = array_shift($values);
                    // play card on display
                    $card_id = $card['card_id'];
                    if ($card['card_type_arg'] == 42) {
                        $this->effect_trap($card_id, $player_id);
                        return true;
                    } else {
                        $this->effect_discardCard($card_id, $player_id);
                    }
                }
                $this->notifyWithName('message', clienttranslate('${player_name} does not play a trap'), [], $player_id);
                return true;
        }
        return false;
    }

    function awardBenefits($player_id, $ben, $count = 1, $reason = '') {
        $be_reason = reason('be', $ben);
        if (!$reason)
            $reason = $be_reason;
        //$this->notifyWithName('message', "award $ben x $count to $player_id $reason");
        if ($this->awardBenefitsAutoma($player_id, $ben, $count, $reason)) {
            return true;
        }
        $state = $this->getRulesBenefit($ben, 'state', null);
        switch ($ben) {
            case 1:
            case 2:
            case 3:
            case 4:
                $this->awardBaseResource($player_id, $ben, $count, $reason);
                return true;
            case BE_TERRITORY:
                $this->awardCard($player_id, $count, CARD_TERRITORY, false, $reason);
                return true;
            case BE_TAPESTRY:
                $this->awardCard($player_id, $count, CARD_TAPESTRY, false, $reason);
                return true;
            case 8:
                return $this->claimIncomeStructure(BUILDING_MARKET);
            case 9: // BE_HOUSE
                return $this->claimIncomeStructure(BUILDING_HOUSE);
            case 10:
                return $this->claimIncomeStructure(BUILDING_FARM);
            case 11:
                return $this->claimIncomeStructure(BUILDING_ARMORY);
            case 13:
                $this->setIncomeTurnPhase(INCOME_TAPESTRY, '', $player_id);
                $this->gamestate->nextState('upgradeTech');
                return false;
            case 14:
                $this->gamestate->nextState('upgradeTech');
                return false;
            case 15: // Gain VP for various reasons
                $this->awardVP($player_id, $count, $reason, null, $ben);
                return true;
            case 12:
                $this->effect_gainVPIncome($player_id);
                return true;
            case 16:
                $this->effect_gainResourcesIncome($player_id);
                return true;
            case 129:
                $this->effect_gainCardsIncome($player_id);
                return true;
            case 602:
                $this->effect_endOfIncome($player_id);
                return true;
            case 17: // EXPLORE
                $this->ageOfSailCheck($player_id);
                $this->gamestate->nextState('explore');
                return false;
            case 46: // EXPLORE anywhere
                $this->ageOfSailCheck($player_id);
                $this->gamestate->nextState('explore');
                return false;
            case 18: // RESEARCH - WITH BENEFIT
            case 19: // RESEARCH - NO BENEFIT
                $this->research($reason);
                return false;
            case 20:

                $this->setGameStateValue('invent_face_up', 0);
                $this->gamestate->nextState('invent');
                return false;
            case 21:
                $this->setGameStateValue('conquer_bonus', 0);
                $this->gamestate->nextState('conquer');
                return false;
            case 22:
            case 23:
            case 24:
            case 25:
                $track = (int) array_get_def($this->benefit_types, $ben, 't', 0);
                return $this->trackMovementInteractive($track, SPOT_SELECT, +1, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS, false, $player_id);
            case BE_PLAY_TAPESTY_INCOME: // case 128
                $this->setIncomeTurnPhase(INCOME_TAPESTRY);
                // play tapestry in income turn
                $this->gamestate->nextState('tapestryChoice');
                return false;
            case 26:
                $this->gamestate->nextState('invent');
                return false;
            case 27:
                // VP tech
                $tech_card_count = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_type='4' AND card_location_arg='$player_id'");
                $this->awardVP($player_id, $tech_card_count * $count, $reason, null, $ben);
                return true;
            case 28:
                //VP Capital
                $rows_cols = $this->getCapitalScoreVP($player_id);
                $this->awardVP($player_id, $rows_cols * $count, $reason, null, $ben);
                return true;
            case BE_VP_TERRITORY: // 29 :
                // VP Conqured
                $this->VPconq($player_id, $count, $reason, $ben);
                return true;
            case 30:
                $this->VPincomeStructure($player_id, BUILDING_FARM, $count, $reason, null, $ben);
                return true;
            case 31:
                $this->VPincomeStructure($player_id, BUILDING_ARMORY, $count, $reason, null, $ben);
                return true;
            case 32:
                $this->gamestate->nextState('techBenefit');
                return false;
            case 33:
                $this->gamestate->nextState('techBenefit');
                return false;
            case 34:
            case 35:
            case 36:
            case 37:
            case 38:
            case 39:
            case 40:
            case 41:
            case 42:
            case 43:
            case 44:
            case 45:
                $landmark_id = $this->getRulesBenefit($ben, 'lm');
                return $this->claimLandmark($landmark_id, $player_id);
            case 47:
            case 48:
            case 49:
            case 50:
                $this->VPtrack($player_id, $ben - 46, 1, $reason, $ben);
                return true;
            case 51:
                $this->awardCard($player_id, $count, CARD_SPACE, false, $reason); // GAIN SPACE TILE
                return true;
            case 54:
                $this->VPincomeStructure($player_id, 1, $count, $reason, null, $ben);
                return true;
            case 55:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 56:
            case 57:
            case 58:
            case 59:
            case 60:
            case 61:
            case 315:
                $landmark_id = $this->getRulesBenefit($ben, 'lm');
                return $this->claimLandmark($landmark_id, $player_id);
            case 62:
                $this->gamestate->nextState('trackSelect');
                return false;
                break;
            case 63:
                $this->VPincomeStructure($player_id, 2, $count, $reason, null, $ben);
                return true;
                break;
            case 64:
                if ($this->getCardCountInHand($player_id, CARD_TAPESTRY) == 0) {
                    $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                        'reason' => $this->getReasonFullRec($reason)
                    ]);
                    return true;
                } else {
                    $this->gamestate->nextState('tapestryChoice');
                    return false;
                }
            case 65: // BE_GAIN_CIV
                if ($this->hasCiv($player_id, CIV_INFILTRATORS)) {
                    $this->queueBenefitInterrupt(172, $player_id, reason(CARD_CIVILIZATION, CIV_INFILTRATORS));
                    return true;
                }
                $this->awardCard($player_id, $count, CARD_CIVILIZATION, false, $reason); // CIVILIZATION
                return true;
            case 66:
                // VPterritoryTileCount($player_id, $value, $reason)
                $value = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location_arg='$player_id' AND (card_type='1') AND (card_location='hand')");
                $this->awardVP($player_id, $value * $count, $reason, "territory_tiles_$player_id", $ben);
                return true;
            case 67:
            case 68:
            case 69:
            case 70:
                $track = (int) $this->getRulesBenefit($ben, 't', 0);
                $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
                return $this->trackMovementInteractive($track, SPOT_SELECT, ACTION_REGRESS, $flags, false, $player_id);
            case 71:
                $this->VPTapestryCards($player_id, $count, $reason, null, $ben);
                return true;
            case 136:
                $this->VPTapestryCardsInHand($player_id, $count, $reason, null, $ben);
                return true;
            case 72:
                $this->research($reason); // RESEARCH - NO BENEFIT, BUT MAXOUT BONUS
                return false;
            case 73:
                $this->setGameStateValue('conquer_bonus', 1);
                $this->gamestate->nextState('conquer');
                return false;
            case 74:
                $this->setGameStateValue('conquer_bonus', 2);
                $this->gamestate->nextState('conquer');
                return false;
            case 75:
                $this->setGameStateValue('conquer_bonus', 0);
                $this->gamestate->nextState('conquer');
                return false;
            case 76:
            case 77:
            case 78:
            case 79:
                $track = (int) $this->getRulesBenefit($ben, 't', 0);
                return $this->trackMovementInteractive($track, SPOT_SELECT, ACTION_ADVANCE, 0, false, $player_id);
            case 80:
            case 81:
            case 82:
            case 83:
                $track = (int) $this->getRulesBenefit($ben, 't', 0);
                return $this->trackMovementInteractive($track, SPOT_SELECT, ACTION_ADVANCE, FLAG_GAIN_BENFIT, false, $player_id);
            case 84:
            case 85:
            case 86:
            case 87:
            case 88:
            case 89:
            case 90:
            case 91:
            case 97:
            case 98:
            case 99:
            case 100:
                $track = (int) $this->getRulesBenefit($ben, 't', 0);
                $this->setGameStateValue('science_die', $track);
                $this->gamestate->nextState('research');
                return false;
            case 92:
            case 93:
            case 94:
            case 95:
            case 151:
            case 152:
            case 153:
            case 154:
            case 156:
            case 157:
            case 158:
            case 159:
                $track = (int) $this->getRulesBenefit($ben, 't', 0);
                $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
                $adv = (int) $this->getRulesBenefit($ben, 'adv', +1);
                return $this->trackMovementInteractive($track, SPOT_SELECT, $adv, $flags, false, $player_id);
            case 96:
                $this->setGameStateValue('invent_face_up', 1);
                $this->gamestate->nextState('invent');
                return false;
            case 127:
                $this->setGameStateValue('invent_face_up', 0);
                $this->gamestate->nextState('invent');
                return false;
                // TAPESTRY CARD BENEFITS:
            case 101:
                $this->effect_ageOfDiscovery($player_id);
                return true;
                break;
            case 102:
                $this->landmarkComparison();
                return true;
                break;
            case 103:
                $this->boastOfSuperiority();
                return true;
                break;
            case 104:
                $this->coalBaron();
                return true;
                break;
            case 105: // COLONIALISM
                $this->gamestate->nextState('explore');
                return false;
            case 106:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 107:
                $this->democracy();
                return true;
                break;
            case 108:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 109:
                $this->resourceForAll();
                return true;
                break;
            case 144:
            case BE_GAIN_ANY_INCOME_BUILDING: // 110
                $lm = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_location='income' AND card_location_arg='$player_id' LIMIT 1");
                if (!$lm) {
                    $this->notifyWithName('message_error', clienttranslate('No more buildings left'));
                    return true;
                }
                $this->gamestate->nextState('buildingChoice');
                return false;
            case 111:
                $lm = $this->getObjectFromDB("SELECT card_id FROM structure WHERE card_location LIKE 'landmark_mat_slot%' LIMIT 1");
                if (!$lm) {
                    $this->notifyWithName('message_error', clienttranslate('No more landmarks left'));
                    return true;
                }
                $this->gamestate->nextState('buildingChoice');
                return false;
            case 112:
                $this->gamestate->nextState('tapestryChoice');
                return false;
            case 113:
                $this->VPforLandmarks($player_id, 3, $reason, $ben);
                return true;
            case 145:
                $this->VPforLandmarks($player_id, $count, $reason, $ben);
                return true;
            case 146:
                $this->effect_automaToppleShadow($player_id, $count, $reason, $ben);
                return true;
            case 114:
                $this->gamestate->nextState('any_resource');
                return false;
            case 115:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 116:
                $this->olympicHostSetup();
                return true;
            case 117:
                return $this->pleaForAid();
            case 118:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 119:
                $this->gamestate->nextState('conquer');
                return false;
            case 120:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 121: // steam tycoon
                //WHEN PLAYED: Invent a face-up ${tech} and upgrade it. Your neighbours (right, then left) then each invent ${tech} from top of deck.
                $this->queueBenefitNormal(96, $player_id, reason_tapestry(TAP_STEAM_TYCOON));
                $neighbours = $this->getPlayerNeighbours($player_id, false);
                foreach ($neighbours as $neighbour) {
                    $this->queueBenefitNormal(126, $neighbour, reason_tapestry(TAP_STEAM_TYCOON));
                }
                return true;
            case 122:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 123:
                $this->effect_technocracy();
                return true;
            case 124:
                $this->gamestate->nextState('trackSelect');
                return false;
            case 125:
                $this->olympicHostEnd($player_id, $ben, $reason);
                return true;
            case 126: // invent from top of the deck, no need for 'invent' user state
                $this->awardCard($player_id, $count, CARD_TECHNOLOGY, false, $reason);
                return true;
            case 130:
                $this->effect_transferTech();
                return true;
            case 131:
            case 132:
            case 133:
            case 134: // regress without benefit
                $track = (int) array_get_def($this->benefit_types, $ben, 't', 0);
                $flags = (int) array_get_def($this->benefit_types, $ben, 'flags', 0);
                $adv = (int) array_get_def($this->benefit_types, $ben, 'adv', +1);
                return $this->trackMovementInteractive($track, SPOT_SELECT, $adv, $flags, true, $player_id);
            case 140: // topple
                $this->gamestate->nextState('trap');
                return false;
            case 141:
                $this->effect_endOfConquer($player_id);
                return false;
            case 142:
                // ajustement, nothing is given
                return true;
                //case 144 : listed above
            case 143:
                // refresh tech cards
                $this->effect_techRefresh($player_id);
                return true;
            case 160:
            case 161:
            case 162:
            case 163:
            case 164:
            case 165:
            case 166:
            case 167:
            case 168:
                // automa income stage
                $this->effect_automaIncomeStage($player_id, $ben - 160);
                return true;
            case 170:
                $this->gamestate->nextState($state);
                return false;
            case 171:
                // place token in opponent capital territory
                $bene = $this->getCurrentBenefit($ben);
                $opponent_id = getReasonPart($bene['benefit_data'], 3);
                $start_info = $this->getStartingPosition($opponent_id);
                $location = $start_info['location'];

                $structure_id = $this->addCube($player_id, 'hand');
                $this->effect_placeOnMap($player_id, $structure_id, $location, '*', false);
                $o_count = count($this->getOutpostsInHand($opponent_id));
                $this->awardVP($player_id, $o_count * $count, $reason, null, $ben);
                return true;
            case 172:
            case 175:
                $card_type = $this->getRulesBenefit($ben, 'ct', 0);
                $draw = $this->getRulesBenefit($ben, 'draw', 1);
                $type_info = $this->card_types[$card_type];
                $cards = $this->dbPickCardsForLocation($count * $draw, $card_type, 'draw', $player_id);
                if (count($cards) == 0) {
                    return true; // cancel action
                }
                $this->notifyWithName("moveCard", clienttranslate('${player_name} draws ${count} x ${type_name} ${reason}'), [
                    'cards' => $cards, 'count' => count($cards), 'type_name' => $type_info['name'],
                    '_private' => true, 'from' => $type_info['deck'], 'reason' => $this->getReasonFullRec($reason)
                ], $player_id);
                $this->gamestate->nextState('keepCard');
                return false;
            case 173:
                //Discard Civilization, gain another
                $card_type = $this->getRulesBenefit($ben, 'ct', 0);
                $draw = $this->getRulesBenefit($ben, 'draw', 1);
                $bene = $this->getCurrentBenefitWithInfo();
                $this->effect_keepCard([], $player_id, $bene);
                $this->awardCard($player_id, $draw, $card_type);
                return true;
            case 174:
                //Keep all
                $bene = $this->getCurrentBenefitWithInfo();
                $this->effect_keepCard(null, $player_id, $bene);
                return true;
            case 194:
                $this->effect_revealHand($this->getTargetPlayer(), $player_id);
                return true;
            case 199: // opponents gain tech card
                $next_player_list = $this->getOpponentsStartingFromLeft($player_id);
                foreach ($next_player_list as $other_id) {
                    $this->awardCard($other_id, $count, CARD_TECHNOLOGY, false, $reason);
                }
                return true;
            case 200:
                // confirm or undo state
                $this->clearCurrentBenefit($ben);
                $this->gamestate->nextState('confirm');
                return false;
            case 201:
                // resume player turn 
                $this->clearCurrentBenefit($ben);
                $this->gamestate->jumpToState(13);
                return false;
            case 301:
                //                 301||Roll the black conquer die twice and gain one benefit of your choice
                $this->clearCurrentBenefit($ben);
                $this->rollBlackConquerDie($player_id);
                $b1 = $this->getConquerDieBenefit('black', $player_id);
                if (!$b1) {
                    $this->notifyAllPlayers('message', clienttranslate('this die roll results in no benefit'), []);
                }
                $this->rollBlackConquerDie($player_id);
                $b2 = $this->getConquerDieBenefit('black', $player_id);
                if (!$b2) {
                    $this->notifyAllPlayers('message', clienttranslate('this die roll results in no benefit'), []);
                }
                if ($b1 && $b2) {
                    // XXX there could be 2 tiles
                    $this->queueBenefitNormal(['or' => [$b1[0], $b2[0]]], $player_id, reason('die', clienttranslate('Conquer die')));
                } else if ($b1) {
                    $this->queueBenefitNormal($b1, $player_id, reason('die', clienttranslate('Conquer die')));
                } else if ($b2) {
                    $this->queueBenefitNormal($b2, $player_id, reason('die', clienttranslate('Conquer die')));
                }
                $this->prepareUndoSavepoint();
                $this->gamestate->nextState('loopback');
                break;
            case 302:
                //302||Roll the research die twice and gain one benefit of your choice
                $this->clearCurrentBenefit($ben);
                $b1 = $this->rollScienceDie($reason, 'science_die', $player_id);
                $b2 = $this->rollScienceDie($reason, 'science_die', $player_id);
                $this->queueBenefitNormal(['or' => [21 + $b1, 21 + $b2]], $player_id, $reason);
                $this->prepareUndoSavepoint();
                $this->gamestate->nextState('loopback');
                break;
                //               
            case 303:
                //                 303||Roll the conquer dice and gain both benefits
                $this->clearCurrentBenefit($ben);
                $this->rollRedConquerDie($player_id);
                $this->conquerDieBenefit('red', $player_id);
                $this->rollBlackConquerDie($player_id);
                $this->conquerDieBenefit('black', $player_id);
                $this->prepareUndoSavepoint();
                $this->gamestate->nextState('loopback');
                return false;
            case 304:
                //                 304||Roll the red conquer die twice and gain both benefits
                $this->clearCurrentBenefit($ben);
                $this->rollRedConquerDie($player_id);
                $this->conquerDieBenefit('red', $player_id);
                $this->rollRedConquerDie($player_id);
                $this->conquerDieBenefit('red', $player_id);
                $this->prepareUndoSavepoint();
                $this->gamestate->nextState('loopback');
                return false;
            case 306: //    BE_ALIEN_D
                $token_data = $this->getCollectionFromDB("SELECT * FROM card WHERE card_location_arg='$player_id' AND card_type='2' AND card_location='civilization_31'");
                if ($token_data) {
                    $space_tile = array_key_first($token_data);
                    $this->effect_discardCard($space_tile, $player_id);
                }
                return true;
            case 309: // BE_SPOT
                $flags = (int) getReasonPart($reason, 3);
                $trsp = getReasonPart($reason, 2);
                $track = (int) getPart($trsp, 0);
                $spot = (int) getPart($trsp, 1);
                //$this->debugConsole("be_spot $reason $flags $trsp $track $spot $player_id");
                $this->processSpotBenefits($track, $spot, $player_id, $flags, $reason);
                return true;
            case 310: //BE_RENEGADES_ADV
                /** @var Renegades */
                $inst = $this->getCivilizationInstance(CIV_RENEGADES, true);
                return $inst->awardBenefits($player_id, $ben, $count, $reason);
            case 311: //BE_GAMBLES_PICK
            case 319:
                $cards = $this->effect_drawFromBenefit($player_id, $ben);
                if (count($cards) == 0) {
                    return true; // cancel action, notif already sent
                }
                $this->gamestate->nextState('keepCard');
                return false;
            case 312: // BE_COLLECTORS_GRAB  structure              
                $bene = $this->clearCurrentBenefit($ben);
                $next_benefit = $this->getCurrentBenefit();
                if ($this->effect_collectorsGrab($player_id, $next_benefit)) {
                    $this->queueBenefitNormal(BE_ANYRES, $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS));
                    $this->benefitCashed($next_benefit);
                }


                $this->nextStateBenefitManager();
                return false;

            case 313: // BE_COLLECTORS_CARD  card
                $bene = $this->clearCurrentBenefit($ben);
                $next_benefit = $this->getCurrentBenefit();
                if ($this->effect_collectorsGrabCard($player_id, $bene)) {
                    $this->queueBenefitNormal(BE_ANYRES, $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS));
                    $this->benefitCashed($next_benefit);
                }

                $this->nextStateBenefitManager();
                return false;

            case 314: //BE_CARD_PLAY_TRIGGER

                $card_id = $this->getReasonArg($reason, 3);
                $this->effect_cardComesInPlayTriggerResolve($card_id, $player_id, $reason);
                return true;
            case 401:
                // decline - no op
                return true;
            case 603:
                // this removes next benefit from the stack
                $this->clearCurrentBenefit($ben);
                $next_benefit = $this->getCurrentBenefit();
                $this->benefitCashed($next_benefit);
                $this->gamestate->nextState('loopback');
                return false; // no cleanup
            default:
                //$this->debugConsole("default ben $ben");

                if ($state !== null) {
                    $this->gamestate->nextState($state);
                    return false;
                }
                $alias = $this->getRulesBenefit($ben, 'alias', null);
                if ($alias !== null) {
                    $this->queueBenefitInterrupt($alias, $player_id, $reason, 1);
                    return true;
                }
                if ($ben > 500 && $ben < 599) {
                    $this->awardVP($player_id, $ben - 500, $reason);
                    return true;
                }
                $civ = $this->getRulesBenefit($ben, 'civ', null);
                if ($civ !== null) {
                    $inst = $this->getCivilizationInstance($civ, true);
                    return $inst->awardBenefits($player_id, $ben, $count, $reason);
                }
                $this->systemAssertTrue("Benefit $ben  not coded yet! Use Unblock");
        }
    }

    function awardBaseResource($player_id, $benefit_id, $increase, $reason) {
        $newCount = $this->dbIncResourceCount($benefit_id, '*', null, $increase, $player_id, $reason);
        // triggers
        if ($this->isAdjustments4() && $this->hasCiv($player_id, CIV_CHOSEN)) {
            if ($newCount >= 6) {
                $this->effect_triggerPrivateAchievement($player_id, 1);
            }
        }
    }

    function effect_triggerPrivateAchievement($player_id, $pos, $civ = 15) {
        if ($this->isAdjustments4() && $this->hasCiv($player_id, $civ)) {
            $destination = "civa_{$civ}_$pos";
            $achievements = $this->getCollectionFromDB("SELECT card_location_arg FROM structure WHERE card_location = '$destination'");
            if (count($achievements) > 0) {
                return; // player already has it
            }
            $token_id = $this->addCube($player_id, $destination);
            $achi = $this->civilizations[$civ]['achi'];
            $this->notifyMoveStructure('', $token_id, [], $player_id);
            $this->notifyWithName('message_info', clienttranslate('${player_name} achieves ${achi_name}'), [
                'achi_name' => $achi[$pos]['tooltip']
            ], $player_id);
            $this->benefitCivEntry($civ, $player_id);
        }
    }

    function dbIncResourceCount($benefit_id, $notif, $new_count, $increase, $player_id, $reason = null) {
        $this->systemAssertTrue("invalid resource type $benefit_id", $this->checkValidIncomeType($benefit_id));
        if ($notif == '*') {
            if ($increase < 0)
                $notif = clienttranslate('${player_name} pays ${mod} ${res_type} ${reason}');
            else
                $notif = clienttranslate('${player_name} gains ${increase} ${res_type} ${reason}');
        }
        if ($reason == null)
            $reason = reason('str', 'unknown reason');
        $resname = $this->income_tracks[$benefit_id]['resource'];
        $res_field = "player_res_$resname";
        if ($new_count === null) {
            $base_count = $this->getUniqueValueFromDB("SELECT $res_field FROM playerextra WHERE player_id='$player_id'");
            $new_count = min($base_count + $increase, 8);
            $this->userAssertTrue(totranslate("You cannot afford this payment combination"), $new_count >= 0);
            if ($base_count + $increase > $new_count) {
                $this->notifyWithName('message_error', clienttranslate('${player_name} is maxed out on ${res_type}'), [
                    'i18n' => ['res_type'], 'res_type' => $this->getBenefitName($benefit_id),
                ]);
            }
            $increase = $new_count - $base_count; // adjusted increase
        }
        $this->notifyWithName("resource", $notif, [
            'i18n' => ['res_type'],
            'res_type' => $this->getBenefitName($benefit_id), 'increase' => $increase,
            'mod' => ($increase < 0 ? -$increase : $increase), 'count' => $new_count, 'benefit_id' => $benefit_id,
            'reason' => $this->getReasonFullRec($reason)
        ], $player_id);
        $this->DbQuery("UPDATE playerextra SET $res_field='$new_count' WHERE player_id='$player_id'");
        if ($increase < 0) {
            $this->incStat(-$increase, 'game_resource_spent', $player_id);
        }
        return $new_count;
    }

    function getMaxTrackSlot($track, $player_id = null) {
        $cubes = $this->dbGetCubesOnTrack($player_id, $track);
        $spots = $this->getTrackPositions($cubes);
        if (count($spots) == 0)
            return 0;
        return $spots[count($spots) - 1];
    }

    function awardVP($player_id, $count, $reason = null, $place = null, $ben = null) {
        $this->dbIncScore($player_id, $count);
        if ($count >= 0) {
            $message = clienttranslate('${player_name} gains ${increase} ${what} ${reason}');
        } else {
            $message = clienttranslate('${player_name} loses ${increase} ${what} ${reason}');
        }
        $source = '';
        if ($ben) {
            if (is_numeric($ben))
                $source = $this->getBenefitName($ben);
            else
                $source = $ben;
        } else {
            $source = clienttranslate('VP');
        }
        // stats by reason
        $reason_type = $this->getReasonId($reason);
        if ($reason_type == 'be') {
            $reason_type = 'other';
            if (!$ben)
                $ben = $this->getReasonValue($reason);
        }
        $this->dbIncStatChecked($count, "game_points_reason_$reason_type", $player_id);
        // stats by method
        if (is_numeric($ben)) {
            if ($this->isRealPlayer($player_id)) {
                if (!$this->dbIncStatChecked($count, "game_points_be_$ben", $player_id)) {
                    $this->warn("vp benefit is not defined for ben $ben");
                    $this->dbIncStatChecked($count, "game_points_be_15", $player_id);
                }
            }
        } else if ($ben) {
            $this->warn("vp benefit is not number $ben for $reason");
            $e = new Exception($reason);
            $this->warn($e->getTraceAsString());
        } else {
            $this->dbIncStatChecked($count, "game_points_be_15", $player_id);
        }
        // stats by era
        $era = $this->getCurrentEra($player_id);
        if ($era)
            $this->dbIncStatChecked($count, "game_points_era_$era", $player_id);
        $this->notifyWithName("VP", $message, array(
            'increase' => $count, 'what' => $source, 'i18n' => ['what'],
            'reason' => $this->getReasonFullRec($reason), 'reason_data' => $reason, 'place' => $place
        ), $player_id);
    }

    function getBenefitName($benefit_id) {
        if (!$benefit_id)
            return '';
        $name = $this->benefit_types[$benefit_id]['name'];
        return $name;
    }

    private function getCapitalScoreVP($player_id) {
        $rows_cols = 0;
        // Get this data from capital table.
        $rows = array();
        $cols = array();
        for ($a = 3; $a < 12; $a++) {
            $rows[$a] = array('count' => 0, 'values' => array());
            $cols[$a] = array('count' => 0, 'values' => array());
        }
        $grid_data = $this->getCollectionFromDB("SELECT capital_id, capital_x x, capital_y y, capital_occupied v FROM capital WHERE player_id='$player_id' AND (capital_x > 2) AND (capital_x<12) AND (capital_y > 2) AND (capital_y < 12) AND (capital_occupied > 0)");
        foreach ($grid_data as $cell) {
            $v = $cell['v'];
            $x = $cell['x'];
            $y = $cell['y'];
            $rows[$x]['count'] = $rows[$x]['count'] + 1;
            $cols[$y]['count'] = $cols[$y]['count'] + 1;
            $v = $cell['v'];
            if (($v > 1) && ($v < 6)) {
                if (!in_array($v, $rows[$x]['values'])) {
                    array_push($rows[$x]['values'], $v);
                }
                if (!in_array($v, $cols[$y]['values'])) {
                    array_push($cols[$y]['values'], $v);
                }
            }
        }
        $architect = $this->hasCiv($player_id, CIV_ARCHITECTS);
        for ($a = 3; $a < 12; $a++) {
            if ($rows[$a]['count'] == 9) {
                $rows_cols++;
                if (($architect) && (sizeOf($rows[$a]['values']) == 1)) {
                    $rows_cols++;
                }
            }
            if ($cols[$a]['count'] == 9) {
                $rows_cols++;
                if (($architect) && (sizeOf($cols[$a]['values']) == 1)) {
                    $rows_cols++;
                }
            }
        }
        return $rows_cols;
    }

    function VPincomeStructure($player_id, $type, $value, $reason = '', $place = null, $ben = null) {
        $count = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM structure WHERE card_location_arg='$player_id' AND (card_type='$type') AND ((card_location LIKE 'capital_cell%') OR (card_location LIKE 'civ_3\\_%') OR (card_location LIKE 'land_%'))");
        $this->awardVP($player_id, $value * $count, $reason, $place, $ben);
    }

    function VPTapestryCards($player_id = 0, $value = 1, $reason = null, $place = null, $ben = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $count = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location_arg='$player_id' AND (card_type='3') AND (card_location LIKE 'era%' OR card_location='hand')");
        $this->awardVP($player_id, $value * $count, $reason, $place, $ben);
    }

    function VPTapestryCardsInHand($player_id, $value = 1, $reason = null, $place = null, $ben = null) {
        $count = $this->getCardCountInHand($player_id, CARD_TAPESTRY);
        $this->awardVP($player_id, $value * $count, $reason, $place, $ben);
    }

    function VPtrack($player_id, $track, $count, $reason = null, $ben = 0) {
        // How far along the track are you?
        if (!$ben)
            $ben = 46 + $track;
        $spot = $this->getMaxTrackSlot($track, $player_id);
        $this->awardVP($player_id, $spot * $count, $reason, "tech_spot_${track}_${spot}", $ben);
    }

    function getNumberOfControlledTerritories($player_id) {
        $territory_count = count($this->getControlHexes($player_id));
        return $territory_count;
    }

    function VPconq($player_id, $count = 1, $reason = null, $ben = null) {
        $territory_count = $this->getNumberOfControlledTerritories($player_id);
        $this->awardVP($player_id, $territory_count * $count, $reason, null, $ben);
    }

    function debug_moveCard($card_type, $card_class, $location = null, $location_arg = 0) {
        if (!$location)
            $location = $this->card_types[$card_type]["deck"];
        //$this->cards->moveCard($card_id, $deck, $location_arg);
        $this->error("moving $card_class to $location state $location_arg");
        $this->DbQuery("UPDATE card SET card_location='$location', card_location_arg='$location_arg' WHERE card_type_arg='$card_class' AND card_type='$card_type'");
    }

    function dbPickCardsForLocation($count, $card_type, $to_location, $player_id = 0, $no_deck_reform = false) {
        $deck = $this->card_types[$card_type]["deck"];
        $cards = $this->cards->pickCardsForLocation($count, $deck, $to_location, $player_id, true);
        $missing = $count - count($cards);
        if ($missing && $no_deck_reform == false) {
            // resuffle discard into deck
            $this->DbQuery("UPDATE card SET card_location='$deck' WHERE card_type='$card_type' AND card_location='discard'");
            $this->cards->shuffle($deck);
            $cards += $this->cards->pickCardsForLocation($missing, $deck, $to_location, $player_id, true);
            $missing = $count - count($cards);
            $this->notifyAllPlayers('message', clienttranslate('Card deck is reshuffled'), []);
        }
        if ($missing) {
            $this->notifyAllPlayers('message', clienttranslate('Insufficient number of cards in deck'), []);
        }
        foreach ($cards as $cd => $card) {
            $card_id = $card['id'];
            $cards[$cd]['location_arg2'] = 0;
            $this->DbQuery("UPDATE card SET card_location_arg2=0 WHERE card_id='$card_id'");
        }
        if (count($cards) > 0) {
            $this->prepareUndoSavepoint();
        }
        return $cards;
    }

    function awardCard($player_id, $count, $card_type, $face_down = false, $reason = '') {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $deck = $this->card_types[$card_type]["deck"];
        $type_name = $this->card_types[$card_type]["name"];
        //$this->error("award card $player_id, $count, $card_type, $face_down, $deck|");
        //$dc= $this->getCollectionFromDB("SELECT * FROM card WHERE card_location='$deck'");
        //$this->error(json_encode($dc, JSON_PRETTY_PRINT));
        if ($card_type != CARD_CIVILIZATION) {
            $cards = $this->dbPickCardsForLocation($count, $card_type, 'hand', $player_id);
        } else {
            $cards = $this->dbPickCardsForLocation($count, $card_type, 'draw', $player_id);
        }
        if (count($cards) == 0) {
            $this->notifyWithName('message_error', clienttranslate('No more ${type_name} left'), [
                'type_name' => $type_name
            ], $player_id);
            return [];
        }
        $send_cards = $cards;
        if ($card_type == CARD_CIVILIZATION) {
            $this->notifyWithName("moveCard", clienttranslate('${player_name} draws ${count} x ${type_name} ${reason}'), [
                'count' => $count, 'type_name' => $type_name, 'cards' => $cards,
                'reason' => $this->getReasonFullRec($reason), '_private' => true
            ], $player_id);
            $send_cards = [];
        } else if ($card_type == CARD_TAPESTRY) {
            if ($face_down) {
                foreach ($cards as $cd => $card) {
                    $cards[$cd]['type_arg'] = 0;
                    $cid = $card['id'];
                    $this->DbQuery("UPDATE card SET card_type_arg=0 WHERE card_id='$cid'");
                }
            }
            $this->notifyPlayer($player_id, "newCardsMine", '', array(
                'player_id' => $player_id, 'count' => $count,
                'card_type' => $card_type, 'cards' => $cards,
            ));
            $send_cards = null; // suppress sending public notif
        }
        $this->notifyWithName("newCards", clienttranslate('${player_name} draws ${count} x ${type_name} ${reason}'), [
            'count' => $count, 'type_name' => $type_name, 'card_type' => $card_type, 'cards' => $send_cards,
            'reason' => $this->getReasonFullRec($reason)
        ], $player_id);
        foreach ($cards as $cd => $card) {
            $this->effect_cardComesInPlay($card['id'], $player_id, $reason);
        }
        $this->notifyDeckCounters($deck);
        return $cards;
    }

    function effect_cardComesInPlay($card_id, $player_id, $reason) {
        $card = $this->cards->getCard($card_id);
        $card_type = $card['type'];
        if ($this->triggerPreKeepCard($player_id, $card_id, $card_type)) {
            $data = $this->withReasonDataArg($reason, $card_id);
            $this->queueBenefitStandardOne(BE_CARD_PLAY_TRIGGER, $player_id, $data);
        } else {
            $this->effect_cardComesInPlayTriggerResolve($card_id, $player_id, $reason);
        }
    }

    function effect_cardComesInPlayTriggerResolve($card_id, $player_id, $reason) {
        $card = $this->cards->getCard($card_id);
        $card_type = $card['type'];
        $card_type_arg = $card['type_arg'];
        $location = $card['location'];
        // Process triggered abilities
        switch ($card_type) {
            case CARD_CIVILIZATION:
                $civ_id = $card_type_arg;
                if ($location == 'hand') {
                    $additions = array('tokens' => [], 'outposts' => []);
                    $this->notifyWithTokenName("civ", clienttranslate('${player_name} gets new Civilization ${name}'), $civ_id, $player_id);
                    $this->interruptBenefit();
                    $additions = $this->setupCiv($civ_id, $player_id, false);
                    // Notify players with extra stuff
                    $this->notifyAllPlayers("setupComplete", '', array(
                        'capitals' => [], 'civilizations' => [],
                        'outposts' => $this->structures->getCards($additions['outposts']),
                        'tokens' => $this->structures->getCards($additions['tokens']),
                    ));
                    $main_player = $this->getGameStateValue('current_player_turn');
                    if ($this->isIncomeTurn() && $this->getIncomeTurnPhase() <= INCOME_CIV && $main_player == $player_id) {
                        // we are in phase 1 of income turn, trigger benefit of just drawn card
                        $this->queueEraCivAbility($civ_id, $player_id);
                    }
                } else if ($location == 'draw') {
                    $choice = array_get($this->civilizations[$civ_id], "midgame_ben", [174]);
                    $era = $this->getCurrentEra($player_id);
                    if ($civ_id == CIV_HISTORIANS && $this->isAdjustments4or8() && $era <= 2) {
                        $choice = [173]; // Discard Civilization, gain another
                    }
                    $this->queueBenefitInterrupt($choice, $player_id, $reason);
                }
                break;
            case CARD_TAPESTRY:
                if ($this->isTapestryActive($player_id, TAP_ACADEMIA)) {
                    $this->awardVP($player_id, 3, reason_tapestry(TAP_ACADEMIA));
                }
                if ($this->isTapestryActive($player_id, TAP_TYRANNY)) {
                    $this->queueBenefitInterrupt(64, $player_id, $card_id);
                }
                break;
            case CARD_TECHNOLOGY:
                $tech_type = $card_type_arg;
                $args = $this->notifArgsAddTokenName('tech', $tech_type, [
                    'card_id' => $tech_type,
                    'reason' => $this->getReasonFullRec($reason)
                ]);
                $this->notifyWithName('invent', clienttranslate('${player_name} invents ${name} ${reason}'), $args, $player_id);
                if ($this->isTapestryActive($player_id, TAP_ACADEMIA)) { // ACADEMIA
                    $this->awardVP($player_id, 3, reason_tapestry(TAP_ACADEMIA));
                }
                if ($this->isTapestryActive($player_id, 22)) { // INDUSTRIALISM
                    $benefit = $this->tech_card_data[$tech_type]['circle']['benefit'];
                    $this->queueBenefitInterrupt($benefit, $player_id, reason_tapestry(22));
                }
                $this->checkMysticPrediction(1 /* tech cards */, $player_id);
                // check chosen achievement
                $this->checkPrivateAchievement(4 /* tech cards */, $player_id);

                break;
            case CARD_TERRITORY:
                if ($this->isAdjustments4() && $this->hasCiv($player_id, CIV_CHOSEN)) {
                    // tile count
                    $tile = count($this->cards->getCardsOfTypeInLocation(CARD_TERRITORY, null, 'hand', $player_id));
                    if ($tile >= 5) {
                        $this->effect_triggerPrivateAchievement($player_id, 3);
                    }
                }
                break;
            default:
                break;
        }
    }


    function effect_ageOfDiscovery($player_id = null) {
        $reason = reason_tapestry(TAP_AGE_OF_DISCOVERY);
        $track = $this->rollScienceDie2($reason);
        $next_player_list = $this->getOpponentsStartingFromLeft($player_id);
        $this->queueBenefitInterrupt(21 + $track, $player_id, $reason);
        foreach ($next_player_list as $other) {
            $this->queueBenefitNormal(75 + $track, $other, $reason); // age of discovery
        }
    }

    function getOpponentsStartingFromLeft($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId(); // default to the active player
        $players = $this->loadPlayersBasicInfosWithBots();
        $next_player_table = $this->createNextPlayerTable(array_keys($players), true);
        $other = $next_player_table[$player_id];
        $res = [];
        while ($other && $other != $player_id) {
            $res[] = $other;
            $other = $next_player_table[$other];
        }
        return $res;
    }

    function boastOfSuperiority() {
        $player_id = $this->getActivePlayerId();
        $count = $this->furthestOnTracks($player_id);
        $this->notifyAllPlayers("message", clienttranslate('${player_name} is leading on ${count} tracks'), array(
            'player_id' => $this->getActivePlayerId(), 'player_name' => $this->getActivePlayerName(),
            'count' => $count,
        ));
        $this->awardVP($player_id, $count * 4, clienttranslate('BOAST OF SUPERIORITY'));
    }

    function coalBaron() { // Draw territory and explore with it. Then give neighbours each 1 territory.
        $player_id = $this->getActivePlayerId();
        $reason = reason_tapestry(TAP_COAL_BARON);
        $cards = $this->awardCard($player_id, 1, CARD_TERRITORY, false, $reason);
        foreach ($cards as $card) {
            $this->setGameStateValue('coal_baron', $card['id']);
        }
        $this->queueBenefitNormal(17, $player_id, $reason);
        $neighbours = $this->getPlayerNeighbours($player_id, false);
        foreach ($neighbours as $neighbour) {
            $this->awardCard($neighbour, 1, CARD_TERRITORY, false, $reason);
        }
    }

    function democracy() {
        $player_id = $this->getActivePlayerId();
        $this->awardCard($player_id, 3, CARD_TAPESTRY, false, reason_tapestry(12)); // DEMOCRACY - draw 3 tapestry
        $this->queueBonus(7, -1 /* unlimited*/, "15,15", 0, $player_id); // then discard any tapestry to gain 2 vp each
    }

    function ageOfSailCheck($player_id) {
        if ($this->isTapestryActive($player_id, TAP_AGE_OF_SAIL)) { // AGE OF SAIL
            $this->awardCard($player_id, 3, CARD_TERRITORY, false, reason_tapestry(TAP_AGE_OF_SAIL));
        }
    }

    function pleaForAid() {
        $player_id = $this->getActivePlayerId();
        $cubes = $this->getCollectionFromDB("SELECT card_id, card_location, card_location_arg FROM structure WHERE card_type='7' AND card_location LIKE 'tech_spot_%'");
        $me = array();
        $others = array();
        for ($a = 1; $a <= 4; $a++) {
            $me[$a] = 0;
            $others[$a] = 13;
        }
        foreach ($cubes as $cube) {
            $coords = explode("_", $cube['card_location']);
            $track = $coords[2];
            $spot = $coords[3];
            if ($cube['card_location_arg'] == $player_id) {
                // mine
                $spot = max($spot, $me[$track]); // highest
                $me[$track] = $spot;
            } else {
                // others.
                $spot = min($spot, $others[$track]); // lowest
                $others[$track] = $spot;
            }
        }
        $count = 0;
        for ($a = 1; $a <= 4; $a++) {
            if ($me[$a] < $others[$a])
                $count++;
        }
        // award benefits.
        $this->awardVP($player_id, 2 * $count, reason_tapestry(32));
        if ($count > 0) {
            $this->queueBenefitNormal(RES_ANY, $player_id, reason_tapestry(32), $count);
            return true;
        }
        return true; // no other actions
    }

    function olympicHostSetup() {
        $player_id = $this->getActivePlayerId();
        // Get each player to choose whether to pay a worker for 10VP.
        $next_player_list = $this->getOpponentsStartingFromLeft($player_id);
        $reason = reason_tapestry(TAP_OLYMPIC_HOST);
        $this->queueBenefitNormal(['p' => RES_WORKER, 'g' => 510], $player_id, $reason);
        foreach ($next_player_list as $other_id) {
            $this->queueBenefitNormal(['p' => RES_WORKER, 'g' => 510], $other_id, $reason);
        }
        // queue the final check:
        $this->queueBenefitNormal(125, $player_id, $reason);
    }

    function olympicHostEnd($player_id, $ben, $reason) {
        $state = getReasonPart($reason, 3);
        $this->interruptBenefit();
        //  If at least 1 opponent does this, you gain 1 building from your income mat. If no opponents do this, you gain ${coins}

        if ($state == 1) {
            $this->queueBenefitNormal(110, $player_id, reason_tapestry(TAP_OLYMPIC_HOST));
        } else {
            $this->queueBenefitNormal(BE_GAIN_COIN, $player_id, reason_tapestry(TAP_OLYMPIC_HOST));
        }
    }

    // WHEN PLAYED: If you are teh first player to enter this era, gain ${tech} and upgrade it.
    // If you are not the first player to enter this era, gain 3${VP} per opponent in the game
    function effect_technocracy() {
        $player_id = $this->getActivePlayerId();
        $era = $this->getCurrentEra($player_id);
        $others = $this->getCollectionFromDB("SELECT player_id,player_income_turns FROM playerextra WHERE player_id<>'$player_id'", true);
        $num = $this->getPlayersNumberWithBots();
        foreach ($others as $other_era) {
            if ($other_era >= $era) {
                // Not first to era: 3VP per opp 
                $this->awardVP($player_id, 3 * ($num - 1), reason_tapestry(38));
                return;
            }
        }
        //$this->debug("xxx $era ".toJson($others));
        $this->queueBenefitInterrupt(127, $player_id, reason_tapestry(38));
    }

    function resourceForAll() {
        $player_data = $this->loadPlayersBasicInfos();
        foreach ($player_data as $player) {
            $this->queueBenefitNormal(5, $player['player_id'], reason_tapestry(14)); // DIPLOMACY
        }
    }

    function VPforLandmarks($player_id, $count, $reason = null, $ben = 0) {
        $landmark_count = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM structure WHERE card_type='6' AND card_location_arg='$player_id'");
        $this->awardVP($player_id, $count * $landmark_count, $reason, null, $ben);
    }

    function landmarkComparison() {
        //AGE OF WONDERS
        //WHEN PLAYED: Gain 2 ${any} if you have the fewest landmarks. If you have the most landmarks, gain 12 ${VP}. Otherwise (and for ties), gain 1 ${any} and 6 ${VP}
        $player_id = $this->getActivePlayerId();
        $player_data = $this->loadPlayersBasicInfosWithBots();
        $landmark_data = $this->getCollectionFromDB("SELECT card_location_arg, COUNT(*) c FROM structure WHERE card_type='6' GROUP BY card_location_arg");
        foreach ($player_data as $pid => $info) {
            if (!isset($landmark_data[$pid]))
                $landmark_data[(int) $pid]['c'] = 0;
        }
        $landmark_count = $landmark_data[$player_id]['c'];
        $highest = 0;
        $lowest = 99;
        foreach ($landmark_data as $pid => $ld) {
            if ($pid == 0)
                continue;
            $count = $ld['c'];
            if ($pid != $player_id) {
                if ($count < $lowest) {
                    $lowest = $count;
                }
                if ($count > $highest) {
                    $highest = $count;
                }
            }
            $this->notifyWithName('message', clienttranslate('${player_name} landmark count: ${count}'), [
                'count' => $count
            ], $pid);
        }
        $reason = reason_tapestry(4); // AGE OF WONDERS
        if ($landmark_count < $lowest) {
            // lowest
            $this->queueBenefitNormal(array(5, 5), $player_id, $reason);
        } else if ($landmark_count > $highest) {
            // highest
            $this->queueBenefitNormal(array(15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15, 15), $player_id, $reason);
        } else {
            //tie or other
            $this->queueBenefitNormal(array(15, 15, 15, 15, 15, 15, 5), $player_id, $reason);
        }
    }

    function benefitCashed($benefit_table_id) {
        if (is_array($benefit_table_id) && isset($benefit_table_id['benefit_id']))
            $benefit_table_id = $benefit_table_id['benefit_id'];
        $this->DbQuery("DELETE FROM benefit WHERE benefit_id='$benefit_table_id'");
        $this->notifyBenefitQueue();
    }

    function dbGetIncomeTrackLevel($track, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $field = $this->getIncomeTrackDbColumn($track);
        $income_level = $this->getUniqueValueFromDB("SELECT $field FROM playerextra WHERE player_id='$player_id'");
        return (int) $income_level;
    }

    function getIncomeTrackDbColumn($track) {
        $this->systemAssertTrue("invalid income track $track", $track >= 1 && $track <= 4);
        $field = "player_income_" . $this->income_tracks[$track]['field'];
        return $field;
    }

    function dbGetIncomeBuildingOfType($type, $throw = false, $notify = true, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $income_level = $this->dbGetIncomeTrackLevel($type, $player_id);
        $sid = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_location='income' AND card_location_arg='$player_id' AND (card_type='$type') LIMIT 1");
        if ($income_level >= 6 || $sid == null) {
            if ($throw) {
                $this->userAssertTrue(totranslate('No more income buildings of this type'));
                return null;
            }
            if ($notify)
                $this->notifyWithName('message_error', clienttranslate('No more income buildings of this type [${structure_type}]'), [
                    'i18n' => array('structure_type'), 'structure_type' => $this->structure_types[$type]["name"],
                ]);
            return null;
        }
        return $sid;
    }

    function claimIncomeStructure($type, $transition = 'structure') {
        $player_id = $this->getActivePlayerId();
        $field = $this->getIncomeTrackDbColumn($type);
        $sid = $this->dbGetIncomeBuildingOfType($type);
        if ($sid == null) {
            return true;
        }
        $curr = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
        if ($curr != null) {
            // we have problem
            $this->error("Claiming income building while another building is not resolved " . $curr['card_type_arg']);
            $this->DbQuery("UPDATE structure SET card_location='hand' WHERE card_location='capital_structure'");
        }
        $this->DbQuery("UPDATE structure SET card_location='capital_structure' WHERE card_id='$sid'");
        $this->DbQuery("UPDATE playerextra SET " . $field . "=" . $field . "+1 WHERE player_id='$player_id'");
        $this->notifyMoveStructure(clienttranslate('${player_name} claims one ${structure_name}'), $sid, [], $player_id);
        if (($type == BUILDING_MARKET) && ($this->isTapestryActive($player_id, 8))) { // CAPITALISM
            $this->awardBaseResource($player_id, RES_COIN, 1, $this->getTokenName('tapestry', 8));
        }
        if ($this->hasCiv($player_id, CIV_RELENTLESS)) {
            /** @var Relentless */
            $inst = $this->getCivilizationInstance(CIV_RELENTLESS, true);
            $inst->relentlessBenefitOnGainBuilding($player_id);
        }
        if ($transition != null)
            $this->gamestate->nextState($transition);
        return false;
    }

    function getLandmarkFromSlot($track, $spot) {
        $data = $this->tech_track_data[$track][$spot];
        $landmark_arr = array_get($data, 'landmark', null);
        if (!$landmark_arr)
            return null;
        $landmark_be = reset($landmark_arr);
        $landmark_id = array_get_def($this->benefit_types, $landmark_be, 'lm');
        $landmark = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location_arg2='$landmark_id' AND (card_type='6')");
        if (!$landmark) {
            $this->debugConsole("Landmark $landmark_id is missing in action - skipping (send bug)"); // NOI18N
            return null;
        } else {
            $landmark['be'] = $landmark_be;
            return $landmark;
        }
    }

    function processLandmarkSlot($track, $spot, $player_id) {
        $landmark = $this->getLandmarkFromSlot($track, $spot);
        if (!$landmark)
            return true;
        $landmark_be = $landmark['be'];
        $landmark_type = $landmark['card_location_arg2'];

        $owner = $landmark['card_location_arg'];
        if ($owner == 0) {
            $reason = reason('spot', $track . "_" . $spot);
            // gain landmark
            $this->queueBenefitNormal($landmark_be, $player_id, $reason);
        } else {
            $revisionism = ($landmark_type < 13) && $this->isTapestryActive($player_id, TAP_REVISIONISM);
            if ($revisionism) {
                $this->queueBenefitNormal(11, $player_id, reason_tapestry(TAP_REVISIONISM));
            }
        }
        return true; // complete
    }

    function claimLandmark($landmark_id, $player_id, $transition = 'structure') {
        $bene = $this->getCurrentBenefitWithInfo();
        $this->systemAssertTrue("non landmark benefit on stack", isset($bene['lm']));
        $this->systemAssertTrue("inconsistnet landmark benefit", $bene['lm'] == $landmark_id);

        $landmark = $this->getObjectFromDB("SELECT card_id, card_location_arg FROM structure WHERE card_location_arg2='$landmark_id' AND (card_type='6')");
        if (!$landmark) {
            $this->debugConsole("Landmark $landmark_id is missing in action - skipping (send bug)"); // NOI18N
            return true;
        }
        $sid = $landmark['card_id'];
        $owner = $landmark['card_location_arg'];
        //$this->debugConsole("claim land $landmark_id $player_id $owner $revisionism");
        if ($owner == 0) {
            if ($this->isRealPlayer($player_id)) {
                $curr = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
                if ($curr != null) {
                    // we have problem
                    $this->error("Claiming landmark while another building is not resolved " . $curr['card_type_arg']);
                    $this->DbQuery("UPDATE structure SET card_location='hand' WHERE card_location='capital_structure'");
                }
                $this->DbQuery("UPDATE structure SET card_location='capital_structure', card_location_arg='$player_id' WHERE card_id='$sid'");
                $this->notifyWithName("claimLandmarkStructure", clienttranslate('${player_name} claims the ${landmark_name}'), [
                    'landmark_name' => $this->landmark_data[$landmark_id]["name"]
                ], $player_id);
                $this->notifyMoveStructure('', $sid, [], $player_id);

                $this->clearCurrentBenefit($bene, true);
                $this->interruptBenefit();
                $this->gainLandmarkTriggers($player_id, $landmark_id);
                // re-insert landmark ben
                $this->interruptBenefit();
                $this->benefitSingleEntryReinsert($bene);
                if ($transition != null)
                    $this->gamestate->nextState($transition);
                return false;
            } else {
                $claim_player_id = $player_id;

                if ($this->isSolo())
                    $player_id = PLAYER_AUTOMA;
                $this->DbQuery("UPDATE structure SET card_location='hand', card_location_arg='$player_id' WHERE card_id='$sid'");
                $this->notifyWithName("claimLandmarkStructure", clienttranslate('${player_name} claims the ${landmark_name}'), [
                    'landmark_name' => $this->landmark_data[$landmark_id]["name"]
                ], $player_id);
                $this->notifyMoveStructure('', $sid, [], $player_id);
                $this->gainLandmarkTriggers($claim_player_id, $landmark_id);
            }
        }
        return true; // complete
    }

    function gainLandmarkTriggers($player_id, $landmark_type) {
        $revisionism = ($landmark_type < 13) && $this->isTapestryActive($player_id, TAP_REVISIONISM);
        if ($revisionism) {
            $this->queueBenefitNormal(RES_ANY, $player_id, reason_tapestry(TAP_REVISIONISM));
        }
        if ($this->hasCiv($player_id, CIV_UTILITARIENS)) {
            //Apothecary: Whenever you gain another landmark, also of the gain [ANY RESOURCE]
            if ($this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_7", $player_id)) {
                $this->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ(CIV_UTILITARIENS));
            }
            $to_spot = $this->getCivSlotWithValue(CIV_UTILITARIENS, "lm", $landmark_type);
            if ($to_spot) {
                $this->notif('message', $player_id)->notifyAll(clienttranslate('${player_name} triggered power of Utilitaries'));
                $this->benefitCivEntry(CIV_UTILITARIENS, $player_id, "triggered::$landmark_type");
            }
        }

        /** @var Historians */
        $inst = $this->getCivilizationInstance(CIV_HISTORIANS, true);
        // placing landmark trigger historian benefits
        $inst->historianBenefits($player_id, $landmark_type);


        if ($this->hasCiv($player_id, CIV_RELENTLESS)) {
            /** @var Relentless */
            $inst = $this->getCivilizationInstance(CIV_RELENTLESS, true);
            $inst->relentlessBenefitOnGainBuilding($player_id);
        }
    }




    function hasCiv($player_id, $civ_id) {
        $card_id = $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_type='5' AND card_type_arg='$civ_id' AND card_location='hand' AND card_location_arg='$player_id'");
        return ($card_id != null);
    }

    function getAllCivs($player_id) {
        return $this->getCardsSearch(CARD_CIVILIZATION, null, 'hand', $player_id);
    }

    function getCivOwner($civ_id) {
        $player_id = $this->getUniqueValueFromDB("SELECT card_location_arg FROM card WHERE card_type='5' AND card_type_arg='$civ_id' AND card_location='hand'");
        return $player_id;
    }

    function getCardDataByType($card_type, $card_type_arg) {
        return $this->getObjectFromDB("SELECT * FROM card WHERE card_type='$card_type'  AND card_type_arg='$card_type_arg' LIMIT 1");
    }

    function getOwnersWithSFlags($player_id, $sflags) {
        $owners = [];
        if (is_flag_set($sflags, FLAG_NEIGHBOUR)) {
            $owners = $this->getPlayerNeighbours($player_id, false);
        } else if (is_flag_set($sflags, FLAG_OPPONENT)) {
            $all = $this->loadPlayersBasicInfosWithBots();
            unset($all[$player_id]);
            $owners = array_keys($all);
        }
        if (is_flag_set($sflags, FLAG_SELF)) {
            $owners[] = $player_id;
        }
        return $owners;
    }

    function getCubeInfoWithFlags($player_id, $sflags, $track = 0, $spot = null) {
        $type_arg = 0;
        if (($sflags & FLAG_VIRTUAL_ALLOWED) != 0) {
            $type_arg = null;
            $sflags &= ~FLAG_VIRTUAL_ALLOWED;
        }
        if ($sflags == 0)
            $sflags = FLAG_OPPONENT | FLAG_SELF;
        $mask = $this->getTrackLocationLike($track);
        $owners = $this->getOwnersWithSFlags($player_id, $sflags);
        $this->systemAssertTrue("no owners for $player_id $sflags", count($owners) > 0);
        return $this->getStructuresSearch(BUILDING_CUBE, $type_arg, $mask, $owners);
    }

    function getActiveTapestriesOfType($tapestry_id) {
        $taps = [];
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $player_id => $info) {
            $tap = $this->isTapestryActive($player_id, $tapestry_id);
            if ($tap) {
                $taps[$tap['card_id']] = $tap;
            }
        }
        return $taps;
    }

    // Checks if player has the tapestry active
    function isTapestryActive($player_id, $tapestry_id, $throw = false) {
        $taps = [];
        $era = $this->getCurrentEra($player_id);
        $current_tapestry = $this->getLatestTapestry($player_id, $era);
        if ($current_tapestry)
            $taps[] = $current_tapestry;
        if ($this->hasCiv($player_id, CIV_HERALDS)) {
            $current_tapestry2 = $this->getTapestryOn($player_id, 'civilization_6'); // special case for heralds
            if ($current_tapestry2)
                $taps[] = $current_tapestry2;
        }
        //         if ($this->hasCiv($player_id, CIV_SPIES)) {
        //             $current_tapestry2 = $this->getTapestryOn($player_id, 'civilization_36'); // special case for spies
        //             if ($current_tapestry2)
        //                 $taps [] = $current_tapestry2;
        //         }
        $tap_type = 0;
        foreach ($taps as $current_tapestry) {
            $tap_type = $current_tapestry['card_type_arg'];
            if ($tap_type == $tapestry_id)
                return $current_tapestry;
            if ($tap_type == TAP_ESPIONAGE) {
                $card_id = $current_tapestry['card_id'];
                $clone = $this->getObjectFromDB("SELECT * FROM card WHERE card_type='3' AND card_location = 'tapestry_$card_id' AND card_location_arg='$player_id' LIMIT 1");
                if ($clone && $clone['card_type_arg'] == $tapestry_id)
                    return $clone;
            }
        }
        if ($current_tapestry == null) {
            $this->systemAssertTrue("expecting tapestry $tapestry_id but it was none", !$throw);
            return null;
        }
        $this->systemAssertTrue("expecting tapestry $tapestry_id but it was $tap_type", !$throw);
        return null;
    }

    // Gets the last played tapestry card
    function getLatestTapestry($player_id, $era = '%') {
        return $this->getObjectFromDB("SELECT * FROM card WHERE card_type='3' AND card_location LIKE 'era$era' AND card_location_arg='$player_id' ORDER BY card_location, card_id DESC LIMIT 1");
    }

    function getTapestryOn($player_id, $loc) {
        return $this->getObjectFromDB("SELECT * FROM card WHERE card_type='3' AND card_location ='$loc' AND card_location_arg='$player_id' ORDER BY card_location, card_id DESC LIMIT 1");
    }

    // Gets the players current era (n.b. Changes at start of income turn)
    function getCurrentEra($player_id) {
        return $this->getUniqueValueFromDB("SELECT player_income_turns FROM playerextra WHERE player_id='$player_id'");
    }

    function isPlayerAlive($player_id) {
        return !$this->isPlayerFinished($player_id);
    }

    function isPlayerFinished($player_id) {
        if ($this->isRealPlayer($player_id) && $this->isZombiePlayer($player_id))
            return true;
        $era = $this->getCurrentEra($player_id);
        if ($era <= 5)
            return false;
        return true;
    }

    function isIncomeTurn() {
        return $this->getGameStateValue('income_turn_phase') != 0;
    }

    function setIncomeTurnPhase($phase, $message = '', $player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if ($phase > 0) {
            $this->setGameStateValue('income_turn_phase', $phase);
        } else {
            $this->setGameStateValue('income_turn_phase', 0);
        }
        $this->notifyWithName('income', $message, [
            'turn_number' => $this->getCurrentEra($player_id),
            'income_turn_phase' => $this->getIncomeTurnPhase()
        ], $player_id);
    }

    function setTargetPlayer($player_id) {
        $this->setGameStateValue('target_player', $player_id);
    }

    function getTargetPlayer() {
        return $this->getGameStateValue('target_player');
    }

    function getIncomeTurnPhase() {
        return $this->getGameStateValue('income_turn_phase');
    }

    function playTapestryCard($card_id, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $opargs = $this->argTapestryCard();
        $era = $this->getCurrentEra($player_id);
        $ben = $this->getCurrentBenefitType();
        $era_string = 'era' . $era;
        $tap_type = $this->getUniqueValueFromDB("SELECT card_type_arg FROM card WHERE card_id='$card_id'");
        $card_effect = array_get($this->tapestry_card_data[$tap_type], 'benefit', null);
        if ($ben) {
            $this->userAssertTrue(totranslate('Cannot play tapestry card from hand, select a card to copy from the mat'), $ben != 112);
            $this->clearCurrentBenefit($ben);
        } else {
            $this->warn("suspicious tapestry played no benefit $card_id era $era");
        }
        if ($ben == 64) {
            $message = clienttranslate('${player_name} over-plays previous tapestry card with ${card_name}');
        } else
            $message = clienttranslate('${player_name} plays a tapestry card ${card_name}');
        if ($era == 1 && $ben == 64 && $this->hasCiv($player_id, CIV_HERALDS)) {
            if ($this->isAdjustments4or8()) {
                $tapvar = $this->tapestry_card_data[$tap_type]['type'];
                $allowed = true;
                if ($tapvar == "now" || $tapvar == "cont") {
                    $allowed = $tap_type != 33; // not RENAISSANCE
                } else {
                    $allowed = false;
                }
                $this->userAssertTrue(totranslate('HERALDS cannot play this type of card over Marker of Fire'), $allowed);
                $card_effect = null;
                $message = clienttranslate('${player_name} plays a tapestry card ${card_name} over Maker Of Fire - no benefits');
            } else {
                $message = clienttranslate('${player_name} plays a tapestry card ${card_name} over Maker Of Fire');
            }
        }
        $prev = $this->getLatestTapestry($player_id, $era);
        if ($prev) {
            $prev_id = $prev['card_id'];
            $this->DbQuery("UPDATE card SET card_location='era_6' WHERE card_id='$prev_id'");
            $args = $this->notifArgsAddCardInfo($prev_id, [
                'espionage' => false,
                'destination' => "tapestry_slot_${player_id}_6"
            ]);
            $this->notifyWithName("tapestrycard", '', $args, $player_id);
        }
        $this->DbQuery("UPDATE card SET card_location='$era_string',card_location_arg='$player_id' WHERE card_id='$card_id'");
        $tyranny = array_get($opargs, 'tyranny');
        if ($tyranny) {
            $just_played = array_get($opargs, 'just_played');
            if ($just_played) {
                if ($tap_type != $just_played) {
                    throw new BgaUserException($this->_('You can only play a just drawn card'));
                }
                $this->awardVP($player_id, 5, reason_tapestry(TAP_TYRANNY));
            }
        }
        $args = $this->notifArgsAddCardInfo($card_id, [
            'espionage' => false,
            'destination' => "tapestry_slot_${player_id}_$era"
        ]);
        $this->notifyWithName("tapestrycard", $message, $args, $player_id);
        if ($card_effect) {
            $this->queueBenefitInterrupt($card_effect, $player_id, reason_tapestry($tap_type));
        }
    }

    function research($reason) {
        $this->rollScienceDie2($reason);
        $this->gamestate->nextState('research');
        return false;
    }

    function rollScienceDie2($reason) {
        $player_id = $this->getActivePlayerId();
        $this->setGameStateValue('science_die_empiricism', 0);
        $roll1 = $this->rollScienceDie($reason);
        if ($this->isTapestryActive($player_id, TAP_EMPIRICISM)) { // EMPIRICISM
            $this->rollScienceDie(reason_tapestry(TAP_EMPIRICISM), 'science_die_empiricism');
        }
        $this->prepareUndoSavepoint();
        return $roll1;
    }

    function rollScienceDie($data, $dievar = 'science_die', $player_id = -1) {
        $die_roll = bga_rand(1, 4);
        $this->setGameStateValue($dievar, $die_roll);
        $this->notifyWithTrack("science_roll", clienttranslate('${player_name} rolls the science die with result ${track_name} ${reason}'), [
            'die' => $die_roll, 'track' => $die_roll, 'reason' => $this->getReasonFullRec($data)
        ], $player_id);
        return $die_roll;
    }

    function getTrackLocationLike($track = null, $spot = null) {
        if ($spot === null) {
            $spot = "%";
        } else {
            $this->checkSpot($spot);
        }
        if (!$track) {
            $track = "%";
        } else {
            $this->checkTrack($track);
        }
        $track_stub = "tech_spot_${track}_${spot}";
        return $track_stub;
    }

    function dbGetCubesOnTrack($player_id, $track = null, $spot = null, $type_arg = null) {
        //$this->getCubeInfoWithFlags($player_id);
        $track_stub = $this->getTrackLocationLike($track, $spot);
        $sql = "SELECT * FROM structure WHERE card_location LIKE '$track_stub' AND card_type='7'";
        if ($player_id < 0) {
            $player_id = -$player_id;
            $sql .= " AND card_location_arg!='$player_id'";
        } else if ($player_id)
            $sql .= " AND card_location_arg='$player_id'";
        if ($type_arg !== null) {
            $sql .= " AND card_type_arg='$type_arg'";
        } else {
            $sql .= " ORDER BY card_type_arg ASC";
        }
        $cubes = $this->getCollectionFromDB($sql);
        foreach ($cubes as &$cube) {
            $spot = (int) getPart($cube['card_location'], 3); // tech_spot_1_3
            $cube['spot'] = $spot;
            $cube['virtual'] = $cube['card_type_arg'] == CUBE_AI;
        }
        $this->sortTrackCubes($cubes);
        return $cubes;
    }

    function sortTrackCubes(array &$cubes) {
        uasort($cubes, function ($a, $b) {
            $track_a = (int) getPart($a['card_location'], 2); // tech_spot_1_3
            $track_b = (int) getPart($b['card_location'], 2);
            $track_dist = $track_a <=> $track_b;
            if ($track_dist != 0)
                return $track_dist;
            $spot_a = (int) getPart($a['card_location'], 3);
            $spot_b = (int) getPart($b['card_location'], 3);
            $dist = $spot_a <=> $spot_b;
            $vdist = ($a['card_type_arg'] - $b['card_type_arg']) <=> 0;
            if ($dist == 0) {
                return $vdist;
            }
            return -$dist;
        });
    }

    function cubeChoiceForTrackBenefitInterractive($track, $spot, $player_id = 0, $flags = FLAG_SELF) {
        $cubes = $this->getCubeInfoWithFlags($player_id, $flags, $track, $spot);
        $count = count($cubes);
        if ($count == 0) {
            if ($flags & FLAG_SELF) {
                $mess = totranslate('Cannot find a cube on this spot');
            } else {
                $mess = totranslate('Cannot find an opponent\'s cube on this spot');
            }
            $this->userAssertTrue($mess);
        }
        if (($flags & FLAG_SINGLE) && $count > 1 && ($flags & FLAG_OPPONENT)) {
            $this->userAssertTrue(totranslate('Cannot determine which cube you are targeting, select the cube, not the spot'));
        }
        $cube = reset($cubes);
        return $cube;
    }

    function cubeChoiceForTrackSingle($track, $player_id, $change = 0, $choice = -1) {
        $arr = $this->cubeChoiceForTrack($track, $player_id, $change, $choice);
        $cube = reset($arr);
        if ($cube)
            $this->userAssertTrue(totranslate('Cannot select virtual cube'), !$cube['virtual']);
        return $cube;
    }

    function cubeChoiceForTrack($track, $player_id, $change = 0, $choice = -1) {
        $arg = null;
        if ($choice == -2) {
            $arg = 0;
        }
        $cubes = $this->dbGetCubesOnTrack($player_id, $track, null, $arg);
        $count = count($cubes);
        $this->systemAssertTrue("Has to be cubes on track $track", $count > 0);
        $first_cube = reset($cubes);
        $first = $first_cube['spot'];
        $same = 0;
        foreach ($cubes as $cube) {
            $spot = $cube['spot'];
            if ($spot == $first)
                $same++;
        }
        if ($choice == -1) {
            if ($count == 1 || $same == $count) {
                return [$first_cube];
            }
            return $cubes;
        }
        if ($choice == -2) {
            return [$first_cube];
        }
        foreach ($cubes as $cube) {
            $spot = $cube['spot'];
            if ($spot == $choice)
                return [$cube];
        }
        foreach ($cubes as $cube) {
            $spot = $cube['spot'];
            if ($spot + $change == $choice) // XXX
                return [$cube];
        }
        return [null];
    }

    function spotChoiceForTrack($track, $player_id, $change = 0, $choice = -1) {
        $cubes = $this->cubeChoiceForTrack($track, $player_id, $change, $choice);
        $this->systemAssertTrue("Has to be cubes on track", count($cubes) > 0);
        if (count($cubes) > 1 && $this->isRealPlayer($player_id)) {
            return -1;
        }
        $cube = array_shift($cubes);
        if (!$cube) {
            return -1;
        }
        return $cube['spot'];
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// DEBUG METHODS
    ////////////
    function debug_bene($benefit = null, $player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if ($benefit) {
            $benefit = explode(':', $benefit);
            $this->queueBenefitInterrupt($benefit, $player_id, reason("str", "debug"));
        }
        $this->gamestate->nextState('benefit');
    }

    function debug_giveCard($type, $id) {
        $player_id = $this->getActivePlayerId();
        $this->DbQuery("UPDATE card SET card_location='hand', card_location_arg='$player_id' WHERE card_type='$type' AND card_type_arg='$id' AND card_location LIKE 'deck%'");
    }

    function searchForCard($array, $card_name) {
        foreach ($array as $cid => $info) {
            if (strcasecmp($info['name'], $card_name) == 0)
                return $cid;
        }
        return 0;
    }

    function debug_civ(string $card_name) {
        if (is_numeric($card_name)) {
            $cid = $card_name;
        } else {
            $cid = $this->searchForCard($this->civilizations, $card_name);
        }
        $this->systemAssertTrue("card not found $card_name $cid", $cid);
        $data = $this->getCardDataByType(CARD_CIVILIZATION, $cid);
        if (!$data) {
            $cards = [];
            $cards[] = array('type' => CARD_CIVILIZATION, 'type_arg' => $cid, 'nbr' => 1);
            $this->cards->createCards($cards, 'deck_civ');
        }
        $this->debug_awardCard(CARD_CIVILIZATION, $cid);
        if ($this->getCurrentBenefit())
            $this->gamestate->nextState('next');
        return;
    }

    function debug_award($card_name) {
        $cid = $this->searchForCard($this->tapestry_card_data, $card_name);
        if ($cid) {
            $this->debug_awardCard(3, $cid);
            return;
        }
        $cid = $this->searchForCard($this->tech_card_data, $card_name);
        if ($cid) {
            $this->debug_awardCard(4, $cid);
            return;
        }
        $cid = $this->searchForCard($this->civilizations, $card_name);
        if ($cid) {
            $this->debug_awardCard(5, $cid);
            return;
        }
        if (startsWith($card_name, "tile") || startswith($card_name, "territory")) {
            $cid = getPart($card_name, 1);
            $this->debug_awardCard(1, $cid);
            return;
        }
        if (startsWith($card_name, "space")) {
            $cid = getPart($card_name, 1);
            $this->debug_awardCard(CARD_SPACE, $cid);
            return;
        }
        $this->userAssertTrue("Card $card_name is not found");
    }

    function debug_discardHand($type, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $cards = $this->getCardsInHand($player_id, $type);
        foreach (array_keys($cards) as $card_id) {
            $this->effect_discardCard($card_id, $player_id);
        }
    }

    function debug_awardCard($card_type, $card_num = 0, $loc = null, $next = false, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if ($card_num)
            $this->debug_insertCard($card_type, $card_num);
        $cards = $this->awardCard($player_id, 1, $card_type, false, reason('str', 'debug'));
        if ($loc)
            foreach ($cards as $card) {
                $this->dbSetCardLocation($card['id'], $loc, null, '', $player_id);
            }
        if ($next)
            if ($this->getCurrentBenefit())
                $this->gamestate->nextState('next');
        return $cards;
    }

    function debug_insertCard($card_type, $card_num) {
        $deck = $this->card_types[$card_type]["deck"];
        $cards = $this->cards->getCardsOfType($card_type, $card_num);
        $card_id = array_key_first($cards);
        if (!$card_id) {
            $cards = [];
            $cards[] = array('type' => $card_type, 'type_arg' => $card_num, 'nbr' => 1);
            $this->cards->createCards($cards, $deck);
            $cards = $this->cards->getCardsOfType($card_type, $card_num);
            $card_id = array_key_first($cards);
        }
        $this->cards->insertCardOnExtremePosition($card_id, $deck, true);
    }

    function debug_discardDeck($deck) {
        $sql = "UPDATE card SET card_location='discard',card_location_arg=0 ";
        $sql .= "WHERE card_location='$deck'";
        self::DbQuery($sql);
    }

    function debug_maxRes() {
        $this->debug_res(8, 8, 8, 8, $this->getCurrentPlayerId());
    }

    function debug_res($coin, $worker, $food, $culture, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $this->dbIncResourceCount(RES_COIN, '', $coin, 0, $player_id);
        $this->dbIncResourceCount(RES_WORKER, '', $worker, 0, $player_id);
        $this->dbIncResourceCount(RES_FOOD, '', $food, 0, $player_id);
        $this->dbIncResourceCount(RES_CULTURE, '', $culture, 0, $player_id);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    function chooseCivilization($civ, $cap) {
        $this->gamestate->checkPossibleAction('chooseCivilization');
        $player_id = $this->getCurrentPlayerId();
        $setup_choice = $this->getSetupChoice($player_id);
        // CIVILIZATION
        $civs = $setup_choice['civilizations'];
        $this->systemAssertTrue("civ not found $civ", array_key_exists($civ,   $civs));
        $this->DbQuery("UPDATE card SET card_location='choice' WHERE card_type='5' AND card_location_arg='$player_id'");
        $this->DbQuery("UPDATE card SET card_location='hand',card_location_arg='$player_id' WHERE card_type='5' AND card_type_arg='$civ'");
        // CAPITAL
        if (($cap == 0) && ($setup_choice['capitals'] != null)) {
            $this->systemAssertTrue('capital choice not made');
        }
        if ($cap > 0) {
            if (!array_key_exists($cap, $setup_choice['capitals'])) {
                $this->systemAssertTrue('capital not found');
            } else {
                $this->DbQuery("UPDATE card SET card_location='choice' WHERE card_type='6' AND card_location_arg='$player_id'");
                $this->DbQuery("UPDATE card SET card_location='hand',card_location_arg='$player_id' WHERE card_type='6' AND card_type_arg='$cap'");
            }
        }
        $cap = $this->getUniqueValueFromDB("SELECT card_type_arg FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='6'");
        $name = $this->capitals[$cap]['name'];
        $this->notifyWithName('message', clienttranslate('${player_name} choses ${civ_name} on ${cap_name}'), [
            'civ_name' => $this->getTokenName('civ', $civ), 'cap_name' => $name, '_private' => true
        ], $player_id);
        $this->gamestate->setPlayerNonMultiactive($player_id, '');
    }

    function importCapitalGrids() {
        $chosen_capitals = $this->getCollectionFromDB("SELECT card_type_arg, card_location_arg FROM card WHERE card_type='6' AND card_location='hand'");
        foreach ($chosen_capitals as $cap) {
            $cap_id = $cap['card_type_arg'];
            $player_id = $cap['card_location_arg'];
            $grid_data = $this->capitals[$cap_id]['grid'];
            for ($x = 0; $x < 15; $x++) {
                for ($y = 0; $y < 3; $y++) {
                    $this->DbQuery("INSERT INTO capital (player_id, capital_x, capital_y, capital_occupied) VALUES ('" . $player_id . "', '" . $x . "', '" . $y . "', '0')");
                }
                for ($y = 12; $y < 15; $y++) {
                    $this->DbQuery("INSERT INTO capital (player_id, capital_x, capital_y, capital_occupied) VALUES ('" . $player_id . "', '" . $x . "', '" . $y . "', '0')");
                }
                if (($x < 3) || ($x > 11)) {
                    for ($y = 3; $y < 12; $y++) {
                        $this->DbQuery("INSERT INTO capital (player_id, capital_x, capital_y, capital_occupied) VALUES ('" . $player_id . "', '" . $x . "', '" . $y . "', '0')");
                    }
                }
            }
            $x = 3;
            foreach ($grid_data as $g) {
                for ($y = 0; $y < 9; $y++) {
                    $value = substr($g, $y, 1);
                    $this->DbQuery("INSERT INTO capital (player_id, capital_x, capital_y, capital_occupied) VALUES ('" . $player_id . "', '" . $x . "', '" . ($y + 3) . "', '" . $value . "')");
                }
                $x++;
            }
        }
    }

    function action_playTapestryCard($card_id) {
        $this->checkAction('playCard');
        $player_id = $this->getActivePlayerId();
        $check_card_id = $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_type='3' AND card_id='$card_id' AND card_location='hand' AND card_location_arg='$player_id'");
        $this->systemAssertTrue("invalid card selected $card_id not in hand", $check_card_id);
        $ben = $this->getCurrentBenefitType();
        if ($ben == 181) {
            // discard
            $this->clearCurrentBenefit();
            $this->effect_discardCard($card_id);
        } else {
            $this->playTapestryCard($card_id, $player_id);
        }
        $this->gamestate->nextState('benefit');
    }

    function removeHeraldTapestryClone($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $this->systemAssertTrue("player has no HERALDS", $this->hasCiv($player_id, CIV_HERALDS));
        $cards = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='3' AND card_location = 'civilization_6'");
        foreach ($cards as $tap_card_data) {
            $card_id = $tap_card_data['card_id'];
            $this->effect_discardCard($card_id, $player_id, 'limbo');
        }
    }

    function copy_card($card_id) {
        $player_id = $this->getActivePlayerId();
        $this->clearCurrentBenefit(112); // espionage
        $tap_card_data = $this->getObjectFromDB("SELECT * FROM card WHERE card_id='$card_id' AND card_type='3' AND card_location LIKE 'era%'");
        $this->systemAssertTrue("invalid card selected $card_id not in era", $tap_card_data != null);
        $tap_type = $tap_card_data['card_type_arg'];
        $location = $tap_card_data['card_location'];
        $this->userAssertTrue("Cannot select covered up tapestry card to copy", $location != 'era_6');
        if ($tap_type == TAP_ESPIONAGE) {
            $all_taps = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='3' AND card_location LIKE 'era%'");
            if (count($all_taps) == 1) {
                // no other targets, nothing to copy
                $this->notifyWithName('message', clienttranslate('ESPIONAGE has not valid targets, no copy is made'));
                $this->gamestate->nextState('benefit');
                return;
            }
            $this->userAssertTrue(totranslate('Cannot copy itself'));
            return;
        }
        $espionage = $this->isTapestryActive($player_id, TAP_ESPIONAGE, false);
        if (!$espionage)
            $espionage = $this->getCardInfoSearch(CARD_TAPESTRY, TAP_ESPIONAGE, "era%");
        $this->systemAssertTrue('cannot find ESPIONAGE', $espionage);
        $eid = $espionage['card_id'];
        $loc = $espionage['card_location'];
        //$this->debugConsole("$loc", $espionage);
        if ($loc == 'civilization_6') { // on heralds
            $this->removeHeraldTapestryClone($player_id);
            $clone_id = $this->addTapestryClone($player_id, 'civilization_6', $tap_type, 'clone');
        } else {
            $clone_id = $this->addTapestryClone($player_id, "tapestry_$eid", $tap_type, 'clone');
        }
        $args = $this->notifArgsAddCardInfo($clone_id, ['espionage' => $espionage]);
        $this->notifyWithName("tapestrycard", clienttranslate('${player_name} uses ESPIONAGE to copy ${card_name}'), $args);
        $card_effect = array_get($this->tapestry_card_data[$tap_type], 'benefit', null);
        if ($card_effect) {
            $this->queueBenefitInterrupt($card_effect, $player_id, reason_tapestry($tap_type));
        }
        $this->gamestate->nextState('benefit');
    }

    function tapestryChoice($card_id) { // Selected a tapestry card for herald power.
        $ben = $this->getCurrentBenefitType();
        if ($ben == 112) { // espionage
            $this->copy_card($card_id);
            return;
        }
        // HERALD ABILITY
        $this->checkAction('tapestryChoice');
        $benefit_data = $this->getCurrentBenefit(CIV_HERALDS, 'civ');
        $this->systemAssertTrue("cannot find HERALDS", $benefit_data);
        $this->benefitCashed($benefit_data); // the civ benefit.
        $player_id = $this->getActivePlayerId();
        // VALIDITY CHECKS
        // 1. Check player owns HERALDS and has available tokens.
        $herald_token = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location LIKE 'civ_6_%') AND card_location_arg='$player_id' LIMIT 1");
        $this->systemAssertTrue('Herald tokens not available', $herald_token);
        // 2. Check era card
        $card_data = $this->getObjectFromDB("SELECT * FROM card WHERE card_type='3' AND card_id='$card_id' AND (card_location LIKE 'era%')");
        $this->systemAssertTrue('Not an era card', $card_data);
        // 3. Check no existing token on card
        $tapestry_id = "tapestry_$card_id";
        $existing_token = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location = '$tapestry_id')");
        $this->userAssertTrue(totranslate('You cannot place a second HERALD token on a card'), $existing_token == null);
        // 4. Check WHEN PLAYED card
        $tap_type = $card_data['card_type_arg'];
        $card_data = $this->tapestry_card_data[$tap_type];
        if ($card_data['type'] != "now") {
            throw new BgaUserException($this->_('Ability can only be used on WHEN PLAYED cards'));
        } // Check a WHEN PLAYED
        // PROCESS ACTION
        $this->DbQuery("UPDATE structure SET card_location='$tapestry_id' WHERE card_id='$herald_token'");
        $this->notifyMoveStructure('', $herald_token, [], $player_id);
        // create clone and put on herald
        $clone_id = $this->addTapestryClone($player_id, "civilization_6", $tap_type);
        $args = $this->notifArgsAddCardInfo($clone_id, ['espionage' => true, 'card_id' => $clone_id]);
        $this->notifyWithName("tapestrycard", clienttranslate('${player_name} places a HERALD token on ${card_name}'), $args);
        // APPLY BENEFITS
        $card_effect = array_get($this->tapestry_card_data[$tap_type], 'benefit', null);
        if ($card_effect) {
            $this->queueBenefitInterrupt($card_effect, $player_id, reason_tapestry($tap_type));
        }
        $this->gamestate->nextState('benefit');
    }

    function takeIncome() {
        $this->checkAction('takeIncome');
        $this->takeIncomeAuto(false);
    }

    function takeIncomeAuto($auto) {
        $player_id = $this->getActivePlayerId();
        $this->DbQuery("UPDATE playerextra SET player_income_turns=player_income_turns+1 WHERE player_id='$player_id'");
        $notif = clienttranslate('${player_name} takes an income turn');
        if ($auto)
            $notif = clienttranslate('${player_name} takes an income turn (auto-trigger)');
        $this->notifyAllPlayers("takeIncome", $notif, array(
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
        ));
        $this->setIncomeTurnPhase(INCOME_CIV, clienttranslate('${player_name} takes income turn ${turn_number}'), $player_id);
        $this->setGameStateValue('income_turn', 1);
        $this->queueIncomeTurn();
        $this->gamestate->nextState('next');
    }

    function queueIncomeTurn() {
        $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        $this->queueEraCivAbilities();
        $reason = reason('str', clienttranslate('income'));
        if ($income_turn_count >= 2 && $income_turn_count <= 4) {
            $this->queueBenefitNormal(BE_PLAY_TAPESTY_INCOME, $player_id, $reason);
        }
        if ($income_turn_count >= 2) {
            $this->queueBenefitNormal(BE_UPGRADE_TECH_INCOME, $player_id, $reason);
            $this->queueBenefitNormal(12, $player_id, $reason); // VP Income
        }
        if ($income_turn_count >= 2 && $income_turn_count < 5) {
            // Resource Income / Cards Income - choice which is first - also acts as Undo prompt
            $this->queueBenefitNormal(['choice' => [16, 129]], $player_id, $reason);
        } else if ($income_turn_count == 1) {
            $this->queueBenefitNormal([16, 129], $player_id, $reason);
        } else if ($income_turn_count == 5) {
            $this->queueBenefitNormal(BE_CONFIRM, $player_id, $reason); // confirm
        }
        if ($income_turn_count == 1) {
            // If owns the heralds can choose to play tapestry card after first income.
            if ($this->hasCiv($player_id, CIV_HERALDS)) {
                $this->queueBenefitNormal(64, $player_id, reason_civ(CIV_HERALDS));
            }
            // civ adjustments can process now
            $civ_id = $this->getUniqueValueFromDB("SELECT card_type_arg FROM card WHERE card_type='5'  AND card_location='hand' AND card_location_arg='$player_id' LIMIT 1");
            $this->civAdjustments($player_id, $civ_id);
        }
        $this->queueBenefitNormal(602, $player_id, $reason); // end of income
    }

    function choose_resources($resources, $type) { // $type can be used for guilds...
        $this->checkAction('choose_resources');
        $player_id = $this->getActivePlayerId();
        $benefit_data = $this->getCurrentBenefit();
        $this->benefitCashed($benefit_data['benefit_id']);
        $data = $benefit_data['benefit_data'];
        switch ($benefit_data['benefit_type']) {
            case BE_ANYRES:
                if (sizeOf($resources) != $benefit_data['benefit_quantity']) {
                    throw new feException('Invalid resource quantity');
                }
                if ($data == reason_tapestry(TAP_MERCANTILISM)) { // MERCANTILISM
                    foreach ($resources as $rtype) {
                        if (!$rtype)
                            continue;
                        if ($rtype == RES_FOOD)
                            continue;
                        $count = 1;
                        $this->awardBaseResource($player_id, RES_FOOD, -$count, $data);
                        $this->awardBaseResource($player_id, $rtype, +$count, $data);
                        $this->awardVP($player_id, 2 * $count, $data);
                    }
                } else
                    foreach ($resources as $rtype) {
                        $this->awardBaseResource($player_id, $rtype, +1, $data);
                    }
                break;
            case '114':
                $vp = 5 * sizeOf($resources);
                $reason = reason_tapestry(21); // GUILDS
                $increase = $type == 1 ? -1 : 1;
                $curScore = $this->dbGetScore($player_id);
                $maxres = $this->getResourceCountAll($player_id);
                if ($maxres == 0 && $curScore < 5) {
                    $this->notifyWithName('message_error', clienttranslate('${player_name} is too poor for the GUILDS, benefit is void'));
                    break;
                }
                foreach ($resources as $rtype) {
                    $this->awardBaseResource($player_id, $rtype, $increase, $reason);
                }
                if ($type == 1) { // Gain VP.
                    $this->awardVP($player_id, $vp, $reason);
                } else { // Gain res
                    if ($curScore < $vp) {
                        throw new BgaUserException($this->_('You do not have that many victory points'));
                    } else {
                        $this->awardVP($player_id, -$vp, $reason);
                    }
                }
                break;
            default:
                throw new feException('Invalid benefit type');
        }
        $this->gamestate->nextState('next');
    }

    function action_alchemistChoice($track) {
        //$this->checkAction('alchemistRoll');
        $options = array();
        array_push($options, $this->getGameStateValue('science_die'));
        array_push($options, $this->getGameStateValue('science_die_empiricism'));
        if (!in_array($track, $options)) {
            throw new feException('Invalid Alchemist Choice');
        }
        $this->setGameStateValue('science_die_empiricism', 0);
        $this->setGameStateValue('science_die', $track);
        $this->alchemistRollFinish($track);
    }

    function alchemistRoll() {
        $this->checkAction('alchemistRoll');
        $benefit_id = $this->getUniqueValueFromDB("SELECT benefit_id FROM benefit WHERE benefit_category='civ' AND benefit_type='1'");
        if ($benefit_id == null)
            throw new feException('Invalid alchemist roll');
        $die_roll = $this->rollScienceDie2(reason_civ(1));
        $this->alchemistRollFinish($die_roll);
    }

    function alchemistRollFinish($die_roll) {
        $player_id = $this->getActivePlayerId();
        $benefit_id = $this->getUniqueValueFromDB("SELECT benefit_id FROM benefit WHERE benefit_category='civ' AND benefit_type='1'");
        $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_1\\_%'");
        $bust = false;
        foreach ($token_data as $tid => $token) {
            $slot = explode("_", $token['card_location'])[2];
            if ($slot == $die_roll) {
                $bust = true;
            }
        }
        $token_id = -1;
        if ($bust) {
            $this->notifyWithName('message_error', clienttranslate('${player_name} busts'));
            $this->benefitCashed($benefit_id);
            if ($this->isAdjustments4()) {
                $track = $die_roll;
                $this->queueBenefitInterrupt(["or" => [BE_REGRESS_E - 1 + $track, 401]], $player_id, reason_civ(CIV_ALCHEMISTS)); // Regress with BB
            } else {
                $this->queueBenefitInterrupt(BE_ANYRES, $player_id, reason_civ(CIV_ALCHEMISTS));
            }
            $this->DbQuery("UPDATE structure SET card_location='hand' WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_1\\_%'");
        }
        if (!$bust)
            $token_id = $this->addCivToken($player_id, $die_roll, CIV_ALCHEMISTS);
        $this->notifyAllPlayers("alchemistRoll", '', array(
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(), 'token_id' => $token_id, 'tokens' => $token_data,
            'die' => $die_roll,
        ));
        if ($bust) {
            $this->gamestate->nextState('benefit');
            return true;
        }
        //$this->gamestate->nextState('benefit');
        return false;
    }

    function matFindBenefit($options) {
        foreach ($this->benefit_types as $ben => $info) {
            $allmatched = true;
            foreach ($options as $key => $value) {
                switch ($key) {
                    case 'adv':
                        $current = array_get($info, $key, 1);
                        break;
                    default:
                        $current = array_get($info, $key, null);
                        break;
                }
                if ($current == $value)
                    continue;
                $allmatched = false;
                break;
            }
            if ($allmatched)
                return $ben;
        }
        $this->systemAssertTrue("No matching benefit for " . toJson($options));
    }

    function alchemistClaim() {
        $this->checkAction('alchemistClaim');
        $player_id = $this->getActivePlayerId();
        $benefit_id = $this->getUniqueValueFromDB("SELECT benefit_id FROM benefit WHERE benefit_category='civ' AND benefit_type='1'");
        if ($benefit_id == null)
            throw new feException('Benefit not available');
        $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_1\\_%'");
        $this->userAssertTrue(totranslate('You must Roll at least once'), count($token_data) > 0);
        $this->benefitCashed($benefit_id);
        $this->interruptBenefit();
        $tracks = [];
        foreach ($token_data as $tid => $token) {
            $track = getPart($token['card_location'], 2);
            $tracks[] = $track;
        }
        $reason = reason_civ(CIV_ALCHEMISTS);
        if ($this->isAdjustments4()) {
            $withben = [];
            foreach ($tracks as $track) {
                $withben[] = $this->matFindBenefit([
                    "t" => $track, "adv" => 1,
                    'flags' => (FLAG_GAIN_BENFIT | FLAG_PAY_BONUS | FLAG_MAXOUT_BONUS)
                ]);
            }
            $withben[] = 401;
            $this->queueBenefitNormal(['or' => $withben], $player_id, $reason);
        } else {
            foreach ($tracks as $track) {
                $this->queueBenefitNormal(96 + $track, $player_id, $reason); // advance no ben, buf +5 for going over
            }
        }
        $this->DbQuery("UPDATE structure SET card_location='hand' WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_1\\_%'");
        $this->notifyAllPlayers("alchemistRoll", '', array(
            'player_id' => $player_id, 'token_id' => -1,
            'tokens' => $token_data, 'die' => 1,
        ));
        $this->gamestate->nextState('benefit');
    }

    function checkTrack($track) {
        $this->systemAssertTrue("invalid track $track", $track > 0 && $track <= 4);
    }

    function checkSpot($spot) {
        $this->systemAssertTrue("invalid spot $spot", $spot >= 0 && $spot <= 12);
    }

    function checkTrackSpot($track, $spot) {
        $this->checkTrack($track);
        $this->checkSpot($spot);
    }

    function mystic($ids) {
        $this->checkAction('mystic');
        $player_id = $this->getActivePlayerId();
        // VALIDITY CHECKS
        $this->systemAssertTrue("user does not have mystics", $this->hasCiv($player_id, CIV_MYSTICS));
        $era = $this->getCurrentEra($player_id);
        if ($era <= 1)
            $this->userAssertTrue(totranslate('You must choose a value from each row'), count($ids) == 4);
        else
            $this->userAssertTrue(totranslate('Make a number of predictions equal to number of remaining eras'), count($ids) + $era == 5);
        $rows = array();
        $this->clearCurrentBenefit();
        foreach ($ids as $id) {
            $index = floor(($id - 1) / 9);
            if (($index > 3) || (array_key_exists((int) $index, $rows))) {
                $this->userAssertTrue(totranslate('You must choose a value from each row'));
            }
            $pos = (($id - 1) % 9) + 1;
            $rows[$index] = $pos;
            $tid = $this->addCube($player_id, "civ_13_$id", CUBE_CIV, 0);
            $this->notifyMoveStructure('', $tid, [], $player_id);
            $this->checkMysticPrediction($index + 1, $player_id);
        }
        $this->gamestate->nextState('next');
    }

    function action_selectCube($cube_id) {
        $this->checkAction('select_cube');
        $player_id = $this->getActivePlayerId();
        $current_benefit = $this->getCurrentBenefit();
        $cube = $cube_data = $this->getStructureInfoById($cube_id);
        $this->systemAssertTrue("invalid cube $cube_id", $cube_data);
        // Get location
        $location = $cube_data['card_location'];
        $owner = $cube_data['card_location_arg'];
        $coords = explode("_", $location);
        $track = $coords[2];
        $spot = $coords[3];
        $ben = (int) $current_benefit['benefit_type'];
        $selectrule = $this->getRulesBenefit($ben, 's');
        $trackrule = $this->getRulesBenefit($ben, 't');
        if ($selectrule == null && $trackrule) {
            // we play this to disambiguate track selector
            //$btext = (json_encode($current_benefit, JSON_PRETTY_PRINT));
            //$this->debugConsole("track choice $btext $player_id => $track $spot");
            $this->systemAssertTrue("unknown benefit " . $current_benefit['benefit_category'], $current_benefit['benefit_category'] == 'standard');
            $this->setGameStateValue('cube_choice', $spot);
            $complete = $this->awardBenefits($current_benefit['benefit_player_id'], $current_benefit['benefit_type'], $current_benefit['benefit_quantity'], $current_benefit['benefit_data']);
            if (!$complete)
                return;
            $this->benefitCashed($current_benefit['benefit_id']);
            $this->gamestate->nextState('next');
            return;
        }
        if ($ben == 122) {
            $owner = $cube_data['card_location_arg'];
            $this->formAlliance($owner);
            return;
        }
        $this->systemAssertTrue("Unexpected track choice action for $ben", $selectrule);
        $this->benefitCashed($current_benefit['benefit_id']);
        $reason = $current_benefit['benefit_data'];
        $this->interruptBenefit();
        $flags = $this->getRulesBenefit($ben, 'sflags');
        if (!($flags & FLAG_VIRTUAL_ALLOWED)) {
            $this->userAssertTrue(totranslate('Cannot select virtual cube'), $cube['card_type_arg'] != CUBE_AI);
        }
        switch ($ben) {
            case 106: // DARK AGES
                // WHEN PLAYED: Regress once on 3 difference advancement tracks if possible,
                // then advance three times on the remaining track. Do not gain any benefits or bonuses.
                $this->userAssertTrue(totranslate('Can only select your own cube'), $owner == $player_id);
                $reason = reason_tapestry(TAP_DARK_AGES);
                for ($x = 1; $x <= 4; $x++) {
                    if ($track == $x)
                        continue;
                    $this->queueBenefitNormal(130 + $x, $player_id, $reason); // regress without benefit
                }
                // Advance the selected cube..
                $this->trackMovementProper($track, $spot, ACTION_ADVANCE, 0, true, $player_id);
                for ($i = $spot + 1; $i <= $spot + 2 && $i <= 12; $i++) {
                    if ($this->trackMovementProper($track, $i, ACTION_ADVANCE, 0, false, $player_id)) {
                        continue;
                    }
                    break;
                }
                $this->setGameStateValue('cube_choice', -1);
                break;
            case 118: // MARRIAGE OF STATE
                // THIS ERA: Choose a track and an opponent.
                // After they gain any benefit on that track, you gain it too (do not gain the bonus)
                $track = explode("_", $location)[2];
                $data = $owner . "_" . $track;
                $tap_data = $this->isTapestryActive($player_id, TAP_MARRIAGE_OF_STATE);
                $this->systemAssertTrue('Player does not have marriage of state', $tap_data);
                $this->userAssertTrue(totranslate('Cannot select your own cube'), $owner != $player_id);
                $card_id = $tap_data['card_id'];
                $this->DbQuery("UPDATE card SET card_location_arg2='$data' WHERE card_id='$card_id'");
                $args = $this->notifArgsAddCardInfo($card_id);
                $args = $this->notifArgsAddTrackSpot($track, null, $args);
                $era = $this->getCurrentEra($player_id);
                $args += [
                    'opp_name' => $this->getPlayerNameById($owner),
                    'destination' => "tapestry_slot_${player_id}_$era"
                ];
                $this->notifyWithName("tapestrycard", clienttranslate('${player_name} selects to benefit from ${opp_name} advancing their ${track_name} (${card_name})'), $args, $player_id);
                break;
            case 124: //  TRADE ECONOMY:
                //  Choose the current position of an opponent on any advancement track.
                //  You gain the corresponding benefit.
                //  If there is a bonus, that opponent gains it for free.
                // Check cube belongs to opponent
                $owner = $cube_data['card_location_arg'];
                $this->userAssertTrue(totranslate('Cannot select your own cube'), $owner != $player_id);
                $this->userAssertTrue(totranslate('Cannot repeat virtual AI Singularity'), $cube_data['card_type_arg'] != CUBE_AI);
                $reason = reason_tapestry(TAP_TRADE_ECONOMY);
                $this->notif()->withPlayer2($owner)->withTrackSpot($track, $spot)->withReason($reason)-> //
                    notifyAll(clienttranslate('${player_name} selects ${player_name2} on ${spot_name} ${reason}'));
                // You gain the benefit
                $this->assertCanUseBenefitOnTrackSpot($player_id, $track, $spot);
                $this->processSpotBenefits($track, $spot, $player_id, FLAG_GAIN_BENFIT, $reason);
                // If bonus, opponent gets for free
                if (array_key_exists('option', $this->tech_track_data[$track][$spot])) {
                    $this->processSpotBenefits($track, $spot, $owner, FLAG_FREE_BONUS, $reason);
                }
                break;
            case 190:
                // repeat neighbour's benefit
                $owner = $cube_data['card_location_arg'];
                $neight = $this->getPlayerNeighbours($player_id, false);
                $this->userAssertTrue(totranslate('Cannot select your own cube'), $owner != $player_id);
                $this->userAssertTrue(totranslate('Can only select a neighbour'), in_array($owner, $neight));
                $this->notif()->withPlayer2($owner)->withTrackSpot($track, $spot)->withReason($reason) /* */
                    ->notifyAll(clienttranslate('${player_name} selects ${player_name2} on ${spot_name} ${reason}'));
                // You gain the benefit
                $this->assertCanUseBenefitOnTrackSpot($player_id, $track, $spot);
                $this->processSpotBenefits($track, $spot, $player_id, FLAG_GAIN_BENFIT, $reason);
                $this->setTargetPlayer($owner);
                $this->queueBenefitNormal(["or" => [194, 401]], $player_id, $reason);
                break;
            case BE_TINKERERS_1: // 195
                //  Gain resources equal to the cost of advancing into its current position
                $this->userAssertTrue(totranslate('Can only select your own cube'), $owner == $player_id);
                $this->notif()->withTrackSpot($track, $spot)->withReason($reason) /* */
                    ->notifyAll(clienttranslate('${player_name} selects ${spot_name} ${reason}'));
                // You gain the benefit
                $cost = $this->tech_track_data[$track][$spot]['cost'];
                $this->queueBenefitNormal($cost, $player_id, reason('be', $ben));
                break;
            case 108: // DICTATORSHIP:  Advance on any track and gain the benefit (you may pay to gain the bonus). Opponents may not advance on that track until after your next turn.
                $this->trackMovementProper($track, $spot, ACTION_ADVANCE, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS, true, $player_id);
                $turn = $this->getPlayerTurn($player_id);
                $data = "dic_${turn}_${track}";
                $this->DbQuery("UPDATE structure SET card_location_arg2='$data' WHERE card_id='$cube_id'");
                $this->notifyMoveStructure('', $cube_id, [], $player_id);
                break;
            case 115: // OIL MAGNATE
                $this->queueBenefitNormal(BE_ADVANCE_EXPLORATION_BENEFIT_OPT + $track - 1, $player_id, reason_tapestry(28));
                $neighbours = $this->getPlayerNeighbours($player_id, false);
                foreach ($neighbours as $neighbour) {
                    if ($this->isRealPlayer($neighbour))
                        $this->queueBenefitNormal(BE_ADVANCE_EXPLORATION_NOBENEFIT_OPT + $track - 1, $neighbour, reason_tapestry(28));
                }
                break;
            case 120: // SOCIALISM
                $start = $this->checkClosestOpponent($player_id, $track, $spot, FLAG_ADVANCE | FLAG_REGRESS);
                $change = $spot - $start;
                $this->trackMovementProper($track, $start, $change, 0, true, $player_id);
                break;
            case BE_TINKERERS_2: // 196
                $start = $this->checkClosestOpponent($player_id, $track, $spot, FLAG_ADVANCE);
                $change = $spot - $start;
                $this->trackMovementProper($track, $start, $change, FLAG_GAIN_BENFIT, true, $player_id);
                break;
            case BE_TINKERERS_2a: // 322
                $start = $this->checkClosestOpponent($player_id, $track, $spot, FLAG_ADVANCE);
                $change = $spot - $start;
                $this->trackMovementProper($track, $start, $change, 0, true, $player_id);
                break;
            case BE_TINKERERS_3: // 197
                $this->checkSpot($spot - 3);
                $this->trackMovementProper($track, $spot, -3, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS, true, $player_id);
                break;
            case BE_TINKERERS_3a: // 323
                $this->checkSpot($spot - 3);
                $this->trackMovementProper($track, $spot, -3, FLAG_GAIN_BENFIT, true, $player_id);
                break;
            case BE_TINKERERS_4:
                //Advance to the next bonus on any track and gain that bonus for free. Do not gain the benefit or any landmarks you pass
                $this->userAssertTrue(totranslate('Cannot select AI Singularity'), $cube_data['card_type_arg'] != CUBE_AI);
                $cubesmap = $this->getClosestBonus($player_id, $track, $cube_id);
                $this->userAssertTrue(totranslate('Cannot determine bonus for this selection'), (count($cubesmap) == 1));
                $this->userAssertTrue(totranslate('Cannot determine bonus for this selection'), getPart(array_key_first($cubesmap), 1) == $cube_id);
                $change = getPart($cubesmap["cube_$cube_id"], 1) - $spot;
                $this->trackMovementProper($track, $spot, $change, FLAG_NO_BENEFIT | FLAG_FREE_BONUS | FLAG_JUMP, true, $player_id);
                break;
            case 62: // repeater.
                // Make sure player has token on that spot then repeat the bonus!
                $this->userAssertTrue(totranslate('Cannot repeat virtual AI Singularity'), $cube['card_type_arg'] != CUBE_AI);
                $this->assertCanUseBenefitOnTrackSpot($player_id, $track, $spot);
                $this->processSpotBenefits($track, $spot, $player_id, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS, $current_benefit['benefit_data']);
                break;
            default:
                throw new feException("Invalid benefit type for select_cube $ben");
        }
        $this->gamestate->nextState('next');
    }

    function getTrackPositions($cubes) {
        $spots = [];
        foreach ($cubes as $cube) {
            $loc = getPart($cube['card_location'], 3);
            $spots[] = $loc;
        }
        sort($spots);
        return $spots;
    }

    function getCubesSpecific($player_id, $sflags, $aflags = FLAG_POSALL, $track = 0, $adv = 0) {
        $res = [];
        $aflags = (int) $aflags;
        $advance = ($aflags & FLAG_ADVANCE) != 0;
        $regress = ($aflags & FLAG_REGRESS) != 0;
        $stay = ($aflags & FLAG_STAY) != 0;
        $self = is_flag_set(FLAG_SELF, $sflags);
        for ($t = 1; $t <= 4; $t++) {
            if ($track != 0 && $track != $t)
                continue;
            $curr_spots = $this->getTrackPositions($this->getCubeInfoWithFlags($player_id, FLAG_SELF, $t));
            $other_cubes = $this->getCubeInfoWithFlags($player_id, $sflags, $t);
            if (count($other_cubes) == 0)
                continue;
            $other_cubes_pos = [];
            foreach ($other_cubes as $cube) {
                $loc = getPart($cube['card_location'], 3);
                $other_cubes_pos[$loc] = $cube;
            }
            if (is_flag_set($aflags, FLAG_POSCLOSEST)) {
                foreach ($curr_spots as $start) {
                    $best_regress = -1;
                    $best_advance = -1;
                    $best_stay = -1;
                    foreach (array_keys($other_cubes_pos) as $spot) {
                        $change = $spot - $start;
                        if ($change < 0) {
                            if ($best_regress == -1 || $best_regress < $spot) {
                                $best_regress = $spot;
                            }
                        } else if ($change > 0) {
                            if ($best_advance == -1 || $best_advance > $spot) {
                                $best_advance = $spot;
                            }
                        } else {
                            $best_stay = $spot;
                        }
                    }
                    if ($advance && $best_advance != -1) {
                        $res[] = $other_cubes_pos[$best_advance];
                    }
                    if ($regress && $best_regress != -1) {
                        $res[] = $other_cubes_pos[$best_regress];
                    }
                    if ($stay && $best_stay != -1) {
                        $res[] = $other_cubes_pos[$best_stay];
                    }
                }
            } else if (is_flag_set($aflags, FLAG_POSEXACT)) {
                foreach ($curr_spots as $start) {
                    $best_advance = $start + $adv;
                    if ($self) {
                        if ($best_advance >= 0 && $best_advance <= 12)
                            $res[] = $other_cubes_pos[$start];
                    } else if (array_key_exists($best_advance, $other_cubes_pos)) {
                        $res[] = $other_cubes_pos[$best_advance];
                    }
                }
            } else {
                $res = array_merge($res, array_values($other_cubes));
            }
        }
        return $res;
    }

    function checkClosestOpponent($player_id, $track, $spot, $flags = FLAG_POSALL) {
        $mask = $this->getTrackLocationLike($track);
        $cubes = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type='7' AND card_type_arg = 0  AND card_location LIKE '$mask'");
        $opp_spots = array();
        $my_spots = array();
        foreach ($cubes as $cube_id => $cube) {
            $loc = explode("_", $cube['card_location'])[3];
            if ($cube['card_location_arg'] == $player_id) {
                $my_spots[] = $loc;
            } else {
                $opp_spots[] = $loc;
            }
        }
        $this->userAssertTrue(totranslate('You must select the cube of nearest opponent'), in_array($spot, $opp_spots));
        $this->userAssertTrue(totranslate('You do not have cubes on this track'), count($my_spots) > 0);
        $flags = (int) $flags;
        $advance = ($flags & FLAG_ADVANCE) != 0;
        $regress = ($flags & FLAG_REGRESS) != 0;
        $stay = ($flags & FLAG_STAY) != 0;
        $invalid = false;
        //$this->debugConsole("vars $advance - $regress - $stay", [ "adv" => $advance,"reg" => $regress,"stay" => $stay ]);
        foreach ($my_spots as $start) {
            $change = $spot - $start;
            $invalid = false;
            if ($change > 0 && $advance) { // Check not an opponent spot in between.
                for ($a = 1; $a < $change; $a++) {
                    if (in_array($start + $a, $opp_spots) || in_array($start + $a, $my_spots)) {
                        $invalid = true;
                        break;
                    }
                }
            } else if ($change < 0 && $regress) {
                for ($a = $change; $a >= 1; $a--) {
                    if (in_array($start + $a, $opp_spots) || in_array($start + $a, $my_spots)) {
                        $invalid = true;
                        break;
                    }
                }
            } else if ($change == 0 && !$stay) {
                $invalid = true;
            } else {
                $invalid = true;
            }
            if ($invalid == false)
                break;
        }
        $this->userAssertTrue(totranslate('You must select the cube of nearest opponent'), !$invalid);
        return $start;
    }

    function action_selectTrackSpot($track, $spot, $cube_id = 0) {
        $this->checkAction('selectTrackSpot');
        $player_id = $this->getActivePlayerId();
        // VALIDITY CHECKS
        // This could be called for 3 different reasons.. need to check current benefit.getUniqueValueFromDB
        $current_benefit = $this->getCurrentBenefit();
        $ben = $current_benefit['benefit_type'];
        $this->checkTrack($track);
        $selectrule = $this->getRulesBenefit($ben, 's');
        $trackrule = $this->getRulesBenefit($ben, 't');
        if ($selectrule == null && $trackrule) {
            // we play this to disambiguate track selector
            //$btext = (json_encode($current_benefit, JSON_PRETTY_PRINT));
            //$this->debugConsole("track choice $btext $player_id => $track $spot");
            if ($spot != 13)
                $this->checkSpot($spot);
            $this->systemAssertTrue("unknown benefit " . $current_benefit['benefit_category'], $current_benefit['benefit_category'] == 'standard');
            $this->setGameStateValue('cube_choice', $spot);
            $complete = $this->awardBenefits($current_benefit['benefit_player_id'], $current_benefit['benefit_type'], $current_benefit['benefit_quantity'], $current_benefit['benefit_data']);
            if (!$complete)
                return;
            $this->benefitCashed($current_benefit['benefit_id']);
            $this->gamestate->nextState('next');
            return;
        }
        $this->checkSpot($spot);
        $this->systemAssertTrue("Unexpected track choice action for $ben", $selectrule);
        if ($selectrule == 'o') {
            // we target opponents not cubes per ce
            $this->userAssertTrue(totranslate('You must select the cube not the spot'));
            return;
        }
        if ($selectrule == 't') {
            // we select the track
            $this->benefitCashed($current_benefit['benefit_id']);
            if ($ben == 55) {
                // AI singlularity new track
                $cubes = $this->dbGetCubesOnTrack($player_id, 4, null, 0);
                $cube_id = array_key_first($cubes);
                if (!$cube_id) {
                    $this->notifyWithName('message_error', clienttranslate('${player_name} does not have cube on technology track'));
                } else {
                    $track_stub = 'tech_spot_' . $track . '_';
                    $new_location = $track_stub . '0';
                    $was = $this->getStructureInfoById($cube_id, false);
                    $was_at_end = $was['card_location'] == 'tech_spot_4_12';
                    $this->DbQuery("UPDATE structure SET card_location='$new_location' WHERE card_id='$cube_id'");
                    $this->notifyMoveStructure(clienttranslate('${player_name} moves their token to the start of the ${track_name} track'), $cube_id, [
                        'track_name' => $this->tech_track_types[$track]['description']
                    ], $player_id);
                    if ($was_at_end) {
                        $structure_id = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type_arg = 1 AND card_location = 'tech_spot_4_12' AND card_location_arg = '$player_id' LIMIT 1");
                        if (!$structure_id) {
                            // place virtual cube
                            $structure_id = $this->addCube($player_id, 'tech_spot_4_12', CUBE_AI);
                            $this->notifyMoveStructure('', $structure_id, [], $player_id);
                        }
                    }
                }
            } else {
                $this->systemAssertTrue("Unexpected track choice action for $ben", $selectrule);
            }
            $this->gamestate->nextState('next');
            return;
        }
        if ($ben == BE_TINKERERS_4) {
            //Advance to the next bonus on any track and gain that bonus for free. Do not gain the benefit or any landmarks you pass
            if (!$cube_id) {
                $cubesmap = $this->getClosestBonus($player_id, $track);
                $this->userAssertTrue(totranslate('Cannot determine bonus for this selection'), (count($cubesmap) > 0));
                foreach ($cubesmap as $cube_iname => $pos) {
                    if ($pos == "${track}_${spot}") {
                        $cube_id = getPart($cube_iname, 1);
                        break;
                    }
                }
                $this->userAssertTrue(totranslate('Cannot determine bonus for this selection'), $cube_id);
            }
        }
        if (!$cube_id) {
            $flags = $this->getRulesBenefit($ben, 'sflags');
            $cube = $this->cubeChoiceForTrackBenefitInterractive($track, $spot, $player_id, $flags);
            $cube_id = $cube['card_id'];
        }
        $this->action_selectCube($cube_id);
    }

    function effect_discardCard($card_id, $player_id = null, $location = 'discard') {
        if (is_array($card_id))
            $cards_ids = $card_id;
        else
            $cards_ids = [$card_id];
        foreach ($cards_ids as $card_id) {
            if (is_array($card_id))
                $card_id = $card_id['card_id'];
            $this->checkNumber($card_id);
            $this->DbQuery("UPDATE card SET card_location='$location',card_location_arg=0,card_location_arg2=0 WHERE card_id='$card_id'");

            $notif = clienttranslate('${player_name} discards ${card_type_name} ${card_name}');
            if ($location == 'limbo') $notif = '';
            $this->notif("discardCard")->withPlayer($player_id)->withCard($card_id)->notifyAll($notif);
        }
        $this->notifyDeckCounters();
    }

    function effect_moveCard($card_id, $player_id = null, $location = 'hand', $location_arg = 0, $location_arg2 = null, $message = null) {
        if (is_array($card_id))
            $cards_ids = $card_id;
        else
            $cards_ids = [$card_id];
        foreach ($cards_ids as $card_id) {
            if (is_array($card_id))
                $card_id = $card_id['card_id'];
            $this->checkNumber($card_id);
            $this->DbQuery("UPDATE card SET card_location='$location',card_location_arg=$location_arg WHERE card_id='$card_id'");
            if ($location_arg2 !== null)
                $this->DbQuery("UPDATE card SET card_location_arg2=$location_arg2 WHERE card_id='$card_id'");
            $args = $this->notifArgsAddCardInfo($card_id);
            if ($location == 'hand' && $player_id != $location_arg)
                $this->notifyWithName("moveCard", clienttranslate('${player_name} gives ${card_name} to ${player_name2}'), $args, $player_id);
            else if ($location == 'discard')
                $this->notifyWithName("discardCard", clienttranslate('${player_name} moves ${card_name}'), $args, $player_id);
            else {
                if ($message == null) $message = clienttranslate('${player_name} gains ${card_name}');
                $this->notifyWithName("moveCard", $message, $args, $player_id);
            }
        }
        $this->notifyDeckCounters();
    }

    function dbSetCardLocation($card_id, $loc = 'hand', $state = null, $message = '', $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $this->effect_moveCard($card_id, $player_id, $loc, $player_id, $state, $message);
    }

    function dbSetStructureLocation($structure_id, $location, $state = null, $message = '', $player_id = null) {
        if ($structure_id === null)
            return;
        $this->DbQuery("UPDATE structure SET card_location='$location' WHERE card_id='$structure_id'");
        if ($state !== null)
            $this->DbQuery("UPDATE structure SET card_location_arg2='$state' WHERE card_id='$structure_id'");
        if (startsWith($location, 'capital_cell')) {
            //$cell = 'capital_cell_' . $player_id . '_' . $x . '_' . $y;
            $structure_data = $this->structures->getCard($structure_id);
            $player_id = getPart($location, 2);
            $cx = getPart($location, 3);
            $cy = getPart($location, 4);
            $type = $structure_data['type'] + 1; // 1 is already a dot on the mat, so increase by 1.
            // NOTE: this won't work for landmarks
            $this->DbQuery("UPDATE capital SET capital_occupied='$type' WHERE player_id='$player_id' AND capital_x='$cx' AND capital_y='$cy'");
        }
        $this->notifyMoveStructure($message, $structure_id, [], $player_id);
    }

    function action_advance($track, $spot, $payment, $choice_order) {
        $this->checkAction('advance');
        $player_id = $this->getActivePlayerId();
        // CHECK TRACK/SPOT
        $cube_spot = $spot - 1;
        $this->checkTrack($track);
        $this->checkSpot($spot - 1);
        // stat
        $era = $this->getCurrentEra($player_id);
        $this->incStat(1, 'turns_era_' . $era, $player_id);
        // CHECK PAYMENT COMBINATION
        $level = 1 + floor(($spot - 1) / 3);
        $base_type = $this->tech_track_types[$track]['resource'];
        $payment_count = count($payment);
        switch ($level) {
            case 1:
                $this->systemAssertTrue("Invalid resource count found $payment_count required $level", $payment_count == $level);
                break;
            case 2:
                $this->systemAssertTrue("Invalid resource count found $payment_count required $level", $payment_count == $level);
                $this->systemAssertTrue("Track resource $base_type not found in payment", in_array($base_type, $payment));
                break;
            case 3:
                $this->systemAssertTrue("Invalid resource count found $payment_count required $level", $payment_count == $level);
                $this->systemAssertTrue("Track resource $base_type not found in payment", in_array($base_type, $payment));
                break;
            case 4:
                $this->systemAssertTrue("Invalid resource count found $payment_count required 2 level $level", $payment_count == 2);
                $this->systemAssertTrue("Track resource $base_type not found in payment 0", $base_type == $payment[0]);
                $this->systemAssertTrue("Track resource $base_type not found in payment 1", $base_type == $payment[1]);
                break;
        }
        $payment_name = [];
        foreach ($payment as $p) {
            $this->dbIncResourceCount($p, '', null, -1, $player_id);
            $payment_name[] = $this->getBenefitName($p);
        }
        $this->notifyWithName("message", clienttranslate('${player_name} pays ${payment_name} for advance'), array(
            'i18n' => array('payment_name'), 'payment_name' => $payment_name, 'payment' => $payment,
        ), $player_id);
        $change = ACTION_ADVANCE;
        $cube = $this->cubeChoiceForTrackSingle($track, $player_id, 0, $cube_spot);
        if (!$cube) {
            if (!$this->isStudio()) {
                throw new BgaUserException($this->_('You may only advance by a single space'));
            }
            $cube = $this->cubeChoiceForTrackSingle($track, $player_id, ACTION_ADVANCE, -2);
            $cube_spot = $cube['spot'];
            $change = $spot - $cube_spot;
            $this->notifyWithName('message', '${player_name} is cheating, can only advance one spot (this is enabled for testing)!!!');
            $this->trackMovementProper($track, $cube_spot, $change, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS | FLAG_JUMP, true, $player_id);
        } else {
            $this->trackMovementProper($track, $cube_spot, $change, FLAG_GAIN_BENFIT | FLAG_PAY_BONUS, true, $player_id);
        }
        $this->gamestate->nextState('advance');
    }

    function action_civDecline($cid) {
        $this->checkAction('civDecline');
        $player_id = $this->getActivePlayerId();
        $this->systemAssertTrue("invalid civilization for decline $cid", $cid > 0);
        $this->notifyWithName("message", clienttranslate('${player_name} declines to use their ${card_name} ability'), [
            'card_name' => $this->civilizations[$cid]['name']
        ], $player_id);
        $benefit_data = $this->getCurrentBenefit($cid, 'civ');
        if ($benefit_data) {
            // its ok to decline even we did not find it, would be just no-op
            $this->benefitCashed($benefit_data);
        }
        if ($cid == CIV_ARCHITECTS) {
            // clean up tokens XXX
            $cubes = $this->structures->getCardsInLocation("civilization_2");
            if (count($cubes) > 0) {
                foreach ($cubes as $key => $value) {
                    $this->dbSetStructureLocation($key, 'hand', null, '', $player_id);
                }
            }
        }
        $this->gamestate->nextState('benefit');
    }

    function action_civTokenAdvance($cid, $spot, $extra) {
        $this->checkAction('civTokenAdvance');
        $player_id = $this->getActivePlayerId();
        // VALIDITY CHECK...
        //benefit_category='civ' AND benefit_type='$cid'
        $current_benefit = $this->getCurrentBenefit($cid, 'civ');
        $this->systemAssertTrue("missing civ benefit for $cid", $current_benefit);
        $civ_args = $this->argCivAbilitySingle($player_id, $cid, $current_benefit);
        $this->benefitCashed($current_benefit);
        $this->notifyWithTokenName('civ', clienttranslate('${player_name} uses ability of ${name}'), $cid, $player_id);
        $this->interruptBenefit();
        $this->saction_civTokenAdvance($player_id, $cid, $spot, $extra, $civ_args);
        $this->gamestate->nextState('benefit');
    }

    function saction_civTokenAdvance($player_id, $cid, $spot, $extra, $civ_args) {
        $condition = $civ_args['benefit_data'];
        $is_midgame = ($condition == 'midgame');
        if ($cid == CIV_ARCHITECTS || $cid == CIV_RENEGADES || $cid == CIV_CRAFTSMEN || $cid == CIV_GAMBLERS) {
            $inst = $this->getCivilizationInstance($cid, true);
            $inst->moveCivCube($player_id, $is_midgame, $spot, $extra);
            return;
        }
        if ($cid == CIV_TRADERS) {
            $this->systemAssertTrue('missing hex for traders', $extra);

            $this->sendTrader($extra, $spot);
            return;
        }
        $slot_data = array_get($this->civilizations[$cid], 'slots', []);
        if ($cid != CIV_INFILTRATORS) {
            $this->systemAssertTrue("slot $spot does not exist for $cid", $spot <= count($slot_data));
        }
        $civ_token_string = "civ_" . $cid . "_" . $spot;
        $token_type = BUILDING_CUBE;
        $income_turn = $this->getCurrentEra($player_id);
        if ($cid == CIV_MILITANTS) {
            $this->systemAssertTrue('cannot use MILITAINT civ now', $is_midgame);
            $token_type = BUILDING_OUTPOST;
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_location = '$civ_token_string'");
            // CHECK SPOT VACANT
            $this->userAssertTrue(totranslate("Outpost already placed on this spot"), !$token_data);
            // it has to be placed at right-most slot, so check the slot to the right - it has to have outpost (or be last)
            if ($spot != 4 && $spot != 8) {
                $next_spot = $spot + 1;
                $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_location = 'civ_${cid}_${next_spot}'");
                $this->userAssertTrue(totranslate("Outpost must be place on the right most spot in each row"), $token_data);
            }
            // get new outpost from hand
            $tid = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_location_arg='$player_id' AND card_type='$token_type' AND card_location='hand' LIMIT 1");
            if ($tid !== null) {
                //$this->benefitCivEntry($cid, $player_id,'midgame');
                $message = clienttranslate('${player_name} places an outpost on their civilization mat (midgame setup)');
                // count all remainig outpots
                $coll = $this->getCollectionFromDB("SELECT card_id FROM structure WHERE card_location_arg='$player_id' AND card_type='5' AND card_location='hand'");
                if (count($coll) > 1) { // 1 is currnet one, we did not place it yet
                    $this->benefitCivEntry($cid, $player_id, 'midgame');
                }
                $this->dbSetStructureLocation($tid, $civ_token_string, $income_turn, $message, $player_id);
            }
            return;
        }
        if ($cid == CIV_LEADERS) {
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_location = '$civ_token_string'");
            // CHECK SPOT VACANT
            $this->userAssertTrue(totranslate("Token already placed on this spot"), $token_data == null);
            $tid = $this->addCube($player_id, 'hand');
            $this->queueBenefitNormal(79 + $spot, $player_id, reason_civ(CIV_LEADERS));
            $message = clienttranslate('${player_name} places a token on their civilization mat');
            $this->dbSetStructureLocation($tid, $civ_token_string, $income_turn, $message, $player_id);
            return;
        }
        if ($cid == CIV_CHOSEN && !$this->isAdjustments4()) {
            // just use effect
            $this->theChosenBenefits();
            return;
        }
        if ($cid == CIV_HERALDS) {
            $this->userAssertTrue(totranslate("To use HERALDS ability click on tapestry card"));
            return;
        }
        if ($cid == CIV_INVENTORS) {
            $this->userAssertTrue(totranslate("To use INVENTORS ability click on tech card"));
            return;
        }
        if ($cid == CIV_MERRYMAKERS && $is_midgame && $this->isAdjustments4or8()) {
            $state = 1;
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_location = '$civ_token_string'");
            $this->userAssertTrue(totranslate("Token already placed on this spot"), $token_data == null);
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='$token_type' AND card_location LIKE 'civ_$cid\\_%' AND card_location_arg2 != $state");
            $x = array_shift($token_data);
            $tid = $x['card_id'];
            $this->userAssertTrue(totranslate("Cannot find a token to place"), $tid);
            $valid = false;
            if (!in_array($spot, [1, 5, 9]))
                $valid = true;
            $this->userAssertTrue(totranslate("Token cannot advance to this spot"), $valid);
            if (count($token_data) == 1)
                $this->benefitCivEntry($cid, $player_id, 'midgame');
            $this->dbSetStructureLocation($tid, $civ_token_string, $state, clienttranslate('${player_name} places a token on their civilization mat'), $player_id);
            return;
        }
        $slots_choice_arr = array_get($civ_args, 'slots_choice', []);
        $slots_choice = array_get($slots_choice_arr, $spot, []);
        if ($cid == CIV_ALIENS) {
            // chose spot
            $benefit = $slots_choice["benefit"];
            $this->checkBenefitFeasibility($benefit, $player_id, true);
            $discard_id = array_get($slots_choice, "discard_id");
            if ($discard_id) {
                $this->effect_discardCard($discard_id, $player_id);
            }
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid));
            return;
        }
        if ($cid == CIV_ADVISORS || $cid == CIV_RECYCLERS || $cid == CIV_ISLANDERS) {
            $benefit = $slots_choice["benefit"];
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid));
            return;
        }
        if ($cid == CIV_TREASURE_HUNTERS) {
            $location = $extra;
            $cube = $this->getStructureInfoSearch(BUILDING_CUBE, null, $civ_token_string);
            $this->effect_placeOnMap($player_id, $cube['card_id'], $location, clienttranslate('${player_name} places a token at ${coord_text}'));


            $coord = getPart($location, 1) . "_" . getPart($location, 2);

            $this->setSelectedMapHex($coord);

            $benefit = $slots_choice["benefit"];
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid));
            return;
        }
        if ($cid == CIV_SPIES || $cid == CIV_TINKERERS) {
            $tid = $this->addCube($player_id, 'hand');
            $this->dbSetStructureLocation($tid, $civ_token_string, $income_turn, clienttranslate('${player_name} places a token on their civilization mat'), $player_id);
            $benefit = $slot_data[$spot]["benefit"];
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid));
            return;
        }
        if ($cid == CIV_INFILTRATORS) {
            $benefit = $slots_choice["benefit"];
            $opp_id = array_get($slots_choice, "player_id", "");
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid, $opp_id));
            return;
        }

        if ($cid == CIV_UTILITARIENS) {
            if (!$is_midgame) {
                $cube = $this->getStructureInfoSearch(BUILDING_CUBE, null, $civ_token_string);
                $this->systemAssertTrue("missing cube", $cube);
                $landmark = getReasonPart($condition, 2);
                $to_spot = $this->getCivSlotWithValue($cid, "lm", $landmark);
                $this->dbSetStructureLocation($cube['card_id'], $to_spot, 0);
            } else {
                $count = count($civ_args['targets']);
                $this->systemAssertTrue("no cubes", $count);
                $cube_id = $civ_args['targets'][0];
                $this->dbSetStructureLocation($cube_id, $civ_token_string, 0);
                if ($count > 1) {
                    $this->benefitCivEntry($cid, $player_id, 'midgame');
                }
            }
            return;
        }
        if ($cid == CIV_MERRYMAKERS) {
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_$cid\\_%' AND card_location_arg2 != $income_turn");
        } else {
            $token_data = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='7' AND card_location LIKE 'civ_$cid\\_%'");
        }
        // CHECK SPOT VALID
        // Where are current tokens on this civ and how do they link to spot?
        $slot_choice = array_get($this->civilizations[$cid], 'slot_choice', '');
        $valid = false;
        if (($cid == CIV_ENTERTAINERS) && $is_midgame) {
            if ($spot != 1)
                $valid = true;
            $tid = array_key_first($token_data);
            $this->userAssertTrue(totranslate("Cannot find a token to place"), $tid);
        } else if ($slot_choice) {
            $valid = true;
        } else {
            foreach ($token_data as $tid => $token) {
                $token_location = explode("_", $token['card_location'])[2];
                $links = array_get($slot_data[$token_location], "link");
                if ($links && in_array($spot, $links)) {
                    $valid = true;
                    break;
                }
            }
        }
        $this->userAssertTrue(totranslate("Token cannot advance to this spottriggered power"), $valid);
        $token_info = $token_data[$tid];
        $state = $token_info['card_location_arg2'];
        if ($cid == CIV_MERRYMAKERS && $this->isAdjustments4or8()) {
            $this->userAssertTrue(totranslate("Cannot move this token, it is already been moved during this era"), $state != $income_turn);
            $cube = $this->getStructureInfoSearch(BUILDING_CUBE, null, $civ_token_string);
            $this->userAssertTrue(totranslate("Token already placed on this spot"), !$cube);
        }
        $this->systemAssertTrue("Token cannot advance to this spot $spot $cid", isset($slot_data[$spot]));
        $benefit = $slot_data[$spot]["benefit"];
        $this->systemAssertTrue("invalid benefot for $cid $spot", is_array($benefit));
        if ($extra == 5 && $cid == CIV_CHOSEN) // 5 VP instead
            $this->queueBenefitNormal([505], $player_id, reason_civ($cid));
        else
            $this->queueBenefitNormal($benefit, $player_id, reason_civ($cid));
        // second token
        if ($cid == CIV_MERRYMAKERS && $this->isAdjustments4or8()) {
            $token_placed = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg='$player_id' AND card_type='$token_type' AND card_location LIKE 'civ_$cid\\_%' AND card_location_arg2 = $income_turn");
            if (count($token_placed) == 0)
                $this->benefitCivEntry($cid, $player_id);
        }
        // UPDATE token
        $this->dbSetStructureLocation($tid, $civ_token_string, $income_turn, clienttranslate('${player_name} advances on their civilization mat'), $player_id);
    }

    function checkBenefitOrder($order, $b, $cat = 'choice') {
        if (is_string($order))
            $order = explode(',', $order);
        if (is_string($b))
            $b = explode(',', $b);
        if ($order && count($order) > 0 && $order[0]) {
            //die("<html><body>order=[".implode(',', $order)."][".implode(",", $b)."]</body></html>");
            $count = count($order);
            $this->systemAssertTrue("invalid order count for $count", $count == count($b));
            foreach ($order as $orb) {
                $this->systemAssertTrue("invalid order number $orb", $orb > 0);
                $key = array_search($orb, $b);
                if ($key === false) {
                    $this->systemAssertTrue("invalid order number $orb " . toJson($b));
                }
                $b[$key] = 0;
            }
            return $order;
        } else {
            return [];
        }
        return $order;
    }

    function debug_q() {
        //$this->queueBenefitNormal([ 'p' => 5,'g' => 142 ],  $this->getActivePlayerId(), reason('str', 'debug'));
        $player_id = $this->getCurrentPlayerId();
        //         $curr = $this->getStructureInfoById(88, false);
        //         $curr ['card_location'] = "land_2_1";
        //         $this->notifyMoveStructure('bzz', $curr, [ ], $player_id);
        //         $this->notifyAllPlayers('simplePause', '', [ 'time' => 1000 ]);
        //         $this->notifyMoveStructure('bzz 2', 88, [ ], $player_id);
        //$builder = new NotifBuilder($this);
        //$this->notif()->send('helo');
        //         // starting civ
        //$type = CARD_CIVILIZATION;
        //$this->debug_insertCard($type, CIV_ADVISORS);
        //$this->debug_awardCard(CARD_CIVILIZATION, CIV_INFILTRATORS, null, true, $player_id);
        //$this->benefitCivEntry(CIV_UTILITARIENS, $player_id, "triggered::2");
        //         $cards = $this->dbPickCardsForLocation(1, $type, 'choice', $player_id);
        //         $this->notifyWithName("newCards", clienttranslate('${player_name} draws'), [ 'card_type' => $type,
        //                 'cards' => $cards ], $player_id);
        // $this->benefitCivEntry(CIV_ARCHITECTS, $this->getActivePlayerId());
        // optional regress
        //         $this->queueBenefitInterrupt(["or"=>[BE_REGRESS_E - 1 + 1,401]], $player_id, reason_civ(CIV_ALCHEMISTS)); 
        //         $this->gamestate->nextState('next');
        //$this->queueBenefitNormal([ 'p' => BE_TAPESTRY,'g' => BE_INVENT,0 => 0 ], $player_id, 'test');
        //return $this->matFindBenefit(["t"=>1,"adv"=>1,'flags'=> (FLAG_GAIN_BENFIT | FLAG_PAY_BONUS | FLAG_MAXOUT_BONUS)]);

        //$this->finalGameScoring($player_id);

        $cards = [];
        $cards[] = array('type' => CARD_CIVILIZATION, 'type_arg' => CIV_FUTURISTS, 'nbr' => 1);
        $this->cards->createCards($cards, 'deck_civ');
        $this->debug_award("FUTURISTS");

        // $this->queueBenefitNormal(BE_CONFIRM, $player_id);
        // $this->benefitCivEntry(CIV_GAMBLERS, $player_id);
        $this->gamestate->jumpToState(18);
    }

    function debug_ben($benefits) {
        $this->queueBenefitInterrupt($benefits, $this->getCurrentPlayerId(), reason("str", "debug"));
        $this->gamestate->nextState('benefit');
    }

    function debug_next() {
        if ($this->getCurrentBenefit())
            $this->gamestate->nextState('next');
    }

    /**
     * Called before benefit is put in queue, some effects may intercept and modify that
     *
     * @return boolean - true - proceed adding it, false - not (effect may pushing something else)
     */
    function effect_onQueueBenefit($ben, $player_id = null, $reason = '', $count = 1) {
        if (!$this->isRealPlayer($player_id)) return true;
        switch ($ben) {
            case BE_TAPESTRY:
                $civ_owner = $this->getCivOwner(CIV_ADVISORS);
                if ($civ_owner && $civ_owner != $player_id && !$this->isIncomeTurn()) {
                    $nei = $this->getPlayerNeighbours($civ_owner, false);
                    if (!in_array($player_id, $nei)) return true;
                    if ($this->getCardCountInHand($civ_owner, CARD_TAPESTRY) == 0) return true;

                    for ($i = 0; $i < $count; $i++) {
                        $this->queueBenefitNormal(['p' => 138, 'g' => [BE_TAPESTRY, 'h' => 603]], $civ_owner, reason(CARD_CIVILIZATION, CIV_ADVISORS, $player_id));
                        $this->benefitSingleEntry('standard', $ben, $player_id, 1, $reason);
                    }
                    return false;
                } else {
                    return true;
                }
            case BE_HOUSE: {
                    $this->triggerPreGainStructure($player_id, BUILDING_HOUSE, $ben);
                    return true;
                }
        }
        $landmark_id = $this->getRulesBenefit($ben, 'lm');
        if ($landmark_id) {
            $this->triggerPreGainStructure($player_id, BUILDING_LANDMARK, $ben);
            return true;
        }
        $rule_type = $this->getRulesBenefit($ben, 'r', 'x');
        if ($rule_type == 'a') {
            $this->triggerPreGainStructure($player_id, BUILDING_OUTPOST, $ben);
            return true;
        }
        return true;
    }

    function queueBenefitStandardOne($ben, $player_id = null, $reason = '', $count = 1) {
        $this->systemAssertTrue("invalid benefit to be queue", is_numeric($ben));
        if ($this->effect_onQueueBenefit($ben, $player_id, $reason, $count))
            $this->benefitSingleEntry('standard', $ben, $player_id, $count, $reason);
    }

    function queueBenefitInterrupt($benefit, $player_id = null, $reason = '', $orderChoice = null) {
        $this->interruptBenefit();
        $this->queueBenefitNormal($benefit, $player_id, $reason, $orderChoice);
    }

    /*
     * Queue up a benefit array.
     * $benefit: the array of benefits to process
     * $after: any relevant prerequisite id
     */
    function queueBenefitNormal($benefit, $player_id = null, $reason = '', $count = 1) {
        if (!$benefit) {
            $this->error("empty benefit provided $reason");
            return;
        }
        $benefit = $this->normalizeBenefitsArray($benefit);
        if (!$player_id)
            $player_id = $this->getActivePlayerId(); // default to the active player
        $vp = 0;

        if (!is_numeric($count)) {
            $count = 1;
        }
        foreach ($benefit as $bid => $ben) {
            if ($ben === 0)
                continue; // skip 0 - no-op
            //$btext = (json_encode([$bid=>$ben], JSON_PRETTY_PRINT));
            //$this->debugConsole("queueB $bid $player_id $reason",['b'=>$ben],true);
            $cat = 'standard';
            if (is_string($bid)) {
                $cat = $bid;
            }
            if ($cat == 'standard') {
                if (is_array($ben)) { // recursive
                    $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                    continue;
                }
                if ($ben == BE_VP) {
                    $vp += $count; // not queued
                } else {
                    $this->queueBenefitStandardOne($ben, $player_id, $reason, $count);
                }
                continue;
            }
            if ($cat == 'p') { // like bonus
                $gain = $benefit['g'];
                if (is_array($gain))
                    $gain = implode(',', $gain);
                $pay = $ben;
                if (is_array($ben))
                    $pay = implode(',', $pay);
                $this->benefitSingleEntry("p,$pay,g,$gain", 0, $player_id, $count, $reason);
                continue;
            }
            if ($cat == 'm') { // standard multiplier
                $gain = $benefit['g'];
                $this->systemAssertTrue("g required for m", $gain);
                $this->systemAssertTrue("m count has to be numeric", is_numeric($ben));
                $this->queueBenefitNormal($gain, $player_id, $reason, $ben * $count);
                continue;
            }
            if ($cat == 'g') {
                continue; // processed with 'p' or 'm'
            }
            if ($cat == 'h') { // hidden group
                $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                continue;
            }
            // OR - one benefit or other
            // CHOICE - AND and can choose order
            $op = $cat == 'choice' ? 'a' : 'o';
            if (is_array($ben)) {
                $ben = implode(',', $ben);
            }
            $this->benefitSingleEntry("$op,$ben", 0, $player_id, 1, $reason);
        }
        if ($vp > 0) {
            $this->awardVP($player_id, $vp, $reason);
        }
    }

    function normalizeBenefitsArray($benefit) {
        if (!$benefit) {
            return [0];
        }
        if (!is_array($benefit))
            return [$benefit];
        return $benefit;
    }

    function checkBenefitFeasibility($benefit, $player_id, $bThrow = false, $pay = false) {
        $benefit = $this->normalizeBenefitsArray($benefit);
        $message = null;
        foreach ($benefit as $bid => $ben) {
            if ($message) {
                if ($bThrow)
                    $this->userAssertTrue($message);
                return $message;
            }
            if ($ben === 0)
                continue; // skip 0 - no-op
            $cat = 'standard';
            if (is_string($bid)) {
                $cat = $bid;
            }
            if ($cat == 'p') {
                $message = $this->checkBenefitFeasibility($ben, $player_id, $bThrow, true);
                continue;
            }
            if ($cat == 'm')
                continue;
            if (is_array($ben)) { // recursive
                $message = $this->checkBenefitFeasibility($ben, $player_id, $bThrow);
                continue;
            }
            if ($pay == false) {
                $type = $this->getRulesBenefit($ben, 'r');
                if ($type == 's') { // explore space
                    $space_tiles = $this->getCardsInHand($player_id, CARD_SPACE);
                    if (count($space_tiles) == 0) {
                        $message = totranslate('Player has not tiles to explore space');
                    }
                    continue;
                }
                if ($type == 'e') { // explore 
                    $space_tiles = $this->getCardsInHand($player_id, CARD_TERRITORY);
                    if (count($space_tiles) == 0) {
                        $message = totranslate('Player has not territoty tiles to explore');
                    }
                }
            } else {
                $actual = $this->getPayResourceCount($ben, $player_id);
                if ($actual == 0) {
                    $message = totranslate('Player cannot pay for this');
                    continue;
                }
            }
        }
        if ($message) {
            if ($bThrow)
                $this->userAssertTrue($message);
            return $message;
        }
    }

    function effect_drawDecisionPair($player_id) {
        #discard and replace decision pair
        $cards = $this->cards->pickCardsForLocation(2, 'decision_pair', 'discard_decision');
        $count = count($cards);
        if ($count > 0)
            $this->notifyWithName("newCards", clienttranslate('${player_name} discards ${count} decision cards'), [
                'count' => $count, 'card_type' => CARD_DECISION, 'cards' => $cards
            ], $player_id);
        $cards = $this->cards->pickCardsForLocation(2, 'deck_decision', 'decision_pair', 0, true);
        $index = 0;
        foreach ($cards as $card_id => $card) {
            $this->DbQuery("UPDATE card SET card_location_arg='$index',card_location_arg2=0 WHERE card_id='$card_id'");
            $index += 1;
        }
        $count = count($cards);
        $this->notifyWithName("newCards", clienttranslate('${player_name} draws ${count} decision cards'), [
            'count' => $count, 'card_type' => CARD_DECISION, 'cards' => $cards
        ], $player_id);
        $this->notifyDeckCounters('deck_decision');
        return $count;
    }

    function effect_automaTakeTurn() {
        if ($this->isSolo()) {
            $player_id = PLAYER_AUTOMA;
            $turn = $this->effect_startOfTurn($player_id);
            if ($turn == 1) {
                $this->effect_automaIncome(1);
                return;
            }
            $this->notifyWithName('message', clienttranslate('${player_name} takes turn ${turn}'), ['turn' => $turn], $player_id);
            $count = $this->effect_drawDecisionPair($player_id);
            if ($count < 2) {
                $this->effect_automaIncome();
                return;
            }
            list($decision_card_num, $tiebreaker_card_num) = $this->getDecisionPair();
            if ($this->getRulesCard(CARD_DECISION, $decision_card_num, 'i') && $this->cards->countCardsInLocation('deck_decision') == 0) {
                $this->effect_automaIncome();
                return;
            }
            $track_tiebreaker = $this->getRulesCard(CARD_DECISION, $tiebreaker_card_num, 'tt');
            $this->interruptBenefit();
            // $this->queueBenefitNormal(BE_CONFIRM,$this->getActivePlayerId(),reason('str','automa turn'));
            $adv = $this->getRulesCard(CARD_DECISION, $decision_card_num, 'at');
            $this->effect_automaAdvance($player_id, $adv, $track_tiebreaker);
            $player_id = PLAYER_SHADOW;
            $turn = $this->effect_startOfTurn($player_id);
            $adv = $this->getRulesCard(CARD_DECISION, $decision_card_num, 'st');
            $this->effect_automaAdvance($player_id, $adv, $track_tiebreaker);
        } else {
            // shadow only
            $player_id = PLAYER_SHADOW;
            $turn = $this->effect_startOfTurn($player_id);
            if ($turn == 1) {
                $this->effect_automaIncome(1);
                return;
            }
            $this->notifyWithName('message', clienttranslate('${player_name} takes turn ${turn}'), ['turn' => $turn], $player_id);
            $count = $this->effect_drawDecisionPair($player_id);
            if ($count < 2) {
                $this->effect_automaIncome();
                return;
            }
            list($decision_card_num, $tiebreaker_card_num) = $this->getDecisionPair();
            $track_tiebreaker = $this->getRulesCard(CARD_DECISION, $tiebreaker_card_num, 'tt');
            $adv = $this->getRulesCard(CARD_DECISION, $decision_card_num, 'st');
            $this->effect_automaAdvance($player_id, $adv, $track_tiebreaker);
        }
    }

    function getFavouriteTrack($player_id) {
        $outposts = $this->structures->getCardsInLocation(['track_fav_1', 'track_fav_2', 'track_fav_3', 'track_fav_4'], $player_id);
        if (count($outposts) == 1) {
            $outpost = array_shift($outposts);
            $track = getPart($outpost['location'], 2);
            return $track;
        }
        // XXX
        $this->debugConsole("cannot determin favourite track", $outposts);
        if ($player_id == PLAYER_AUTOMA)
            return 4;
        return 3;
    }

    function getValidTracksAutoma($player_id, $adv, $track_tiebreaker) {
        $fur = 0;
        $valid = [];
        $landmarks = [];
        $tbs = str_split($track_tiebreaker);
        if ($player_id == PLAYER_SHADOW) {
            $tbs = array_reverse($tbs);
        }
        $land_dist = 12;
        for ($track = 1; $track <= 4; $track++) {
            $slot = $this->getMaxTrackSlot($track, $player_id);
            #$this->debugConsole("max ($player_id) slot on $track => $slot");
            if ($slot >= 12)
                continue;
            $valid[$track] = $slot;
            $landmarks[$track] = 12 - $slot;
            if ($slot > $fur)
                $fur = $slot;
            for ($i = $slot + 1; $i <= 12; $i++) {
                $landmark = $this->getLandmarkFromSlot($track, $slot);
                if (!$landmark)
                    continue;
                $owner = $landmark['card_location_arg'];
                if ($owner == 0) {
                    $dist = $i - $slot;
                    $landmarks[$track] = $dist;
                    break;
                }
            }
            if ($land_dist > $landmarks[$track])
                $land_dist = $landmarks[$track];
        }
        $fur_dist = 12 - $fur;
        foreach ($valid as $tt_track => $slot) {
            if ($adv == 'f' || ($adv == 'l' && $fur_dist <= $land_dist)) {
                if ($slot != $fur) {
                    #$this->debugConsole("removing $adv $fur_dist => $tt_track");
                    unset($valid[$tt_track]);
                }
            }
            if ($adv == 'l' && $fur_dist > $land_dist) {
                if ($landmarks[$tt_track] != $land_dist) {
                    #$this->debugConsole("removing $adv $land_dist => $tt_track");
                    unset($valid[$tt_track]);
                }
            }
        }
        $fav_track = $this->getFavouriteTrack($player_id);
        $track = 0;
        foreach ($tbs as $tt_track) {
            if ($tt_track == 5)
                $tt_track = $fav_track;
            if (array_key_exists($tt_track, $valid)) {
                $track = $tt_track;
                break;
            }
        }
        //$this->debugConsole("adv $adv $track_tiebreaker => $track");
        if ($track == 0)
            $track = $fav_track;
        return $track;
    }

    function effect_automaAdvance($player_id, $adv, $track_tiebreaker) {
        $track = $this->getValidTracksAutoma($player_id, $adv, $track_tiebreaker);
        $spot = $this->spotChoiceForTrack($track, $player_id, +1, SPOT_SELECT);
        $this->notifyWithTrack('message', clienttranslate('${player_name} advances on ${track_name}'), [
            'track' => $track,
        ], $player_id);
        $flags = $player_id == PLAYER_AUTOMA ? FLAG_GAIN_BENFIT : 0;
        $this->interruptBenefit();
        $this->trackMovementProper($track, $spot, +1, $flags, false, $player_id);
    }

    function queueBenefitAutoma($benefit, $player_id, $reason = '', $count = 1) {
        if ($player_id != PLAYER_AUTOMA)
            return;
        if (!$benefit) {
            $this->error("empty benefit provided $reason");
            return;
        }
        if (!is_array($benefit))
            $benefit = [$benefit];
        $gained = [];
        foreach ($benefit as $cat => $ben) {
            if ($cat == 'standard' || is_numeric($cat)) {
                if (array_key_exists($ben, $gained))
                    continue; // only 1 time
                $this->queueBenefitAutomaSingle($ben, $reason, $count);
                $gained[$ben] = 1;
                continue;
            }
            if ($cat == 'or' || $cat == 'or2') {
                $this->systemAssertTrue("expecting array for $cat", is_array($ben));
                $cor = count($ben);
                if ($cor == 2) {
                    // take first
                    $this->queueBenefitAutoma($ben[0], $player_id, $reason, $count);
                } else {
                    $pick = bga_rand(0, count($ben) - 1);
                    $selben = $ben[$pick];
                    $this->queueBenefitAutoma($selben, $player_id, $reason, $count);
                }
                continue;
            }
            if ($cat == 'choice') {
                $this->queueBenefitAutoma($ben, $player_id, $reason, $count);
                continue;
            }
        }
    }

    function queueBenefitAutomaSingle($ben, $reason = '', $count = 1) {
        $player_id = PLAYER_AUTOMA;
        $spotreason = startsWith($reason, ':spot:');
        $track = (int) $this->getRulesBenefit($ben, 't', 0);
        $action = $this->getRulesBenefit($ben, 'r', 'x');
        $recreason = $this->getReasonFullRec($reason, true);
        //$this->queueBenefitNormal(BE_CONFIRM,$this->getActivePlayerId(),$reason,1);
        switch ($action) {
            case 'e': // explore
                $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                break;
            case 'a': // attack
                $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                break;
            case 'i': // tech
                $this->effect_techRefresh($player_id);
                break;
            case 'g':
                if ($ben == BE_TAPESTRY)
                    $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                break;
            case 'r': // research
                $this->notifyWithName('message', clienttranslate('${player_name} reseaches ${reason}'), [
                    'reason' => $recreason
                ], $player_id);
                $track = $this->rollScienceDie($reason, 'science_die', $player_id);
                $spot = $this->spotChoiceForTrack($track, $player_id, +1, SPOT_SELECT);
                $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
                $this->trackMovementProper($track, $spot, +1, $flags, false, $player_id);
                break;
            case 'x': // other
            default:
                if ($ben == 146) {
                    $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                    break;
                }
                if ($track > 0) {
                    if ($track < 5) {
                        $adv = (int) $this->getRulesBenefit($ben, 'adv', 1);
                        $this->notifyWithTrack('message', clienttranslate('${player_name} ${advance_regress} on ${track_name} ${reason}'), [
                            'track' => $track, 'adv' => $adv, 'reason' => $recreason
                        ], $player_id);
                    }
                    $this->queueBenefitNormal($ben, $player_id, $reason, $count);
                    break;
                }
                //$this->debugConsole("atoma ben $ben $reason");
                break;
        }
    }

    function getSoloPlayerId() {
        $all = $this->loadPlayersBasicInfos();
        return array_key_first($all);
    }

    function getMidIslandClosest($player_id, $valid_targets, $map) {
        $mid_island = '0_0';
        $mid = !$this->isHexBlockedForConquer($player_id, $mid_island, $map);
        if ($mid) {
            $goals = [$mid_island];
            $valid_targets = $this->getGoalsClosest($player_id, $valid_targets, $map, $goals);
        }
        return $valid_targets;
    }

    function getGoalsClosest($player_id, $valid_targets, $map, $goals) {
        if (count($goals) == 0)
            return $valid_targets;
        $ranked = [];
        foreach ($valid_targets as $coords) {
            $distmap = $this->floodPath(
                $coords,
                8, //
                fn ($x) => $this->isHexBlockedForConquer($player_id, $x, $map), // blocked
                fn ($x) => $this->getNeighbourHexes($x, $map)
            );
            $dist = -1;
            foreach ($goals as $goal) {
                if (!array_key_exists($goal, $distmap))
                    continue; // unreachable
                $dist1 = $distmap[$goal];
                if ($dist1 < $dist || $dist == -1)
                    $dist = $dist1;
            }
            $a = array_get($ranked, $dist, []);
            $a[] = $coords;
            $ranked[$dist] = $a;
            if ($dist == 0)
                break;
        }
        ksort($ranked, SORT_NUMERIC);
        $shortest = array_key_first($ranked);
        if ($shortest != -1) {
            $valid_targets = array_values($ranked[$shortest]);
        }
        return $valid_targets;
    }

    function getMap($xcoords = null) {
        // build a map
        $map = $this->getMapDataFromDb('map', $xcoords);
        foreach ($map as $coords => $info) {
            $map[$coords]['structures'] = [];
            $map[$coords]['map_owners'] = [];
            $map[$coords]['map_occupants'] = [];
            $map[$coords]['occupancy'] = 0;
        }

        if ($xcoords !== null) {
            $structures = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location = 'land_$xcoords'");
        } else {
            $structures = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'land\\_%'");
        }

        foreach ($structures as $struc) {
            $coords = substr($struc['card_location'], 5); // land_
            $map[$coords]['structures'][] = $struc;
            $map[$coords]['occupancy'] += 1;
            $toppled = $struc['card_type_arg'];
            if ($toppled != 1) {
                $map[$coords]['map_owners'][] = $struc['card_location_arg'];
            }
            $map[$coords]['map_occupants'][] = $struc['card_location_arg'];
        }

        $map[$coords]['map_owners'] = array_values(array_unique($map[$coords]['map_owners']));
        $map[$coords]['map_occupants'] = array_values(array_unique($map[$coords]['map_occupants']));

        return $map;
    }

    function getMapHexData($xcoords, $map = null) {
        if ($map == null) $map = $this->getMap($xcoords);
        return $map[$xcoords];
    }

    function isHexOwner($player_id, $coords, $map = null) {
        if ($map != null) {
            $info = array_get($map, $coords);
            if (!$info) return false;
            $owners = array_get($info, 'map_owners');
            if (!$owners || count($owners) == 0) return false;
            if (array_search($player_id, $owners) !== false) return true;
            return false;
        } else {
            $structures = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg = $player_id AND card_location = 'land_$coords' AND card_type_arg != 1");
            if (count($structures) > 0) return true;
            return false;
        }
    }
    function getControlHexes($player_id, $map = null) {
        if ($map == null) $map = $this->getMap();
        $owned = [];
        foreach ($map as $coords => $info) {
            if ($this->isHexOwner($player_id, $coords, $map)) {
                $owned[$coords] = $info;
            }
        }
        return $owned;
    }

    function automa_getHexTiebreaker($valid_targets, $player_id = PLAYER_AUTOMA) {
        $this->systemAssertTrue('no valid targets', count($valid_targets) > 0);
        //$this->debugConsole('hex tb', [$valid_targets], true);
        $loc = bga_rand(0, count($valid_targets) - 1); // random valid target for now XXX automa
        $coord = $valid_targets[$loc];
        return $coord;
    }

    function effect_automaConquer() {
        $bene = $this->getCurrentBenefit();
        $ben = $bene['benefit_type'];
        $player_id = $bene['benefit_player_id'];
        $opponent_id = $this->getSoloPlayerId();
        $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
        $anywhere = ($flags & FLAG_ANYWHERE) != 0;
        $targets = $this->getConquerTargets(false, $anywhere, $player_id);
        $exp_targets = $this->getExplorationTargets($anywhere, $player_id);
        $be_reason = reason('be', $ben);
        if (count($targets) + count($exp_targets) == 0) {
            $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                'reason' => $this->getReasonFullRec($be_reason)
            ], $player_id);
            return;
        }
        $outpost_id = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='5' AND card_location='hand' AND card_location_arg='$player_id' LIMIT 1");
        if (!$outpost_id) {
            $this->notifyWithName("message_error", clienttranslate('${player_name} has no more outposts, effect is void ${reason}'), [
                'reason' => $this->getReasonFullRec($be_reason)
            ], $player_id);
            return;
        }
        // build a map
        $map = $this->getMap();
        $conquer_empty = false;
        //$this->debugConsole('conq_start', [ $targets,$exp_targets ], true);
        if (count($targets) > 0) {
            //CONQUER OPPONENT
            //Valid territories: All territories you control, which the Automa can legally conquer, are valid.
            $valid_targets = [];
            foreach ($targets as $coord) {
                if ($this->isHexOwner($opponent_id, $coord, $map))
                    $valid_targets[] = $coord;
            }
            //$this->debugConsole('conq_reduce', [$valid_targets], true);
            if (count($valid_targets) > 0) {
                //Tiebreakers
                //1.	If the Automa can still gain the “middle island” achievement, only the valid territories closest
                //to the middle island remain valid.
                $valid_targets = $this->getMidIslandClosest($player_id, $valid_targets, $map);
                //$this->debugConsole('conq_mid', [$valid_targets], true);
                //2.	Use the hex tiebreaker to pick one territory among the valid ones.
                $coord = $this->automa_getHexTiebreaker($valid_targets, $player_id);
            } else {
                $conquer_empty = true;
            }
        } else {
            $conquer_empty = true;
        }
        list($decision_card_num, $tiebreaker_card_num) = $this->getDecisionPair();
        $topple = $this->getRulesCard(CARD_DECISION, $tiebreaker_card_num, 't') == 1;
        if ($conquer_empty) { //count($exp_targets) > 0
            // CONQUER NEUTRAL
            // 1.	All hexes that can legally be conquered or explored by the Automa are valid.
            $all_targets = array_merge($targets, $exp_targets);
            // $this->debugConsole('conq_exp', [$all_targets], true);
            // 2.	Hexes adjacent to territories you control are only valid if a
            // icon is on the tiebreaker card.
            $valid_targets = $all_targets;
            if (!$topple) {
                // remove targets adjacent to territories you control
                foreach ($valid_targets as $coords) {
                    $neighbours = $this->getNeighbourHexes($coords);
                    foreach ($neighbours as $neighbour) {
                        if ($this->isHexOwner($opponent_id, $neighbour, $map)) {
                            if (($key = array_search($coords, $valid_targets)) !== false) {
                                unset($valid_targets[$key]);
                            }
                            break;
                        }
                    }
                }
            }
            if (count($valid_targets) == 0) {
                $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                    'reason' => $this->getReasonFullRec($be_reason)
                ], $player_id);
                return;
            }
            $valid_targets = array_values($valid_targets);
            // If there are no valid hexes, skip the action.
            //             Tiebreakers
            //             1.	If the Automa can still gain the “middle island” achievement, only the valid hexes closest to
            //             the middle island remain valid.
            $valid_targets = $this->getMidIslandClosest($player_id, $valid_targets, $map);
            //$this->debugConsole('conq_mid', [ $valid_targets ], true);
            if (count($valid_targets) > 1) {
                $goals = [];
                $goals2 = [];
                foreach ($map as $coord => $info) {
                    if ($this->isHexOwner($opponent_id, $coord, $map)) {
                        $goals2[] = $coord;
                        if (count($info['structures']) == 1)
                            $goals[] = $coord;
                    }
                }
                if (count($goals) > 0) {
                    //             2.	If you control any territories that have a single token on them, only valid hexes closest to such
                    //             territories remain valid.
                    $valid_targets = $this->getGoalsClosest($player_id, $valid_targets, $map, $goals);
                    //$this->debugConsole('conq_goals_1', [ $valid_targets,$goals ], true);
                } else {
                    //             3.	If you don’t control a territory with a single token on it, only valid hexes closest to any territory
                    //             you control remain valid.
                    $valid_targets = $this->getGoalsClosest($player_id, $valid_targets, $map, $goals2);
                    //$this->debugConsole('conq_goals_2', [$valid_targets, $goals2], true);
                }
            }
            //             4. Use the hex tiebreaker to pick one hex among the valid ones.
            //             Actions
            $coord = $this->automa_getHexTiebreaker($valid_targets, $player_id);
            if (!$coord) {
                $this->notifyWithName('message_error', clienttranslate('no valid targets'));
                return;
            }
            //             1.	If the Automa is conquering an empty hex: Draw a territory tile and place it face-up with a
            //             random orientation on the chosen hex.
            if (array_search($coord, $exp_targets)) {
                $this->notifyWithName('message', clienttranslate('${player_name} conquers an empty territory'), [], PLAYER_AUTOMA);
                $cards = $this->awardCard($player_id, 1, CARD_TERRITORY, false, $be_reason);
                $tile_data = array_shift($cards);
                if ($tile_data != null) // no more tiles if null
                    $this->effect_exploreWithCard($player_id, $tile_data['id'], "land_$coord");
                else {
                    $this->notifyWithName('message_error', clienttranslate('no more territory tiles left'));
                    return;
                }
            }
        }
        //             2.	Place an outpost from the Automa’s supply (of its own color) on the conquered territory.
        $this->effect_conquer($player_id, $coord, [$coord], false, null, $be_reason);
        //             3.	If the     icon is on the tiebreaker card and the conquered terrain isn’t the middle island,
        //             place one of the Shadow Empire’s outposts toppled on the territory. 
        if ($coord != '0_0') {
            if ($topple) {
                $location = "land_$coord";
                $structures = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM structure WHERE (card_location = '$location') ");
                if ($structures >= 2) {
                    return;
                }
                $this->effect_addToppledShadowOutpost($coord);
            }
        }
    }

    function coordText($coord) {
        if (startsWith($coord, 'land_')) {
            $coord = str_replace('land_', '', $coord);
        }
        $c = str_replace('_', ',', $coord);
        return "(${c})";
    }

    function getDecisionPair() {
        $cards = $this->cards->getCardsInLocation('decision_pair');
        $count = count($cards);
        if ($count == 2) {
            $track_card = array_shift($cards);
            $tiebreaker_card = array_shift($cards);
            return [$track_card['type_arg'], $tiebreaker_card['type_arg']];
        } else {
            $random_card = bga_rand(1, 22);
            return [$random_card, $random_card];
        }
    }

    function effect_automaIncome($income_turn = 0) {
        $player_id = PLAYER_AUTOMA;
        $shadow_only = $this->isShadowEmpireOnly();
        if ($shadow_only)
            $player_id = PLAYER_SHADOW;
        if ($income_turn == 0) {
            $this->DbQuery("UPDATE playerextra SET player_income_turns=player_income_turns+1 WHERE player_id='$player_id'");
            $income_turn = $this->getCurrentEra($player_id);
        } else {
            $this->DbQuery("UPDATE playerextra SET player_income_turns=$income_turn WHERE player_id='$player_id'");
        }
        if ($income_turn > 5) {
            return;
        }
        $reason = reason('str', clienttranslate('income'));
        $this->setIncomeTurnPhase(INCOME_CIV, clienttranslate('${player_name} takes income turn ${turn_number}'), $player_id);
        if ($shadow_only) {
            $this->effect_automaChangeFavoriteTrack(PLAYER_SHADOW, 0);
            $this->effect_automaShuffleDecisionDeck(PLAYER_SHADOW);
            return;
        }
        // $this->queueBenefitNormal(BE_CONFIRM, $this->getActivePlayerId(), $reason); // confirm
        $this->queueBenefitNormal(602, $player_id, $reason); // end of income
        $this->interruptBenefit();
        $level = $this->getAutomaLevel();
        if ($income_turn == 1) {
            if ($level > 1)
                $this->awardCard($player_id, 1, CARD_TAPESTRY);
            return;
        }
        // first in era
        if ($income_turn <= 4) {
            $era_string = 'era' . $income_turn;
            $neighbours = $this->getPlayerNeighbours($player_id, false);
            $neighbours_in_era = $this->getUniqueValueFromDB("SELECT COUNT(*) c FROM card WHERE card_type='3' AND card_location='$era_string' AND card_location_arg IN (" . implode(',', $neighbours) . ")");
            if ($neighbours_in_era == 0) { // own tapestry is not played yet
                // If first of neighbours to play, get bonus!
                $this->awardVP($player_id, $income_turn, reason('str', clienttranslate('first to era')));
            }
        }
        for ($i = 0; $i < 9; $i += 1) {
            $this->queueBenefitNormal(160 + $i, $player_id, $reason);
        }
    }

    function effect_automaIncomeStage($player_id, $stage) {
        $reason = reason('str', clienttranslate('income'));
        $level = $this->getAutomaLevel();
        $income_turn = $this->getCurrentEra($player_id);
        //         $this->notifyWithName('message', clienttranslate('${player_name} income stage ${stage}'), [ 
        //                 'stage' => ($stage + 1) ], $player_id);
        switch ($stage) {
            case 0:
                // Change Favorite track - all levels
                $this->effect_automaChangeFavoriteTrack(PLAYER_AUTOMA, 0);
                $this->effect_automaChangeFavoriteTrack(PLAYER_SHADOW, 0);
                break;
            case 1:
                // VP income - all levels
                $this->effect_automaIncomeVP();
                break;
            case 2:
                // Play tapesty - all levels
                if ($income_turn <= 4) {
                    $cards = $this->awardCard($player_id, 1, CARD_TAPESTRY, true, $reason);
                    $card = array_shift($cards);
                    $card_id = $card['id'];
                    //$this->playTapestryCard($card_id);
                    $era = $this->getCurrentEra($player_id);
                    $era_string = 'era' . $era;
                    $this->DbQuery("UPDATE card SET card_location='$era_string',card_location_arg='$player_id' WHERE card_id='$card_id'");
                    $args = $this->notifArgsAddCardInfo($card_id, ['destination' => "tapestry_slot_${player_id}_$era"]);
                    $this->notifyWithName("tapestrycard", clienttranslate('${player_name} plays a tapestry card ${card_name}'), $args, $player_id);
                }
                break;
            case 3:
                // Extra advance - levels 3+
                if ($level >= 3) {
                    list($decision_card_num, $tiebreaker_card_num) = $this->getDecisionPair();
                    $track_tiebreaker = $this->getRulesCard(CARD_DECISION, $tiebreaker_card_num, 'tt');
                    $adv = $this->getRulesCard(CARD_DECISION, $decision_card_num, 'at');
                    $this->effect_automaAdvance($player_id, $adv, $track_tiebreaker);
                }
                break;
            case 4:
                // Add progress cards 2 - all levels
                if ($income_turn <= 4) {
                    $this->effect_automaAddProgressCards(2);
                }
                break;
            case 5:
                // Draw tapestry
                if ($income_turn <= 4 && $level >= 2) {
                    $this->awardCard($player_id, 1, CARD_TAPESTRY);
                }
                break;
            case 6:
                // Civ ability
                if ($level >= 2) {
                    $this->effect_automaCivAbility($income_turn);
                }
                break;
            case 7:
                // Add progress cards 2 - level 3+
                if ($income_turn >= 3 && $income_turn <= 4) {
                    if ($level >= 3) {
                        $this->effect_automaAddProgressCards(2);
                    }
                    if ($level >= 5) {
                        $this->effect_automaAddProgressCards(2);
                    }
                }
                break;
            case 8:
                //Shuffle
                if ($income_turn <= 4) {
                    $this->effect_automaShuffleDecisionDeck();
                }
                break;
            default:
                break;
        }
    }

    function effect_automaChangeFavoriteTrack($player_id, $track) {
        if ($track == 0) {
            $cards = $this->cards->getCardsInLocation('decision_pair');
            $count = count($cards);
            if ($count == 2) {
                array_shift($cards);
                $tiebreaker_card = array_shift($cards);
                $track_tiebreaker = $this->getRulesCard(CARD_DECISION, $tiebreaker_card['type_arg'], 'tt');
            } else {
                $track_tiebreaker = $this->getRulesCard(CARD_DECISION, bga_rand(1, 22), 'tt');
            }
            $track = $this->getValidTracksAutoma($player_id, 'l', $track_tiebreaker);
        }
        $loc = "track_fav_$track";
        $marker_id = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND card_location LIKE 'track_fav_%' AND card_location_arg='$player_id' LIMIT 1");
        if ($marker_id == null)
            $marker_id = $this->addCube($player_id, $loc);
        $this->DbQuery("UPDATE structure SET card_location='$loc' WHERE card_id='$marker_id'");
        $this->notifyWithTrack('message', clienttranslate('${player_name} changes their favorite track to ${track_name}'), [
            'track' => $track
        ], $player_id);
        $this->notifyMoveStructure('', $marker_id, [], $player_id);
    }

    function effect_automaIncomeVP() {
        $reason = reason('str', 'income');
        $player_id = PLAYER_AUTOMA;
        $era = $this->getCurrentEra($player_id);
        $level = $this->getAutomaLevel();
        if ($level <= 4) {
            $table = [[1, 0, 1], [2, 1, 1], [2, 1, 2], [3, 2, 3],];
        } else {
            $table = [[1, 0, 1], [2, 1, 2], [2, 2, 2], [3, 3, 4],];
        }
        list($m1, $m2, $m3) = $table[$era - 2];
        $this->notifyWithName("message", clienttranslate('Automa\'s VP multipliers x${m1} x${m2} x${m3}'), [
            'm1' => $m1, 'm2' => $m2, 'm3' => $m3
        ], $player_id);
        $this->VPconq($player_id, $m1, $reason, BE_VP_TERRITORY);
        $this->VPforLandmarks($player_id, $m1, $reason, 145);
        $this->VPtrack($player_id, TRACK_MILITARY, $m2, $reason);
        $this->VPtrack($player_id, TRACK_SCIENCE, $m2, $reason);
        $this->VPtrack($player_id, TRACK_TECHNOLOGY, $m3, $reason);
        $this->VPtrack($player_id, TRACK_EXPLORATION, $m3, $reason);
    }

    function effect_automaShuffleDecisionDeck($player_id = PLAYER_AUTOMA) {
        $this->cards->pickCardsForLocation(2, 'decision_pair', 'discard_decision');
        $this->cards->moveAllCardsInLocation('discard_decision', 'deck_decision');
        $cards = $this->cards->getCardsInLocation('deck_decision');
        $this->notifyWithName("newCards", clienttranslate('${player_name} reshuffles decision cards'), [
            'count' => count($cards), 'card_type' => CARD_DECISION, 'cards' => $cards
        ], $player_id);
        $this->cards->shuffle('deck_decision');
    }

    function effect_automaCivAbility($income_turn) {
        $reason = reason('str', 'income');
        $player_id = PLAYER_AUTOMA;
        $civ = $this->getGameStateValue('automa_civ');
        $this->interruptBenefit();
        if ($civ == TRACK_MILITARY) {
            if ($income_turn >= 2 && $income_turn <= 5) {
                //Income Turns 2-5: If Automa controls fewer territories than the income turn number, it does a conquer action.
                $territory_count = $this->getNumberOfControlledTerritories($player_id);
                if ($territory_count < $income_turn) {
                    $this->queueBenefitAutoma(21, $player_id, $reason); // conquer
                }
                // If more than 2 territories controlled by the Automa have only 1 token, place a toppled Shadow Empire outpost of one of those.<p>
                $this->queueBenefitAutoma(146, $player_id, $reason);
                // Income Turns 2-5: The Automa gains 1 VP extra for each territory it controls.
                $this->queueBenefitAutoma(29, $player_id, $reason, 1); // vp for controlled territory
            }
        } else if ($civ == TRACK_TECHNOLOGY) {
            if ($income_turn >= 2 && $income_turn <= 5) {
                $this->VPforLandmarks($player_id, 1, $reason, 145);
            }
        } else if ($civ == TRACK_SCIENCE) {
            if ($income_turn >= 3 && $income_turn <= 5) {
                // research
                $this->queueBenefitAutoma(18, $player_id, $reason);
            }
        } else if ($civ == TRACK_EXPLORATION) {
            if ($income_turn == 2 || $income_turn == 4) {
                // explore
                $this->queueBenefitAutoma(46, $player_id, $reason);
            } else if ($income_turn == 3 || $income_turn == 5) {
                // conquer
                $this->queueBenefitAutoma(21, $player_id, $reason);
            }
        }
    }

    function effect_automaToppleShadow($player_id = PLAYER_AUTOMA, $count = 1, $reason = null, $ben = 0) { // 146
        // If more than 2 territories controlled by the Automa have only 1 token, place a toppled Shadow Empire outpost of one of those.<p>
        $map = $this->getMap();
        $goals = [];
        foreach ($map as $coord => $info) {
            if ($this->isHexOwner($player_id, $coord, $map)) {
                if ($info['occupancy'] == 1)
                    $goals[] = $coord;
            }
        }
        if (count($goals) > 2) {
            $coord = $this->automa_getHexTiebreaker($goals);
            $this->effect_addToppledShadowOutpost($coord);
        }
    }

    function effect_addToppledShadowOutpost($coord) {
        $location = "land_$coord";
        $player_id = PLAYER_SHADOW;
        $oid = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='5' AND card_location='hand' AND card_location_arg='$player_id' LIMIT 1");
        if ($oid) {
            //$this->DbQuery("UPDATE structure SET card_type_arg=1 WHERE card_id='$oid'");
            //$this->dbSetStructureLocation($oid, $location, null, '', $player_id);
            //$this->notifyWithName("topple", clienttranslate('${player_name} adds toppled outpost'), [ 'bid' => $oid ], $player_id);
            $this->effect_placeOnMap($player_id, $oid, $location, clienttranslate('${player_name} adds toppled outpost'), false);
        } else {
            $this->notifyWithName('message', clienttranslate('${player_name} does not have more outposts'), [], $player_id);
        }
    }

    function effect_automaAddProgressCards($num) {
        $this->cards->pickCardsForLocation($num, 'deck_progress', 'deck_decision', 0, true);
        $this->notifyWithName('message', clienttranslate('${player_name} adds ${num} cards to decision deck'), [
            'num' => $num
        ], PLAYER_AUTOMA);
        $this->notifyDeckCounters('deck_decision');
    }

    function effect_automaExplore() {
        $bene = $this->getCurrentBenefit();
        $ben = $bene['benefit_type'];
        $player_id = $bene['benefit_player_id'];
        $be_reason = reason('be', $ben);
        $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
        $anywhere = ($flags & FLAG_ANYWHERE) != 0;
        $targets = $this->getExplorationTargets($anywhere, $player_id);
        if (count($targets) == 0) {
            $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                'reason' => $this->getReasonFullRec($be_reason)
            ], $player_id);
            return;
        }
        $loc = bga_rand(0, count($targets) - 1); // random valid target for now XXX automa
        $coord = $targets[$loc];
        $cards = $this->awardCard($player_id, 1, CARD_TERRITORY, false, $be_reason);
        $tile_data = array_shift($cards);
        if ($tile_data != null) // no more tiles if null
            $this->effect_exploreWithCard($player_id, $tile_data['id'], "land_$coord");
    }

    function effect_exploreWithCard($player_id, $tile_id, $location, $rot = -1, $reason = null) {
        if ($rot == -1)
            $rot = bga_rand(0, 5);
        $tile_data = $this->getCardInfoById($tile_id);
        $this->systemAssertTrue("invalid id $tile_id", $tile_data);
        $tile_type = $tile_data['card_type_arg'];
        $this->systemAssertTrue("invalid tile " . toJson($tile_data), $tile_type);
        $u = getPart($location, 1);
        $v = getPart($location, 2);
        $map = getPart($location, 0);
        $coord = $u . '_' . $v;
        if ($map == 'land' || $map == 'map') {
            $this->DbQuery("UPDATE card SET card_location='map', card_location_arg2='$coord', card_location_arg='$rot' WHERE card_id='$tile_id'");
            $mapcell = $this->getObjectListFromDB("SELECT * FROM map WHERE map_coords='$coord'");
            $this->systemAssertTrue("invalid map loc $coord", $mapcell);
            $this->DbQuery("UPDATE map SET map_tile_id='$tile_type', map_tile_orient='$rot' WHERE map_coords='$coord'");
        } else {
            $this->DbQuery("UPDATE card SET card_location='$map', card_location_arg2='$coord', card_location_arg='$rot' WHERE card_id='$tile_id'");
        }
        // UPDATE
        $coord_text = $this->coordText($location);
        $tile_data = $this->getCardInfoById($tile_id);
        $args = $this->notifArgsAddCardInfo($tile_data);
        $this->notifyWithName('explore', clienttranslate('${player_name} explores territory ${card_name} at ${coord_text} ${reason}'), $args + [
            'orient' => $rot, 'coord_text' => $coord_text, 'location' => $location,
            'reason' => $this->getReasonFullRec($reason, false)
        ], $player_id);
    }

    function interruptBenefit() {
        $this->DbQuery("UPDATE benefit SET benefit_prerequisite = benefit_prerequisite + 1");
    }

    function withReasonDataArg($reason, $arg) {
        $data = $reason ?? ":::";
        $split = explode(':', $data, 4);
        //$curr = getReasonPart($bene['benefit_data'], 3);
        for ($i = 0; $i < 4; $i++) {
            if (!isset($split[$i]))
                $split[$i] = "";
        }
        $split[3] = $arg;
        return implode(':', $split);
    }

    function setBenefitDataArg($bene, $arg, $commit = true) {
        if (is_numeric($bene)) {
            $bene = $this->getCurrentBenefit($bene);
            if (!$bene) return null;
        }
        $this->systemAssertTrue("invalid arg for setBenefitDataArg", is_array($bene));
        $beid = $bene['benefit_id'];
        $this->systemAssertTrue("invalid arg for setBenefitDataArg benid=$beid", $beid);

        $ben_data = $bene['benefit_data'] = $this->withReasonDataArg($bene['benefit_data'], $arg);
        if ($commit)
            $this->DbQuery("UPDATE benefit SET benefit_data = '$ben_data' WHERE benefit_id = $beid");
        return $bene;
    }

    function dbGetBenefitQueue() {
        $benefits = $this->getCollectionFromDB("SELECT * FROM benefit ORDER BY benefit_prerequisite, benefit_id");
        $order = 0;
        $inorder = [];
        foreach ($benefits as $bid => &$bene) {
            $ben_type = $bene['benefit_type'];
            $cat = $bene['benefit_category'];
            $bene['reason'] = $this->getReasonFullRec($bene['benefit_data'], false);
            if ($ben_type != 0) {
                if ($cat == 'standard')
                    $bene['name'] = $this->getReasonFullRec(reason('be', $ben_type), false);
                else if ($cat == 'civ')
                    $bene['name'] = $this->getReasonFullRec(reason_civ($ben_type), false);
            }
            $bene['order'] = $order;
            $inorder[] = $bene;
            $order++;
        }
        return $inorder;
    }

    function notifyBenefitQueue() {
        $inorder = $this->dbGetBenefitQueue();
        $this->notifyAllPlayers('benefitQueue', '', $inorder);
    }

    function queueBonus($type, $quantity, $benefit, $after, $player_id) {
        //$this->debugConsole("bene bonus, $type, $after, $player_id, $quantity, $benefit");
        if ($this->isPlayerAlive($player_id)) {
            $this->DbQuery("INSERT INTO benefit (benefit_category, benefit_type, benefit_prerequisite, benefit_quantity, benefit_player_id, benefit_data) VALUES ('bonus', '$type','$after', '$quantity', '$player_id', '$benefit')");
            $this->notifyBenefitQueue();
            return $this->getLastId('benefit');
        } else {
            if ($type > 0) {
                $this->notifyAllPlayers('message', clienttranslate('${player_name} is finished, cannot get the bonus'), [
                    'player_name' => $this->getPlayerNameById($player_id)
                ]);
            }
        }
    }

    function isBenefitVP($ben) {
        if ($ben == 0)
            return false;
        if ($ben > 500 && $ben < 599)
            return true;
        $rule = $this->getRulesBenefit($ben, 'r');
        if ($rule == 'v')
            return true;
        return false;
    }

    function checkAliveForBenefit($player_id, $ben, $cat) {
        if ($this->isPlayerAlive($player_id))
            return true;
        if ($cat == 'standard' && $this->isBenefitVP($ben))
            return true;
        $auto = $this->getRulesBenefit($ben, 'auto');
        if ($auto)
            return true;
        if ($ben > 0) {
            $bename = $this->getTokenName('be', $ben);
            $this->notifyAllPlayers('message', clienttranslate('${player_name} is finished, cannot get ${bename}'), [
                'i18n' => ['bename'], 'bename' => $bename, 'player_name' => $this->getPlayerNameById($player_id)
            ]);
        }
        return false;
    }

    /*
     * Add an entry to the benefit table.
     * $cat: category (or, choice, standard)
     * $type: type
     * $after: prerequisite (other benefit id)
     * $quantity: quantity/value
     */
    function benefitSingleEntry($cat, $type, $player_id, $quantity = 1, $data = '') {
        // $this->debugConsole("bene $cat, $type, $after, $player_id, $quantity, $data");
        $this->systemAssertTrue("data is array", !is_array($data));
        $this->systemAssertTrue("ben is array", !is_array($type));
        $this->systemAssertTrue("playerid is array", !is_array($player_id));
        $this->DbQuery("INSERT INTO benefit (benefit_category, benefit_type, benefit_prerequisite, benefit_quantity, benefit_data, benefit_player_id) VALUES ('$cat', '$type', '0', '$quantity','$data','$player_id')");
        $this->notifyBenefitQueue();
    }

    function benefitSingleEntryReinsert($bene) {
        $this->benefitSingleEntry($bene['benefit_category'],  $bene['benefit_type'], $bene['benefit_player_id'], $bene['benefit_quantity'], $bene['benefit_data']);
    }

    function benefitCivEntry($cid, $player_id, $data = '') {
        if ($data == '')
            $data = reason_civ($cid);
        $this->benefitSingleEntry('civ', $cid, $player_id, 1, $data);
    }

    function stInvent() {
        //$player_id = $this->getActivePlayerId();
        $current_benefit = $this->getCurrentBenefit();
        $this->systemAssertTrue("Cannot find benefit on stack", $current_benefit);
        $ben = $current_benefit['benefit_type'];
        $i = $this->getRulesBenefit($ben, 'r');
        //$flags = $this->getRulesBenefit($ben);
        $this->systemAssertTrue("Invalid invent benefit", $i == 'i');
        $discards = $this->cards->getCardsInLocation('deck_tech_vis');
        $void = false;
        if (count($discards) == 0) {
            $void = true;
        }
        if ($void) {
            // no more cards
            $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                'reason' => $this->getReasonFullRec(reason('be', $this->getCurrentBenefitType()))
            ]);
            $this->clearCurrentBenefit();
            $this->gamestate->nextState('next');
            return false;
        }
        return true;
    }

    function action_invent($card_type) {
        $this->checkAction('invent');
        $player_id = $this->getActivePlayerId();
        $hasRecycles = $this->hasCiv($player_id, CIV_RECYCLERS);
        if ($card_type == -1 && $hasRecycles) {
            self::DbQuery("UPDATE card SET card_location='draw' WHERE card_type=4 AND card_location='discard'");
            $cards = $this->getCardsSearch(null, null, 'draw');
            $this->notifyWithName("moveCard", '', ['cards' => $cards, '_private' => true,], $player_id);

            $this->gamestate->nextState('next');
            return;
        }
        $current_benefit = $this->getCurrentBenefit();
        $this->systemAssertTrue("Cannot find benefit on stack", $current_benefit);
        $card_id = 0;
        $this->clearCurrentBenefit();
        $ben = $current_benefit['benefit_type'];
        $i = $this->getRulesBenefit($ben, 'r');
        $flags = $this->getRulesBenefit($ben);
        $this->systemAssertTrue("Invalid invent benefit", $i == 'i');
        if ($card_type > 0) {
            $card_data = $this->getCardInfoSearch(CARD_TECHNOLOGY, $card_type);
            $this->systemAssertTrue("invalid card selected $card_type", $card_data);
            $location = $card_data['card_location'];
            $message = totranslate("This does not seems to be tech card from the face up display");

            $card_id = $card_data['card_id'];
            $this->dbSetCardLocation($card_id, 'hand', 0, '', $player_id);

            if ($location == 'draw') {
                $this->userAssertTrue($message, $hasRecycles);
            } else if ($location == 'deck_tech_vis') {
                $this->drawTechCards(1); // replenish
            } else {
                $this->userAssertTrue($message);
            }
        } else {
            $this->userAssertTrue(totranslate("You may only invent a face up card at this time"), $flags & FLAG_FACE_DOWN);
            $cards = $this->dbPickCardsForLocation(1, CARD_TECHNOLOGY, 'hand', $player_id);
            if (count($cards) > 0) {
                $new_card = array_shift($cards);
                $card_id = $new_card['id'];
                $card_type = $new_card['type_arg'];
                $this->dbSetCardLocation($card_id, 'hand', 0, '', $player_id);
            } else {
                $card_id = 0; // void effect
            }
        }
        if ($hasRecycles) {
            // discard cards recyclers looked at 
            $cards = $this->getCardsSearch(null, null, 'draw');
            if (count($cards) > 0) {
                self::DbQuery("UPDATE card SET card_location='discard' WHERE card_type=4 AND card_location='draw'");
                $cards = $this->getCardsSearch(CARD_TECHNOLOGY, null, 'discard');
                $this->notifyWithName("moveCard", '', ['cards' => $cards, '_private' => true,], $player_id);
            }
        }
        if ($card_id) {


            if ($flags & FLAG_UPGRADE) { // invent and upgrade!
                // XXX when invent and upgrade cannot do delayed resolve
                $this->effect_cardComesInPlayTriggerResolve($card_id, $player_id,  $current_benefit['benefit_data']);
                $this->upgradeTechCard($card_id);
            } else {
                $this->effect_cardComesInPlay($card_id, $player_id,  $current_benefit['benefit_data']);
            }
        }
        $this->notifyDeckCounters();
        $this->gamestate->nextState('next');
    }

    function drawTechCards($count, $refresh = false) {
        $discards = [];
        if ($refresh) {
            $discards = $this->cards->getCardsInLocation('deck_tech_vis');
            foreach ($discards as $card) {
                $this->cards->playCard($card['id']);
            }
        }
        $this->notifyAllPlayers("newTechCards", '', array('cards' => [], 'discards' => $discards));
        $this->notifyAllPlayers('simplePause', '', ['time' => 500 * count($discards)]);
        if ($count > 0) {
            $cards = $this->dbPickCardsForLocation($count, CARD_TECHNOLOGY, 'deck_tech_vis', 0);
            $this->notifyAllPlayers("newTechCards", '', array('cards' => $cards, 'discards' => []));
        }
        $this->notifyDeckCounters('deck_tech');
    }

    function action_upgrade($card_type) {
        $this->checkAction('upgrade');
        $player_id = $this->getActivePlayerId();
        $card_data = $this->getCardInfoSearch(CARD_TECHNOLOGY, $card_type, 'hand', $player_id, null);
        $this->userAssertTrue(totranslate("Technology card must be in player's hand"), $card_data);
        $this->userAssertTrue(totranslate('Prerequisite for this upgrade is not met'), $this->isUpgradePrereqMet($card_type));
        $b = $this->getCurrentBenefit();
        $this->systemAssertTrue("upgrade ben", $b);
        $type = $b['benefit_type'];
        $this->systemAssertTrue("upgrade type $type", $type == BE_UPGRADE_TECH || $type == BE_UPGRADE_TECH_INCOME);
        $this->clearCurrentBenefit();
        $this->upgradeTechCard($card_data['card_id']);
        $this->gamestate->nextState('benefit');
    }

    function isUpgradePrereqMet($card_type) {
        $card_data = $this->getCardInfoSearch(CARD_TECHNOLOGY, $card_type, 'hand', null, null);
        if (!$card_data)
            return false;
        $slot = $card_data['card_location_arg2'];
        if ($slot == 1) { // upgrading to 2
            $checkupgrade = true;
            $player_id = $card_data['card_location_arg'];
            if ($this->isTapestryActive($player_id, 20)) { // CAN SKIP IF GOLDEN AGE
                $checkupgrade = false;
            }
            $card = "tech_card_$card_type";
            $inventor = $this->getStructureInfoSearch(BUILDING_CUBE, CUBE_CIV, $card, null, reason_civ(CIV_INVENTORS));
            if ($inventor) {
                $checkupgrade = false;
            }
            if ($checkupgrade) {
                // Need to check if any of neighbours have reached the correct track/level...
                $pre = $this->tech_card_data[$card_type]['requirement'];
                $track = $pre['track'];
                $neighbours = $this->getPlayerNeighbours($player_id);
                if ($track <= 4) {
                    foreach ($neighbours as $p) {
                        $max = $this->getMaxTrackSlot($track, $p);
                        if ($max >= $pre['level']) {
                            return true;
                        }
                    }
                    return false;
                }
                $track_field = $this->getIncomeTrackDbColumn($track - 4);
                $highest = $this->getUniqueValueFromDB("SELECT MAX($track_field) FROM playerextra WHERE player_id IN (" . implode(',', $neighbours) . ")");
                if ($highest < $pre['level']) {
                    return false;
                }
            }
            return true;
        } else if ($slot == 2) {
            return $this->hasCiv($card_data['card_location_arg'], CIV_RECYCLERS);
        }
        return true;
    }

    function upgradeTechCard($card_id, $inventors = false) {
        $card_data = $this->getObjectFromDB("SELECT * FROM card WHERE card_id='$card_id'");
        $player_id = $card_data['card_location_arg'];
        $slot = $card_data['card_location_arg2'] + 1;
        $recyclers = $this->hasCiv($player_id, CIV_RECYCLERS);
        if ($slot == 3) {
            if (!$recyclers)
                throw new BgaUserException($this->_('Cards from the upper row cannot be upgraded'));
            $slot = 0;
        }
        $card_type = $card_data['card_type_arg'];
        $this->DbQuery("UPDATE card SET card_location_arg2 = '$slot' WHERE card_id='$card_id'");
        $active_player = $this->getActivePlayerId();
        // may not be owner of the card (if inventors used!)
        $message = ($active_player == $player_id) ? clienttranslate('${player_name} upgrades ${card_name}') : clienttranslate('${player_name} upgrades ${card_name} for ${player_name2}');
        $args = $this->notifArgsAddCardInfo($card_id, [
            'player_id2' => $player_id,
            'player_name2' => $this->getPlayerNameById($player_id)
        ]);
        $this->notifyWithName("moveCard", $message, $args, $active_player);
        $benefit = ($slot == 1) ? $this->tech_card_data[$card_type]['circle']['benefit'] : $this->tech_card_data[$card_type]['square']['benefit'];
        $this->interruptBenefit(); // this will go on top of everything
        $this->queueTechBenefit($card_type, $slot, $player_id);
        // IS THIS UPGRADE PART OF ALLIANCE - IF SO ALLY GETS BENEFIT
        $alliances = $this->getActiveTapestriesOfType(5);
        foreach ($alliances as $alliance) {
            $ally = $alliance['card_location_arg2'];
            $orig = $alliance['card_location_arg'];
            if (($ally == $active_player) || ($ally == $player_id)) {
                $this->queueBenefitNormal($benefit, $orig, reason_tapestry(5));
            }
        }
        if (!$inventors) {
            // GOLDEN AGE BONUS - DOESN'T APPLY IF INVENTORS HAVE DONE THE UPGRADE FOR YOU.
            if ($this->isTapestryActive($player_id, 20)) { // GOLDEN AGE
                $this->awardVP($player_id, 3, reason_tapestry(20));
            }
            if ($this->hasCiv($player_id, CIV_UTILITARIENS) && $slot == 2) {
                //UTILITARIENS Forge: Whenever upgrade a tech card to the top row, also gain [ANY RESOURCE]
                if ($this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_1", $player_id)) {
                    $this->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ(CIV_UTILITARIENS));
                }
            }
        } else {
            // INVENTORS
            if ($player_id != $active_player) { // opponent gets benefit
                $this->queueTechBenefit($card_type, $slot, $active_player);
            }
        }
        // INVENTOR TOKEN CHECK - CARDS ON 2ND ROW TRANSFER TO INVENTOR
        if ($slot == 2) {
            $cube = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='tech_card_$card_type' LIMIT 1");
            if ($cube != null) {
                $owner = $cube['card_location_arg'];
                if ($owner != $player_id) {
                    $this->queueBenefitNormal(130, $owner, reason_civ(CIV_INVENTORS));
                }
            }
        }
        if ($recyclers && $slot == 0) {
            $this->awardVP($player_id, 5, reason_civ(CIV_RECYCLERS));
        }
        //  check achievemtns
        $this->checkPrivateAchievement(4, $player_id);
        return $slot;
    }

    function effect_transferTech() {
        $player_id = $this->getActivePlayerId();
        $cubes = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type=7 AND card_type_arg=2 AND card_location_arg=$player_id AND card_location LIKE 'tech_card_%'");
        $found = false;
        foreach ($cubes as $cube) {
            $card = $cube['card_location'];
            $card_type = getPart($card, 2);
            $card_data = $this->getCardInfoSearch(CARD_TECHNOLOGY, $card_type, 'hand', null, null);
            if ($card_data && $card_data['card_location_arg2'] == 2) {
                $card_id = $card_data['card_id'];
                $owner = $card_data['card_location_arg'];
                if ($owner != $player_id) {
                    $this->DbQuery("UPDATE card SET card_location_arg='$player_id', card_location_arg2='0' WHERE card_id='$card_id'");
                    $this->notifyWithName("techtransfer", clienttranslate('${player_name} takes ${card_name} from ${player_name2}'), array(
                        'card_id' => $card_type, 'card_name' => $this->tech_card_data[$card_type]['name'],
                        'i18n' => array('card_name'), 'player_id2' => $owner,
                        'player_name2' => $this->getPlayerNameById($owner), 'slot' => 0
                    ));
                    $found = true;
                }
            }
        }
        if (!$found) {
            $this->notifyWithName('message_error', 'Inventor cube is not found'); // NOI18N
        }
    }

    function action_techBenefit($card_type) {
        $this->checkAction('techBenefit');
        $player_id = $this->getActivePlayerId();
        $args = $this->argTechBenefit();
        // Check card in valid slot and owned by player...
        $this->systemAssertTrue("invalid card for tech benefit $card_type", array_search($card_type, $args['cards']) !== false);
        $this->clearCurrentBenefit();
        $this->interruptBenefit();
        $ben = $this->queueTechBenefit($card_type, $args['slot'], $player_id);
        $this->userAssertTrue(totranslate('Card benefit is already used this turn'), $ben);
        $this->gamestate->nextState('next');
    }

    function queueTechBenefit($card_type, $slot, $player_id) {
        $benefits = [];
        if ($slot == 1)
            $benefits = $this->tech_card_data[$card_type]['circle']['benefit'];
        else if ($slot == 2)
            $benefits = $this->tech_card_data[$card_type]['square']['benefit'];
        else
            return;
        $reason = reason('tech', $card_type);
        $once = false;
        if ($card_type == 7 && $slot == 2) // Lithium battery
            $once = true;
        if ($once) {
            $card = "tech_card_$card_type";
            $current = $this->getStructureInfoSearch(BUILDING_MARKER, null, $card, $player_id, null);
            if ($current != null) {
                $args = ['card_name' => $this->getTokenName(CARD_TECHNOLOGY, $card_type)];
                $this->notifyWithName("message", clienttranslate('${player_name} attempts to take benefits of ${card_name} but it was already used this turn'), $args);
                return false;
            }
            $cube_id = $this->addMarker($player_id, 'hand');
            $this->dbSetStructureLocation($cube_id, $card);
        }
        $this->queueBenefitNormal($benefits, $player_id, $reason);
        return true;
    }

    function assertCanUseBenefitOnTrackSpot($player_id, $track, $spot) {
        return $this->useTrackSpot($player_id, $track, $spot, true, true);
    }

    function useTrackSpot($player_id, $track, $spot, $bThrow = false, $checkOnly = false) {
        if ($spot < 1 || $spot > 12) return true;
        $loc = "tech_spot_${track}_${spot}";
        $current = $this->getStructureInfoSearch(BUILDING_MARKER, null, $loc, $player_id, null);
        if ($current != null) {
            if ($bThrow) {
                $this->userAssertTrue(totranslate('Tech spot benefit or bonus is already used this turn'));
            }
            $args = $this->notifArgsAddTrackSpot($track, $spot);
            $this->notifyWithName("message", clienttranslate('${player_name} attempts to take benefits of ${spot_name} but it was already used this turn'), $args);
            return false;
        }
        if ($checkOnly == false) {
            $cube_id = $this->addMarker($player_id, 'hand');
            $this->dbSetStructureLocation($cube_id, $loc);
        }
        return true;
    }

    function getColonialismTargets($player_id) {
        $data = $this->getControlHexes($player_id);
        $valid = array();
        foreach ($data as $coords => $hex) {
            if ($hex['map_tile_id'] < 49)
                array_push($valid, $coords);
        }
        return $valid;
    }

    function getIslandersTargets() {
        // card_location_arg2='$coord', card_location_arg='$rot' WHERE card_id='$tile_id'"
        $location = "islanders";
        $map_data = $this->getInitMapData($location);
        $tiles = $this->getCardsSearch(CARD_TERRITORY, null, $location);
        foreach ($tiles as $card) {
            $coord = $card['card_location_arg2'];
            unset($map_data[$coord]);
        }
        foreach ($map_data as $coord => $info) {
            if ($info['map_tile_id'] != 0)
                unset($map_data[$coord]);
        }
        return array_prefix_all(array_keys($map_data), "islanders_");
    }

    function getMapDataFromDb($location = null, $xcoords = null) {
        // card_location_arg2='$coord', card_location_arg='$rot' WHERE card_id='$tile_id'"
        if ($location == 'land' ||  !$location)
            $location = 'map';
        $map_data = $this->getInitMapData($location);
        foreach ($map_data as $coord => &$info) {
            $info['map_coords'] = $coord;
        }

        $tiles = $this->getCardsSearch(CARD_TERRITORY, null, $location, null, $xcoords);
        // if (count($tiles) == 0 && $location != 'map')
        //     $tiles = $this->getCardsSearch(CARD_TERRITORY, null, "$location%");
        foreach ($tiles as $card) {
            $coord = $card['card_location_arg2'];
            $info = &$map_data[$coord];
            $info['map_tile_orient'] = (int) $card['card_location_arg'];
            $info['map_tile_id'] = (int) $card['card_type_arg'];
        }
        foreach ($map_data as $coord => &$info) {
            $tile_id = $info['map_tile_id'];
            if ($tile_id == 0) {
                $info['terrain_types'] = [];
                continue;
            }
            $terrains = array_get($this->territory_tiles[$tile_id], 'x', []);
            foreach ($terrains as $type) {
                $info['terrain_types'][$type] = 1;
            }
            $terrains = array_get($this->territory_tiles[$tile_id], 'h', []); // inside terrain
            foreach ($terrains as $type) {
                $info['terrain_types'][$type] = 1;
            }
        }
        return $map_data;
    }

    function getExplorationTargets($anywhere, $player_id) {
        // If anywhere, then can explore on any empty hex.
        if ($anywhere) {
            $data = $this->getCollectionFromDB("SELECT map_coords FROM map WHERE map_tile_id=0");
            $valid = array();
            foreach ($data as $d) {
                array_push($valid, $d['map_coords']);
            }
            return $valid;
        }
        // If restricted, must be adjacent to 'owned' hex.
        $hex_data = $this->getMap();
        $valid = array();
        $checked = array();
        $to_check = array();

        foreach ($hex_data as $coords => $hex) {
            // Get neighbours of owned hexes
            if ($this->isHexOwner($player_id, $coords, $hex_data)) {
                if (!in_array($coords, $checked))
                    array_push($checked, $coords);
                // Add neighbours to 'to_check' list.
                $neighbours = $this->getNeighbourHexes($coords);
                foreach ($neighbours as $neighbour) {
                    if ((!in_array($neighbour, $checked)) && (!in_array($neighbour, $to_check))) {
                        array_push($to_check, $neighbour);
                    }
                }
            }
        }
        // Check if neighbours are vacant
        while (sizeOf($to_check) > 0) {
            $checking = array_pop($to_check);
            if ($hex_data[$checking]['map_tile_id'] == 0) {
                array_push($valid, $checking);
            }
        }
        return $valid;
    }

    function getPlayerNeighbours($player_id, $bSelf = true) {
        $neighbours = [];
        $all = $this->loadPlayersBasicInfosWithBots();
        $next_player_table = $this->createNextPlayerTable(array_keys($all), true);
        $left = $next_player_table[$player_id];
        $prev_player_table = $this->createPrevPlayerTable(array_keys($all), true);
        $right = $prev_player_table[$player_id];
        if ($bSelf)
            $neighbours[$player_id] = 1;
        $neighbours[$right] = 1;
        $neighbours[$left] = 1;
        return array_keys($neighbours);
    }

    function getToppleTargets() {
        $player_id = $this->getActivePlayerId();
        return $this->getCollectionFromDB("SELECT card_id FROM structure WHERE card_location_arg='$player_id' AND (card_type=5) AND (card_type_arg=1) AND card_location LIKE 'land_%'");
    }

    function getTraderTargets($player_id) {
        $valid = [];
        $map_data = $this->getMap();
        if (!$this->isAdjustments4()) {
            // not owned explored
            foreach ($map_data as $coord => $hex) {
                if ($hex['map_tile_id'] == 0) continue;
                if ($hex['occupancy'] == 0) {
                    array_push($valid, $coord);
                } else if ($hex['occupancy'] == 1 && !$this->isHexOwner($player_id, $coord, $map_data)) {
                    $building = array_shift($hex['structures']);
                    if ($building['card_type'] == BUILDING_OUTPOST) {
                        array_push($valid, $coord);
                    }
                }
            }
        } else {
            foreach ($map_data as $coord => $hex) {
                if ($hex['map_tile_id'] == 0) continue;
                if ($hex['occupancy'] == 1) {
                    $building = array_shift($hex['structures']);
                    if ($building['card_type'] == BUILDING_OUTPOST) {
                        array_push($valid, $coord);
                    }
                }
            }
        }
        return $valid;
    }

    function getConquerTargets($nomads = false, $anywhere = false, $player_id = -1, $only_empty = false) {
        if ($player_id == -1)
            $player_id = $this->getActivePlayerId();
        $valid = array();
        $map_data = $this->getMap();

        $checked = array();
        $to_check = array();
        foreach ($map_data as $coords => $hex) {
            $structures = $hex['occupancy'];
            if ($this->isHexOwner($player_id, $coords, $map_data)) {
                if ($nomads) {
                    if ($structures < 2) {
                        array_push($valid, $coords);
                    }
                }
                if (!in_array($coords, $checked))
                    array_push($checked, $coords);
                // If restricted, must be adjacent to 'owned' hex.
                // Add neighbours to 'to_check' list.
                $neighbours = $this->getNeighbourHexes($coords);
                foreach ($neighbours as $neighbour) {
                    if ((!in_array($neighbour, $checked)) && (!in_array($neighbour, $to_check))) {
                        array_push($to_check, $neighbour);
                    }
                }
            } else if ($anywhere) {
                // If anywhere, then can conquer any available, non-full hex.
                if ((!in_array($coords, $checked)) && (!in_array($coords, $to_check))) {
                    array_push($to_check, $coords);
                }
            }
        }
        while (sizeOf($to_check) > 0) {
            $coords = array_pop($to_check);
            $hex = $map_data[$coords];
            if ($hex['map_tile_id'] == 0)  continue;
            $structures = $hex['occupancy'];
            if ($nomads) {
                if ($structures < 2 && $this->isHexOwner($player_id, $coords, $map_data)) {
                    array_push($valid, $coords);
                } else if ($structures == 0) {
                    array_push($valid, $coords);
                }
            } else {
                if ($only_empty) {
                    if ($structures == 0) {
                        array_push($valid, $coords);
                    }
                } else if ($this->isHexBlockedForConquer($player_id, $coords, $map_data) == false && !$this->isAlly($player_id, $hex['map_owners'])) {
                    array_push($valid, $coords);
                }
            }
        }
        return $valid;
    }





    function isHexBlockedForConquer($player_id, $x, $map) {
        return ($map[$x]['occupancy'] >= 2 || $this->isHexOwner($player_id, $x, $map));
    }

    function getNeighbourHexes($coords, $valid_coords = null) {
        if ($valid_coords == null)
            $valid_coords = $this->getCollectionFromDB("SELECT map_coords FROM map");
        $axis = explode("_", $coords);
        $neighbours = array();
        $x = $axis[0];
        $y = $axis[1];
        $new_coords = $x . "_" . ($y + 1);
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        $new_coords = $x . "_" . ($y - 1);
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        $new_coords = ($x + 1) . "_" . $y;
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        $new_coords = ($x - 1) . "_" . $y;
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        $new_coords = ($x + 1) . "_" . ($y + 1);
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        $new_coords = ($x - 1) . "_" . ($y - 1);
        if (array_key_exists($new_coords, $valid_coords))
            array_push($neighbours, $new_coords);
        return $neighbours;
    }

    /**
     * Deikstra algorithm of finding shortest path in hex grid with blocked cells
     * Returns map of all cells => k, where k is length of path from start to that cell
     * Limit by max size
     */
    function floodPath($start, $max, $fn_blocked, $fn_get_neighbor) {
        $visited = []; # set of hexes
        $visited[$start] = 0;
        $fringes = []; # array of arrays of hexes
        $fringes[0] = [$start];
        $k = 1;
        while ($k <= $max) {
            $fringes[$k] = [];
            foreach ($fringes[$k - 1] as $hex) {
                //print("$k = $hex\n");
                $nei = $fn_get_neighbor($hex);
                foreach ($nei as $neighbor) {
                    if ($fn_blocked($neighbor))
                        continue;
                    if (array_key_exists($neighbor, $visited))
                        continue;
                    $visited[$neighbor] = $k;
                    $fringes[$k][] = $neighbor;
                }
            }
            $k++;
        }
        return $visited;
    }

    function stExplore() {
        $args = $this->argExplore();
        if ($args['void']) {
            // no valid location, skip
            $this->notifyWithName("message_error", clienttranslate('${player_name} has no valid targets, effect is void ${reason}'), [
                'reason' => $this->getReasonFullRec(reason('be', $this->getCurrentBenefitType()))
            ]);
            $this->clearCurrentBenefit();
            $this->gamestate->nextState('next');
            return false;
        }
        return true;
    }

    function action_explore($location, $tile_type, $rot, $extra) {
        $this->checkAction('explore');
        $player_id = $this->getActivePlayerId();
        $args = $this->argExplore();
        $benefit_data = $this->subtractCurrentBenefit();
        // reset coal baron
        $this->setGameStateValue('coal_baron', 0);
        // untap age of sail
        $tap_age_of_sail = $this->isTapestryActive($player_id, TAP_AGE_OF_SAIL);
        if ($tap_age_of_sail) {
            $card_id = $tap_age_of_sail['card_id'];
            $this->DbQuery("UPDATE card SET card_location_arg2='0' WHERE card_id='$card_id'");
        }
        $valid_locations = $args['exploration_targets'];
        if (count($valid_locations) == 0) {
            // no valid location, skip
            $this->notifyWithName("message", clienttranslate('${player_name} has no valid targets, effect is void'));
            $this->gamestate->nextState('next');
            return;
        }
        if (!in_array($location, $valid_locations)) {
            throw new feException('invalid map location');
        }
        $tile_data = $this->getObjectFromDB("SELECT * FROM card WHERE card_type='1' AND card_type_arg='$tile_type'");
        if (($tile_data == NULL) || ($tile_data['card_location'] != 'hand') || ($tile_data['card_location_arg'] != $player_id)) {
            throw new feException('Invalid territory tile');
        }
        $coal_baron = $args['coal_baron'];
        $tile_id = $tile_data['card_id'];
        if ($coal_baron && ($coal_baron['card_id'] != $tile_id)) {
            throw new BgaUserException($this->_('You must explore with the territory tile drawn by the Coal Baron'));
        }
        // 1. Update territory tile location.
        $u = getPart($location, 1);
        $v = getPart($location, 2);
        $map = getPart($location, 0);
        $coord = $u . '_' . $v;
        $this->effect_exploreWithCard($player_id, $tile_id, $location, $rot, $benefit_data['benefit_data']);
        $flags = $this->getRulesBenefit($benefit_data['benefit_type'], 'flags');
        $no_benefit = ($flags & FLAG_NO_BENEFIT) != 0;
        if (!$no_benefit) {
            // 2. Queue the bonus benefit for the player.
            $tile_benefits = $this->getRulesCard(CARD_TERRITORY, $tile_type, 'benefit');
            $this->interruptBenefit();
            $reason = reason(CARD_TERRITORY, $tile_type);
            $this->queueBenefitNormal($tile_benefits, $player_id, $reason);
            if ($extra['exploitation'] && $this->isTapestryActive($player_id, 18)) { // EXPLOITATION
                $this->queueBenefitNormal($tile_benefits, $player_id, reason_tapestry(18));
            } else {
                //  3. Check edges that match for bonus points.
                $edge_count = $this->getMatchingEdgeCount($coord, $map);
                $this->awardVP($player_id, $edge_count, $reason, "${map}_wrapper_$coord", 47);
            }
        } else {
            $this->notifyWithName('message', clienttranslate('${player_name} no benefit for the territory'), [], $player_id);
        }
        // 4. Trigger effects of successful explore
        if ($map == 'land' && $extra['militarism'] && $this->isTapestryActive($player_id, TAP_MILITARISM)) {
            $oid = $extra['outpost_id'];
            $outpost_id = $this->getOutpostId($oid, $player_id);
            $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$coord'");
            $this->DbQuery("UPDATE structure SET card_location='land_$coord' WHERE card_id='$outpost_id'");
            $this->notifyMoveStructure(clienttranslate('${player_name} places an outpost on the new territory (MILITARISM) at ${coord_text}'), $outpost_id, [], $player_id);
            $this->checkMysticConquerBonus();
        }
        $this->gamestate->nextState('next');
    }

    function stSpaceExploration() {
        $player_id = $this->getActivePlayerId();
        $space_tiles = $this->getCardsInHand($player_id, CARD_SPACE);
        if (count($space_tiles) == 0) {
            // no card, skip this
            $this->clearCurrentBenefit();
            $this->notifyWithName('message', clienttranslate('${player_name} has no tiles to explore space, benefit is skipped'));
            $this->gamestate->nextState('next');
        }
    }

    function action_exploreSpace($sid) {
        $this->checkAction('explore_space');
        $player_id = $this->getActivePlayerId();
        $ben = $this->getCurrentBenefitType();
        $this->clearCurrentBenefit();
        if ($ben == BE_EXPLORE_SPACE_ALIEN) {
            $this->saction_exploreSpace($sid, 'civilization_31', $player_id);
        } else {
            $this->saction_exploreSpace($sid, 'hand_space', $player_id);
        }
        $this->gamestate->nextState('next');
    }

    function saction_exploreSpace($sid, $location, $player_id) {
        $card_id = $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_type='2' AND card_location='hand' AND card_type_arg='$sid' AND card_location_arg='$player_id'");
        $this->userAssertTrue(totranslate("Select unexplored space tile that you own"), $card_id);
        $this->DbQuery("UPDATE card SET card_location='$location' WHERE card_id='$card_id'");
        // UPDATE
        $args = $this->notifArgsAddCardInfo($card_id);
        $this->notifyWithName("exploreSpace", clienttranslate('${player_name} explores a space tile ${card_name}'), $args, $player_id);
        // Queue tile benefits
        $tile_benefits = $this->getRulesCard(CARD_SPACE, $sid);
        $this->queueBenefitInterrupt($tile_benefits, $player_id, reason('space', $sid));
    }

    function getMatchingEdgeCount($coord, $map) {
        $edge_count = 0;
        $axis = explode("_", $coord);
        $axis_x = $axis[0];
        $axis_y = $axis[1];
        $calc_map = $this->getMapDataFromDb($map);
        $main_tile_data = $calc_map[$coord];
        $this->systemAssertTrue("no tile at $coord", $main_tile_data);
        $main_tile = $main_tile_data['map_tile_id'];
        $this->systemAssertTrue("invalid map tile id " . toJson($main_tile_data), $main_tile);
        $main_rot = $main_tile_data['map_tile_orient'];
        $map_data = $calc_map;
        $main_data = $this->territory_tiles[$main_tile]['x'];
        foreach ($map_data as $hex) {
            $tile_id = $hex['map_tile_id'];
            $working_rot = $hex['map_tile_orient'];
            $working_coords = $hex['map_coords'];
            $working_axis = explode("_", $working_coords);
            $x = $working_axis[0];
            $y = $working_axis[1];
            $main_edge = -1;
            if (($x == $axis_x) && ($y == $axis_y + 1)) {
                $main_edge = 2;
            }
            if (($x == $axis_x) && ($y == $axis_y - 1)) {
                $main_edge = 5;
            }
            if (($x == $axis_x + 1) && ($y == $axis_y)) {
                $main_edge = 4;
            }
            if (($x == $axis_x - 1) && ($y == $axis_y)) {
                $main_edge = 1;
            }
            if (($x == $axis_x + 1) && ($y == $axis_y + 1)) {
                $main_edge = 3;
            }
            if (($x == $axis_x - 1) && ($y == $axis_y - 1)) {
                $main_edge = 0;
            }
            if (($main_edge > -1) && ($tile_id > 0)) {
                $working_edge = ($main_edge + 3) % 6;
                $main_left = (6 + $main_edge - $main_rot) % 6;
                $main_right = ($main_left + 1) % 6;
                $working_data = $this->territory_tiles[$tile_id]['x'];
                if (count($working_data) > 6) {
                    // have 12 probing point instead of 6
                    $working_left = (($working_edge - $working_rot) * 2 + 12) % 12;
                    $working_right = ($working_left + 1) % 12;
                } else {
                    $working_left = (6 + $working_edge - $working_rot) % 6;
                    $working_right = ($working_left + 1) % 6;
                }
                // $this->debugConsole("w $main_tile $tile_id " . $main_data [$main_left] . $working_data [$working_right] . $main_data [$main_right] . $working_data [$working_left]);
                if (($main_data[$main_left] == $working_data[$working_right]) || ($main_data[$main_right] == $working_data[$working_left]))
                    $edge_count++;
            }
        }
        return $edge_count;
    }

    function getTileRotation($vertex, $rotation, $mul = 1) {
        $this->systemAssertTrue("invalid vertex $vertex", $vertex >= 0 && $vertex < 6);
        $this->systemAssertTrue("invalid rotation $rotation", $rotation >= 0 && $rotation < 6);
        return (6 + $vertex - $rotation * $mul) % 6;
    }

    function colonialism($u, $v) {
        $this->checkAction('colonialism');
        $player_id = $this->getActivePlayerId();
        $valid_targets = $this->getColonialismTargets($player_id);
        $this->clearCurrentBenefit(105, true);
        $coord = $u . "_" . $v;
        if (!in_array($coord, $valid_targets)) {
            throw new BgaUserException($this->_('Choose a territory tile that you control (not pre-printed)'));
        }
        $map = $this->getMap();
        $has_terrain = $map[$coord]['terrain_types'];
        $order = "2534";
        foreach ($has_terrain as $t => $x) {
            $pos = strpos($order, (string) $t);
            if ($pos === false) continue;
            $benefit = $pos + 1;
            $this->awardBenefits($player_id, $benefit, 1, reason('be', 105));
        }
        $this->gamestate->nextState('next');
    }

    function action_standup($arr) {
        $this->checkAction('standup');
        $player_id = $this->getActivePlayerId();
        $this->isTapestryActive($player_id, 35, true); // revolution    
        $this->clearCurrentBenefit(119, true);
        $count = count($arr);
        $this->userAssertTrue(totranslate('Select at least one outpost and at most 3 to stand up or Decline'), $count > 0 && $count <= 3);
        foreach ($arr as $card_id) {
            if ($card_id == 0)
                break; // decline
            $structure_data = $this->getObjectFromDB("SELECT * FROM structure WHERE card_id='$card_id'");
            $land_coords = $structure_data['card_location'];
            $this->systemAssertTrue('Outpost is not toppled', $structure_data['card_type_arg'] == 1);
            $this->systemAssertTrue('Outpost is not yours', $structure_data['card_location_arg'] == $player_id);
            $u = getPart($land_coords, 1);
            $v = getPart($land_coords, 2);
            $coords = $u . '_' . $v;
            $this->DbQuery("UPDATE structure SET card_type_arg = 1-card_type_arg WHERE card_location='$land_coords'"); // toggle topple flag.
            $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$coords'");
            $affected = $this->getCollectionFromDB("SELECT card_id FROM structure WHERE card_location='$land_coords'");
            $this->notifyWithName("trap", clienttranslate('${player_name} stands up an outpost'), [
                'outposts' => $affected
            ]);
        }
        $this->checkMysticConquerBonus();
        $this->checkToppleAward($player_id);
        $this->gamestate->nextState('next');
    }

    function conquer_structure($u, $v) {
        $this->checkAction('conquer_structure');
        $player_id = $this->getActivePlayerId();
        if (!$this->hasCiv($player_id, CIV_NOMADS)) {
            $this->systemAssertTrue('Player does not have NOMADS');
        }
        $coord = $u . "_" . $v;
        $valid_locations = $this->getConquerTargets(true);
        if (!in_array($coord, $valid_locations)) {
            $this->userAssertTrue(totranslate('Invalid conquer location'));
        }
        $structure = $this->getObjectFromDB("SELECT card_id, card_type FROM structure WHERE card_location='capital_structure'");
        $location = 'land_' . $coord;
        $structure_id = $structure['card_id'];
        $this->DbQuery("UPDATE structure SET card_location='$location' WHERE card_id='$structure_id'");
        $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$coord'");
        $this->notifyMoveStructure(clienttranslate('${player_name} uses the Nomad ability to place a structure on the map'), $structure_id, [], $player_id);
        $this->clearCurrentBenefit();
        $structures = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM structure WHERE card_location='$location'");
        if ($structures == 2) {
            $this->queueBenefitInterrupt(RES_ANY, $player_id, reason_civ(CIV_NOMADS));
        }
        $this->gamestate->nextState('next');
    }

    function getOutpostId($oid = null, $player_id = null, $bThrow = true) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $outposts = $this->getOutpostsInHand($player_id);
        $outpost_id = array_key_first($outposts);
        if ($this->hasCiv($player_id, CIV_MILITANTS)) { // MILITANTS
            if (count($outposts) == 0) {
                return $this->addCube($player_id, 'hand');
            }

            $top = 10;
            $bottom = 10;
            $top_id = null;
            $bottom_id = null;
            foreach ($outposts as $outid => $outpost) {
                $location = $outpost['card_location'];
                $slot_id = substr($location, -1);
                if (($slot_id < 5) && ($slot_id < $top)) {
                    $top = $slot_id;
                    $top_id = $outpost['card_id'];
                }
                if (($slot_id > 4) && ($slot_id < $bottom)) {
                    $bottom = $slot_id;
                    $bottom_id = $outpost['card_id'];
                }
                if ($oid && $outpost['card_location'] == "civ_12_" . $oid) {
                    $outpost_id = $outpost['card_id'];
                }
            }
            if ($oid == null) {
                if ($top_id) return $top_id;
                return $bottom_id;
            }
            if (($oid != $top) && ($oid != $bottom)) {
                if ($bThrow)
                    $this->userAssertTrue(totranslate('You must choose one of the leftmost outposts'));
                $outpost_id = null;
            }
        }
        if ($bThrow)
            $this->userAssertTrue(totranslate('No more outposts'), $outpost_id);
        return $outpost_id;
    }

    function getOutpostChoices($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $outposts = $this->getOutpostsInHand($player_id);
        if ($this->hasCiv($player_id, CIV_MILITANTS)) { // MILITANTS
            $result = [];
            if (count($outposts) == 0) {
                $result[] = $this->addCube($player_id, 'hand');
                return $result;
            } else {
                $top = 10;
                $bottom = 10;
                $top_outpost = null;
                $bottom_outpost = null;
                foreach ($outposts as $outid => $outpost) {
                    $location = $outpost['card_location'];
                    $slot_id = substr($location, -1);
                    if (($slot_id < 5) && ($slot_id < $top)) {
                        $top = $slot_id;
                        $top_outpost = $outid;
                    }
                    if (($slot_id > 4) && ($slot_id < $bottom)) {
                        $bottom = $slot_id;
                        $bottom_outpost = $outid;
                    }
                }
                if ($top_outpost)
                    $result[] = $top_outpost;
                if ($bottom_outpost)
                    $result[] = $bottom_outpost;
                return $result;
            }
        } else {
            if (count($outposts) == 0)
                return [];
            $outpost_id = array_key_first($outposts);
            return [$outpost_id];
        }
    }

    function isAlly($player_id, $owners) {
        if (!$owners || count($owners) == 0)
            return false;
        $alliances = $this->getActiveTapestriesOfType(5);
        foreach ($alliances as $card) {
            $allies = [];
            array_push($allies, $card['card_location_arg']);
            array_push($allies, $card['card_location_arg2']);
            if (in_array($player_id, $allies)) {
                foreach ($owners as $owner_id) {
                    if (in_array($owner_id, $allies))
                        return true;
                }
            }
        }
        return false;
    }

    function action_conquer($u, $v, $isol, $oid) {
        $this->checkAction('conquer');
        $args = $this->argConquer();
        $this->clearCurrentBenefit();
        $player_id = $this->getActivePlayerId();
        $coord = "${u}_${v}";
        $valid_locations = $args['targets'];
        $orig_reason = $args['data'];
        $this->effect_conquer($player_id, $coord, $valid_locations, $isol, $oid, $orig_reason);
        $this->gamestate->nextState('next');
    }

    function effect_placeOnMap($player_id, $structure_id, $location, $notif = '*', $ownership = true) {
        $map = getPart($location, 0);
        $coord = getPart($location, 1) . "_" . getPart($location, 2);
        if ($ownership && $map == 'land')
            $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$coord'");
        $this->DbQuery("UPDATE structure SET card_location='land_$coord' WHERE card_id='$structure_id'");
        if ($ownership == false) {
            $this->DbQuery("UPDATE structure SET card_type_arg=1 WHERE card_id='$structure_id'");
        }
        $this->notifyMoveStructure(($notif == '*') ? clienttranslate('${player_name} conquers a territory at ${coord_text}') : $notif, $structure_id, [], $player_id);
    }

    function effect_conquer($player_id, $coord, $valid_locations, $isol, $oid, $orig_reason) {
        $this->interruptBenefit();
        if ($isol) { // Check player owns ISOLATIONISTS and has available tokens.
            $isol_token = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location LIKE 'civ_9_%') AND card_location_arg='$player_id' LIMIT 1");
            $this->systemAssertTrue('Isolation tokens not available', $isol_token);
        }
        $map = $this->getMapHexData($coord);
        $owners = $map['map_owners'];
        $owner_id = array_shift($owners);


        if (!in_array($coord, $valid_locations)) {
            $this->userAssertTrue(totranslate('Invalid conquer location'));
        }
        // GET AN AVAILABLE OUTPOST TO USE
        $outpost_id = $this->getOutpostId($oid, $player_id, true);
        // UPDATE
        $location = 'land_' . $coord;
        $this->effect_placeOnMap($player_id, $outpost_id, $location);
        // ISOLATIONISTS MAY SECURE TERRITORY WITH A TOKEN
        if ($isol) {
            $this->effect_placeOnMap($player_id, $isol_token, $location, clienttranslate('${player_name} places an isolationist token at ${coord_text}'), true);
            $this->queueBenefitNormal(RES_ANY, $player_id, reason_civ(CIV_ISOLATIONISTS));
        }
        $this->setSelectedMapHex($coord);
        if ($owner_id) {
            // TOPPLE...
            $oid = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_location='$location' AND card_location_arg='$owner_id'");
            $this->DbQuery("UPDATE structure SET card_type_arg=1 WHERE card_id='$oid'");
            $this->notifyAllPlayers("topple", '', array('bid' => $oid,));
            $this->setGameStateValue('toppled_player', $owner_id);
            $this->setGameStateValue('toppled_by', $player_id);
            if (!$this->isTapestryActive($player_id, 30)) { // PILLAGE AND PLUNDER
                $this->queueBenefitNormal(140, $owner_id, $orig_reason); // topple pick
            } else {
                $this->notifyWithName('message_error', clienttranslate('${player_name} has PILLAGE AND PLUNDER, trap cannot be played'));
            }
        } else {
            $this->setGameStateValue('toppled_player', 0);
        }
        if ($this->isRealPlayer($player_id))
            $this->rollConquerDice($player_id);
        $this->queueBenefitNormal(141, $player_id, $orig_reason); // die pick
        if ($this->hasCiv($player_id, CIV_UTILITARIENS)) {
            //Barracks: Whenever you conquer, gain the result of the red die (even if you also chose that die’s benefit)
            if ($this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_3", $player_id)) {
                $benefit = $this->getConquerDieBenefit('red', $player_id);
                if ($benefit)
                    $this->queueBenefitNormal($benefit, $player_id, reason_civ(CIV_UTILITARIENS));
            }
        }
    }

    function action_choose_die($die) {
        $this->checkAction('choose_die');
        $player_id = $this->getActivePlayerId();
        $color = $die ? 'black' : 'red';
        $this->interruptBenefit();
        $this->conquerDieBenefit($color, $player_id, true);
        // Did you conquer trader token? If so, they can claim the unclaimed die!
        $coords = $this->getSelectedMapHex()['map_coords'];
        $land_id = 'land_' . $coords;
        if (!$this->isAdjustments4()) {
            $trader = $this->getUniqueValueFromDB("SELECT DISTINCT(card_location_arg) FROM structure WHERE card_location='$land_id' AND card_type='7'");
            if ($trader && $trader != $player_id && $this->hasCiv($trader, CIV_TRADERS)) { // if player has trader civ - it is trader
                $other_color = ($color == 'red') ? 'black' : 'red';
                $this->conquerDieBenefit($other_color, $trader);
            }
        }
        if ($this->isTapestryActive($player_id, 31)) { // PIRATE RULE - gain benefit of conquered territory.
            $tileBen = $this->getTileBenefit();
            $this->queueBenefitNormal($tileBen, $player_id, reason_tapestry(31));
        }
        $this->gamestate->nextState('next');
    }

    function getTileBenefit() {
        $tile_id = $this->getSelectedMapHex()['map_tile_id'];
        return $this->getRulesCard(CARD_TERRITORY, $tile_id, 'benefit', []);
    }

    function conquerDieBenefit($die_color, $player_id, $throw = false) {
        $benefit = $this->getConquerDieBenefit($die_color);
        if ($benefit != null) {
            $this->queueBenefitNormal($benefit, $player_id, reason('die', clienttranslate('Conquer die')));
        } else if ($throw) {
            $this->userAssertTrue(self::_('This die has net effect of zero. Want to try another one?'));
        }
    }

    function getConquerDieBenefit($die_color) {
        $roll = $this->getGameStateValue("conquer_die_$die_color");
        $benefit = null;
        if (($roll == 1) && ($die_color == 'black')) {
            $tile_ben = $this->getTileBenefit();
            $benefit = $tile_ben;
        } else {
            $benefit = $this->dice[$die_color][$roll];
        }
        return $benefit;
    }

    function action_research_decision($track, $spot) {
        $this->checkAction('research_decision');
        $benefit_data = $this->clearCurrentBenefit();
        if ($track > 0) { // Only need to do anything if accepting advancement.
            $op1 = $this->getGameStateValue('science_die');
            $op2 = $this->getGameStateValue('science_die_empiricism');
            if ($op1 != $track && $op2 != $track) {
                $message = '';
                if ($op2 > 0) {
                    $message = sprintf(
                        self::_('You can only select %s track or %s track (EMPIRICISM) or Decline'),
                        $this->tech_track_types[$op1]['description'], //
                        $this->tech_track_types[$op2]['description']
                    );
                } else {
                    $message = sprintf(self::_('You can only select %s track or Decline'), $this->tech_track_types[$op1]['description']);
                }
                $this->userAssertTrue($message);
            }
            $this->setGameStateValue('science_die', $track);
            $this->setGameStateValue('science_die_empiricism', 0);
            if ($spot == 0)
                $spot = 13;
            $this->setGameStateValue('cube_choice', $spot - 1);
            $player_id = $this->getActivePlayerId();
            $cubes = $this->dbGetCubesOnTrack($player_id, $track, $spot - 1);
            $this->userAssertTrue(totranslate('You may only advance by a single space'), count($cubes) >= 1);
            $ben = $benefit_data['benefit_type'];
            $flags = (int) array_get_def($this->benefit_types, $ben, 'flags', 0);
            $this->interruptBenefit();
            $this->trackMovementProper($track, $spot - 1, ACTION_ADVANCE, $flags, false, $player_id); // advance
        }
        $this->gamestate->nextState('next');
    }

    function trackMovementInteractive($track, $spot, $change, $flags = 0, $mandatory = false, $player_id = null) {
        $track = (int) $track;
        $spot = (int) $spot;
        $this->checkTrack($track);
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $jump = ($flags & FLAG_JUMP) != 0;
        $advance = $change > 0 && !$jump;
        if ($advance) {
            if ($this->triggerAdvanceCheck($player_id, $track, $mandatory))
                return true;
            // if advance is not allowed - it with throw an exception
        }
        if ($spot == -1)
            $choice = $this->getGameStateValue('cube_choice', -1);
        $spot = $this->spotChoiceForTrack($track, $player_id, $change, $choice);
        //$this->debugConsole("choice $track $choice => $spot");
        $this->setGameStateValue('cube_choice', -1);
        if ($spot == -1) {
            if ($jump) {
                return true; // skip
            }
            if ($this->isRealPlayer($player_id)) {
                $this->gamestate->nextState('trackSelect');
                return false;
            } else if (!$this->isAutoma()) {
                $this->error("Suspicious non-real player $player_id");
            }
            return true; // skip if not real player
        }
        $this->interruptBenefit();
        $this->trackMovementProper($track, $spot, $change, $flags, $mandatory, $player_id);
        return true;
    }

    /**
     * Perform track movement or repeat.
     * This is non-interactive call - all interactions must be complete before.
     *
     * @param int $track
     *            - current track
     * @param int $spot
     *            - current spot
     * @param int $change
     *            - positive advance, negative regress, 0 same
     * @param boolean $mandatory
     *            - true - through exception if not possible, false message if not possibe
     * @param int $flags
     *            FLAG_GAIN_BENFIT // gain befit
     *            FLAG_PAY_BONUS // may pay for bonus
     *            FLAG_FREE_BONUS // gain free bonus
     *            FLAG_MAXOUT_BONUS // gain 5VP if maxout
     *            FLAG_JUMP // do not count as advance
     */
    function trackMovementProper($track, $spot, $change, $flags, $mandatory, $player_id) {
        $track = (int) $track;
        $spot = (int) $spot;
        //$this->warn("track move $track, $spot, $change, $flags, $mandatory, $player_id");
        $this->checkTrackSpot($track, $spot);
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $jump = ($flags & FLAG_JUMP) != 0;
        $advance = $change > 0 && !$jump;
        if ($advance) {
            if ($this->triggerAdvanceCheck($player_id, $track, $mandatory))
                return false;
            // if advance is not allowed - it with throw an exception
        }
        $new_spot = (int) ($spot) + (int) ($change);
        $cubes = $this->dbGetCubesOnTrack($player_id, $track, $spot);
        $cube_id = array_key_first($cubes); // note this can be an AI cube
        $this->systemAssertTrue("cannot find cube on track $track $spot $player_id", $cube_id);
        if ($jump) {
            if ($new_spot > 12)
                $new_spot = 12;
        }
        if ($new_spot < 0) {
            $this->notifyWithTrack("message_error", clienttranslate('${player_name} cannot regress on the ${track_name} track'), [
                'track' => $track,
            ], $player_id);
        } else if ($new_spot <= 12) {
            $virtual = $cubes[$cube_id]['virtual'];
            if ($virtual) {
                $message = clienttranslate('${player_name} cannot ${advance_regress} on the ${track_name} track - no cubes');
                $args = [
                    'track' => $track, 'adv' => $change, 'player_id' => $player_id,
                    'player_name' => $this->getPlayerNameById($player_id)
                ];
                if ($mandatory && $advance) {
                    $args = $this->notifArgsAddTrackSpot(null, null, $args);
                    $this->userAssertTrue(varsub(self::_($message), $args));
                } else {
                    $this->notifyWithTrack("message_error", $message, $args);
                }
            } else {
                $this->notifyWithTrack("advance", clienttranslate('${player_name} ${advance_regress} onto ${spot_name}'), [
                    'track' => $track, 'spot' => $new_spot, 'adv' => $change
                ], $player_id);
                $location = $this->getTrackLocationLike($track, $new_spot);
                $this->dbSetStructureLocation($cube_id, $location, null, '', $player_id);
                // stats
                $track_value = $this->getMaxTrackSlot($track, $player_id);
                if ($this->isRealPlayer($player_id))
                    $this->setStat($track_value, "track$track", $player_id);
                // triggered advance effects
                if ($advance)
                    $this->triggerAdvanceResolve($player_id, $track, $new_spot);
                // triggered end of track events
                if ($new_spot == 12) {
                    $this->achievementEOT($player_id);
                    $this->checkMysticPrediction(4, $player_id); // tracks 
                }

                if (!$jump) {
                    // LANDMARK
                    if ($change > 0) {
                        for ($i = $spot + 1; $i <= $new_spot; $i++) {
                            $this->processLandmarkSlot($track, $i, $player_id);
                        }
                    }
                }
                // BENEFITS
                if ($mandatory && $flags) {
                    $this->assertCanUseBenefitOnTrackSpot($player_id, $track, $spot);
                }
                if ($this->triggerPreGainBenefit($player_id, $track, $new_spot, $flags, $advance)) {
                    $reason = reason('spot', $track . "_" . $new_spot);
                    $data = $this->withReasonDataArg($reason, $flags);
                    // $this->debugConsole(">be_spot $data $flags $reason $track $new_spot $player_id");
                    $this->queueBenefitStandardOne(BE_SPOT, $player_id, $data);
                }
            }
        } else { // > 12
            $this->notifyWithTrack("message_error", clienttranslate('${player_name} cannot further advance on the ${track_name} track'), [
                'track' => $track
            ], $player_id);
            if (($flags & FLAG_MAXOUT_BONUS) != 0)
                $this->awardVP($player_id, 5, reason('str', clienttranslate('end of the track bonus')));
        }
        $this->setGameStateValue('cube_choice', -1); // XXX why here?
        return true;
    }

    function triggerPreGainStructure($player_id, $type, $ben) {
        if (!$this->isRealPlayer($player_id))
            return false;
        if ($this->hasCiv($player_id, CIV_COLLECTORS)) {
            $civ = CIV_COLLECTORS;
            $table = 'structure';
            $slot = $this->getCivSlotNumberForGain($civ, $table, $type);
            if ($slot <= 0)
                return false;
            $cube = $this->getStructureOnCivSlot($civ, $slot);
            if ($cube) // already there
                return false;
            $this->queueBenefitNormal(['or' => [BE_COLLECTORS_GRAB, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS, "$table:$type"));
            return true;
        }
        if ($this->hasCiv($player_id, CIV_URBAN_PLANNERS)) {
            /** @var UrbanPlanners */
            $inst = $this->getCivilizationInstance(CIV_URBAN_PLANNERS, true);
            return $inst->triggerPreGainStructure($player_id, $type, $ben);
        }
        return false;
    }
    function triggerPreKeepCard($player_id, $card_id, $type) {
        if (!$this->isRealPlayer($player_id))
            return false;
        if ($this->hasCiv($player_id, CIV_COLLECTORS)) {
            $civ = CIV_COLLECTORS;
            $table = 'card';
            $slot = $this->getCivSlotNumberForGain($civ, $table, $type);
            if ($slot <= 0) return false;
            $sql = "SELECT * FROM card WHERE card_location = 'civ_{$civ}_${slot}' LIMIT 1";
            $cube =  $this->getObjectFromDB($sql);
            if ($cube) // already there 
                return false;

            $this->queueBenefitInterrupt(['or' => [BE_COLLECTORS_CARD, BE_DECLINE]], $player_id, reason(CARD_CIVILIZATION, CIV_COLLECTORS, $card_id));
            return true;
        }
        return false;
    }

    function effect_collectorsGrabCard($player_id, $bene) {
        $data = $bene['benefit_data'];
        $card_id = $this->getReasonArg($data, 3);

        $card = $this->getCardInfoById($card_id);
        $card_type = $card['card_type'];
        $slot = $this->getCivSlotNumberForGain(CIV_COLLECTORS, 'card', $card_type);
        $this->systemAssertTrue("cannot find card $card_id $slot", $slot > 0);
        $civ = CIV_COLLECTORS;
        $sql = "SELECT * FROM card WHERE card_location = 'civ_{$civ}_${slot}' LIMIT 1";
        $cube =  $this->getObjectFromDB($sql);

        if ($cube) // already there
            return false;
        $this->dbSetCardLocation($card_id, "civ_21_${slot}", 0, clienttranslate('${player_name} collected ${card_type_name} ${card_name} on COLLECTORS mat'), $player_id);
        return true;
    }

    function effect_collectorsGrab($player_id, $bene) {
        $ben = $bene['benefit_type'];

        if ($ben == BE_HOUSE) {
            // gain house
            if ($this->claimIncomeStructure(BUILDING_HOUSE, null)) {
                // no more houses
                return false;
            }
            $structure_data = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
            $structure_id = $structure_data['card_id'];
            $this->dbSetStructureLocation($structure_id, 'civ_21_2', null, clienttranslate('${player_name} collected ${structure_name} on COLLECTORS mat'), $player_id);
            return true;
        }
        if ($ben == BE_CONQUER) {
            $structure_id = $this->getOutpostId(null, $player_id, false); // XXX Militants
            if (!$structure_id) return false;
            $this->dbSetStructureLocation($structure_id, 'civ_21_1', null, clienttranslate('${player_name} collected ${structure_name} on COLLECTORS mat'), $player_id);
            return true;
        }
        $landmark_id = $this->getRulesBenefit($ben, 'lm');
        if ($landmark_id) {
            // landmark benefit
            if ($this->claimLandmark($landmark_id, $player_id, null)) {
                // no landmark?
                return false;
            }
            $structure_data = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
            $structure_id = $structure_data['card_id'];
            $this->dbSetStructureLocation($structure_id, 'civ_21_3', null, clienttranslate('${player_name} collected ${structure_name} on COLLECTORS mat'), $player_id);
            return true;
        }
        $this->systemAssertTrue("unexpected benefit $ben");
    }

    function triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance) {
        if (!$this->isRealPlayer($player_id))
            return true;
        if ($this->hasCiv($player_id, CIV_RENEGADES)) {
            $civinst = $this->getCivilizationInstance(CIV_RENEGADES);
            return $civinst->triggerPreGainBenefit($player_id, $track, $spot, $flags, $advance);
        }
        return true;
    }

    function triggerAdvanceResolve($player_id, $track, $new_spot) {
        //$this->debugConsole("advance resove $track");
        if ($track == 3) {
            $bop = $this->getActiveTapestriesOfType(7); // BROKER OF PEACE - Could be multiple through espionage!
            foreach ($bop as $card) {
                $this->awardVP($card['card_location_arg'], 3, reason_tapestry(7));
            }
        }
        $theocracy = $this->isTapestryActive($player_id, 40); // THOCRACY
        if (($theocracy) && (in_array($new_spot, array(1, 4, 7, 10)))) {
            $this->awardVP($player_id, 4, reason_tapestry(40));
        }
        if (($track == 2) && ($this->isTapestryActive($player_id, 16))) { // EMPIRICISM
            $this->awardVP($player_id, 2, reason_tapestry(16));
        }
        if ($this->hasCiv($player_id, CIV_UTILITARIENS) && $new_spot >= 7) {
            // tank factory tier III and IV of military
            if ($track == 3 && $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_4", $player_id)) {
                $this->awardVP($player_id, 4, reason_civ(CIV_UTILITARIENS));
            }
            // train station tier III and IV of exploration
            if ($track == 1 && $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_6", $player_id)) {
                $this->awardVP($player_id, 4, reason_civ(CIV_UTILITARIENS));
            }
            //Rubber Works: Whenever you you advance on tiers III and IV of the Technology track, gain 4 VP
            if ($track == 4 && $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_2", $player_id)) {
                $this->awardVP($player_id, 4, reason_civ(CIV_UTILITARIENS));
            }
            //Academy: Whenever you advance on tiers III and IV of the Science track, gain 4 VP"
            if ($track == 2 && $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_8", $player_id)) {
                $this->awardVP($player_id, 4, reason_civ(CIV_UTILITARIENS));
            }
        }
    }

    function triggerAdvanceCheck($player_id, $track, $check) {
        if (($track == 3) && ($this->isTapestryActive($player_id, 7))) { // BROKER OF PEACE
            $message = clienttranslate('As BROKER OF PEACE you cannot advance on the military track');
            if ($check)
                throw new BgaUserException(self::_($message));
            else
                $this->notifyWithName('message', $message);
            return true;
        }
        if (($track == 2) && ($this->isTapestryActive($player_id, 40))) { // THEOCRACY
            $message = clienttranslate('With THEOCRACY you cannot advance on the science track');
            if ($check)
                throw new BgaUserException(self::_($message));
            else
                $this->notifyWithName('message', $message);
            return true;
        }
        $track_stub = "tech_spot_{$track}_";
        $dictator_data = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location LIKE '${track_stub}%' AND card_location_arg2 LIKE 'dic_%' LIMIT 1");
        if ($dictator_data) {
            $dd = explode("_", $dictator_data['card_location_arg2']);
            $dictator = $dictator_data['card_location_arg'];
            $turn = $this->getPlayerTurn($dictator);
            if ($turn == $dd[1] && $dictator && $player_id != $dictator) {
                $dictName = $this->getPlayerNameById($dictator);
                if ($check)
                    throw new BgaUserException($this->_('Cannot advance on this track this turn due to DICTATORSHIP') . " " . $dictName);
                $track_name = $this->tech_track_types[$track]['name'];
                $this->notifyWithName('message_error', _('${player_name} cannot advance on ${track_name} track this turn due to DICTATORSHIP of ${opp_name}'), [
                    'preserve' => ['track'], 'track' => $track, 'i18n' => array('track_name'),
                    'opp_name' => $dictName, 'track_name' => $track_name,
                ], $player_id);
                return true;
            }
        }
        return false;
    }

    function achievementEOT($player_id) {
        $achievements = $this->getCollectionFromDB("SELECT card_location_arg FROM structure WHERE card_location LIKE 'achievement_1_%'");
        foreach ($achievements as $achievement) {
            if ($achievement['card_location_arg'] == $player_id) {
                return; // player already has it
            }
        }
        $pos = sizeOf($achievements) + 1;
        $destination = 'achievement_1_' . $pos;
        $player_count = $this->getPlayersNumber();
        if (($player_count < 4) && ($pos < 3)) {
            $points = (15 - ($pos * 5));
        } else if (($player_count > 3) && ($pos < 4)) {
            $points = (20 - ($pos * 5));
        } else {
            $points = 0;
        }
        if ($points > 0) {
            $token_id = $this->addCube($player_id, $destination);
            $this->notifyMoveStructure(clienttranslate('${player_name} reaches end of track'), $token_id, [], $player_id);
            $this->awardAchievementVP($player_id, $points, reason('achi', clienttranslate('End of track')), $destination);
        }
    }

    function getMysticPrediction($category, $player_id) {
        if (!$this->hasCiv($player_id, CIV_MYSTICS))
            return null;
        $civ_tokens = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'civ_13\\_%'");
        if (count($civ_tokens) == 0)
            return null;
        $prediction = 0;
        $awarded = false;
        $category_name = 'unknown';
        $actual = 0;
        $prediction = -1;
        $civ_location = null;
        $cube_id = null;
        switch ($category) {
            case 1:
                $actual = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='4'");
                $category_name = clienttranslate('Tech cards');
                break;
            case 2:
                $actual = $this->getDistrictCount($player_id);
                $category_name = clienttranslate('Complete districts');
                break;
            case 3:
                $actual = $this->getNumberOfControlledTerritories($player_id);
                $category_name = clienttranslate('Controlled territories');
                break;
            case 4:
                $actual = $this->getFinishedTracks($player_id);
                $category_name = clienttranslate('Completed tracks');
                break;
            default:
                return null;
        }
        foreach ($civ_tokens as $ct) {
            $holder = explode("_", $ct['card_location'])[2];
            $awarded = ($ct['card_location_arg2']) ? true : false;
            $civ_location = $ct['card_location'];
            $cube_id = $ct['card_id'];
            if (($category == 1) && ($holder < 10)) { // TECH CARDS
                $prediction = $holder;
                break;
            }
            if (($category == 2) && ($holder > 9) && ($holder < 19)) { // DISTRICTS
                $prediction = $holder - 9;
                break;
            }
            if (($category == 3) && ($holder > 18) && ($holder < 28)) { // CONTROLLED OR CONQUERED TERRITORY?
                $prediction = $holder - 18;
                break;
            }
            if (($category == 4) && ($holder > 27)) { // TECH TRACKS
                $prediction = $holder - 27;
                break;
            }
        }
        return array(
            'awarded' => $awarded, 'value' => $prediction, 'cube_id' => $cube_id, 'location' => $civ_location,
            'actual' => $actual, 'category_name' => $category_name
        );
    }

    function checkMysticConquerBonus() {
        $player_id = $this->getUniqueValueFromDB("SELECT card_location_arg FROM card WHERE card_type='5' AND card_type_arg='13' AND card_location='hand'");
        if ($player_id == null)
            return;
        $this->checkMysticPrediction(3, $player_id);
    }

    function getFinishedTracks($player_id) {
        $finished_tracks = 0;
        for ($t = 1; $t <= 4; $t++) {
            $cubes = $this->dbGetCubesOnTrack($player_id, $t, 12);
            if (count($cubes) > 0)
                $finished_tracks++;
        }
        return $finished_tracks;
    }

    function checkMysticPrediction($category, $player_id = null) {
        // Completed track - check for mystic bonus
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if (!$this->hasCiv($player_id, CIV_MYSTICS))
            return;
        //What did they predict?
        $mystic_prediction = $this->getMysticPrediction($category, $player_id);
        if ($mystic_prediction == null)
            return;
        $prediction = $mystic_prediction['value'];
        $awarded = $mystic_prediction['awarded'];
        $actual = $mystic_prediction['actual'];
        // Award
        if (($prediction == $actual) && (!$awarded)) {
            $this->notifyAllPlayers("message", clienttranslate('${player_name} achieves their MYSTICS prediction: ${category_name}'), array(
                'player_id' => $player_id, 'player_name' => $this->getPlayerNameById($player_id),
                'category_name' => $mystic_prediction['category_name'], 'i18n' => ['category_name']
            ));
            $civ_location = $mystic_prediction['location'];
            $this->DbQuery("UPDATE structure SET card_location_arg2=1 WHERE card_location='$civ_location'");
            $this->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ(CIV_MYSTICS));
        }
    }

    function checkPrivateAchievement($category, $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if (!$this->hasCiv($player_id, CIV_CHOSEN))
            return;
        if (!$this->isAdjustments4()) {
            return;
        }
        $achieved = false;
        switch ($category) {
            case 1:
                // resource count
                for ($i = 1; $i <= 4; $i++) {
                    $newCount = $this->getResourceCountAll($player_id, $i);
                    if ($newCount >= 6) {
                        $achieved = true;
                        break;
                    }
                }
                break;
            case 2:
                // tap count
                $taps = count($this->cards->getCardsOfTypeInLocation(CARD_TAPESTRY, null, 'hand', $player_id));
                if ($taps >= 5) {
                    $achieved = true;
                }
                break;
            case 3:
                $tile = count($this->cards->getCardsOfTypeInLocation(CARD_TERRITORY, null, 'hand', $player_id));
                if ($tile >= 5) {
                    $achieved = true;
                }
                break;
            case 4: // tech cards
                $levels = $this->getCollectionFromDB("SELECT DISTINCT card_location_arg2 FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type=4");
                //$this->debugConsole("tech levels " . count($levels));
                if (count($levels) >= 3) {
                    $achieved = true;
                }
                break;
            case 5: // houses in districts
                $houses_complete = false;
                $this->getDistrictCount($player_id, $houses_complete);
                if ($houses_complete) {
                    $achieved = true;
                }
                break;
        }
        if ($achieved) {
            $this->effect_triggerPrivateAchievement($player_id, $category);
        }
    }


    function processSpotBenefits($track, $spot, $player_id, $flags = FLAG_GAIN_BENFIT, $reason = null) {
        $benefit_available = ($flags & FLAG_GAIN_BENFIT) != 0 ? 1 : 0;
        $bonus_state = ($flags & FLAG_PAY_BONUS) != 0 ? 1 : 0;
        $bonus_state = ($flags & FLAG_FREE_BONUS) != 0 ? 2 : $bonus_state;

        if ($player_id == null)
            $player_id = $this->getActivePlayerId();
        if (($benefit_available == 1 && $spot > 0) || $bonus_state >= 1) {
            if ($this->useTrackSpot($player_id, $track, $spot, false) == false) {
                return;
            }
        }
        if ($reason == null)
            $reason = reason('spot', $track . "_" . $spot);
        // BENEFIT
        if ($benefit_available == 1 && $spot > 0) {
            $ben = $this->tech_track_data[$track][$spot]['benefit'];
            if ($this->isRealPlayer($player_id)) {
                $this->queueBenefitNormal($ben, $player_id, $reason);
                // trigger marriage
                $ma_reason = reason_tapestry(TAP_MARRIAGE_OF_STATE);
                $mos = $this->getActiveTapestriesOfType(TAP_MARRIAGE_OF_STATE); // MARRIAGE OF STATE
                foreach ($mos as $mos_data) {
                    $owner = $mos_data['card_location_arg'];
                    $mos_parts = explode("_", $mos_data['card_location_arg2']);
                    if (($mos_parts[0] == $player_id) && ($mos_parts[1] == $track)) {
                        $this->queueBenefitNormal($ben, $owner, $ma_reason);
                    }
                }
            } else
                $this->queueBenefitAutoma($ben, $player_id, $reason);
        }
        // BONUS
        if ($bonus_state >= 1 && $this->isRealPlayer($player_id)) {
            if (array_key_exists('option', $this->tech_track_data[$track][$spot])) {
                $type = $this->tech_track_data[$track][$spot]['option']['type'];
                $quantity = $this->tech_track_data[$track][$spot]['option']['quantity'];
                $bonus = $this->tech_track_data[$track][$spot]['option']['benefit'];
                $free_bonus = ($bonus_state == 2);
                if ($free_bonus) {
                    $this->queueBenefitNormal(explode(",", $bonus), $player_id, $reason);
                } else {
                    $this->queueBonus($type, $quantity, $bonus, ORDER_NORMAL, $player_id);
                }
            }
        }
    }

    function clearCurrentBenefit($ben = null, $throw = false) {
        $benefit_data = $this->getCurrentBenefit($ben);
        if ($benefit_data) {
            //$this->debugConsole("clear benefit ".json_encode($benefit_data));
            $this->benefitCashed($benefit_data['benefit_id']);
            return $benefit_data;
        } else {
            if (is_array($ben) && isset($ben['benefit_id'])) {
                $ben = $ben['benefit_id'];
            }
            if ($throw && $ben)
                $this->systemAssertTrue("Benefit $ben is not on stack");
            $e = new Exception("no benefit $ben found in " . ($this->getStateName()));
            $this->error($e->getTraceAsString());
        }
        return null;
    }

    function subtractCurrentBenefit($ben = null, $throw = false) {
        $benefit_data = $this->getCurrentBenefit($ben);
        if ($benefit_data) {
            $benefit_table_id = $benefit_data['benefit_id'];
            $count = $benefit_data['benefit_quantity'];
            if ($count <= 1) {
                $this->benefitCashed($benefit_table_id);
                return $benefit_data;
            } else {
                $count--;
                $this->DbQuery("UPDATE benefit SET benefit_quantity = $count  WHERE benefit_id='$benefit_table_id'");
                $benefit_data['benefit_quantity'] = 1;
                return $benefit_data;
            }
        } else {
            if ($throw && $ben)
                $this->systemAssertTrue("Benefit $ben is not on stack");
            $e = new Exception("no benefit $ben found in " . ($this->getStateName()));
            $this->error($e->getTraceAsString());
        }
        return null;
    }

    function getCompositeBenefit($benefit_data) {
        $cat = $benefit_data['benefit_category'];
        $type = $benefit_data['benefit_type'];
        if ($cat == 'standard' || $cat == 'civ') {
            return ['op' => $cat, 'types' => [$type]];
        }
        $options = explode(",", $cat); // new way category is 'o,23,45,67'
        $op = array_shift($options);
        if ($op == 'o')
            $op = 'or';
        else if ($op = 'a')
            $op = 'choice';
        return ['op' => $op, 'types' => $options];
    }

    function action_choose_benefit($bid, $spot) {
        $this->checkAction('choose_benefit');
        $this->setGameStateValue('cube_choice', $spot);
        $benefit_data = $this->getCurrentBenefitWithInfo();
        $args = $this->argBenefitChoice();
        $comp = $this->getCompositeBenefit($benefit_data);
        $op = $comp['op'];
        $types = $comp['types'];
        if ($op == 'standard' && $bid == $types[0]) {
            $track = $args['tracks'][$bid]['track'];
            $this->action_selectTrackSpot($track, $spot);
            return;
        }
        $this->systemAssertTrue("wrong category $op", $op == 'or');
        $this->benefitCashed($benefit_data['benefit_id']);
        $player_id = $benefit_data['benefit_player_id'];
        $data = $benefit_data['benefit_data'];
        $cat = $benefit_data['benefit_category'];
        $this->systemAssertTrue("Wrong player $player_id for benefit choice", $this->getActivePlayerId() == $player_id);
        $this->systemAssertTrue("Invalid benefit logged $bid $cat $data", $types);
        $this->systemAssertTrue("invalid benefit choice $bid", in_array($bid, $types));
        $change = array_get($args, 'tracks_change', 0);
        $this->setGameStateValue('cube_choice', $spot - $change);
        $this->processBenefitChoice($bid);
        $this->queueBenefitInterrupt($bid, $player_id, $data);
        $this->gamestate->nextState('next');
    }

    function action_first_benefit($bid, $spot) {
        $this->checkAction('first_benefit');
        $this->setGameStateValue('cube_choice', $spot);
        $benefit_data = $this->getCurrentBenefit();
        $comp = $this->getCompositeBenefit($benefit_data);
        $op = $comp['op'];
        $this->systemAssertTrue("wrong category $op", $op == 'choice');
        $this->benefitCashed($benefit_data['benefit_id']);
        $player_id = $benefit_data['benefit_player_id'];
        $data = $benefit_data['benefit_data'];
        $cat = $benefit_data['benefit_category'];
        $this->systemAssertTrue("wrong player $player_id", $this->getActivePlayerId() == $player_id);
        $types = $comp['types'];
        if ($types)
            $options = $types;
        else
            $options = $comp['ids'];
        $or_id = $benefit_data['benefit_id'];
        if (($key = array_search($bid, $options)) !== false) {
            unset($options[$key]);
            $options = array_values($options);
        } else {
            $this->systemAssertTrue("illegal benefit choice $bid $data $cat");
        }
        $this->interruptBenefit();
        if ($types) {
            $this->queueBenefitNormal($bid, $player_id, $data);
            if (count($options) > 1)
                $this->queueBenefitNormal(['choice' => $options], $player_id, $data);
            else
                $this->queueBenefitNormal($options, $player_id, $data);
        } else { // XXX remove
            if (count($options) <= 1) {
                $this->benefitCashed($or_id);
                $this->DbQuery("UPDATE benefit SET benefit_prerequisite='0' WHERE benefit_id='$bid'");
            } else {
                $this->DbQuery("UPDATE benefit SET benefit_prerequisite='0' WHERE benefit_id='$bid'");
                $this->DbQuery("UPDATE benefit SET benefit_data='" . implode(',', $options) . "' WHERE benefit_id='$or_id'");
            }
        }
        $this->notifyBenefitQueue();
        $this->gamestate->nextState('next');
    }

    function processBenefitAdjustment($selected_ben) {
        // this is special benefit that removes prviously selected track from the next choice
        if ($selected_ben == 401)
            return 1; // declined
        $selected_track = (int) $this->getRulesBenefit($selected_ben, 't', 0);
        if ($selected_track == 0) {
            $this->error("ben601: cannot determine selected track $selected_ben=>$selected_track");
            return 2;
        }
        $next_benefit = $this->getCurrentBenefitWithInfo();
        if ($next_benefit) {
            $bid = $next_benefit['benefit_id'];
            $comp = $this->getCompositeBenefit($next_benefit);
            if ($comp['op'] == 'or') {
                foreach ($comp['types'] as $ben) {
                    $track = (int) $this->getRulesBenefit($ben, 't', 0);
                    if ($track == 0) {
                        $this->error("ben601: cannot determine selected track for $ben");
                        return 3;
                    }
                    if ($track == $selected_track) {
                        $options = $comp['types'];
                        if (($key = array_search($ben, $options)) !== false) {
                            unset($options[$key]);
                            $options = array_values($options);
                        }
                        $cat = "o," . implode(',', $options);
                        //$this->debugConsole("Benefit updated $bid $cat");
                        $this->DbQuery("UPDATE benefit SET benefit_category='$cat' WHERE benefit_id = $bid");
                        $this->notifyBenefitQueue();
                        return 0;
                    }
                }
            }
        }
        return 4;
    }

    function processBenefitChoice($selected_ben) {
        $next_benefit = $this->getCurrentBenefit();
        if (array_get($next_benefit, 'benefit_type', 0) == 601) {
            $this->benefitCashed($next_benefit);
            $this->processBenefitAdjustment($selected_ben);
        }
    }

    function checkValidIncomeType($type) {
        return (($type < 5) && ($type > 0));
    }

    function selectIncomeBuilding($type) {
        $this->checkAction('selectIncomeBuilding');
        $player_id = $this->getActivePlayerId();
        if (!$this->checkValidIncomeType($type)) {
            throw new feException('Invalid Income type');
        }
        $building_id = $this->dbGetIncomeBuildingOfType($type, true); // to check
        $current_benefit = $this->getCurrentBenefit();
        $this->clearCurrentBenefit();
        $ben = $current_benefit['benefit_type'];
        // PASS OFF TO 'CLAIM INCOME BUILDING'
        if ($ben == 110) {
            $this->queueBenefitNormal(array(7 + $type), $player_id, reason('be', 110));
        } else if ($ben == 144) {
            // discard
            $this->userAssertTrue(self::_('No more buildings left'), $building_id);
            $this->claimIncomeStructure($type, null);
            $this->DbQuery("UPDATE structure SET card_location='hand', card_type_arg='0' WHERE card_id='$building_id'");
            $this->notif("moveStructure", $player_id)->withStructure($building_id)->withReason(reason('be', $ben))-> //
                notifyAll(clienttranslate('${player_name} gains an income building - ${structure_name} ${reason}'));
        } else {
            throw new BgaUserException($this->_('You cannot select an income building at this time'));
        }
        $this->gamestate->nextState('next');
    }

    function selectLandmark($type) {
        $this->checkAction('selectBuilding');
        $player_id = $this->getActivePlayerId();
        $this->userAssertTrue(totranslate('You should select a landmark on the landmark mat'), (($type > 0) && ($type <= 12)));
        // CHECK ON THE MAT...
        $current_benefit = $this->getCurrentBenefit();
        $location = 'landmark_mat_slot' . $type;
        $structure = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_location='$location'");
        $this->userAssertTrue(totranslate('You should select a landmark on the landmark mat'), $structure);
        $this->clearCurrentBenefit();
        $ben = $current_benefit['benefit_type'];
        switch ($ben) {
            case 111:
                $this->queueBenefitNormal($this->landmark_data[$type]['benefit'], $player_id, reason_tapestry(15)); // DYSTOPIA
                break;
            case 305:
                //                 $cube = $this->getStructureInfoSearch(BUILDING_CUBE, CUBE_CIV, "civ_39_9", $player_id);
                //                 $this->systemAssertTrue("cannot find cube on utilitariens", $cube);
                //                 $slot = $this->getCivSlotWithValue(CIV_UTILITARIENS, "lm", $type);
                //                 if ($slot) {
                //                     $this->dbSetStructureLocation($cube ['card_id'], $slot);
                //                 }
                $this->queueBenefitNormal($this->landmark_data[$type]['benefit'], $player_id, reason_civ(CIV_UTILITARIENS));
                break;
            default:
                throw new BgaUserException($this->_('You cannot select a landmark at this time'));
        }
        $this->gamestate->nextState('next');
    }

    function getCivSlotWithValue($civ, $field, $value) {
        $slots = $this->getRulesCiv($civ, 'slots');
        foreach ($slots as $i => $info) {
            if ($info[$field] == $value) {
                return "civ_{$civ}_$i";
            }
        }
        return null;
    }

    function getCivSlotNumberForGain($civ, $table, $type) {
        $slots = $this->getRulesCiv($civ, 'slots');
        $slot = -1;
        foreach ($slots as $i => $info) {
            if ($info['tt'] == $table && $info['ct'] == $type) {
                $slot = $i;
                break;
            }
        }

        return $slot;
    }

    function getCivBenefit($civ, $slot, $field = 'benefit') {
        $slots = $this->getRulesCiv($civ, 'slots');
        return array_get($slots[$slot], $field, null);
    }

    function getDistrictCount($player_id, &$houses_complete = null) {
        $capital = $this->getCapitalData($player_id);
        $districts = 0;
        $houses_complete = false;
        for ($x = 0; $x < 3; $x++) {
            for ($y = 0; $y < 3; $y++) {
                $district_info = $this->getDistrictInfo(3 * $x + 4, 3 * $y + 4, $capital);
                $complete = $district_info['complete'];
                $districts += $complete;
                if ($complete && array_get($district_info['build_types'], BUILDING_HOUSE, 0) >= 3) {
                    $houses_complete = true;
                }
            }
        }
        return $districts;
    }

    function effect_placeOnCapitalMat($structure_id, $x, $y, $rot = 0, $player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $structure_data = $this->getStructureInfoById($structure_id, false);
        $sid = $structure_data['card_id'];
        $cell = 'capital_cell_' . $player_id . '_' . $x . '_' . $y;
        $type = $structure_data['card_type'] + 1; // 1 is already a dot on the mat, so increase by 1.
        $building_type = $structure_data['card_type'];
        $terraforming_claimed = true;
        $unpassable = ($this->isTapestryActive($player_id, 39)) ? 1 : 0; // TERRAFORMING
        if ($unpassable)
            $terraforming_claimed = false;
        if ($this->hasCiv($player_id, CIV_RIVERFOLK)) {
            $unpassable = 1;
        }
        // Get structure mask
        if ($building_type == BUILDING_LANDMARK) {
            // landmark
            $landmark_id = $structure_data['card_location_arg2'];
            $landmark = $this->landmark_data[$landmark_id];
            $width = $landmark['width'];
            $height = $landmark['height'];
            $masks = $landmark['mask'];
        } else {
            $masks = array();
            $masks[0] = array(0 => array(0 => 1));
            $height = 1;
            $width = 1;
        }
        // Orientate with rot.
        $mask = $masks[$rot];
        if ($rot % 2 == 0) {
            $dummy = $height;
            $height = $width;
            $width = $dummy;
        }
        $oobounds = 0;
        if ($x >= 12 && $y >= 12) {
            // out of bounds
            $oobounds = 1;
            $this->DbQuery("UPDATE structure SET card_location='hand', card_type_arg='$rot' WHERE card_id='$sid'");
        } else {
            $this->DbQuery("UPDATE structure SET card_location='$cell', card_type_arg='$rot' WHERE card_id='$sid'");
        }
        $this->notifyMoveStructure(clienttranslate('${player_name} places a structure'), $sid, [], $player_id);
        if (($this->isTapestryActive($player_id, 27)) && ($type <= 5)) { // MONARCHY
            $this->awardVP($player_id, 3, reason_tapestry(27));
        }
        // Update capital mat
        if ($oobounds)
            return;
        $riverfolk = $this->hasCiv($player_id, CIV_RIVERFOLK);
        $capital = $this->getCapitalData($player_id);
        for ($dx = 0; $dx < $width; $dx++) {
            for ($dy = 0; $dy < $height; $dy++) {
                if ($mask[$dx][$dy] != 1)
                    continue;
                $cx = $x + $dx;
                $cy = $y + $dy;
                $completed_block = true;
                if ($capital[$cx][$cy] > 0) {
                    if ($unpassable == 1 && $capital[$cx][$cy] == 1) {
                        if (!$terraforming_claimed) {
                            $this->awardVP($player_id, 5, reason_tapestry(39));
                            $terraforming_claimed = true;
                        }
                    } else {
                        $this->userAssertTrue(totranslate('Invalid structure placement'));
                    }
                    $completed_block = false; // if we are on unpassable territory it does not complete a district
                }
                $district_info_before = $this->getDistrictInfo($cx, $cy, $capital);
                $this->DbQuery("UPDATE capital SET capital_occupied='$type' WHERE player_id='$player_id' AND capital_x='$cx' AND capital_y='$cy'");
                if (!$district_info_before)
                    continue;
                // Check if this cell completed a block (district). If so, need to award a resource!
                $capital[$cx][$cy] = $type;
                $district_info = $this->getDistrictInfo($cx, $cy, $capital);
                if ($riverfolk) {
                    if (array_get($district_info_before['build_types'], BUILDING_IMPASS, 0) > 0) {
                        if (array_get($district_info['build_types'], BUILDING_IMPASS, 0) == 0) {
                            // last impassible slot is covered in district
                            $this->interruptBenefit();
                            $this->queueBenefitNormal(RES_ANY, $player_id, reason_civ(CIV_RIVERFOLK));
                        }
                    }
                }
                $completed_block = $completed_block && $district_info['complete'];
                if ($completed_block) {
                    $income_same = $district_info['unique_income'] == 1;
                    $this->interruptBenefit();
                    $this->queueBenefitNormal(RES_ANY, $player_id, reason('str', clienttranslate('complete district')));
                    if ($this->hasCiv($player_id, CIV_ARCHITECTS) && $income_same) { // Extra for Architect if all income types the same (and at least one).
                        $this->queueBenefitNormal(RES_ANY, $player_id, reason('str', clienttranslate('district architect')));
                    }
                    $dn = $district_info['district'];
                    $this->notifyAllPlayers("message", clienttranslate('${player_name} completes district #${dn}'), array(
                        'player_id' => $player_id, 'player_name' => $this->getActivePlayerName(), 'dn' => $dn
                    ));
                    $this->checkMysticPrediction(2, $player_id); // district
                    // trigger
                    $this->checkPrivateAchievement(5, $player_id);
                }
            }
        }
    }

    function place_structure($rot, $x, $y) {
        $this->checkAction('place_structure');
        $player_id = $this->getActivePlayerId();
        $bene = $this->getCurrentBenefitWithInfo();
        $bid = $bene['benefit_id'];
        $ct = null;
        if (array_get($bene, 'lm')) {
            // landmark ok
            $ct = BUILDING_LANDMARK;
        } else if (array_get($bene, 'r') == 'g' && array_get($bene, 'tt') == 'structure') {
            // income building ok   
            $ct = (int) array_get($bene, 'ct');
        } else {
            $this->systemAssertTrue("unexpected benefit on stack $bid");
        }

        $structure_data = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
        $structure_id = $structure_data['card_id'];
        $this->systemAssertTrue("unexpected structure type $ct", $ct == $structure_data['card_type']);
        $this->effect_placeOnCapitalMat($structure_id, $x, $y, $rot, $player_id);
        $this->clearCurrentBenefit($bene, true);

        $this->gamestate->nextState('next');
    }

    function getBlockCentre($x) {
        $reg = floor(($x - 3) / 3);
        return $reg * 3 + 4;
    }

    /* Return district info for coordinates */
    function getDistrictInfo($cx, $cy, $capital = null, $player_id = null) {
        if (!$capital) {
            if (!$player_id)
                $player_id = $this->getActivePlayerId();
            $capital = $this->getCapitalData($player_id);
        }
        $build_types = [];
        $income_types = [];
        if (!$this->onMat($cx, $cy)) {
            return false;
        }
        $rx = $this->getBlockCentre($cx);
        $ry = $this->getBlockCentre($cy);
        for ($di = -1; $di <= 1; $di++) {
            for ($dj = -1; $dj <= 1; $dj++) {
                $value = $capital[$rx + $di][$ry + $dj];
                if ($value == 1)
                    $btype = BUILDING_IMPASS;
                else if ($value == 0)
                    $btype = 0;
                else
                    $btype = $value - 1;
                array_inc($build_types, $btype);
                if ($btype >= BUILDING_MARKET && $btype <= BUILDING_ARMORY) {
                    array_inc($build_types, BUILDING_ANYINCOME);
                    array_inc($income_types, $btype);
                }
            }
        }
        $res = [
            'build_types' => $build_types, 'unique_income' => count($income_types),
            'complete' => array_get($build_types, 0, 0) == 0, 'district' => ($ry - 4) + ($rx - 4) / 3 + 1
        ];
        return $res;
    }

    function effect_techRefresh($player_id) {
        $this->drawTechCards(3, true);
        $this->notifyWithName("tech_refresh", clienttranslate('${player_name} refreshes the technology cards'), [], $player_id);
    }

    public function getStateName() {
        $state = $this->gamestate->state();
        return $state['name'];
    }

    function action_decline() {
        $this->checkAction('decline');
        $player_id = $this->getActivePlayerId();
        $stateName = $this->getStateName();
        switch ($stateName) {
            case 'explore': {
                    $args = $this->argExplore();
                    $decline = $args['decline'];
                    if ($decline) {
                        // no valid location, skip
                        $benefit_data = $this->clearCurrentBenefit();
                        $this->notifyWithName("message", clienttranslate('${player_name} declined ${ben_name}'), [
                            'ben_name' => $this->getBenefitName($benefit_data['benefit_type'])
                        ], $player_id);
                        $this->gamestate->nextState('next');
                        return;
                    } else {
                        $this->systemAssertTrue('Cannot decline in this state');
                    }
                };
                break;
            case 'conquer': {
                    $args = $this->argConquer();
                    if ($args['decline']) {
                        // no valid location, skip
                        $this->clearCurrentBenefit();
                        $this->notifyAllPlayers("message", clienttranslate('${player_name} has no valid targets, action is skipped'), array(
                            'player_id' => $player_id, 'player_name' => $this->getActivePlayerName()
                        ));
                        $this->gamestate->nextState('decline');
                        return;
                    } else {
                        $this->systemAssertTrue('Cannot decline in this state');
                    }
                }
                break;
            case 'upgradeTechnology':
                $this->clearCurrentBenefit();
                $this->notifyWithName("message", clienttranslate('${player_name} declines a technology upgrade'));
                break;
            case "resourceChoice":
            default:
                $this->clearCurrentBenefit();
                $this->gamestate->nextState('next');
                return;
        }
        $this->gamestate->nextState('benefit');
    }

    function action_unblock() {
        if ($this->getCurrentPlayerId() == $this->getActivePlayerId()) {
            $this->notifyWithName("message", '${player_name} declines current benefit to unblock the game'); // NOI18N
            $civ = $this->getObjectFromDB("SELECT * FROM benefit WHERE  benefit_category = 'civ' ORDER BY benefit_prerequisite, benefit_id LIMIT 1");
            $bene = $this->getCurrentBenefit();
            if ($civ && $bene && $civ['benefit_id'] != $bene['benefit_id']) {
                $this->interruptBenefit();
                $bid = $civ['benefit_id'];
                $this->DbQuery("UPDATE benefit SET benefit_prerequisite='0' WHERE benefit_id='$bid'");
            } else {
                $this->clearCurrentBenefit();
            }
            $trans = $this->gamestate->state()['transitions'];
            if (isset($trans['decline']))
                $this->gamestate->nextState('decline');
            else if (isset($trans['next']))
                $this->gamestate->nextState('next');
            else
                $this->gamestate->nextState('');
        } else {
            $this->notifyWithName("message", '${player_name} only can decline the benefit'); // NOI18N
        }
    }

    function actionEliminate() {
        $player_id = $this->getCurrentPlayerId();
        if (!$this->isRealPlayer($player_id)) {
            $this->systemAssertTrue("Cannot eliminate player - not a player");
        }
        if (!$this->isPlayerAlive($player_id)) {
            if (!$this->isPlayerEliminated($player_id)) {
                $this->eliminatePlayer($player_id);
            }
            $this->notifyWithName("message", clienttranslate('${player_name} leaves the game'));
        } else
            $this->systemAssertTrue("Cannot eliminate player - not finished");
    }

    function ownsLighthouseAndCanPlayIt($player_id, $bThrow = false) {
        if (!$this->hasCiv($player_id, CIV_UTILITARIENS)) {
            $this->systemAssertTrue("player does not have Utilitaties", $bThrow == false); // NON-I18N
            return false;
        }
        if (!$this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_39_5", $player_id)) {
            $this->systemAssertTrue("player does not have Lighthouse ability activated", $bThrow == false); // NON-I18N
            return false;
        }
        $items = $this->getCardsInHand($player_id, CARD_TERRITORY);
        if (count($items) < 2) {
            $this->userAssertTrue(self::_("You do not have enough Territory Tiles"), $bThrow == false);
            return false;
        }
        return true;
    }

    function action_activatedAbility($ability, $arg) {
        $player_id = $this->getActivePlayerId();
        $state = 18;
        // no check action it can be out of turn
        if ($ability == 'civ_39_5') { // Lighthouse
            $this->userAssertTrue(_("This is not your turn"), $this->getCurrentPlayerId() == $player_id);

            // Lighthouse: Once on each of your advancement turns you may spend 2 [TERRITORY TILE] to gain [ANY REOURCE]
            if ($this->ownsLighthouseAndCanPlayIt($player_id, true)) {
                $curr = $this->getCurrentBenefit();
                $this->interruptBenefit();
                $this->queueBonus(BE_TERRITORY, 2, BE_ANYRES, ORDER_NORMAL, $player_id);
                if ($curr == null && $this->getStateName() != 'playerTurnEnd')
                    $this->queueBenefitNormal(201, $player_id);
            } else {
                $this->systemAssertTrue("player does not have Lighthouse");
            }
        } else {
            $didit = false;
            $civs = $this->getAllCivs($player_id);
            foreach ($civs as $info) {
                $civ = $info['card_type_arg'];
                $civ = $this->getCivilizationInstance($civ);
                if ($civ->action_activatedAbility($player_id, $ability, $arg, $state)) {
                    $didit = true;
                    break;
                }
            }
            if (!$didit)
                $this->systemAssertTrue("Unknown action", false);
        }
        $this->gamestate->jumpToState($state);
    }

    function decline_tapestry() {
        $this->checkAction('decline_tapestry');
        $player_id = $this->getActivePlayerId();
        $this->notifyAllPlayers("message", clienttranslate('${player_name} declines to play a tapestry card'), array(
            'player_id' => $player_id, 'player_name' => $this->getActivePlayerName(),
        ));
        $this->clearCurrentBenefit();
        $this->gamestate->nextState('benefit');
    }

    function placeCraftsmen($slot) {
        $this->checkAction('placeCraftsmen');
        $player_id = $this->getActivePlayerId();
        /** @var Craftsmen */
        $inst = $this->getCivilizationInstance(CIV_CRAFTSMEN, true);
        $inst->moveCivCube($player_id, false, $slot);
        $this->gamestate->nextState('next');
    }

    function action_moveStructureOnto($location, $structure_id) {
        $player_id = $this->getActivePlayerId();
        $bene = $this->getCurrentBenefitWithInfo();
        $ben = $bene['benefit_type'];
        $args = $this->arg_moveStructureOnto();
        $this->clearCurrentBenefit($bene);
        switch ($ben) {
            case 170:

                $structure_id = $this->getOutpostId($structure_id, $player_id, true);
                if (array_search($location, $args['targets']) === false) {
                    $this->userAssertTrue("Invalid location, misclicked?");
                }
                $this->effect_placeOnMap($player_id, $structure_id, $location, clienttranslate('${player_name} places ${structure_name} on the map at ${coord_text}'), true);

                $coord = getPart($location, 1) . "_" . getPart($location, 2);
                $tile = $this->getCardInfoSearch(CARD_TERRITORY, null, 'map', null, $coord);
                if ($tile) {
                    $tile_type = $tile['card_type_arg'];
                    $benefit = $this->getRulesCard(CARD_TERRITORY, $tile_type, 'benefit', []);
                    $this->queueBenefitNormal($benefit, $player_id, reason('tile', $tile_type));
                }
                break;
            default:
                $this->effect_placeOnMap($player_id, $structure_id, $location, clienttranslate('${player_name} places ${structure_name} on the map at ${coord_text}'), true);

                break;
        }
        $this->gamestate->nextState('next');
    }

    function action_keepCard($ids, $dest) {
        $player_id = $this->getActivePlayerId();
        $bene = $this->getCurrentBenefitWithInfo();
        // action
        $this->effect_keepCard($ids, $player_id, $bene);
        $benefit_data = $this->getCurrentBenefit($bene);
        if ($benefit_data) $this->clearCurrentBenefit($bene, false);
        $this->gamestate->nextState('next');
    }

    function effect_keepCard($ids, $player_id, $bene) {
        $args = $this->arg_keepCard($bene);
        $cards = $args['cards'];
        $ben = $bene['benefit_type'];
        $keep = $this->getRulesBenefit($ben, 'keep', 1);
        if ($ids === null)
            $ids = array_keys($cards);
        $this->systemAssertTrue("can only keep $keep cards", $keep == count($ids));
        foreach ($ids as $card_id) {
            $this->checkNumber($card_id);
            $this->systemAssertTrue("invalid card selected", array_search($card_id, array_keys($cards)) !== false);
        }
        if (count($ids) > 0) {
            $card_id = $ids[0];
            $card = $this->getCardInfoById($card_id);
            $owner = $card['card_location_arg'];
        }
        if ($ben == 191 || $ben == 321) { // gain terr/spce card benefit and do not move it
            $this->systemAssertTrue("can only select 1", $keep == 1);
            $this->setTargetPlayer($owner);
            $card_benefit = $this->getRulesCard($card['card_type'], $card['card_type_arg'], 'benefit');
            $reason = reason($card['card_type'], $card['card_type_arg']);
            $this->queueBenefitNormal($card_benefit, $player_id, $reason);
            $this->queueBenefitNormal(["or" => [194, 401]], $player_id, $reason);
            return;
        }
        if ($ben == 192  || $ben == 320) { // gain tech card benefit and do not move it
            $this->systemAssertTrue("can only select 1", $keep == 1);
            $this->setTargetPlayer($owner);
            if ($ben == 192) {
                $slot = $card['card_location_arg2'];
            } else {
                $slot = 1; // circle
            }
            $reason = reason($card['card_type'], $card['card_type_arg']);
            $this->queueTechBenefit($card['card_type_arg'], $slot, $player_id);
            $this->queueBenefitNormal(["or" => [194, 401]], $player_id, $reason);
            return;
        }
        if ($ben == 193) { // gain tap card benefit and do not move it
            $this->systemAssertTrue("can only select 1", $keep == 1);
            $this->setTargetPlayer($owner);
            $tap_type = $card['card_type_arg'];
            $card_benefit = $this->getRulesCard($card['card_type'], $tap_type, 'benefit');
            $reason = reason($card['card_type'], $tap_type);
            $this->userAssertTrue(self::_('This card does not have WHEN PLAYED benefit'), $card_benefit);
            $this->queueBenefitNormal($card_benefit, $player_id, $reason);
            $this->queueBenefitNormal(["or" => [194, 401]], $player_id, $reason);
            return;
        }
        if ($ben == BE_GAMBLES_PICK || $ben ==  319) {
            if ($this->isWhenPlayedTapestry($card)) {
                foreach ($cards as $ocard_id => $ocard) {
                    if ($ocard_id != $card_id)
                        $this->effect_discardCard($ocard_id, $player_id);
                }
                // play
                $this->playTapestryCard($card_id, $player_id);
                return;
            } else {
                foreach ($cards as $ocard_id => $ocard) {
                    if ($this->isWhenPlayedTapestry($ocard)) {       // other card has when played benefit
                        $this->userAssertTrue(self::_('This card does not have WHEN PLAYED benefit'));
                    }
                }
            }
        }
        foreach ($ids as $card_id) {
            if ($ben != 172) { // keep civ card in draw area
                $extra = 0;
                //if ($ben==175) $extra=4; // recyclers tech card cannot be upgraded on first income turn
                $this->effect_moveCard($card_id, $player_id, 'hand', $player_id, $extra);
            }
            $this->effect_cardComesInPlay($card_id, $player_id, reason('be', $ben));
            unset($cards[$card_id]);
        }
        foreach (array_keys($cards) as $card_id) {
            $this->effect_discardCard($card_id, $player_id);
        }
    }

    function effect_revealHand($owner, $player_id = null, $bene = null) {
        if (!$bene)
            $bene = $this->getCurrentBenefitWithInfo();
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $cards = $this->getCardsInHand($owner, CARD_TAPESTRY);
        $this->notif('revealCards')->withPlayer($player_id)->withArg("cards", $cards)->notifyPlayer('');
        $this->notif()->withPlayer($player_id)->withPlayer2($owner)-> //
            withReason($bene['benefit_data'])-> //
            notifyAll(clienttranslate('${player_name} looks at ${player_name2} hand ${reason}'));
    }

    function effect_moveStructure($location, $building_type, $structure_id, ?int $player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if (!$building_type) {
            $info = $this->getStructureInfoById($structure_id);
            $this->systemAssertTrue("Not such structure $structure_id", $info);
            $building_type = $info['card_type'];
        }
        switch ($building_type) {
            case BUILDING_ARMORY:
            case BUILDING_HOUSE:
            case BUILDING_FARM:
            case BUILDING_MARKET:
                $structure_id = $this->dbGetIncomeBuildingOfType($building_type, true);
                $this->claimIncomeStructure($building_type, null);
                break;
            case BUILDING_LANDMARK:
                $this->systemAssertTrue("landmark id required", $structure_id);
                break;
            case BUILDING_CUBE:
                if (!$structure_id)
                    $structure_id = $this->addCube($player_id, 'hand');
                break;
            case BUILDING_OUTPOST:
                $structure_id = $this->getOutpostId($structure_id, $player_id);
                break;
            default:
                $this->systemAssertTrue("Not supported building type");
                break;
        }
        $this->systemAssertTrue("cannot determin structure id", $structure_id);

        if (startsWith($location, 'land_')) {
            $this->effect_placeOnMap($player_id, $structure_id, $location, false);
            return;
        }
        $message = clienttranslate('${player_name} places ${structure_name}');
        $this->DbQuery("UPDATE structure SET card_location='$location' WHERE card_id='$structure_id'");
        $this->notifyMoveStructure($message, $structure_id, [], $player_id);
    }

    function sendTrader($land_coords, $building_type) {
        $player_id = $this->getActivePlayerId();

        // VALIDITY check. Check that coords match an empty or single outpost space.
        $coords = getPart($land_coords, 1) . "_" . getPart($land_coords, 2);
        $targets = $this->getTraderTargets($player_id);
        if (!in_array($coords, $targets)) {
            throw new BgaUserException('Invalid location for Trader');
        }
        $map_data = $this->getMap();
        $hex = $map_data[$coords];
        $tile_structs = $hex['structures'];

        $reason = reason_civ(CIV_TRADERS);

        if ($building_type) {
            $this->systemAssertTrue('trader cannot place income building with default rules', $this->isAdjustments4());
            //--Gain an income building and place it on a territory with exactly 1 opponent outpost token and nothing else; the opponent immediately gains the benefit revealed by the income building. The opponent controls the territory.
            $message = clienttranslate('${player_name} places an icome building at ${coord_text}');
            $trader = $this->dbGetIncomeBuildingOfType($building_type, true);
            $this->systemAssertTrue("no income building of this type left", $trader);
            $this->claimIncomeStructure($building_type, null);
            $income_level = $this->dbGetIncomeTrackLevel($building_type, $player_id);
            $building_benefits = $this->income_tracks[$building_type][$income_level]['benefit'];
            $this->effect_placeOnMap($player_id, $trader, $land_coords, $message, false);
            $other_building = array_shift($tile_structs);
            $this->userAssertTrue(totranslate('TRADERS can only place income building on opponent\'s territory'), $other_building['card_location_arg'] != $player_id);
            $this->queueBenefitNormal($building_benefits, $other_building['card_location_arg'], $reason);
            return;
        }
        // Check player owns TRADERS and the token is available.
        $token_location = 'civ_16_%';
        $message = clienttranslate('${player_name} places a Trader token at ${coord_text}');
        $trader = $this->getUniqueValueFromDB("SELECT card_id FROM structure WHERE card_type='7' AND (card_location LIKE '$token_location') AND card_location_arg='$player_id' LIMIT 1");
        $this->systemAssertTrue("no traders left", $trader);
        $this->effect_placeOnMap($player_id, $trader, $land_coords, $message, true);

        // APPLY BENEFITS
        if (count($tile_structs) == 0) { // benefit is 1VP per adjacent opponent territory
            if ($this->isAdjustments4()) {
                return $this->userAssertTrue(totranslate('Cannot place on empty territory'));
            }

            $neighbours = $this->getNeighbourHexes($coords);
            $count = 0;
            $opponents = $this->getOpponentsStartingFromLeft($player_id);
            foreach ($neighbours as $neighbour) {
                foreach ($opponents as $opponent_id)
                    if ($this->isHexOwner($opponent_id, $neighbour, $map_data)) {
                        $count++;
                        break 1;
                    }
            }
            $this->awardVP($player_id, $count, $reason);
            return;
        }
        $other_building = array_shift($tile_structs);

        $map_tile_id = $hex['map_tile_id'];
        $tile_data = $this->territory_tiles[$map_tile_id];
        $benefits = array_get($tile_data, 'benefit');
        if ($other_building['card_type'] != '5') {
            return $this->userAssertTrue(totranslate('TRADERS can only share hexes with outposts') . toJson($other_building));
        }
        if ($this->isAdjustments4()) {
            //At the beginning of your income turns (2-5), choose one:
            //--Gain an income building and place it on a territory with exactly 1 opponent outpost token and nothing else; the opponent immediately gains the benefit revealed by the income building. The opponent controls the territory.
            //--Place a player token on a territory with exactly 1 opponent outpost token and nothing else: Gain the benefit on the territory (if any); you both share control of this territory for scoring purposes.
            //--Place a player token on a territory you control with exactly 1 outpost token and nothing else: Gain the benefit on the territory (if any).
        } else {
            $this->userAssertTrue(totranslate('TRADERS can only place a cube on opponent\'s territory'), $other_building['card_location_arg'] != $player_id);
        }
        if ($benefits)
            $this->queueBenefitInterrupt($benefits, $player_id, $reason);
    }

    function sendInventor($type) {
        $this->checkAction('sendInventor');
        $player_id = $this->getActivePlayerId();
        $benefit_data = $this->getCurrentBenefit(CIV_INVENTORS, 'civ');
        $this->systemAssertTrue("cannot find civ INVENTORS", $benefit_data);
        $this->benefitCashed($benefit_data); // the civ benefit.
        // VALIDITY CHECKS
        // 1. Check player owns INVENTORS and a token is available.

        $cubes = $this->getStructuresOnCiv(CIV_INVENTORS, BUILDING_CUBE);
        $cube = array_shift($cubes);
        if ($cube) {
            $inventor = $cube['card_id'];
        } else {
            $inventor = $this->addCube($player_id, 'hand');
        }

        // 2. Check that $id is a technology card in play
        $tech_card = $this->getObjectFromDB("SELECT card_id, card_location_arg player_id FROM card WHERE card_type='4' AND card_type_arg='$type' AND card_location='hand' AND (card_location_arg2 IS NULL OR card_location_arg2 <> 2)");
        $this->userAssertTrue(totranslate('Cannot use this tech card for inventor'), $tech_card != null);
        // Place token on tech card..
        $location = 'tech_card_' . $type;
        $reason = reason_civ(CIV_INVENTORS);
        $this->DbQuery("UPDATE structure SET card_location='$location',card_location_arg2='$reason' WHERE card_id='$inventor'");
        $this->notifyMoveStructure(clienttranslate('${player_name} places an Inventor on ${card_name}'), $inventor, [
            'card_name' => $this->tech_card_data[$type]['name'],
        ], $player_id);
        // Upgrade the tech card...  benefits can be given to opponent!...
        $this->upgradeTechCard($tech_card['card_id'], true);
        $this->gamestate->nextState('benefit');
    }

    function ageOfSail($pid, $tid) {
        $this->checkAction('ageOfSail');
        $player_id = $this->getActivePlayerId();
        $tap_card_data = $this->isTapestryActive($player_id, TAP_AGE_OF_SAIL);
        $this->systemAssertTrue("No tapestry ageOfSail", $tap_card_data);
        $territory_id = $this->getUniqueValueFromDB("SELECT card_id FROM card WHERE card_type='1' AND card_type_arg='$tid' AND card_location='hand' AND card_location_arg='$player_id'");
        $this->systemAssertTrue('Invalid territory tile', $territory_id != null);
        if ($tap_card_data['card_location_arg2'] == 1)
            throw new BgaUserException($this->_('You can only give away 1 territory card'));
        $players = $this->loadPlayersBasicInfosWithBots();
        $this->systemAssertTrue("invalid player id for age of sail", array_key_exists($pid, $players));

        $this->DbQuery("UPDATE card SET card_location_arg='$pid' WHERE card_id='$territory_id'");

        $this->notif("moveCard", $player_id)->withCard($territory_id)->withPlayer2($pid)->notifyAll(clienttranslate('${player_name} gives a territory ${card_name} to ${player_name2}'));
        $this->awardVP($player_id, 3, reason_tapestry(3));
        $card_id = $tap_card_data['card_id'];
        $this->DbQuery("UPDATE card SET card_location_arg2='1' WHERE card_id='$card_id'");
        $this->gamestate->nextState('loopback');
    }

    function formAlliance($pid) {
        $this->checkAction('formAlliance');
        $player_id = $this->getActivePlayerId();
        $card = $this->isTapestryActive($player_id, 5, true);
        $card_id = $card['card_id'];
        $this->DbQuery("UPDATE card SET card_location_arg2='$pid' WHERE card_id='$card_id'");
        $player_data = $this->loadPlayersBasicInfos();
        $this->notifyAllPlayers("alliance", clienttranslate('${player_name} forms an alliance with ${opp_name}'), array(
            'player_id' => $player_id, 'player_name' => $this->getActivePlayerName(),
            'opp_name' => $player_data[$pid]['player_name'], 'opp_id' => $pid,
        ));
        $this->clearCurrentBenefit();
        $this->gamestate->nextState('next');
    }

    function sendHistorian($pid, $tid, $token_id) {
        $this->checkAction('sendHistorian');
        /** @var Historians */
        $inst = $this->getCivilizationInstance(CIV_HISTORIANS, true);
        $inst->sendHistorian($pid, $tid, $token_id);
        $this->gamestate->nextState('benefit');
    }

    function declineBonus() {
        $this->checkAction('declineBonus');
        $player_id = $this->getActivePlayerId();
        $this->notifyAllPlayers("message", clienttranslate('${player_name} declines the bonus'), array(
            'player_id' => $player_id, 'player_name' => $this->getActivePlayerName(),
        ));
        $this->clearCurrentBenefit();
        $this->gamestate->nextState('next');
    }

    function action_acceptBonus($params, $dest) {
        $this->checkAction('acceptBonus');
        $player_id = $this->getActivePlayerId();
        $bonus_details = $this->getCurrentBenefit();
        $args = $this->argBonus();
        $this->benefitCashed($bonus_details['benefit_id']);
        $count = $args['pay_quantity'];
        $type = (int) $args['pay'];
        if (($count > 0) && (sizeOf($params) != $count))
            throw new feException('Invalid quantity for bonus');
        $this->userAssertTrue(totranslate('Cannot accept bonus without payment, use Decline'), count($params) > 0);
        $is_bonus = $args['benefit_category'] == 'bonus';
        $reason = $args['reason_data'];
        // Check the quantity and that player owns the ids..
        switch ($type) {
            case BE_GAIN_WORKER: // OLYMPIC HOST (1 worker for 10 VP) process as resources (e.g. fall through) although need to add tag to olympic host card.
                $oliben = $this->getCurrentBenefit(125); // olympic host on stack
                if ($oliben) {
                    // update benefit argument if opponent played it
                    if ($oliben['benefit_player_id'] != $player_id)
                        $this->setBenefitDataArg($oliben, 1);
                }
                //fallthough
            case BE_ANYRES: // resources
                foreach ($params as $res) {
                    $this->dbIncResourceCount($res, '*', null, -1, $player_id, $reason);
                }
                break;
            case BE_TERRITORY: // territory tiles.
                $items = $this->getCardsInHand($player_id, CARD_TERRITORY, $params);
                if (sizeOf($items) != sizeOf($params))
                    throw new feException('items do not belong to player');
                $this->effect_discardCard($items, $player_id);
                break;
            case BE_TAPESTRY: // tapestry cards.
            case 137:
            case 138:
                $items = $this->getCardsInHand($player_id, CARD_TAPESTRY, null, $params);
                if (sizeOf($items) != sizeOf($params))
                    throw new feException('items do not belong to player');
                $desttype = $this->getRulesBenefit($type, 'dest', 0);
                if ($desttype != 0) {
                    $this->systemAssertTrue("cannot pass card to yourself", $dest != $player_id);
                    // XXX validate desttype vs $dest
                    $this->effect_moveCard($items, $player_id, 'hand', $dest);
                } else
                    $this->effect_discardCard($items, $player_id);
                break;
            case BE_TECH_CARD: // tech cards.
                $items = $this->getCardsInHand($player_id, CARD_TECHNOLOGY, $params);
                if (sizeOf($items) != sizeOf($params))
                    throw new feException('items do not belong to player');
                $this->effect_discardCard($items, $player_id);
                break;
            default:
                break;
        }
        if ($is_bonus) {
            $this->incStat(1, 'bonuses', $player_id);
        }
        $benefit = $args['benefits'];
        $this->interruptBenefit();
        if ($count > 0) {
            $this->queueBenefitNormal($benefit, $player_id, $reason);
        } else {
            // gain benefit for evey resource
            for ($a = 0; $a < sizeOf($params); $a++) {
                $this->queueBenefitNormal($benefit, $player_id, $reason);
            }
        }
        $this->gamestate->nextState('next');
    }

    function getSelectedMapHex() {
        $map_id = $this->getGameStateValue('map_id');
        $coords = $this->getGameStateValue('map_coords_selected');
        if ($coords || $map_id == 0) {
            $x = floor($coords / 100) - 50;
            $y = $coords % 100 - 50;
            return $this->getMapHexData("${x}_${y}");
        } else {
            $map_data = $this->getObjectFromDB("SELECT * FROM map WHERE map_id='$map_id'");
            return $this->getMapHexData($map_data['map_coords']);
        }
    }

    function setSelectedMapHex($coord) {
        $x = getPart($coord, 0) + 50;
        $y = getPart($coord, 1) + 50;
        // $map = $this->getObjectFromDB("SELECT * FROM map WHERE map_coords='$coord'");
        // $map_id = $map ['map_id'];
        // $this->setGameStateValue('map_id', $map_id);
        $this->setGameStateValue('map_id', 0);
        $this->setGameStateValue('map_coords_selected', $x * 100 + $y);
        //$this->warn("selected hex $x * 100 + $y ".toJson($this->getSelectedMapHex()));
    }

    function decline_trap() {
        $this->checkAction('decline_trap');
        $this->clearCurrentBenefit(140);
        $player_id = $this->getActivePlayerId();
        $this->notifyWithName("message", clienttranslate('${player_name} does not play a trap'), [], $player_id);
        $this->gamestate->nextState('next');
    }

    function trap($card_id) {
        $this->checkAction('trap');
        $this->clearCurrentBenefit(140);
        $player_id = $this->getActivePlayerId();
        $this->effect_trap($card_id, $player_id);
        $this->gamestate->nextState('next');
    }

    function effect_trap($card_id, $player_id) {

        $map_data = $this->getSelectedMapHex();
        $card_data = $this->getObjectFromDB("SELECT card_id, card_type_arg FROM card WHERE card_id='$card_id' AND card_location='hand' AND card_location_arg='$player_id'");

        if ($card_data === null || $card_data['card_type_arg'] != 42) {
            throw new BgaUserException($this->_('You can only use a trap card'));
        }

        $coords = $map_data['map_coords'];
        $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$coords'");
        $land_coords = 'land_' . $coords;
        $structure_data = $this->getCollectionFromDB("SELECT card_id FROM structure WHERE card_location='$land_coords'");
        $this->DbQuery("UPDATE structure SET card_type_arg = 1-card_type_arg WHERE card_location='$land_coords'"); // toggle topple flag.
        $this->notifyWithName("trap", clienttranslate('${player_name} plays a trap card'), [
            'outposts' => $structure_data
        ], $player_id);
        $this->effect_discardCard($card_id, $player_id);
        $this->queueBenefitInterrupt(BE_ANYRES, $player_id, reason('str', clienttranslate('trap')));
    }

    function getCurrentBenefit($ben = null, $cat = 'standard') {
        if (is_array($ben) && isset($ben['benefit_id'])) {
            $id = $ben['benefit_id'];
            return $this->getObjectFromDB("SELECT * FROM benefit WHERE benefit_id = $id");
        }
        $this->systemAssertTrue("invalid array argument for getCurrentBenefit", !is_array($ben));
        if ($ben !== null)
            return $this->getObjectFromDB("SELECT * FROM benefit WHERE benefit_type = '$ben' AND benefit_category = '$cat' ORDER BY benefit_prerequisite, benefit_id LIMIT 1");
        else
            return $this->getObjectFromDB("SELECT * FROM benefit ORDER BY benefit_prerequisite, benefit_id LIMIT 1"); // should be current!
    }

    function getCurrentBenefitWithInfo() {
        $current_benefit = $this->getCurrentBenefit();
        if (!$current_benefit)
            return null;
        $ben = ($current_benefit == null) ? 0 : $current_benefit['benefit_type'];
        $info = array_get($this->benefit_types, $ben, []);
        return $current_benefit + $info;
    }

    function getCurrentBenefitType() {
        $current_benefit = $this->getCurrentBenefit();
        $bid = ($current_benefit == null) ? 0 : $current_benefit['benefit_type'];
        return $bid;
    }

    function getCurrentBenefitId() {
        $current_benefit = $this->getCurrentBenefit();
        $bid = ($current_benefit == null) ? 0 : $current_benefit['benefit_id'];
        return $bid;
    }

    function getPlayerTurn($player_id) {
        if ($this->isRealPlayer($player_id)) {
            return $this->getStat('turns_total', $player_id);
        } else
            return $this->getStat("game_turns_total_1");
    }

    function incPlayerTurn($player_id) {
        $this->incStat(1, 'turns_number');
        if ($this->isRealPlayer($player_id)) {
            $this->incStat(1, 'turns_total', $player_id);
        } else if ($player_id == PLAYER_AUTOMA || $this->isShadowEmpireOnly())
            $this->incStat(1, "game_turns_total_1");
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////
    /*
     * Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
     * These methods function is to return some additional information that is specific to the current
     * game state.
     */
    function __ARGS__() {
        // my anchor for IDE
    }

    function argExplore($player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $ageOfSail = $this->isTapestryActive($player_id, TAP_AGE_OF_SAIL);
        $militarism = $this->isTapestryActive($player_id, 26);
        $exploitation = $this->isTapestryActive($player_id, 18);
        $current_benefit = $this->getCurrentBenefit();
        $res = $this->notifArgsAddBen($current_benefit);
        $ben = $current_benefit['benefit_type'];
        $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
        $anywhere = ($flags & FLAG_ANYWHERE) != 0;
        $res['decline'] = false;
        if ($ben == 105) { // COLONIALISM        
            $res['reason'] = $this->getReasonFullRec(reason_tapestry(10));
            $res['colonialism'] = true;
            $targets = $this->getColonialismTargets($player_id);
            $res['title'] = clienttranslate("COLONIALISM: Select a territory TILE you control. Pre-printed hexes on the board are not tiles.");
        } else {
            $targets = $this->getExplorationTargets($anywhere, $player_id);
        }
        $targets = array_prefix_all($targets, "land_");
        if ($ageOfSail) {
            if ($ageOfSail['card_location_arg2'] == 1)
                $ageOfSail = false; // already played
        }
        $coal_baron = $this->getGameStateValue('coal_baron');
        if ($coal_baron && $this->isTapestryActive($player_id, TAP_COAL_BARON))
            $res['coal_baron'] = $this->getObjectFromDB("SELECT * FROM card WHERE card_id='$coal_baron'");
        else
            $res['coal_baron'] = null;
        if ($this->hasCiv($player_id, CIV_ISLANDERS)) {
            if ($ben == 178 || $ben == 179) {
                $targets = [];
                $res['decline'] = true;
                $res['title'] = $this->getBenefitName($ben);
            }
            $islander_targets = $this->getIslandersTargets();
            $targets = array_merge($targets, $islander_targets);
        }
        $tiles = $this->getCardsInHand($player_id, CARD_TERRITORY);
        $res += array(
            'exploration_targets' => $targets, 'ageOfSail' => $ageOfSail, 'exploitation' => $exploitation,
            'militarism' => $militarism, 'tiles' => array_keys($tiles), 'anywhere' => $anywhere
        );
        if ($militarism) {
            $res['outpost'] = $this->getOutpostId(null, $player_id, false);
        }
        $void = false;
        if (count($targets) == 0) {
            $void = true;
        }
        if (count($res['tiles']) == 0 && !isset($res['colonialism'])) {
            $void = true;
        }
        $res['void'] = $void;
        if ($void)
            $res['decline'] = true;
        return $res;
    }

    function argConquer($player_id = -1) {
        if ($player_id == -1)
            $player_id = $this->getActivePlayerId();
        $current_benefit = $this->getCurrentBenefit();
        $res = $this->notifArgsAddBen($current_benefit);
        $ben = $current_benefit['benefit_type'];
        $flags = (int) $this->getRulesBenefit($ben, 'flags', 0);
        $anywhere = ($flags & FLAG_ANYWHERE) != 0;
        $targets = ($ben == 119) ? $this->getToppleTargets() : $this->getConquerTargets(false, $anywhere, $player_id);
        $decline = false;
        $res['outpost'] = $outpost_id = $this->getOutpostId(null, $player_id, false);
        if (!$outpost_id) {
            $decline = true;
        }
        if (count($targets) == 0)
            $decline = true;
        return $res + array('targets' => $targets, 'decline' => $decline, 'anywhere' => $anywhere);
    }

    function getOutpostsInHand($player_id) {
        return $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type='5' AND (card_location NOT LIKE 'land%' AND card_location NOT LIKE 'civ_21\_%') AND card_location_arg='$player_id'");
    }

    function argConquerRoll() {
        $die_red = $this->getGameStateValue("conquer_die_red");
        $die_black = $this->getGameStateValue("conquer_die_black");
        return array('bid' => $this->getCurrentBenefitType(), 'die_red' => $die_red, 'die_black' => $die_black);
    }

    function argPlayerTurn() {
        $all = $this->getPossibleAdvances(false);
        $advance = array_keys($all, 1);
        $updates = $this->argUpdateCardList();
        return array('advances' => $advance, 'all_advances' => $all, 'technology_updates' => $updates);
    }

    function argPlayerTurnEnd() {
        return $this->argUndo();
    }

    function argUndo() {
        $move = $this->getNextMoveId();
        $undo_move = (int) $this->getGameStateValue('undo_move');
        $undo_moves_player = self::getGameStateValue('undo_moves_player');
        return array(
            'partial_undo' => $this->getGameStateValue('partial_undo'), 'move' => $move,
            'undo_move' => $undo_move, 'undo_player_id' => $undo_moves_player
        );
    }

    function argTrackSelect() {
        $res = [];
        $this->addBenefitData($res);
        $ben = $res['bid'];
        $player_id = $this->getActivePlayerId();
        $s = $this->getRulesBenefit($ben, 's', '');
        $res['cubes'] = [];
        $res['spots'] = [];
        if ($s == 'c') {
            $sflags = (int) $this->getRulesBenefit($ben, 'sflags', 0); // defines players
            $aflags = (int) $this->getRulesBenefit($ben, 'aflags', 0); // defines advance/regression or special
            $adv = (int) $this->getRulesBenefit($ben, 'adv', 0); // defines exact number of steps for advance/regress if FLAG_POSEXACT is set
            $t = (int) $this->getRulesBenefit($ben, 't', 0); // defines track, 0 all tracks
            $cubes_info = $this->getCubesSpecific($player_id, $sflags, $aflags, $t, $adv);
            // track/spot choosing benefit
            $cubes = [];
            foreach ($cubes_info as $info) {
                $cube_id = $info["card_id"];
                $cubes[] = "cube_$cube_id";
            }
            $res['cubes'] = $cubes;
            if (count($cubes) == 0) {
                $res['decline'] = true;
            }
        }
        // track spot select
        if ($ben == BE_TINKERERS_4) {
            $map = $this->getClosestBonus($player_id);
            $res['spots'] = array_values($map);
            $res['cubes'] = array_keys($map);
            if (count($map) == 0) {
                $res['decline'] = true;
            }
        }
        return $res;
    }

    function argReason() {
        return $this->addBenefitData();
    }

    function argBuildingSelect() {
        $player_id = $this->getActivePlayerId();
        $arr = $this->addBenefitData();
        $ben = $arr['bid'];
        $arr['choices'] = [];
        switch ($ben) {
            case BE_GAIN_ANY_INCOME_BUILDING: // 110
            case 144: // select building
                for ($type = 1; $type <= 4; $type++) {
                    $building = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='income' AND card_location_arg='$player_id' AND (card_type='$type') LIMIT 1");
                    if ($building)
                        $arr['choices'][$building['card_id']] = $building;
                }
                break;
            case 111: // select landmark
                $lm = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg2 <= 12 AND card_location LIKE 'landmark_mat_slot%'");
                $arr['choices'] = $lm;
                $arr['title'] = clienttranslate('${You} may choose any remaining landmark from the landmark\'s mat');
                break;
            case 305: // select landmark tier 2
                $lm = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location_arg2 in (2,6,9,10) AND card_location LIKE 'landmark_mat_slot%'");
                $arr['choices'] = $lm;
                $arr['title'] = clienttranslate('${You} may choose tier II landmark from the landmark\'s mat');
                break;
            default:
                $action = $this->getRulesBenefit($ben, 'r', 'x');
                if ($action == 'a') {
                }
                break;
        }
        return $arr;
    }

    function argResearch() {
        $res = array(
            'science' => $this->getGameStateValue('science_die'),
            'empiricism' => $this->getGameStateValue('science_die_empiricism')
        );
        $res['cubes'] = [];
        $res['all_advances'] = $this->getPossibleTrackChoices($res['science'], 1, $res['cubes']);
        if ($res['empiricism'] != 0 && $res['empiricism'] != $res['science'])
            $res['all_advances'] += $this->getPossibleTrackChoices($res['empiricism'], 1, $res['cubes']);
        $this->addBenefitData($res);
        return $res;
    }

    function canDeclineTapestry() {
        $player_id = $this->getActivePlayerId();
        $tyranny = $this->isTapestryActive($player_id, TAP_TYRANNY);
        $herald = ($this->getCurrentEra($player_id) == 1);
        return ($tyranny || $herald);
    }

    function argTapestryCard() {
        $res = array('decline' => $this->canDeclineTapestry());
        $this->addBenefitData($res);
        $player_id = $this->getActivePlayerId();
        $tyranny = $this->isTapestryActive($player_id, TAP_TYRANNY);
        if ($tyranny) {
            $card_id = array_get($res, 'data');
            if ($card_id) {
                $info = $this->cards->getCard($card_id);
                $res['just_played'] = $info ? $info['type_arg'] : 0;
                $res['tyranny'] = $tyranny;
            }
        }
        return $res;
    }

    function arg_moveStructureOnto() {
        $player_id = $this->getActivePlayerId();
        $benefit_data = $this->getCurrentBenefit();
        $res = $this->notifArgsAddBen($benefit_data);
        $ben = $benefit_data['benefit_type'];
        switch ($ben) {
            case 170:
                $outposts = $this->getOutpostsInHand($player_id);
                $res['outpost'] = $outpost_id = $this->getOutpostId(null, $player_id, false);
                if (count($outposts) == 0 && $this->hasCiv($player_id, CIV_MILITANTS)) { // MILITANTS
                    $res['structures'] = [$outpost_id];
                } else {
                    $res['structures'] = array_keys($outposts);
                }
                $res['targets'] = [];
                $opp_outposts = $this->getStructuresSearch(BUILDING_OUTPOST, 0, "land_%", $this->getOpponentsStartingFromLeft($player_id));
                foreach ($opp_outposts as $out) {
                    $location = $out['card_location'];
                    $count = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM structure WHERE card_location='$location'");
                    if ($count == 1) {
                        $res['targets'][] = $location;
                    }
                }
                $res['title'] = clienttranslate('${You} must place an outpost on the map');
                break;
        }
        return $res;
    }

    function arg_keepCard($benefit_data = null) {
        $player_id = $this->getActivePlayerId();
        if (!$benefit_data) {
            $benefit_data = $this->getCurrentBenefit();
        }
        $args = [];
        $ben = $benefit_data['benefit_type'];
        if ($ben == 191 || $ben == 192 || $ben == 193 || $ben == 321 || $ben == 320) {
            $neighbours = $this->getPlayerNeighbours($player_id, false);
            $cards = [];
            foreach ($neighbours as $other) {
                if ($ben == 191) {
                    $cards += $this->getCardsSearch(CARD_TERRITORY, null, 'hand', $other);
                    $cards += $this->getCardsSearch(CARD_SPACE, null, 'hand', $other);
                } else if ($ben == 192) {
                    $cards += $this->getCardsSearch(CARD_TECHNOLOGY, null, 'hand', $other, 1);
                    $cards += $this->getCardsSearch(CARD_TECHNOLOGY, null, 'hand', $other, 2);
                } else if ($ben == 320) {
                    $cards += $this->getCardsSearch(CARD_TECHNOLOGY, null, 'hand', $other);
                } else if ($ben == 193) {
                    $cards += $this->getCardsSearch(CARD_TAPESTRY, null, 'era%', $other);
                } else if ($ben == 321) {
                    $cards += $this->getCardsSearch(CARD_TERRITORY, null, 'hand', $other);
                }
            }
            $args['title'] = $this->getBenefitName($ben);
        } else {
            $cards = $this->getCardsSearch(null, null, 'draw', $player_id);
            if ($ben == BE_GAMBLES_PICK || $ben == 319) {
                $args['title'] = $this->getBenefitName($ben);
            }
        }
        $args = $this->notifArgsAddBen($benefit_data, $args);
        $args['cards'] = $cards;
        return $args;
    }

    function getRulesBenefit($ben, $field = 'flags', $def = null) {
        if ($ben == 0)
            return $def;
        return array_get_def($this->benefit_types, $ben, $field, $def);
    }

    function getRulesCard($card_category, $card_type, $field = 'benefit', $def = null) {
        if ($card_type == 0)
            return $def;
        return array_get_def($this->card_types[$card_category]['data'], $card_type, $field, $def);
    }

    function getRulesCiv($card_type, $field = 'r', $def = null) {
        return $this->getRulesCard(CARD_CIVILIZATION, $card_type, $field, $def);
    }

    function argBenefitOption() {
        return $this->argBenefitChoice();
    }

    function argAddTrackChoice($ben, &$res) {
        // $this->debugConsole("calling argAddTrackChoice $ben $oid");
        $track = (int) $this->getRulesBenefit($ben, 't', 0);
        if ($track && $track <= 4) {
            $change = (int) $this->getRulesBenefit($ben, 'adv', 1);
            $res['tracks'][$ben]['cubes'] = [];
            $res['tracks'][$ben]['spots'] = $this->getPossibleTrackChoices($track, $change, $res['tracks'][$ben]['cubes']);
            $res['tracks'][$ben]['track'] = $track;
            $res['tracks'][$ben]['change'] = $change;
            $res['tracks_change'] = $change; // it should be same for all
        }
    }

    function argBenefitChoice() {
        // Need to provide array of options
        // For each: benefit_id, along with benefit_type.
        $benefit_data = $this->getCurrentBenefit();
        $comp = $this->getCompositeBenefit($benefit_data);
        $choice_cat = $comp['op'];
        $types = $comp['types'];
        $options = [];
        foreach ($types as $type) {
            $options[] = (int) $type;
        }
        $res = $this->notifArgsAddBen($benefit_data);
        foreach ($options as $type) {
            $this->argAddTrackChoice($type, $res);
        }
        $res['options'] = $options;
        $res['c'] = $choice_cat;
        return $res;
    }

    function argCivAbility() {
        $current_benefit = $this->getCurrentBenefit();
        $this->systemAssertTrue("missing benefit", $current_benefit);
        if ($current_benefit['benefit_category'] != 'civ')
            return [];
        $player_id = $current_benefit['benefit_player_id'];
        $order = $current_benefit['benefit_prerequisite'];
        $this->systemAssertTrue("wrong benefit category for civ", $current_benefit['benefit_category'] == 'civ');
        $all = $this->getCollectionFromDB("SELECT * FROM benefit WHERE benefit_category = 'civ' AND benefit_prerequisite = '$order' ORDER BY benefit_id");
        $res = [];
        $res['benefits'] = $all;
        foreach ($all as $i => $b) {
            $cid = $b['benefit_type'];
            $res['benefits'][$i] = $this->argCivAbilitySingle($player_id, $cid, $b);
        }
        return $res;
    }

    function argCivAbilitySingle($player_id, $civ, $benefit) {
        $data = $benefit;
        $condition = $benefit['benefit_data'];
        $data['reason'] = $this->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data['slots'] = [];
        $slots = $this->getRulesCiv($civ, 'slots');
        $slot_choice = $this->getRulesCiv($civ, 'slot_choice');
        $is_midgame = $condition == 'midgame';
        $civinst = $this->getCivilizationInstance($civ);
        if ($is_midgame) {
            switch ($civ) {
                case CIV_RENEGADES:
                case CIV_CRAFTSMEN:
                case CIV_GAMBLERS:
                    return $civinst->argCivAbilitySingle($player_id, $benefit);
                case CIV_ENTERTAINERS:
                    $data['slots'] = array_keys($slots);
                    break;
                case CIV_MERRYMAKERS:
                    $token_data = $this->getStructuresOnCiv($civ, BUILDING_CUBE);
                    unset($slots[1]);
                    unset($slots[5]);
                    unset($slots[9]);
                    foreach ($token_data as $token) {
                        $slot = getPart($token['card_location'], 2);
                        if (isset($slots[$slot]))
                            unset($slots[$slot]);
                    }
                    $data['slots'] = array_keys($slots);
                    break;
                case CIV_ADVISORS:
                    $choices = [];
                    $choices[0]['benefit'] = [];
                    for ($i = 1; $i <= 3; $i++) {
                        $choices[0]['benefit'][] = ['p' => 137, 'g' => 505];
                    }
                    $data['slots_choice'] = $choices;
                    $data['decline'] = true;
                    break;
                case CIV_UTILITARIENS:
                    $own_landmarks = $this->getStructuresSearch(BUILDING_LANDMARK, null, null, $player_id);
                    $cube1 = $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_{$civ}_9", $player_id);
                    $cube2 = $this->getStructureInfoSearch(BUILDING_CUBE, null, "civ_{$civ}_10", $player_id);
                    foreach ($own_landmarks as $landmark_data) {
                        $landmark_type = $landmark_data['card_location_arg2'];
                        $slot = $this->getCivSlotWithValue(CIV_UTILITARIENS, "lm", $landmark_type);
                        if ($slot) {
                            $cubeon = $this->getStructureInfoSearch(BUILDING_CUBE, null, $slot, $player_id);
                            if ($cubeon)
                                continue;
                            $i = getPart($slot, 2);
                            $toname = $this->landmark_data[$landmark_type]['name'];
                            $data['slots_choice'][$i]['title'] = $toname;
                        }
                    }
                    if (count($data['slots_choice']) == 0) {
                        $data['title'] = clienttranslate('You have no landmarks to choose from');
                    } else
                        $data['title'] = clienttranslate('Pick up to ${num} slots');
                    $data['targets'] = [];
                    if ($cube1 != null)
                        $data['targets'][] = $cube1['card_id'];
                    if ($cube2 != null)
                        $data['targets'][] = $cube2['card_id'];
                    $data['num'] = count($data['targets']);
                    break;
            }
            return $data;
        }
        $data['slots_choice'] = [];
        if ($slots) {
            $only = [];
            if ($slot_choice == 'unoccupied') {
                $token_data = $this->getStructuresOnCiv($civ, BUILDING_CUBE);
                foreach ($token_data as $token) {
                    $slot = getPart($token['card_location'], 2);
                    unset($slots[$slot]);
                }
            }
            if ($slot_choice == 'occupied') {
                $token_data = $this->getStructuresOnCiv($civ, BUILDING_CUBE);
                foreach ($token_data as $token) {
                    $slot = getPart($token['card_location'], 2);
                    $only[$slot] = 1;
                }
            }
            if ($slot_choice) {
                foreach ($slots as $i => $info) {
                    if (count($only) == 0 || isset($only[$i]))
                        $data['slots_choice'][$i]['benefit'] = $info['benefit'];
                }
                if (count($slots) > 1)
                    $data['title'] = clienttranslate('Choose one of these options');
            }
        }
        switch ($civ) {
            case CIV_GAMBLERS:
                return $civinst->argCivAbilitySingle($player_id, $benefit);
            case CIV_TRADERS:

                $targets = $this->getTraderTargets($player_id);
                $data['targets'] = $targets;

                break;
            case CIV_LEADERS:
                $data['slots'] = array_keys($slots);
                break;
            case CIV_ALIENS:
                $space_type = CARD_SPACE;
                $token_data = $this->getCollectionFromDB("SELECT * FROM card WHERE card_location_arg='$player_id' AND card_type='$space_type' AND card_location='civilization_$civ'");
                $space_tile = 0;
                $ben = $slots[1]['benefit'];
                if (count($token_data) >  0) {
                    $space_tile = array_key_first($token_data);
                    if ($this->isAdjustments8()) {
                        $data['slots_choice'][1]['benefit'] = [$ben];
                    } else {
                        // pay to explore
               
                        $data['slots_choice'][1]['benefit'] = ['p' => BE_ANYRES, 'g' => $ben];
                    }
                }
                $data['slots_choice'][1]['discard_id'] = $space_tile;
                break;
            case CIV_ARCHITECTS:
                $data['capital'] = $this->argPlaceStructure(BUILDING_CUBE);
                break;
            case CIV_ADVISORS:
                $cards = $this->getCardsInHand($player_id, CARD_TAPESTRY);
                if (count($cards) > 4) {
                    $count = count($cards) - 4;
                    $data['slots_choice'][1]['benefit'] = [136,  array_fill(0, $count, 181), BE_ANYRES];
                    $data['title'] = clienttranslate('Discard down to 4 tapestry cards and gain any resource');
                } else {
                    $data['title'] = clienttranslate('You have 4 or less tapestry cards in hand');
                    $data['slots_choice'][1]['benefit'] = [136]; // 1 vp per tapestry in hand
                }
                break;
            case CIV_INFILTRATORS:
                $data['title'] = clienttranslate('Chose to place an outpost or gain vp');
                $players = $this->loadPlayersBasicInfosWithBots();
                unset($data['slots_choice'][1]); // remove this it will be replace with choices with players
                $no = 0;
                foreach ($players as $opponent_id => $player_info) {
                    if ($opponent_id == $player_id)
                        continue;
                    if ($opponent_id == PLAYER_SHADOW)
                        continue;
                    $no = $no + 1;
                    $o_count = count($this->getOutpostsInHand($opponent_id));
                    $start_info = $this->getStartingPosition($opponent_id);
                    $location = $start_info['location'];
                    $cubes = $this->getStructuresSearch(BUILDING_CUBE, null, $location, $player_id, null, false);
                    $prev_cubes = count($cubes);
                    $data['slots_choice'][$no]['benefit'] = [171];
                    $data['slots_choice'][$no]['player_id'] = $opponent_id;
                    $data['slots_choice'][$no]['count'] = $o_count;
                    $data['slots_choice'][$no]['player_name'] = $this->getPlayerNameById($opponent_id);
                    $data['slots_choice'][$no]['title'] = clienttranslate('Give token to ${player_name}: ${count} VP');
                    $data['slots_choice'][$no]['cubes'] = $prev_cubes;
                    if ($prev_cubes == 2) { // 3rd cube is about to be added
                        $data['slots_choice'][$no]['benefit'][] = [BE_GAIN_CIV];
                        $data['slots_choice'][$no]['title'] = clienttranslate('Give token to ${player_name}: ${count} VP + Civilization');
                    }
                }
                break;
            case CIV_MERRYMAKERS:
                $income_turn = $this->getCurrentEra($player_id);
                $token_data = $this->getStructuresOnCivExceptArg($civ, BUILDING_CUBE, $income_turn);
                $data['slots'] = $this->getLinkedSlots($civ, $token_data);
                break;
            case CIV_CHOSEN:
            case CIV_ENTERTAINERS:
                $token_data = $this->getStructuresOnCiv($civ, BUILDING_CUBE);
                $data['slots'] = $this->getLinkedSlots($civ, $token_data);
                break;
            case CIV_SPIES:
                $data['slots'] = array_keys($slots);
                $data['slots_choice'] = [];
                $data['title'] = clienttranslate('Choose the slot on the civilization mat');
                break;
            case CIV_TREASURE_HUNTERS:
                $targets = $this->getConquerTargets(false, false, $player_id, true);
                $map = $this->getMapDataFromDb('map');

                foreach ($slots as $i => $info) {
                    if (isset($data['slots_choice'][$i])) {
                        $terrain = $info['ter'];
                        $data['slots_choice'][$i]['targets'] = [];
                        foreach ($targets as $coord) {
                            if (array_key_exists($terrain, $map[$coord]['terrain_types'])) {
                                $data['slots_choice'][$i]['targets'][] = $coord;
                            }
                        }
                    }
                }
                $data['targets'] = $targets;
                break;
            case CIV_UTILITARIENS:
                $condtype = getReasonPart($condition, 0);
                if ($condtype == 'triggered') {
                    $landmark = getReasonPart($condition, 2);
                    // $this->debugConsole("$condtype $landmark");
                    $slot = $this->getCivSlotWithValue(CIV_UTILITARIENS, "lm", $landmark);
                    if ($slot) {
                        $cubes = $this->getStructuresOnCiv(CIV_UTILITARIENS, BUILDING_CUBE);
                        $slots = $this->getRulesCiv(CIV_UTILITARIENS, 'slots');
                        foreach ($cubes as $cube) {
                            $i = getPart($cube['card_location'], 2);
                            $landmark_type = $slots[$i]['lm'];
                            if ($landmark_type)
                                $toname = $this->landmark_data[$landmark_type]['name'];
                            else
                                $toname = $slots[$i]['title'];
                            $data['slots_choice'][$i]['title'] = $toname;
                        }
                        $data['title'] = clienttranslate('Move token into ${spot_name} from');
                        $data['spot_name'] = $this->landmark_data[$landmark]['name'];
                    }
                }
                break;
            default:
                break;
        }
        $income_trigger = $this->getRulesCiv($civ, 'income_trigger', null);
        $decline = array_get($income_trigger, 'decline', true);
        $data['decline'] = $decline;
        return $data;
    }

    function getLinkedSlots($civ, $token_list) {
        $slots = $this->getRulesCiv($civ, 'slots', []);
        $bslots = [];
        // add all slots connected to tokens on token_list
        foreach ($token_list as $token) {
            if ($token == null)
                return [];
            $slot = getPart($token['card_location'], 2);
            $slot_info = array_get($slots, $slot, []);
            $links = array_get($slot_info, "link");
            if ($links) {
                $bslots = array_merge($bslots, $links);
            }
        }
        // remove slots blocked by current cubes
        $token_data = $this->getStructuresOnCiv($civ, BUILDING_CUBE);
        foreach ($token_data as $token) {
            $slot = getPart($token['card_location'], 2);
            array_remove_value($bslots, $slot);
        }
        return $bslots;
    }

    function argResourceChoice() {
        return $this->addBenefitData();
    }

    function argUpgradeTechnology() {
        $res = $this->addBenefitData();
        $res['possible'] = $this->argUpdateCardList();
        return $res;
    }

    function argUpdateCardList($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $techs = $this->getCardsInHand($player_id, 4);
        $techs_ok = [];
        foreach ($techs as $card) {
            if ($this->isUpgradePrereqMet($card['card_type_arg'])) {
                $techs_ok[] = $card['card_type_arg'];
            }
        }
        return $techs_ok;
    }

    function getTokenTypeName($type) {
        $type = $this->getNormalizedReasonId($type);
        switch ($type) {
            case 'other':
                return '';
            case 'die':
                return '';
            case 'tapestry':
            case CARD_TAPESTRY:
                return clienttranslate("Tapestry card");
            case 'be':
                return clienttranslate("Benefit");
            case 'tech':
            case CARD_TECHNOLOGY:
                return clienttranslate("Tech card");
            case 'spot':
                return clienttranslate("Track spot");
            case 'inspot':
                return clienttranslate("Income track spot");
            case 'civ':
            case CARD_CIVILIZATION:
                return clienttranslate("Civilization");
            case CARD_TERRITORY:
            case 'tile':
                return clienttranslate("Territory");
            case CARD_SPACE:
            case 'space':
                return clienttranslate("Space Tile");
            case 'achi':
                return clienttranslate("ACHIEVEMENT");
            default:
                $this->warn("unsupported type $type for name getTokenTypeName");
                return "$type";
        }
    }

    function getReasonId($reason) {
        if (!$reason)
            return 'other';
        $reason_type = '';
        $data_arr = explode(':', $reason);
        if (count($data_arr) > 1) {
            $reason_type = $data_arr[1];
        }
        return $this->getNormalizedReasonId($reason_type);
    }

    function getReasonValue($reason) {
        if (!$reason)
            return '';
        $data_arr = explode(':', $reason);
        if (count($data_arr) > 2) {
            return $data_arr[2];
        }
        return '';
    }

    function getReasonArg($reason, $i) {
        return getReasonPart($reason, $i);
    }

    private function getNormalizedReasonId($reason_type) {
        if (!$reason_type)
            $reason_type = 'other';
        switch ($reason_type) {
            case '':
            case 'str':
                return 'other';
            case 'tapestry':
            case 'tap':
            case CARD_TAPESTRY:
                return 'tapestry';
            case 'be':
                return 'be';
            case 'tech':
            case CARD_TECHNOLOGY:
                return 'tech';
            case 'spot':
                return 'spot';
            case 'civ':
            case CARD_CIVILIZATION:
                return 'civ';
            case 'ter':
            case 'tile':
            case CARD_TERRITORY:
                return 'tile';
            case 'space':
            case CARD_SPACE:
                return 'space';
            case 'inspot':
            case 'die':
            case 'other':
            case 'achi':
                return "$reason_type";
            default:
                $this->warn("unsupported type $reason_type for name getReasonId");
                return "$reason_type";
        }
        return "$reason_type";
    }

    function getTokenName($type, $value) {
        $type = $this->getNormalizedReasonId($type);
        switch ($type) {
            case 'die':
            case 'achi':
                return $value;
            case 'other': // as is
                return $value;
            case 'tapestry':
                return $this->tapestry_card_data[$value]['name'];
            case 'be':
                if ($value > 500 && $value < 599) {
                    return clienttranslate("VP");
                }
                if (!$value) return "";
                return $this->benefit_types[$value]['name'];
            case 'tech':
                return $this->tech_card_data[$value]['name'];
            case 'spot':
                $arr = explode('_', $value);
                $track = $arr[0];
                $spot = $arr[1];
                return $this->tech_track_data[$track][$spot]['name'];
            case 'civ':
                return $this->civilizations[$value]['name'];
            case 'tile':
                return $value; //$this->territory_tiles
            case 'space':
                return $value;
            case 'inspot':
                $arr = explode('_', $value);
                $track = $arr[0];
                $spot = $arr[1];
                if (!$spot)
                    return $this->income_tracks[$track]['name'];
                return $this->income_tracks[$track][$spot]['name'];
            default:
                $this->warn("unsupported type '$type' for name getTokenName");
                return "$type:$value";
        }
    }

    function getReasonName($data) {
        $data_arr = explode(':', $data);
        if (count($data_arr) > 2) {
            $type = $data_arr[1];
            $value = $data_arr[2];
            return $this->getTokenName($type, $value);
        }
        if (is_numeric($data))
            return '';
        return $data;
    }

    function getReasonType($data) {
        return $this->getTokenTypeName($this->getReasonId($data));
    }

    /* this only can be used in notifications */
    function getReasonFullRec($data, $brackets = true) {
        if (is_numeric($data) || !$data)
            return '';
        $data_arr = explode(':', $data);
        if (count($data_arr) > 2) {
            $type = $this->getReasonId($data);
            $value = $data_arr[2];
            $type_name = $this->getTokenTypeName($type);
            $name = $this->getTokenName($type, $value);
            if (!$type_name)
                $res = ['log' => '${n}', 'args' => ['n' => $name, 'i18n' => ['n']]];
            else {
                $res = ['log' => '${t}: ${n}', 'args' => ['t' => $type_name, 'n' => $name, 'i18n' => ['t', 'n']]];
                if ($type == 'spot') {
                    $arr = explode('_', $value);
                    $track = $arr[0];
                    $spot = $arr[1];
                    $res['args'] = $this->notifArgsAddTrackSpot($track, $spot, $res['args']);
                    $res['log'] = '${t}: ${spot_name}';
                }
            }
        } else {
            $res = ['log' => '${n}', 'args' => ['n' => $data, 'i18n' => ['n']]];
        }
        if ($brackets) {
            $res['log'] = "(" . $res['log'] . ")";
        }
        return $res;
    }

    function parsePay($benefit_cat) {
        $items = explode(",", $benefit_cat);
        $this->systemAssertTrue("invalid cat $benefit_cat", $items[0] == 'p');
        $pay = null;
        $q = 0;
        $benefits = [];
        array_shift($items);
        $mode = 0;
        while ($item = array_shift($items)) {
            if ($mode == 0) {
                if ($item == 'g') {
                    $mode = 1;
                    continue;
                }
                if ($pay == null) {
                    $pay = (int) $item;
                    $q++;
                } else if ($item == $pay) {
                    $q++;
                } else
                    $this->systemAssertTrue("invalid cat $benefit_cat");
            } else {
                $benefits[] = (int) $item;
            }
        }
        return [$pay, $q, $benefits];
    }

    function argBonus() {
        $benefit = $this->getCurrentBenefit();
        //$this->error(json_encode($benefit, JSON_PRETTY_PRINT));
        $benefit_cat = $benefit['benefit_category'];
        $benefit_data = $benefit['benefit_data'];
        $benefits = [];
        $name = null;
        $benefit['reason_data'] = $benefit_data;
        if ($benefit_cat == 'civ') {
            return $benefit; // not suppose to happen in bonus state
        }
        if ($benefit_cat == 'bonus') {
            if ($benefit_data) {
                $benefits = explode(",", $benefit_data);
            }
            $benefit['reason_data'] = reason('str', clienttranslate('bonus'));
            $benefit['pay_quantity'] = $benefit['benefit_quantity'];
            $benefit['pay'] = (int) $benefit['benefit_type'];
        } else {
            list($pay, $q, $benefits) = $this->parsePay($benefit_cat);
            $benefit['pay_quantity'] = $q;
            $benefit['pay'] = $pay;
        }
        $benefit['reason'] = $this->getReasonFullRec($benefit['reason_data'], true);
        $benefit_id = $benefits[0]; // TODO
        $benefit['benefits'] = $benefits;
        $name = $this->getBenefitName($benefit_id);
        if (!$name)
            $name = "unknown";
        $benefit += ['i18n' => ['bonus_name'], 'bonus_name' => $name];
        return $benefit;
    }

    function getPayResourceCount($pay_benefit_type, $player_id) {
        $actual = 100;
        // Check the quantity and that player owns the ids..
        switch ($pay_benefit_type) {
            case BE_GAIN_WORKER: // OLYMPIC HOST (1 worker for 10 VP) process as resources (e.g. fall through) although need to add tag to olympic host card.
                $actual = $this->getResourceCountAll($player_id, $pay_benefit_type);
                break;
            case BE_ANYRES: // resources
                $actual = $this->getResourceCountAll($player_id);
                break;
            case BE_TERRITORY: // territory tiles.
                $actual = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE (card_type='1')  AND (card_location='hand') AND (card_location_arg='$player_id')");
                break;
            case BE_TAPESTRY: // tapestry cards.
                $actual = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE (card_type='3') AND (card_location='hand') AND (card_location_arg='$player_id')");
                break;
            case BE_TECH_CARD: // tech cards.
                $actual = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE (card_type='4') AND (card_location='hand') AND (card_location_arg='$player_id')");
                break;
            default:
                break;
        }
        return $actual;
    }

    function stBonus() {
        $args = $this->argBonus();
        $count = $args['pay_quantity'];
        $type = (int) $args['pay'];
        $player_id = $this->getActivePlayerId();
        $reason = $args['reason'];
        if (($count == 1) && ($type == BE_ANYRES) && ($this->isTapestryActive($player_id, 44))) { // WARTIME ECONOMY
            $this->clearCurrentBenefit();
            $this->notifyWithName("message", clienttranslate('${player_name} gets a free bonus ${reason}'), [
                'reason' => $this->getReasonFullRec(reason_tapestry(44))
            ]);
            $this->queueBenefitInterrupt($args['benefits'], $player_id, $args['reason_data']);
            $this->gamestate->nextState('next');
            return;
        }
        $actual = $this->getPayResourceCount($type, $player_id);
        if ($actual < $count) {
            $this->notifyWithName('message_error', clienttranslate('${player_name} cannot pay for bonus ${reason}'), [
                'reason' => $reason
            ]);
            $this->clearCurrentBenefit();
            $this->gamestate->nextState('next');
        }
    }

    function getCapitalData($player_id) {
        $grid_data = $this->getCollectionFromDB("SELECT * FROM capital WHERE player_id='$player_id'");
        $capital = array();
        for ($a = 0; $a < 15; $a++) {
            $capital[$a] = array();
        }
        foreach ($grid_data as $cell) {
            $used = $cell['capital_occupied'];
            $x = $cell['capital_x'];
            $y = $cell['capital_y'];
            $capital[$x][$y] = $used;
        }
        return $capital;
    }

    function argPlaceStructure($stype = null) {
        // Need to provide details of which cells on the capital can be selected with each rotation of the structure.
        $options = array();
        $player_id = $this->getActivePlayerId();
        $capital = $this->getCapitalData($player_id);
        // Get dimensions of the structure.  (need mask for which we need id)
        $structure = $this->getObjectFromDB("SELECT * FROM structure WHERE card_location='capital_structure' LIMIT 1");
        $name = '?';
        if (!$stype)
            $stype = $structure['card_type'];
        if ($stype == 6) { // landmark
            $landmark_id = $structure['card_location_arg2'];
            $landmark = $this->landmark_data[$landmark_id];
            $width = $landmark['width'];
            $height = $landmark['height'];
            $masks = $landmark['mask'];
            $name = $landmark['name'];
        } else {
            $masks = array();
            $masks[0] = array(0 => array(0 => 1));
            $height = 1;
            $width = 1;
            $name = $this->structure_types[$stype]['name'];
        }
        $canunpass = $this->isTapestryActive($player_id, 39) || // TERRAFORMING
            $this->hasCiv($player_id, CIV_RIVERFOLK);
        $unpassable = ($canunpass) ? 1 : 0;
        // Build the options based on each rotation of the mask
        $any = false;
        foreach ($masks as $rot => $mask) {
            $options[$rot] = array();
            if ($rot % 2 == 1) {
                $mask_width = $width;
                $mask_height = $height;
            } else {
                $mask_width = $height;
                $mask_height = $width;
            }
            for ($x = 0; $x < 12; $x++) {
                for ($y = 0; $y < 12; $y++) {
                    $in_range = false;
                    $valid = true;
                    for ($a = 0; $a < $mask_width; $a++) {
                        for ($b = 0; $b < $mask_height; $b++) {
                            $xd = $x + $a;
                            $yd = $y + $b;
                            $this->systemAssertTrue("Landamrk data is not set for $name rot $rot $a,$b ($width,$height)", isset($mask[$a][$b]));
                            if (($mask[$a][$b] == 1) && ($capital[$xd][$yd] > $unpassable)) {
                                $valid = false;
                                break 2;
                            }
                            if ((!$in_range) && ($mask[$a][$b] == 1) && $this->onMat($xd, $yd)) {
                                $in_range = true;
                            }
                        }
                    }
                    if ($valid && $in_range) {
                        array_push($options[$rot], $x . "_" . $y);
                        $any = true;
                    }
                }
            }
        }
        $stra = [
            'i18n' => ['structure_name'], 'structure_name' => $name, 'structure_type' => $stype,
            'id' => $structure ? $structure['card_id'] : 0
        ];
        $this->addBenefitData($stra);
        /** @var Craftsmen */
        $cr = $this->getCivilizationInstance(CIV_CRAFTSMEN, true);
        $slots = $cr->getCraftsmenSlots($player_id);
        return array(
            'options' => $options, 'slots' => $slots, 'anyoptions' => $any ? 1 : 0,
            'conquer_targets' => $this->getConquerTargets(true)
        ) + $stra;
    }

    function onMat($x, $y) {
        return ($x >= 3) && ($x <= 11) && ($y >= 3) && ($y <= 11);
    }

    function argInvent() {
        return $this->notifArgsAddBen();
    }

    function argTechBenefit() {
        $benefit_type = $this->getCurrentBenefitType();
        $slot = ($benefit_type == 32) ? 1 : 2; // 32 circle, 33 square
        $circle = ($slot == 1);
        $circlesquare = ($circle) ? clienttranslate('circle') : clienttranslate('square');
        $player_id = $this->getActivePlayerId();
        $tech_cards = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='4' AND card_location_arg2='$slot' AND card_location='hand' AND card_location_arg='$player_id'");
        $cards = [];
        foreach ($tech_cards as $tc) {
            $card_type = $tc['card_type_arg'];
            $card = "tech_card_$card_type";
            $current = $this->getStructureInfoSearch(BUILDING_MARKER, null, $card, $player_id, null);
            if ($current)
                continue;
            $cards[] = $card_type;
        }
        return array(
            'circle' => $circle, 'circlesquare' => $circlesquare, 'slot' => $slot, 'i18n' => ['circlesquare'],
            'cards' => $cards
        );
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////
    /*
     * Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
     * The action method of state X is called everytime the current game state is set to X.
     */
    function __STGAME__() {
        // my anchor for IDE
    }

    function stSetupChoice() {
        $this->gamestate->setAllPlayersMultiactive();
    }

    function getStartingPosition($player_id) {
        $player_count = $this->getPlayersNumberWithBots();
        $is_solo = $this->isSolo();
        $cap_id = 0;
        $capitals = $this->getCardsInHand($player_id, CARD_CAPITAL);
        if (!$capitals)
            return [];
        $capital = array_shift($capitals);
        $cap_id = $capital['card_type_arg'];
        if ($is_solo) {
            if ($this->isRealPlayer($player_id))
                $cap_id = 2; // player starts at 2/4 if solo
            else if ($player_id == PLAYER_AUTOMA)
                $cap_id = 3; // at 3/5
            else
                $cap_id = 0;
        }
        if ($cap_id) {
            $start = ($player_count < 4) ? $this->capitals[$cap_id]['start']['small'] : $this->capitals[$cap_id]['start']['large'];
            $land_id = 'land_' . $start;
            return ['location' => $land_id, 'coords' => $start, 'start_pos' => $cap_id];
        } else {
            return [];
        }
    }

    function stFinishSetup() {
        // Discard/return unselected capitals/civilizations
        $this->importCapitalGrids();
        $this->DbQuery("UPDATE card SET card_location='discard', card_location_arg=0 WHERE card_location='choice' AND card_type='6'"); // Discard unused capitals
        $civ_data = $this->getCollectionFromDB("SELECT card_id FROM card WHERE card_location='choice'");
        $civ_ids = array();
        foreach ($civ_data as $cid => $civ) {
            array_push($civ_ids, $cid);
        }
        $this->cards->moveCards($civ_ids, 'deck_civ');
        $this->cards->shuffle('deck_civ');
        $this->notifyDeckCounters('deck_civ');

        $capital_data = $this->getCollectionFromDB("SELECT card_location_arg, card_type_arg FROM card WHERE card_type='6' AND card_location ='hand'");

        // Deploy starting outposts
        $outpost_ids = array();
        foreach (array_keys($this->loadPlayersBasicInfosWithBots()) as $player_id) {
            if ($player_id == PLAYER_SHADOW)
                continue;
            $start_info = $this->getStartingPosition($player_id);
            $cap_id = $start_info['start_pos'];
            if ($player_id != PLAYER_AUTOMA)
                $this->setStat($cap_id, 'capital', $player_id);
            $start = $start_info['coords'];
            $this->DbQuery("UPDATE map SET map_owner='$player_id' WHERE map_coords='$start'");
            $land_id = $start_info['location'];
            array_push($outpost_ids, $this->addStructure($player_id, $land_id, BUILDING_OUTPOST));
            array_push($outpost_ids, $this->addStructure($player_id, $land_id, BUILDING_OUTPOST));
        }
        // Deploy any starting civilization tokens
        $civ_data = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='5' AND card_location = 'hand'");
        $tokens = [];
        foreach ($civ_data as $civ) {
            $civ_id = $civ['card_type_arg'];
            $player_id = $civ['card_location_arg'];
            $this->setStat($civ_id, 'civ', $player_id);
            $additions = $this->setupCiv($civ_id, $player_id, true);
            $tokens = array_merge($tokens, $additions['tokens']);
            $outpost_ids = array_merge($outpost_ids, $additions['outposts']);
        }
        // Notify players
        $this->notifyAllPlayers("setupComplete", '', array(
            'capitals' => $capital_data, 'civilizations' => $civ_data,
            'outposts' => $this->structures->getCards($outpost_ids),
            'tokens' => $this->structures->getCards($tokens),
        ));
        $adjustmentvar = $this->getAdjustmentVariant();
        switch ($adjustmentvar) {
            case 1:
                $this->notifyAllPlayers('message', clienttranslate('civilization adjustment will be applied after first income (game option)'), []);
                break;
            case 4:
                $this->notifyAllPlayers('message', clienttranslate('experimental civilization adjustment rules are applied (game option)'), []);
                break;
            case 2:
                $this->notifyAllPlayers('message', clienttranslate('no civilization adjustment is applied (game option)'), []);
                break;
            case 8:
                $this->notifyAllPlayers('message', clienttranslate('civilization adjustment pack rules are applied (game option)'), []);
                break;
        }

        // pick a random player. Not using player_no=1 because mats setup using numbers
        $ids = array_keys($this->loadPlayersBasicInfos());
        shuffle($ids);
        $active_player = array_shift($ids);
        $this->gamestate->changeActivePlayer($active_player);
        $this->setGameStateValue('current_player_turn', $active_player);
        $this->setGameStateValue('starting_player', $active_player);
        $this->notifyWithName('message', clienttranslate('${player_name} is starting player'), [], $active_player);
        $this->prepareUndoSavepoint();
        $this->queueBenefitNormal(BE_RESUME, $active_player);
        $this->gamestate->nextState('next');
    }

    function removeSomeComponents() {
        $gameoptions = $this->getTableOptions();
        foreach ($gameoptions as $gameoption_id => $gameoption) {
            $value = 0;
            if (array_key_exists($gameoption_id, $this->gamestate->table_globals)) {
                $value = (int) $this->gamestate->table_globals[$gameoption_id];
            }
            if ($value == 1 && isset($gameoption['tap_remove'])) {
                foreach ($gameoption['tap_remove'] as $rem) {
                    list($card_type, $card_type_arg) = $rem;
                    $this->DbQuery("UPDATE card SET card_location='limbo' WHERE card_type='$card_type' AND card_type_arg='$card_type_arg'");
                    $this->notifyWithName('message', clienttranslate('game options: ${card_name} is removed from the game'), [
                        'card_name' => $this->getTokenName($card_type, $card_type_arg)
                    ]);
                }
            }
        }
    }

    function setupCiv($civ, $player_id, $start) {
        $tokens = array();
        $outpost_ids = array();
        $reason = reason_civ($civ);

        $this->interruptBenefit();
        $start_benefit = $this->getRulesCiv($civ, 'start_benefit', []);
        //         $this->notifyWithName('message', clienttranslate('${player_name} setup civ {$civ}'), [ 'civ'=>$civ, 'start'=>$start_benefit ],
        //                 $player_id);
        if (count($start_benefit) > 0) {
            $this->queueBenefitNormal($start_benefit, $player_id, $reason);
        }
        switch ($civ) {
            case CIV_MYSTICS:
                if (!$start && $this->isAdjustments4()) {
                    $era = $this->getCurrentEra($player_id);
                    if ($era < 5) // in era 5 - no effect
                        $this->benefitCivEntry(CIV_MYSTICS, $player_id);
                } else {
                    $this->benefitCivEntry(CIV_MYSTICS, $player_id);
                }
                break;

            case CIV_CHOSEN:
                // 1 token needed on slot 1.
                if ($this->isAdjustments4()) {
                    array_push($tokens, $this->addCivToken($player_id, 0, $civ));
                    if (!$start) {
                        $this->effect_gainChosenMidGame($player_id);
                    }
                }
                break;
            case CIV_HERALDS: // HERALDS 
                // 4 tokens to slots 1-4
                $tokens = $this->effect_setupCivTokens($civ, $player_id);
                if ($this->isAdjustments4or8()) {
                    if ($start) {
                        $this->effect_drawCardsUntil($player_id, $reason);
                    }
                }
                break;
            case CIV_MERRYMAKERS: // MERRYMAKERS
                // 3 tokens to 1,5,9
                array_push($tokens, $this->addCivToken($player_id, 1, $civ));
                array_push($tokens, $this->addCivToken($player_id, 5, $civ));

                if ($this->isAdjustments4or8()) {
                    $this->benefitCivEntry($civ, $player_id, 'midgame');
                } else {
                    array_push($tokens, $this->addCivToken($player_id, 9, $civ));
                }
                break;
            case CIV_MILITANTS: // MILITANTS
                $max = 8;
                if (!$start) {
                    // count all remainig outpots
                    $coll = $this->getCollectionFromDB("SELECT card_id FROM structure WHERE card_location_arg='$player_id' AND card_type='5' AND card_location='hand'");
                    $outcount = count($coll);
                    $max = ($outcount - 4) * 2;
                    if ($outcount > 0 && $outcount < 8) {
                        //  4- -> 0, 5 -> 2, 6 -> 4, 7 -> 6, 8 -> 8
                        $this->benefitCivEntry($civ, $player_id, 'midgame');
                    }
                }
                // 8 outposts to 1-8, fill 4,8,3,7,2,6,1,5
                $fill = [4, 8, 3, 7, 2, 6, 1, 5];
                for ($i = 0; $i < $max; $i++) {
                    $a = $fill[$i];
                    array_push($outpost_ids, $this->addStructure($player_id, 'civ_' . $civ . '_' . $a, BUILDING_OUTPOST));
                }
                break;
            case CIV_ISLANDERS:
                if (!$start) {
                    $this->queueBenefitNormal(178, $player_id, $reason, 4);
                }
                break;
            case CIV_RIVERFOLK:
                // Start with 4 VP per district in your capital city with at least 2 impassable plots.
                $this->vpRiverfolkStart($player_id);
                break;
            case CIV_UTILITARIENS:
                // 2 tokens to 9,10
                array_push($tokens, $this->addCivToken($player_id, 9, $civ));
                array_push($tokens, $this->addCivToken($player_id, 10, $civ));
                if (!$start) {
                    $this->benefitCivEntry($civ, $player_id, 'midgame');
                } else {
                    $this->queueBenefitNormal(305, $player_id, $reason);
                }
                break;
            default:
                $civinst = $this->getCivilizationInstance($civ);
                return $civinst->setupCiv($player_id, $start);
        }
        return array('tokens' => $tokens, 'outposts' => $outpost_ids);
    }

    function vpRiverfolkStart($player_id) {
        if (!$this->hasCiv($player_id, CIV_RIVERFOLK)) {
            return;
        }
        $capital = $this->getCapitalData($player_id);
        $vp = 0;
        $num = 4;
        if ($this->isAdjustments8()) {
            $num = 5;
        }
        for ($dx = 3; $dx < 12; $dx += 3) {
            for ($dy = 3; $dy < 12; $dy += 3) {
                $district_info = $this->getDistrictInfo($dx, $dy, $capital, $player_id);
                if (!$district_info)
                    continue;
                $impasse = array_get($district_info['build_types'], BUILDING_IMPASS, 0);
                //$this->debugConsole("district " . $district_info ['district'] . " impass $impasse");
                if ($impasse >= 2) {
                    $vp += $num;
                }
            }
        }
        $this->awardVP($player_id, $vp, reason_civ(CIV_RIVERFOLK));
    }

    function finalRiverfolkScoring($player_id) {
        if (!$this->hasCiv($player_id, CIV_RIVERFOLK)) {
            return;
        }
        if ($this->isAdjustments8()) {
            return; // no neg scoring
        }
        $capital = $this->getCapitalData($player_id);
        $vp = 0;
        for ($dx = 3; $dx < 12; $dx += 3) {
            for ($dy = 3; $dy < 12; $dy += 3) {
                $district_info = $this->getDistrictInfo($dx, $dy, $capital, $player_id);
                if (!$district_info)
                    continue;
                $impasse = array_get($district_info['build_types'], BUILDING_IMPASS, 0);
                //$this->debugConsole("district " . $district_info ['district'] . " impass $impasse");
                if ($impasse > 0) {
                    $vp += $impasse;
                }
            }
        }
        $this->awardVP($player_id, -$vp, reason_civ(CIV_RIVERFOLK));
    }

    function finalCollectorsScoring($player_id) {
        $civ = CIV_COLLECTORS;
        if (!$this->hasCiv($player_id, $civ)) {
            return;
        }
        $num = 0;
        $vp = 0;

        for ($i = 1; $i <= 6; $i++) {
            $cube = $this->getStructureOnCivSlot($civ, $i);
            if ($cube) {
                $num++;
            }
            $sql = "SELECT * FROM card WHERE card_location = 'civ_{$civ}_$i' LIMIT 1";
            $cube =  $this->getObjectFromDB($sql);
            if ($cube) {
                $num++;
            }
        }
        // 1/3/6/10/15/21
        for ($i = 1; $i <= $num; $i++) {
            $vp = $vp + $i;
        }

        $this->awardVP($player_id, $vp, reason_civ($civ));
    }



    function effect_setupCivTokens($civ_id, $player_id) {
        $tokens = [];
        $tokens_count = (int) array_get_def($this->civilizations, $civ_id, 'tokens_count', 0);
        for ($i = 1; $i <= $tokens_count; $i++) {
            $tokens[] = $this->addCivToken($player_id, $i, $civ_id);
        }
        return $tokens;
    }

    function effect_gainChosenMidGame($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $civ_id = CIV_CHOSEN;
        $achievements = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'achievement%' AND card_location_arg='$player_id'");
        $achi_count = sizeOf($achievements);
        $this->notifyAllPlayers("message", clienttranslate('${player_name} has completed ${count} public achievements'), array(
            'player_id' => $this->getActivePlayerId(), 'player_name' => $this->getActivePlayerName(),
            'count' => $achi_count,
        ));
        for ($a = 0; $a < $achi_count; $a++) {
            $this->benefitCivEntry($civ_id, $player_id);
        }
        // private achivements 
        for ($i = 1; $i <= 5; $i++) {
            $this->checkPrivateAchievement($i, $player_id);
        }
    }

    function effect_drawFromBenefit($player_id, $ben, $count = 1, $reason = null) {
        $card_type = $this->getRulesBenefit($ben, 'ct', 0);
        $draw = $this->getRulesBenefit($ben, 'draw', 1);
        $type_info = $this->card_types[$card_type];
        $cards = $this->dbPickCardsForLocation($count * $draw, $card_type, 'draw', $player_id);
        if (count($cards) == 0) {
            return []; // cancel action, notif already sent
        }
        $this->notifyWithName("moveCard", clienttranslate('${player_name} draws ${count} x ${type_name} ${reason}'), [
            'cards' => $cards, 'count' => count($cards), 'type_name' => $type_info['name'],
            'from' => $type_info['deck'], 'reason' => $this->getReasonFullRec($reason)
        ], $player_id);
        return $cards;
    }

    function isWhenPlayedTapestry($card) {
        $tapnum = $card;
        if (is_array($card)) {
            $tapnum = $card['card_type_arg'];
        }
        $tapvar = $this->tapestry_card_data[$tapnum]['type'];
        if ($tapvar == "now" || $tapvar == "cont") {
            return true;
        }
        return false;
    }

    function effect_drawCardsUntil($player_id = null, $reason = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $card_found = false;
        while ($card_found == false) {
            $cards = $this->awardCard($player_id, 1, CARD_TAPESTRY, false, $reason);
            $card = array_shift($cards);
            if (!$card)
                break;
            $tapvar = $this->tapestry_card_data[$card['type_arg']]['type'];
            if ($tapvar == "now" || $tapvar == "cont") {
                $card_found = true;
                break;
            }
            $card_id = $card['id'];
            $this->effect_discardCard($card_id, $player_id);
        }
        return $card_found;
    }

    function civAdjustments($player_id, $civ_id) {
        $this->systemAssertTrue("no civilization selected", $civ_id);
        $this->systemAssertTrue("no player selected", $player_id);
        $num_players = $this->getPlayersNumberWithBots();
        $adjustments1 = $this->getAdjustmentVariant() == 1;
        // apply adjustments

        if ($adjustments1) {
            $reason = reason('str', clienttranslate('civilization adjustment'));
            $civ_name = $this->civilizations[$civ_id]['name'];
            $this->notifyWithName('message', clienttranslate('${player_name} applies adjustments for ${civ_name}: ${adjustment_name}'), [
                'civ_name' => $civ_name, 'adjustment_name' => $this->civilizations[$civ_id]['adjustment']
            ]);
            switch ($civ_id) {
                case 1: //ALCHEMISTS
                    // 2 any resource
                    $this->awardVP($player_id, 10, $reason, "civilization_$civ_id");
                    $this->queueBenefitNormal([RES_ANY, RES_ANY], $player_id, $reason);
                    break;
                case 2: // ARCHITECTS
                    if ($num_players >= 3) {
                        $this->awardVP($player_id, 10 * ($num_players - 1), $reason, "civilization_$civ_id");
                    }
                    break;
                case 3: // CRAFTSMEN
                    // lose 1 resource
                    $this->queueBenefitNormal(['p' => RES_ANY, 'g' => 142], $player_id, $reason);
                    break;
                case 4: // ENTERTAINERS
                    $this->queueBenefitNormal(RES_ANY, $player_id, $reason);
                    break;
                case 5: // FUTURISTS
                    // lose 1 resource and 1 culture
                    $this->awardBenefits($player_id, 4, -1, $reason); // -1 culture
                    // lose 1 resource
                    $this->queueBenefitNormal(['p' => RES_ANY, 'g' => 142], $player_id, $reason);
                    break;
                case 11: // MERRYMAKERS
                    $this->queueBenefitNormal(RES_ANY, $player_id, $reason);
                    break;
                case 12: // MILITANTS
                    // no change
                    break;
                case 13: //MYSTICS
                    $this->queueBenefitNormal(RES_ANY, $player_id, $reason);
                    break;
                case 15: // CHOSEN
                    $this->awardVP($player_id, 15 * ($num_players - 1), $reason);
                    break;
                case 16: // TRADERS
                    $this->awardVP($player_id, 10, $reason);
                    $this->queueBenefitNormal([RES_ANY, RES_ANY], $player_id, $reason);
                    break;
                case 6: // HERALDS
                    // no change
                case 7: // HISTORIANS
                    // no change
                case 8: // INVENTORS
                    // no change
                case 9: // ISOLATIONISTS
                    // no change
                case 10: //LEADERS
                case 14: //NOMADS
                default:
                    $this->notifyWithName('message', clienttranslate('${civ_name}: no adjustments'), [
                        'i18n' => ['civ_name'], 'civ_name' => $civ_name
                    ]);
                    break;
            }
        }
    }

    function stTechBenefit() {
        $args = $this->argTechBenefit();
        $size = count($args['cards']);
        if ($size == 0) {
            $this->notifyWithName("message_error", clienttranslate('${player_name} does not have a suitable technology card for the ${circlesquare} benefit'), $args);
            $this->clearCurrentBenefit();
            $this->gamestate->nextState('next');
        }
    }

    function getPlayersInGame() {
        $player_data = $this->loadPlayersBasicInfos();
        $still_playing = [];
        foreach ($player_data as $player) {
            $player_id = $player['player_id'];
            if ($this->isPlayerAlive($player_id))
                $still_playing[$player_id] = $player;
        }
        if ($this->isSolo()) {
            $playerswithbots = $this->loadPlayersBasicInfosWithBots();
            if ($this->isPlayerAlive(PLAYER_AUTOMA)) {
                $still_playing[PLAYER_AUTOMA] = $playerswithbots[PLAYER_AUTOMA];
            }
        } else if ($this->isShadowEmpireOnly() && count($still_playing) > 0) {
            $playerswithbots = $this->loadPlayersBasicInfosWithBots();
            $still_playing[PLAYER_SHADOW] = $playerswithbots[PLAYER_SHADOW];
        }
        return $still_playing;
    }

    function endOfGame($tr = false) {
        $players = $this->loadPlayersBasicInfosWithBots();
        $value_min = 10000;
        $value_max = 0;
        $this->notifyAllPlayers('simplePause', '', ['time' => 1000]);
        foreach ($players as $player_id => $info) {
            if ($player_id == PLAYER_SHADOW)
                continue;
            $score = $this->dbGetScore($player_id);
            if ($score > $value_max)
                $value_max = $score;
            if ($score < $value_min)
                $value_min = $score;
            // make sure score display is up to date
            $this->notifyWithName('VP', '', ['increase' => 0, 'score' => $score], $player_id);
        }
        if ($this->isSolo()) {
            $player_id = $this->getSoloPlayerId();
            $automa_score = $this->dbGetScore(PLAYER_AUTOMA);
            $player_score = $this->dbGetScore($player_id);
            if ($player_score < $automa_score) {
                $score = -1 * $player_score;
                $this->dbSetScore($player_id, $score);
                $this->notifyWithName('VP', clienttranslate('${player_name} loses, negating the score ${score}'), [
                    'score' => $score, 'increase' => 0
                ], $player_id);
                $this->notifyWithName('message_info', clienttranslate('${player_name} loses to Automa ${player_score} to ${automa_score}!'), [
                    'player_score' => $player_score, 'automa_score' => $automa_score
                ], $player_id);
            } else {
                $this->notifyWithName('message_info', clienttranslate('${player_name} wins over Automa ${player_score} to ${automa_score}!'), [
                    'player_score' => $player_score, 'automa_score' => $automa_score
                ], $player_id);
            }
            $this->setStat($automa_score, 'automa_score');
        }
        $this->setStat($value_max, 'game_winner_score');
        $this->setStat($value_min, 'game_loser_score');
        if ($tr)
            $this->gamestate->nextState('endGame');
    }

    function stTransition() {
        $still_playing = $this->getPlayersInGame();
        // If all players have finished, end game!
        if (count($still_playing) == 0) {
            $this->endOfGame(true);
            return;
        }
        $next_player_table = $this->createNextPlayerTable(array_keys($this->loadPlayersBasicInfosWithBots()), true);
        // check if current player finished and elimite
        $current_player_id = $this->getGameStateValue('current_player_turn');
        $player_id = $next_player_table[$current_player_id];
        $this->notifyWithName('message', clienttranslate('${player_name} is current player'), [], $current_player_id);
        // Find an active player who hasn't finished.
        $count = $this->getPlayersNumberWithBots();
        while (!array_key_exists($player_id, $still_playing) && $count > 0) {
            $this->notifyWithName('message', clienttranslate('${player_name} is skipped'), [], $player_id);
            $player_id = $next_player_table[$player_id];
            $count--;
        }
        $this->notifyWithName('message', clienttranslate('${player_name} is the next player'), [], $player_id);
        if (!$this->isRealPlayer($player_id)) {
            $this->effect_automaTakeTurn();
            $this->gamestate->nextState('benefit');
            return;
        } else {
            $this->gamestate->changeActivePlayer($player_id);
            $this->giveExtraTime($player_id);
            $this->prepareUndoSavepoint(true);
            $this->notifyAllPlayers('ack', '', []);
            $this->gamestate->nextState('next');
        }
    }

    function getPossibleAdvances($onlyValid = true) {
        $player_id = $this->getActivePlayerId();
        $tokens = $this->dbGetCubesOnTrack($player_id);
        $player_data = $this->getObjectFromDB("SELECT * FROM playerextra WHERE player_id='$player_id'");
        $advances = array();
        foreach ($tokens as $token) {
            $coords = explode("_", $token['card_location']);
            if ($token['virtual'])
                continue;
            $track = $coords[2];
            $spot = $coords[3];
            $new_spot = $spot + 1;
            if ($spot < 12) {
                $advance_cost = $this->tech_track_data[$track][$new_spot]['cost'];
                if ($this->canAffordPayment($player_data, $advance_cost)) {
                    $advances[$track . "_" . $new_spot] = 1;
                } else {
                    if (!$onlyValid)
                        $advances[$track . "_" . $new_spot] = 0;
                }
            } else {
                if (!$onlyValid)
                    $advances[$track . "_" . $spot] = -1;
            }
        }
        return $advances;
    }

    function getClosestBonus($player_id = 0, $sel_track = 0, $cube_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        if ($cube_id) {
            $cube = $this->getStructureInfoById($cube_id);
            $tokens = [$cube_id => $cube];
        } else {
            $tokens = $this->dbGetCubesOnTrack($player_id, $sel_track, null, 0 /* no ai */);
        }
        $advances = array();
        foreach ($tokens as $token) {
            $id = $token['card_id'];
            $coords = explode("_", $token['card_location']);
            $track = (int) $coords[2];
            $spot = (int) $coords[3];
            for ($i = $spot + 1; $i <= 12; $i++) {
                if (array_key_exists('option', $this->tech_track_data[$track][$i])) {
                    $advances["cube_$id"] = "${track}_${i}";
                    break;
                }
            }
        }
        return $advances;
    }

    function getPossibleTrackChoices($sel_track, $change = 0, &$cubes = null, $player_id = 0) {
        if (!$cubes)
            $cubes = [];
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $tokens = $this->dbGetCubesOnTrack($player_id);
        $advances = array();
        foreach ($tokens as $token) {
            $coords = explode("_", $token['card_location']);
            $arg = $token['card_type_arg'];
            $tid = $token['card_id'];
            $track = (int) $coords[2];
            $spot = (int) $coords[3];
            $new_spot = $spot + $change;
            if ($new_spot < 0)
                $new_spot = 0;
            if ($new_spot > 13)
                $new_spot = 13;
            if ($change <= 0 && $arg == CUBE_AI) {
                // cannot regress or repeat on virtual cube
                continue;
            }
            if ($track == $sel_track || $sel_track == 0) {
                $advances["${track}_${new_spot}"] = 1;
                $cubes[] = "cube_$tid";
            }
        }
        return $advances;
    }

    function canAffordPayment($player_data, $payment) {
        $coins = 0;
        $workers = 0;
        $food = 0;
        $culture = 0;
        $general = 0;
        foreach ($payment as $pid => $p) {
            switch ($p) {
                case 1:
                    $coins++;
                    break;
                case 2:
                    $workers++;
                    break;
                case 3:
                    $food++;
                    break;
                case 4:
                    $culture++;
                    break;
                case 5:
                    $general++;
                    break;
            }
        }
        if ($player_data['player_res_coin'] < $coins)
            return false;
        if ($player_data['player_res_worker'] < $workers)
            return false;
        if ($player_data['player_res_food'] < $food)
            return false;
        if ($player_data['player_res_culture'] < $culture)
            return false;
        $total = $player_data['player_res_coin'] + $player_data['player_res_worker'] + $player_data['player_res_food'] + $player_data['player_res_culture'];
        if ($total < sizeOf($payment))
            return false;
        return true;
    }

    function stPlayerTurn() {
        $player_id = $this->getActivePlayerId();
        $turn = $this->effect_startOfTurn($player_id);
        // If first turn, must take income
        if ($turn == 1) {
            $this->takeIncomeAuto(true); // Auto income for in first turn 
            return;
        }
        if ($this->ownsLighthouseAndCanPlayIt($player_id)) {
            return; // do not auto-income
        }

        $civs = $this->getAllCivs($player_id);
        foreach ($civs as $info) {
            $civ = $info['card_type_arg'];
            $civ = $this->getCivilizationInstance($civ);
            if ($civ->hasActivatedAbilities($player_id)) {
                return;
            }
        }

        if ((count($this->getPossibleAdvances()) == 0)) {
            $this->takeIncomeAuto(true); // When cannot afford advancement.
            return;
        }
    }

    function checkDictatorship($player_id, $forceEnd = false) {
        $cleared = false;
        $dictator_datas = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'tech_spot_%' AND card_location_arg='$player_id' AND card_location_arg2 LIKE 'dic_%'");
        foreach ($dictator_datas as $dictator_data) {
            $dd = explode("_", $dictator_data['card_location_arg2']);
            $dictator = $dictator_data['card_location_arg'];
            $dictator_cur_turn = $this->getPlayerTurn($dictator);
            $dic_turn = $dd[1];
            //$this->debugConsole("dict check $dictator $dictator_cur_turn  $dic_turn");
            if ($dictator_cur_turn > $dic_turn || $forceEnd) {
                $cube_id = $dictator_data['card_id'];
                $this->DbQuery("UPDATE structure SET card_location_arg2='' WHERE card_id='$cube_id'");
                $this->notifyMoveStructure(clienttranslate('DICTATORSHIP of ${player_name} is over'), $cube_id, [], $dictator);
                $cleared = true;
            }
        }
        return $cleared;
    }

    function stPlayerTurnEnd() {
        // can check if they want to end it automatically?
        $player_id = $this->getActivePlayerId();
        if ($this->canSkipConfirm($player_id)) {
            $this->gamestate->nextState('next');
        }
    }

    function canSkipConfirm($player_id) {
        if ($player_id != $this->getGameStateValue('current_player_turn')) {
            return true;
        }
        $args = $this->argPlayerTurnEnd();
        if ($args['move'] == $args['undo_move']) {
            return true;
        }
        if ($this->isPlayerEliminated($player_id)) {
            return true;
        }
        if ($this->isZombiePlayer($player_id)) {
            return true;
        }
        if (!$this->isPlayerAlive($player_id)) {
            return true;
        }
        if (!$this->isRealPlayer($player_id)) {
            return true;
        }
        return false;
    }

    function effect_endOfTurn(int $player_id) {




        $this->setGameStateValue('cube_choice', -1);
        $this->setGameStateValue('coal_baron', 0);
        $this->checkDictatorship($player_id);
        $marker = CUBE_MARKER;
        $turn_markers = $this->getObjectListFromDB("SELECT * FROM structure WHERE card_type='7' AND card_type_arg='$marker'");
        $turn_markers += $this->getObjectListFromDB("SELECT * FROM structure WHERE card_type='8'");
        if (count($turn_markers) > 0) {
            foreach ($turn_markers as $marker) {
                $structure_id = $marker['card_id'];
                $this->dbSetStructureLocation($structure_id, 'hand');
            }
        }
        if ($this->getGameStateValue('income_turn')) {
            $this->setGameStateValue('income_turn', 0);
        }
    }

    function effect_startOfTurn($player_id) {
        $this->incPlayerTurn($player_id);
        $this->setGameStateValue('current_player_turn', $player_id);
        $this->setGameStateValue('cube_choice', -1);
        $this->setGameStateValue('coal_baron', 0);
        $turns = $this->getPlayerTurn($player_id);
        return $turns;
    }

    function actionUndo() {
        //self::checkAction('actionUndo');
        $undo_moves_player = self::getGameStateValue('undo_moves_player');
        if ($undo_moves_player != self::getCurrentPlayerId())
            throw new BgaUserException(self::_("The stored UNDO corresponds to another player's turn : you cannot restore it"));
        $args = $this->argUndo();
        if ($args['move'] == $args['undo_move']) {
            throw new BgaUserException(self::_("Nothing can be undone"));
        }
        $this->undoRestorePoint();
        //$this->gamestate->reloadState();
        //$this->debugConsole("undo restore " . ($this->getStateName()));
        // $this->gamestate->nextState('next'); // transition to single player state (i.e. beginning of player actions for this turn)
    }

    function actionConfirm() {
        $this->checkAction('actionConfirm');
        $this->gamestate->nextState('next');
    }

    function queueEraCivAbilities() {
        $player_id = $this->getActivePlayerId();
        $era = $this->getCurrentEra($player_id);
        if ($era > 1) {
            // If first of neighbours to play, get bonus!
            $era_string = 'era' . $era;
            $neighbours = $this->getPlayerNeighbours($player_id, false);
            $neighbours_in_era = $this->getUniqueValueFromDB("SELECT COUNT(*) c FROM card WHERE card_type='3' AND card_location='$era_string' AND card_location_arg IN (" . implode(',', $neighbours) . ")");
            if (($neighbours_in_era == 0) && ($era > 1 && $era < 5)) { // own tapestry is not played yet
                $this->notifyAllPlayers("message", clienttranslate('${player_name} is the first of their neighbours to enter era ${era}'), array(
                    'i18n' => array('card_name'), 'player_id' => $player_id,
                    'player_name' => $this->getActivePlayerName(), 'era' => $era,
                ));
                $reason = reason('str', clienttranslate('first to era'));
                $this->queueBenefitNormal(BE_ANYRES, $player_id, $reason, $era - 1);
            }
        }
        $civs = $this->getCollectionFromDB("SELECT card_type_arg FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='5'");
        foreach ($civs as $cid => $civ) {
            $this->queueEraCivAbility($cid, $player_id, $era);
        }
    }

    function queueEraCivAbility($cid, $player_id, $incomeTurn = 0) {
        $inst = $this->getCivilizationInstance($cid, false);
        $inst->queueEraCivAbility($player_id, $incomeTurn);
    }

    function militantBenefits() {
        $player_id = $this->getActivePlayerId();
        $cubes = $this->getCollectionFromDB("SELECT card_location FROM structure WHERE card_location LIKE 'civ_12_%'");
        $covered = array();
        foreach ($cubes as $cube) {
            array_push($covered, explode("_", $cube['card_location'])[2]);
        }
        $vp = 0;
        for ($a = 1; $a <= 8; $a++) {
            if (!in_array($a, $covered)) {
                $benefit = $this->civilizations[12]['slots'][$a]['benefit'];
                foreach ($benefit as $b) {
                    if ($b == RES_VP) {
                        $vp++;
                    } else {
                        $this->queueBenefitNormal($b, $player_id, reason_civ(CIV_MILITANTS));
                    }
                }
            }
        }
        if ($vp > 0) {
            $this->awardVP($player_id, $vp, reason_civ(CIV_MILITANTS));
        }
    }

    function furthestOnTracks($player_id) {
        $count = 0;
        for ($track = 1; $track <= 4; $track++) {
            $thisplayer = $this->getMaxTrackSlot($track, $player_id);
            $max = $this->getMaxTrackSlot($track, null);
            if ($max == $thisplayer) {
                $count++;
            }
        }
        return $count;
    }

    function theChosenBenefits() {
        if ($this->isAdjustments4()) {
            return;
        }
        $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        // PART 1: POSITION ON TRACKS
        $count = $this->furthestOnTracks($player_id);
        $vp = $this->getPlayersNumberWithBots() - 1;
        $this->notifyAllPlayers("message", clienttranslate('${player_name} is leading on ${count} tracks'), array(
            'player_id' => $this->getActivePlayerId(), 'player_name' => $this->getActivePlayerName(),
            'count' => $count,
        ));
        $reason = reason_civ(CIV_CHOSEN);
        $this->awardVP($player_id, $count * $vp, $reason); // 1 VP per opponent per track
        // PART 2: ACHIEVEMENTS
        $achievements = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'achievement%' AND card_location_arg='$player_id'");
        $achi_count = sizeOf($achievements);
        $this->notifyAllPlayers("message", clienttranslate('${player_name} has completed ${count} achievements'), array(
            'player_id' => $this->getActivePlayerId(), 'player_name' => $this->getActivePlayerName(),
            'count' => $achi_count,
        ));
        if ($income_turn_count < 5) {
            for ($a = 0; $a < $achi_count; $a++) {
                $this->queueBenefitNormal(BE_ANYRES, $player_id, $reason);
            }
        } else {
            $this->awardVP($player_id, $achi_count * 5, $reason);
        }
    }

    function stTapestryCard() {
        $player_id = $this->getActivePlayerId();
        $era = $this->getCurrentEra($player_id);
        $type = $this->getCurrentBenefitType();
        if ($type == 64) { // tapestry overplay
            if ($era == 1)
                return;
            if ($era >= 5) {
                $this->clearCurrentBenefit($type);
                $this->notifyWithName('message_error', clienttranslate('Cannot overplay tapestry in round 5 - no tapestry can be played'));
                $this->nextStateBenefitManager();
                return;
            }
            $prev = $this->getLatestTapestry($player_id, $era);
            if (!$prev) {
                $this->clearCurrentBenefit($type);
                $this->notifyWithName('message_error', clienttranslate('Cannot overplay tapestry card at this moment, no tapestry is played yet'));
                $this->nextStateBenefitManager();
                return;
            }
            return;
        }
        if ($type == 112) { // espionage
            $all_taps = $this->getCollectionFromDB("SELECT * FROM card WHERE card_type='3' AND card_location LIKE 'era%'");
            if (count($all_taps) <= 1) {
                // no other targets, nothing to copy
                $this->clearCurrentBenefit($type);
                $this->notifyWithName('message_error', clienttranslate('ESPIONAGE has not valid targets, no copy is made'));
                $this->nextStateBenefitManager();
                return;
            }
            return;
        }
        if ($type == BE_PLAY_TAPESTY_INCOME) {
            if ($era < 2 || $era > 4) { // Income tap in ROUNDS 2-4 ONLY
                $this->clearCurrentBenefit($type);
                $this->nextStateBenefitManager();
                return;
            }
        }
        $tap_cards = $this->getCollectionFromDB("SELECT * FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='3'");
        $can_decline = $this->canDeclineTapestry();
        // If no tapestry cards, draw one face down
        if (count($tap_cards) == 0 && !$can_decline) {
            $this->awardCard($player_id, 1, CARD_TAPESTRY, true, reason('str', clienttranslate('No Tapestry cards')));
            $tap_cards = $this->getCollectionFromDB("SELECT * FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='3'");
        }
        // If only one card, play it.
        if ((count($tap_cards) == 1) && !$can_decline) {
            foreach ($tap_cards as $tcid => $card) {
                if (TAP_ESPIONAGE == $card['card_type_arg']) {
                    break; // cannot auto-play
                }
                $this->playTapestryCard($card['card_id'], $player_id);
                $this->nextStateBenefitManager();
                return;
            }
        }
    }

    function nextStateBenefitManager() {
        $this->gamestate->jumpToState(18);
    }

    function stUpgradeTechnology() {
        $b = $this->getCurrentBenefit();
        if (!$b) {
            $this->gamestate->nextState('benefit');
            return;
        }
        $this->systemAssertTrue("upgrade ben", $b);
        $type = $b['benefit_type'];
        $this->systemAssertTrue("upgrade type $type", $type == 14 || $type == 13);
        $player_id = $b['benefit_player_id'];
        $tech_cards_all = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='4'");
        if ($this->hasCiv($player_id, CIV_RECYCLERS) && $tech_cards_all > 0) {
            // don't auto-skip
            return;
        }

        $tech_cards_top = $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_location_arg='$player_id' AND card_type='4' AND card_location_arg2 = '2'");
        if ($tech_cards_all == $tech_cards_top) {
            // None to upgrade, move on.
            $this->clearCurrentBenefit();
            $this->notifyWithName('message_error', clienttranslate('${player_name} - has no tech cards levels I or II, upgrade is skipped'));
            $this->gamestate->nextState('benefit');
        }
    }

    function getResourceCountAll($player_id = 0, $specific = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $maxres = 0;
        for ($ben = 1; $ben <= 4; $ben++) {
            if ($specific)
                if ($specific != $ben)
                    continue;
            $resname = $this->income_tracks[$ben]['resource'];
            $res_field = "player_res_$resname";
            $base_count = $this->getUniqueValueFromDB("SELECT $res_field FROM playerextra WHERE player_id='$player_id'");
            $maxres += $base_count;
        }
        return $maxres;
    }

    function effect_IncomeBenefits($allowed_benefits = null, $player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $player_income_data =  $this->getPlayerIncomeData($player_id);
        $total_benefits = [];
        $allowed_action = 'zzz';
        if (is_string($allowed_benefits)) {
            $allowed_action = $allowed_benefits;
            $allowed_benefits = [];
        }
        for ($track = 1; $track <= 4; $track++) {
            $field = $this->income_tracks[$track]['field'];
            $limit = $player_income_data[$field];
            for ($slot = 1; $slot <= $limit; $slot++) {
                $benefits = [];
                foreach ($this->income_tracks[$track][$slot]['benefit'] as $b) {
                    $action = $this->getRulesBenefit($b, 'r', 'x');
                    if ($allowed_benefits === null || $action == $allowed_action || in_array($b, $allowed_benefits)) {
                        array_inc($benefits, $b, 1);
                        array_inc($total_benefits, $b, 1);
                    }
                }
                $reason = reason('inspot', "${track}_${slot}");
                foreach ($benefits as $bid => $count) {
                    $this->awardBenefits($player_id, $bid, $count, $reason);
                }
            }
        }
        if (isset($total_benefits[RES_FOOD]) && $this->isTapestryActive($player_id, TAP_MERCANTILISM)) { // MERCANTILISM
            $this->queueBenefitNormal(BE_ANYRES, $player_id, reason_tapestry(TAP_MERCANTILISM), $total_benefits[RES_FOOD]);
        }
        if (isset($total_benefits[RES_COIN]) && $this->isTapestryActive($player_id, 8)) { // CAPITALISM
            $this->awardVP($player_id, 2 * $total_benefits[RES_COIN], reason_tapestry(8));
        }
    }

    function effect_gainVPIncome($player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        if ($income_turn_count > 1) { // NO VP IN FIRST ROUND
            $this->setIncomeTurnPhase(INCOME_VP, clienttranslate('${player_name} receives income VP'), $player_id);
            $this->effect_IncomeBenefits('v', $player_id);
        }
    }

    function effect_gainResourcesIncome($player_id = 0) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        //RESOURCES
        if ($income_turn_count < 5) { // NO INCOME IN LAST ROUND
            $this->setIncomeTurnPhase(40, clienttranslate('${player_name} receives resources income for turn #${turn_number}'), $player_id);
            // Give resources for each uncovered symbol on the resource tracks!
            $allowed_benefits = [RES_COIN, RES_WORKER, RES_FOOD, RES_CULTURE];
            $this->effect_IncomeBenefits($allowed_benefits, $player_id);
        }
    }

    function effect_gainCardsIncome($player_id = 0) {
        if ($player_id == 0)
            $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        //CARDS
        if ($income_turn_count < 5) { // NO INCOME IN LAST ROUND
            $this->setIncomeTurnPhase(40, clienttranslate('${player_name} receives cards and tiles income for turn #${turn_number}'), $player_id);
            $allowed_benefits = [BE_TERRITORY, BE_TAPESTRY];
            $this->effect_IncomeBenefits($allowed_benefits, $player_id);
        }
    }

    function effect_endOfIncome($player_id = 0) {
        if ($player_id == 0)
            $player_id = $this->getActivePlayerId();
        $income_turn_count = $this->getCurrentEra($player_id);
        $this->setIncomeTurnPhase(0, clienttranslate('${player_name} ends income turn'), $player_id);
        if ($income_turn_count == 5) { // FINAL INCOME
            $this->finalGameScoring($player_id);
            $this->DbQuery("UPDATE playerextra SET player_income_turns=6 WHERE player_id='$player_id'");
            if ($player_id == PLAYER_AUTOMA) {
                $this->DbQuery("UPDATE playerextra SET player_income_turns=6 WHERE player_id='2'"); // shadow is done also
            }
            $this->setIncomeTurnPhase(0, '', $player_id); // to notify of update era to 6
        }
    }

    function finalGameScoring($player_id) {
        $civs = $this->getAllCivs($player_id);
        foreach ($civs as $info) {
            $civ = $info['card_type_arg'];
            if ($civ == (CIV_MYSTICS)) {
                $this->finalMysticsScoring($player_id);
            } else if ($civ == (CIV_ISLANDERS)) {
                $this->finalIslandersScoring();
            } else if ($civ == (CIV_RIVERFOLK)) {
                $this->finalRiverfolkScoring($player_id);
            } else if ($civ == (CIV_COLLECTORS)) {
                $this->finalCollectorsScoring($player_id);
            } else {
                $civ = $this->getCivilizationInstance($civ);
                $civ->finalScoring($player_id);
            }
        }

        // clear dictator data
        $this->checkDictatorship($player_id, true);
        // clear heralds data
        if ($this->hasCiv($player_id, CIV_HERALDS))
            $this->removeHeraldTapestryClone($player_id);
        $this->finalStats($player_id);
        $this->dbSetAuxScore($player_id, $this->getResourceCountAll($player_id));
        $this->notifyWithName('message_error', clienttranslate('${player_name} ends their game!'), [], $player_id);
    }

    function getCivilizationInstance(int $civ, bool $strict = false): AbsCivilization {
        $info = array_get($this->civilizations, $civ);
        $this->systemAssertTrue("ERR:game:02:$civ", $info);
        $derivedClass = $info['name'];
        $derivedClass = strtolower($derivedClass);
        $derivedClass = ucwords($derivedClass);
        $derivedClass = str_replace(" ", "", $derivedClass);
        $classname = array_get($info, "class", $derivedClass);
        try {
            $file = "civs/$classname.php";
            if (@include_once($file)) {
                $opinst = new $classname($this);
                return $opinst;
            }
        } catch (Throwable $e) {
            if ($strict) {
                throw new BgaSystemException("Cannot instantiate $classname for $civ (error)");
            }
        }
        if ($strict) {
            throw new BgaSystemException("Cannot instantiate $classname for $civ");
        }

        $opinst = new BasicCivilization($civ, $this);
        return $opinst;
    }

    function getPlayerIncomeData($player_id) {
        $player_income_data = $this->getObjectFromDB("SELECT player_income_armories armories, player_income_houses houses, player_income_markets markets, player_income_farms farms FROM playerextra WHERE player_id='$player_id'");
        return $player_income_data;
    }

    function finalStats($player_id) {
        if ($player_id == PLAYER_SHADOW) {
            return;
        }
        $score = $this->dbGetScore($player_id);
        if ($player_id == PLAYER_AUTOMA) {
            $this->setStat($score, 'automa_score');
            return;
        }
        if (!$this->isRealPlayer($player_id)) {
            $this->systemAssertTrue("unathorized move");
        }
        $this->setStat($score, 'game_points_total', $player_id);
        $player_income_data =  $this->getPlayerIncomeData($player_id);
        $total = 0;
        for ($track = 1; $track <= 4; $track++) {
            $field = $this->income_tracks[$track]['field'];
            $limit = (int) ($player_income_data[$field]);
            $total += $limit - 1;
            $this->setStat($limit, "game_income_$track", $player_id);
        }
        $this->setStat($total, 'game_building_income', $player_id);
        $landmarks = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type='6' AND card_location_arg=$player_id");
        $this->setStat(count($landmarks), 'game_building_landmark', $player_id);
        $outposts = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_type='5' AND card_location_arg=$player_id AND card_location LIKE 'land%'");
        $this->setStat(count($outposts), 'game_building_outpost', $player_id);
        $civs = $this->getCollectionFromDB("SELECT card_type_arg FROM card WHERE card_type='5' AND card_location='hand' AND card_location_arg='$player_id'");
        $this->setStat(count($civs), 'game_card_civ', $player_id);
    }

    function finalMysticsScoring($player_id = null) {
        if (!$player_id)
            $player_id = $this->getActivePlayerId();
        for ($i = 1; $i <= 4; $i++) {
            $data = $this->getMysticPrediction($i, $player_id);
            if ($data == null)
                continue; // ?
            $prediction = $data['value'];
            if ($prediction == -1)
                continue; // no prediction
            $real = $data['actual'];
            $category_name = $data['category_name'];
            $this->notifyAllPlayers('message', clienttranslate('Final MYSTICS prediction for ${category_name}: predicted ${predicted}, actual ${actual}'), [
                'i18n' => ['category_name'], 'category_name' => $category_name, 'predicted' => $prediction,
                'actual' => $real
            ]);
            if ($real == $prediction) {
                $reason = reason_civ(CIV_MYSTICS);
                $count = 10;
                if ($this->isAdjustments4())
                    $count = 20;
                $this->awardVP($player_id, $count, $reason);
            }
        }
    }

    function finalIslandersScoring($doscore = true) {
        $player_id = $this->getActivePlayerId();
        $map = $this->getMapDataFromDb('islanders');
        $highest = 0;
        foreach ($map as $info) {
            $tile_id = $info['map_tile_id'];
            if (!$tile_id)
                continue;
            $edge = array_get($info, 'edge', -1);
            if ($edge < 0)
                continue;
            $working_data = $this->territory_tiles[$tile_id]['x'];
            $working_rot = $info['map_tile_orient'];
            $allsea = 0;
            for ($i = 1; $i <= 2; $i++) {
                $working_left = (6 + $edge + $i - $working_rot) % 6;
                if ($working_data[$working_left] == TERRAIN_SEA)
                    $allsea++;
            }
            if ($allsea == 2)
                $highest++;
        }
        $this->notifyWithName('message', clienttranslate('${player_name} (ISLANDERS) has ${count} tiles with water on outer edges'), [
            'count' => $highest
        ]);
        if ($doscore)
            $this->awardVP($player_id, $highest * 4, reason_civ(CIV_ISLANDERS));
    }



    function dbGetBenefits() {
        $benefit_data = $this->getCollectionFromDB("SELECT * FROM benefit ORDER BY benefit_prerequisite, benefit_id");
        return $benefit_data;
    }

    function switchPlayer($target_player_id, $save = true) {
        if (!$this->isRealPlayer($target_player_id))
            return false;
        if ($this->getActivePlayerId() != $target_player_id) {
            $this->gamestate->changeActivePlayer($target_player_id);
            if ($save) {
                $this->giveExtraTime($target_player_id);
                $this->prepareUndoSavepoint(); // if we changed active player we need to change undo savepoint
            }
            return true;
        }
        if ($save) {
            $this->giveExtraTime($target_player_id); // give more time to current player
        }
        return false;
    }

    function stBenefitManager() {
        // Are there any benefits to award?
        for ($b = $this->getCurrentBenefit(); $b; $b = $this->getCurrentBenefit()) {
            $bplayer = $b['benefit_player_id'];
            $bcat = $b['benefit_category'];
            $ben = $b['benefit_type'];
            $bid = $b['benefit_id'];
            if ($bcat == 'standard' && ($ben == RES_VP || ($ben > 500 && $ben < 600))) {
                // do not need to switch player for these
                $completed = $this->awardBenefits($bplayer, $ben, $b['benefit_quantity'], $b['benefit_data']);
                $this->systemAssertTrue("Problem with awarding VP $ben", $completed);
                $this->benefitCashed($bid);
                continue;
            }
            if (!$this->checkAliveForBenefit($bplayer, $ben, $bcat)) {
                $this->benefitCashed($bid);
                continue;
            }
            $this->switchPlayer($bplayer);
            $bcat = explode(',', $bcat)[0];
            switch ($bcat) {
                case 'standard':
                    $count = $b['benefit_quantity'];
                    $data = $b['benefit_data'];
                    if ($count <= 0) {
                        $this->error("benefit $ben quantify $count $data");
                        $count = 1;
                    }
                    $completed = $this->awardBenefits($bplayer, $ben, $count, $data);
                    if (!$completed)
                        return; // prev call makes state transition
                    $this->benefitCashed($bid);
                    break;
                case 'choice':
                case 'a':
                    $this->gamestate->nextState('benefitChoice');
                    return;
                case 'or':
                case 'o':
                    $this->gamestate->nextState('benefitOption');
                    return;
                case 'civ':
                    $this->gamestate->nextState('civReturn');
                    return;
                case 'bonus':
                case 'p':
                    $this->gamestate->nextState('bonus');
                    return;
                default:
                    $this->systemAssertTrue("invalid type $bcat");
                    return;
            }
        }
        $current_player = $this->getGameStateValue('current_player_turn');
        $this->switchPlayer($current_player, false);
        // check for end of turn trigger effects
        $player_id = $current_player;
        $this->checkPrivateAchievement(2, $player_id);
        if ($this->hasCiv($player_id, CIV_RELENTLESS)) {
            /** @var Relentless */
            $inst = $this->getCivilizationInstance(CIV_RELENTLESS, true);
            $inst->relentlessBenefitOnEndOfTurn($player_id);
        }

        // triggering of effect caused more stuff on stack
        if ($this->getCurrentBenefit()) {
            $this->gamestate->nextState('loopback');
            return;
        }
        $this->effect_endOfTurn($current_player);
        $player_id = $this->getActivePlayerId();
        if ($this->canSkipConfirm($player_id))
            $this->gamestate->nextState('nextPlayer');
        else
            $this->gamestate->nextState('finish');
    }

    /**
     * Override
     */
    function setStat($value, $name, $player_id = null, $bDoNotLoop = false) {
        if ($this->isRealPlayer($player_id) || $player_id == null)
            parent::setStat($value, $name, $player_id, $bDoNotLoop);
        else
            $this->warn("Calling set stat $name of $player_id");
    }

    /**
     * Override
     */
    function getStat($name, $player_id = null) {
        if ($this->isRealPlayer($player_id) || $player_id == null)
            return parent::getStat($name, $player_id);
        $this->warn("Calling get stat $name of $player_id");
        return 0;
    }

    function checkToppleAward($owner) {
        $owner_tiles = $this->getControlHexes($owner);
        $enemy_structures = 0;
        foreach ($owner_tiles as $coord => $hex) {
            foreach ($hex['structures'] as $struc) {
                if ($struc['card_location_arg'] == $owner) continue; // owned
                if ($struc['card_type_arg'] != 1) continue; // not toppled
                if ($struc['card_type'] != BUILDING_OUTPOST) continue; // not outpost
                $enemy_structures++;
                break;
            }
        }
        // ACHIEVEMENT MET
        if ($enemy_structures < 2)  return;

        // CHECK IF ALREADY AWARDED.
        $achievement = $this->getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'achievement_2_%'");
        $awarded = false;
        foreach ($achievement as $a) {
            if ($a['card_location_arg'] == $owner)
                $awarded = true;
        }
        if ($awarded)  return;
        $pos = sizeOf($achievement) + 1;
        $destination = 'achievement_2_' . $pos;
        $player_data = $this->loadPlayersBasicInfos();
        $player_count = sizeOf($player_data);
        if (($player_count < 4) && ($pos < 3)) {
            $points = (15 - ($pos * 5));
        } else if (($player_count > 3) && ($pos < 4)) {
            $points = (20 - ($pos * 5));
        } else {
            $points = 0;
        }
        if ($points > 0) {
            $token_id = $this->addCube($owner, $destination);
            $this->notifyMoveStructure(clienttranslate('${player_name} has toppled at least 2 opponent outposts'), $token_id, [], $owner);
        }
        $this->awardAchievementVP($owner, $points, reason('achi', clienttranslate('Topple 2')), $destination);
    }

    function awardAchievementVP($player_id, $count, $reason = null, $place = null) {
        $this->awardVP($player_id, $count, $reason, $place);
        if ($this->isAdjustments4()) {
            if ($this->hasCiv($player_id, CIV_CHOSEN)) {
                $this->notifyWithName('message', clienttranslate('${player_name} - The Chosen - gains achievement benefit'));
                $this->benefitCivEntry(CIV_CHOSEN, $player_id);
            }
        }
    }

    function effect_endOfConquer($player_id = null) {
        $this->clearCurrentBenefit(141);
        //$this->debugConsole("end of conquer for $player_id");

        // Let's find out who now owns it!
        if (!$player_id)
            $player_id = $this->getActivePlayerId();

        $map_data = $this->getSelectedMapHex();
        $owners = $map_data['map_owners'];
        $this->systemAssertTrue('conquer action can result only in one owner ' . toJson($map_data), count($owners) == 1);
        $owner = array_shift($owners);
        $this->checkMysticConquerBonus();
        // AWARD 2: Somebody has been toppled, have they done it twice (must be shown on board, e.g. no history.)
        $toppled = (count($map_data['map_occupants']) == 2);
        if ($toppled) {
            $this->checkToppleAward($owner);
        }
        // AWARD 3: Who owns the centre island?
        if (($map_data['map_coords'] == "0_0") && ($owner == $player_id)) {
            $achievement = $this->getObjectFromDB("SELECT card_location FROM structure WHERE card_location LIKE 'achievement_3_%' LIMIT 1"); // will be at most 1
            $awarded = (($achievement != null) && ($achievement['card_location'] == $player_id));
            if (!$awarded) {
                $pos = ($achievement == null) ? 1 : 2;
                $destination = 'achievement_3_' . $pos;
                $token_id = $this->addCube($player_id, $destination);
                $this->notifyMoveStructure(clienttranslate('${player_name} conquers the middle island'), $token_id, [], $player_id);
                $points = ($pos == 1) ? 10 : 5;
                $this->awardAchievementVP($player_id, $points, reason('achi', clienttranslate('Central island')), $destination);
            }
        }
        // If necessary, need to award both benefits without a choice!
        switch ($this->getGameStateValue('conquer_bonus')) {
            case 2:
                $both = true;
                break;
            case 1:
                $both = $toppled;
                break;
            default:
                $both = false;
                break;
        }
        if ($toppled && $owner == $player_id && $this->isTapestryActive($player_id, 30))
            $both = true; // PILLAGE AND PLUNDER
        if ($both) {
            $this->notifyWithName('message', clienttranslate('${player_name} gains benefits from both dice'));
            $this->conquerDieBenefit('black', $player_id);
            $this->conquerDieBenefit('red', $player_id);
            if ($this->isTapestryActive($player_id, 31)) { // PIRATE RULE
                $this->queueBenefitNormal($this->getTileBenefit(), $player_id, reason_tapestry(31));
            }
            $this->gamestate->nextState('next');
            return;
        }
        if ($this->isRealPlayer($player_id))
            $this->gamestate->nextState('conquer_die');
        else
            $this->gamestate->nextState('next');
    }

    function rollConquerDice($player_id) {
        // Roll conquer dice
        $die_red = bga_rand(0, 5);
        $die_black = bga_rand(0, 5);
        if ($die_black == 5)
            $die_black = 1;
        if ($die_red == 5)
            $die_red = 2;
        $this->setGameStateValue("conquer_die_red", $die_red);
        $this->setGameStateValue("conquer_die_black", $die_black);
        $this->notifyAllPlayers("conquer_roll", clienttranslate('${player_name} rolls the conquer dice ${black_name}/${red_name}'), array(
            'player_id' => $player_id, 'player_name' => $this->getPlayerNameById($player_id), 'die_red' => $die_red,
            'die_black' => $die_black, 'black_name' => $this->dice_names['black'][$die_black]['name'],
            'red_name' => $this->dice_names['red'][$die_red]['name'], 'i18n' => ['black_name', 'red_name'],
            'preserve' => ['die_red', 'die_black']
        ));
        $this->prepareUndoSavepoint();
    }

    function rollRedConquerDie($player_id) {
        // Roll conquer dice
        $die_red = bga_rand(0, 5);
        if ($die_red == 5)
            $die_red = 2;
        $this->setGameStateValue("conquer_die_red", $die_red);
        $this->notif('conquer_roll')->withPlayer($player_id)->withArg('red_name', $this->dice_names['red'][$die_red]['name'])->withPreserveArg('die_red', $die_red)->notifyAll(clienttranslate('${player_name} rolls red conquer dice ${red_name}'));
        //$this->prepareUndoSavepoint();
        return $die_red;
    }

    function rollBlackConquerDie($player_id) {
        // Roll conquer dice
        $die_black = bga_rand(0, 5);
        if ($die_black == 5)
            $die_black = 1;
        $this->setGameStateValue("conquer_die_black", $die_black);
        $ben_name = $this->dice_names['black'][$die_black]['name'];
        $notif =  $this->notif('conquer_roll', $player_id)->withArg('black_name', $ben_name)->withPreserveArg('die_black', $die_black);
        if ($die_black == 1) {
            // territory benefit
            $tileben = $this->getTileBenefit();

            if (count($tileben) == 0)
                $tilebenname = clienttranslate('No benefits');
            else
                $tilebenname = $this->getBenefitName($tileben[0]);


            $notif->withArg('tile_ben_name', $tilebenname)->notifyAll(clienttranslate('${player_name} rolls black conquer die ${black_name} (${tile_ben_name})'));
        } else {
            $notif->notifyAll(clienttranslate('${player_name} rolls black conquer die ${black_name}'));
        }


        //$this->prepareUndoSavepoint();
        return $die_black;
    }

    function notif($type = null, $player_id = null) {
        $builder = new NotifBuilder($this);
        $builder = $builder->ofType($type);
        if ($player_id)
            $builder = $builder->withPlayer($player_id);
        return $builder;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Zombie
    ////////////
    /*
     * zombieTurn:
     *
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged"
     * error message.
     */
    function zombieTurn($state = null, $active_player = null) {
        if (!$state)
            $state = $this->gamestate->state();
        if (!$active_player)
            $active_player = $this->getActivePlayerId();
        $statename = $state['name'];
        $player_id = $active_player;
        if ($state['type'] === "activeplayer") {
            $this->DbQuery("DELETE FROM benefit WHERE benefit_player_id='$player_id'");
            $main_player = $this->getGameStateValue('current_player_turn');
            if ($this->isIncomeTurn() && $main_player == $player_id) {
                $this->setIncomeTurnPhase(0, '', $player_id);
            }
            if ($statename == "playerTurnEnd") {
                $this->notifyWithName('message', clienttranslate('${player_name} is zombie, ends their game'), [], $player_id);
                $this->finalGameScoring($player_id);
                $this->gamestate->nextState('next');
                return;
            }
            $this->notifyWithName('message', clienttranslate('${player_name} is zombie, skips'), [], $player_id);
            $trans = $state['transitions'];
            if (isset($trans['decline']))
                $this->gamestate->nextState('decline');
            else if (isset($trans['next']))
                $this->gamestate->nextState('next');
            else
                $this->gamestate->jumpToState(18);
            return;
        } else if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, 'next');
            return;
        }
        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }
}

class NotifBuilder {
    private $type = 'message';
    private $args = [];
    private $player_id = null;
    private $preserve = [];
    private $game;

    function __construct($game) {
        $this->game = $game;
    }

    function ofType($p) {
        if ($p)
            $this->type = $p;
        return $this;
    }

    function withCard($card) {
        $this->args = $this->game->notifArgsAddCardInfo($card, $this->args);
        return $this;
    }

    function withStructure($id) {
        if (is_array($id)) {
            $curr = $id;
        } else {
            $curr = $this->game->getStructureInfoById($id, false);
            $this->game->systemAssertTrue("cannot find structure $id", $curr);
        }
        $location = $curr['card_location'];
        if (startsWith($location, 'land')) {
            $curr['coord_text'] = $this->game->coordText($location);
        }
        $type = $curr['card_type'];
        $curr['structure_name'] = $this->game->structure_types[$type]["name"];
        $this->args = array_merge($this->args, $curr);
        return $this;
    }

    function withArgs($args) {
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    function withArg($key, $value) {
        $this->args[$key] = $value;
        return $this;
    }

    function withPreserveArg($key, $value) {
        $this->args[$key] = $value;
        $this->preserve[$key] = 1;
        return $this;
    }

    /**
     * Injects ${track_name} and ${spot_name} variables (i18n) and ${track}/${spot} on preserve list
     *
     * @param number $track
     * @param number $spot
     * @return NotifBuilder
     */
    function withTrackSpot($track = null, $spot = null) {
        $this->args = $this->game->notifArgsAddTrackSpot($track, $spot, $this->args);
        return $this;
    }

    /**
     * Injects ${player_id} and ${player_name} variables (should not be i18n)
     *
     * @param number $p
     *            - player id
     * @return NotifBuilder
     */
    function withPlayer($p) {
        if (!$p)
            $p = $this->game->getActivePlayerId();
        $this->player_id = $p;
        return $this;
    }

    /**
     * Injects ${player_id2} and ${player_name2} variables (should not be i18n)
     *
     * @param number $owner
     *            - player id
     * @return NotifBuilder
     */
    function withPlayer2($owner) {
        if ($owner)
            $this->withArgs(['player_id2' => $owner, 'player_name2' => $this->game->getPlayerNameById($owner)]);
        return $this;
    }

    /**
     * Injects ${reason} variable (recursive, should not be i18n itself)
     *
     * @param string $reason
     *            - reason to inject user friendly format
     * @return NotifBuilder
     */
    function withReason($reason) {
        if ($reason)
            $this->args['reason'] = $this->game->getReasonFullRec($reason);
        return $this;
    }

    function notifyAll($message = null) {
        $this->send($message);
    }

    function notifyPlayer($message = null) {
        $this->withArg('_private', true);
        $this->send($message);
    }

    function send($message = null) {
        if (!$message)
            $message = '';
        if (count($this->preserve) > 0) {
            $p = array_keys($this->preserve);
            if (!isset($this->args['preserve']))
                $this->args['preserve'] = $p;
            else
                $this->args['preserve'] = array_merge($this->args['preserve'], $p);
        }
        $this->game->notifyWithName($this->type, $message, $this->args, $this->player_id);
    }
}
