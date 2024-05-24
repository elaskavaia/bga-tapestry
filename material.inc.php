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
 * material.inc.php
 *
 * tapestry game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */
if ( !defined('TAPESTRY')) { // guard since this included multiple times
    define("TAPESTRY", 0);
    define("TRACK_EXPLORATION", 1);
    define("TRACK_SCIENCE", 2);
    define("TRACK_MILITARY", 3);
    define("TRACK_TECHNOLOGY", 4);
    define("CARD_TERRITORY", 1);
    define("CARD_SPACE", 2);
    define("CARD_TAPESTRY", 3);
    define("CARD_TECHNOLOGY", 4);
    define("CARD_CIVILIZATION", 5);
    define("CARD_CAPITAL", 6);
    define("CARD_DECISION", 7);
    define("CARD_AUTOMACIV", 8);
    // RESOURCES
    define("RES_COIN", 1);
    define("RES_WORKER", 2);
    define("RES_FOOD", 3);
    define("RES_CULTURE", 4);
    define("RES_ANY", 5);
    define("RES_VP", 15);
    //  STRUCTURES
    define("BUILDING_MARKET", 1);
    define("BUILDING_HOUSE", 2);
    define("BUILDING_FARM", 3);
    define("BUILDING_ARMORY", 4);
    define("BUILDING_OUTPOST", 5);
    define("BUILDING_LANDMARK", 6);
    define("BUILDING_CUBE", 7);
    define("BUILDING_MARKER", 8);
    define("BUILDING_IMPASS", 9);
    define("BUILDING_ANYINCOME", 10);
    // cube sub types
    define("CUBE_MARKER", 3);
    define("CUBE_CIV", 2);
    define("CUBE_AI", 1);
    define("CUBE_NORMAL", 0);
    // players
    define("PLAYER_AUTOMA", 1);
    define("PLAYER_SHADOW", 2);
    // BENEFITS
    define("BE_GAIN_COIN", 1);
    define("BE_GAIN_WORKER", 2);
    define("BE_GAIN_FOOD", 3);
    define("BE_GAIN_CULTURE", 4);
    define("BE_ANYRES", 5);
    define("BE_TERRITORY", 6);
    define("BE_TAPESTRY", 7);
    define("BE_MARKET", 8);
    define("BE_HOUSE", 9);
    define("BE_FARM", 10);
    define("BE_ARMORY", 11);
    define("BE_INCOME_VP", 12);
    define("BE_UPGRADE_TECH_INCOME", 13);
    define("BE_UPGRADE_TECH", 14);
    define("BE_VP", 15);
    define("BE_INCOME_RES", 16);
    define("BE_EXPLORE", 17);
    define("BE_RESEARCH", 18);
    define("BE_RESEARCH_NB", 19);
    define("BE_INVENT", 20);
    define("BE_CONQUER", 21);
    define("BE_ADVANCE_E", 22);
    define("BE_ADVANCE_S", 23);
    define("BE_ADVANCE_M", 24);
    define("BE_ADVANCE_T", 25);
    define("BE_TECH_CARD", 26);
    define("BE_VP_TECH", 27);
    define("BE_VP_CAPITAL", 28);
    define("BE_VP_TERRITORY", 29);
    define("BE_VP_FARM", 30);
    define("BE_VP_ARMORY", 31);
    define("BE_TECH_CIRCLE", 32);
    define("BE_TECH_SQUARE", 33);
    define("BE_SPACE", 51);
    define("BE_EXPLORE_SPACE", 52);

    define("BE_VP_HOUSE", 63);
    define("BE_GAIN_CIV", 65);
    define("BE_VP_TILES", 66);
    define("BE_REGRESS_E", 67);
    define("BE_REGRESS_S", 68);
    define("BE_REGRESS_M", 69);
    define("BE_REGRESS_T", 70);
    define("BE_VP_TAPESTY", 71);
    define("BE_ADVANCE_EXPLORATION_BENEFIT_NOBONUS", 80);
    define("BE_ADVANCE_SCIENCE_BENEFIT_NOBONUS", 81);
    define("BE_ADVANCE_MILITARY_BENEFIT_NOBONUS", 82);
    define("BE_ADVANCE_TECHNOLOGY_BENEFIT_NOBONUS", 83);
    define("BE_ADVANCE_EXPLORATION_NOBENEFIT_OPT", 84);
    define("BE_ADVANCE_SCIENCE_NOBENEFIT_OPT", 85);
    define("BE_ADVANCE_MILITARY_NOBENEFIT_OPT", 86);
    define("BE_ADVANCE_TECHNOLOGY_NOBENEFIT_OPT", 87);
    define("BE_ADVANCE_EXPLORATION_BENEFIT_OPT", 88);
    define("BE_ADVANCE_SCIENCE_BENEFIT_OPT", 89);
    define("BE_ADVANCE_MILITARY_BENEFIT_OPT", 90);
    define("BE_ADVANCE_TECHNOLOGY_BENEFIT_OPT", 91);
    define("BE_GAIN_ANY_INCOME_BUILDING", 110);
    define("BE_STANDUP_3_OUTPOSTS", 119);
    define("BE_PLAY_TAPESTY_INCOME", 128);
    define("BE_EXPLORE_SPACE_ALIEN", 135);
    define("BE_CIV_ADJUSTMENT", 142);
    define("BE_REFRESH", 53);
    define("BE_REFRESH_DO", 143);
    define("BE_MIDGAME_SETUP", 180);
    define("BE_CONFIRM", 200);
    define("BE_RESUME", 201);
    define("BE_DECLINE", 401);
    define("BE_TINKERERS_1", 195);
    define("BE_TINKERERS_2", 196);
    define("BE_TINKERERS_3", 197);
    define("BE_TINKERERS_4", 198);
    define("BE_ALIEN_D", 306);
    define("BE_2_TERRITORY", 307);
    define("BE_2_INVENT", 308);
    define("BE_SPOT", 309);
    define("BE_RENEGADES_ADV", 310);
    define("BE_GAMBLES_PICK", 311);
    define("BE_COLLECTORS_GRAB", 312);
    define("BE_COLLECTORS_CARD", 313);
    define("BE_CARD_PLAY_TRIGGER", 314);
    
    // TERRAIN
    define("TERRAIN_SEA", 1);
    define("TERRAIN_DESERT", 2);
    define("TERRAIN_FOREST", 3);
    define("TERRAIN_GRASS", 4);
    define("TERRAIN_MOUNTAIN", 5);
    // CIV
    define("CIV_TEMPLATES", 0);
    define("CIV_ALCHEMISTS", 1);
    define("CIV_ARCHITECTS", 2);
    define("CIV_CRAFTSMEN", 3);
    define("CIV_ENTERTAINERS", 4);
    define("CIV_FUTURISTS", 5);
    define("CIV_HERALDS", 6);
    define("CIV_HISTORIANS", 7);
    define("CIV_INVENTORS", 8);
    define("CIV_ISOLATIONISTS", 9);
    define("CIV_LEADERS", 10);
    define("CIV_MERRYMAKERS", 11);
    define("CIV_MILITANTS", 12);
    define("CIV_MYSTICS", 13);
    define("CIV_NOMADS", 14);
    define("CIV_CHOSEN", 15);
    define("CIV_TRADERS", 16);
    // AA
    define("CIV_COLLECTORS", 21);
    define("CIV_GAMBLERS", 22);
    define("CIV_RELENTLESS", 23);
    define("CIV_RENEGADES", 24);
    define("CIV_URBAN_PLANNERS", 25);
    // PP
    define("CIV_ADVISORS", 30);
    define("CIV_ALIENS", 31);
    define("CIV_INFILTRATORS", 32);
    define("CIV_ISLANDERS", 33);
    define("CIV_RECYCLERS", 34);
    define("CIV_RIVERFOLK", 35);
    define("CIV_SPIES", 36);
    define("CIV_TINKERERS", 37);
    define("CIV_TREASURE_HUNTERS", 38);
    define("CIV_UTILITARIENS", 39);
    // income phases
    define("INCOME_FIRSTBONUS", 1);
    define("INCOME_CIV", 10);
    define("INCOME_TAPESTRY", 20);
    define("INCOME_UPGRADE", 30);
    define("INCOME_VP", 35);
    define("INCOME_RES", 40);
    // TAPESTRY CARDS
    define("TAP_ACADEMIA", 1);
    define("TAP_AGE_OF_DISCOVERY", 2);
    define("TAP_AGE_OF_SAIL", 3);
    define("TAP_COAL_BARON", 9);
    define("TAP_DARK_AGES", 11);
    define("TAP_DICTATORSHIP", 13);
    define("TAP_EMPIRICISM", 16);
    define("TAP_ESPIONAGE", 17);
    define("TAP_MARRIAGE_OF_STATE", 23);
    define("TAP_MERCANTILISM", 24);
    define("TAP_MILITARISM", 26);
    define("TAP_OLYMPIC_HOST", 29);
    define("TAP_REVISIONISM", 34);
    define("TAP_STEAM_TYCOON", 37);
    define("TAP_TRADE_ECONOMY", 41);
    define("TAP_TRAP", 42);
    define("TAP_TYRANNY", 43);
    // BENEFIT FLAGS
    define("FLAG_GAIN_BENFIT", 0b0001); // gain befit
    define("FLAG_PAY_BONUS", 0b0010); // may pay for bonus
    define("FLAG_FREE_BONUS", 0b0100); // gain free bonus
    define("FLAG_MAXOUT_BONUS", 0b1000); // gain 5VP if maxout
    define("FLAG_JUMP", 0b10000); // do not count as advance
    // conquir and explore
    define("FLAG_ANYWHERE", 0b0001); // explore or conquer anywhere
    define("FLAG_NO_BENEFIT", 0b10000); // explore or conquer without benefit
    // conq
    define("FLAG_CONQ_BOTH_DICE", 0b0010);
    define("FLAG_CONQ_OPPONENT", 0b0100);
    // invent
    define("FLAG_FACE_UP", 0b0010);
    define("FLAG_FACE_DOWN", 0b001);
    define("FLAG_FACE_BOTH", 0b011);
    define("FLAG_UPGRADE", 0b0100);
   
    // opponent selection
    define("FLAG_NEIGHBOUR", 0b0100);
    define("FLAG_SELF", 0b0001);
    define("FLAG_OPPONENT", 0b0010);
    // target cubes
    define("FLAG_SINGLE", 0b01000); // must select specific cube, not just slot
    define("FLAG_VIRTUAL_ALLOWED", 0b10000);
    define("FLAG_TARGET", 0b00100000);

    // track advance flags
    define("FLAG_ADVANCE", 0b0001);
    define("FLAG_REGRESS", 0b0010);
    define("FLAG_STAY", 0b0100);
    define("FLAG_POSALL", 0b0111);
    define("FLAG_POSCLOSEST", 0b00001000);
    define("FLAG_POSEXACT", 0b00010000);

    define("SPOT_SELECT", -1);
    define("ACTION_ADVANCE", 1);
    define("ACTION_REGRESS", -1);
    define("ACTION_REPEAT", 0);
    define("ORDER_INTERRUPT", -1);
    define("ORDER_NORMAL", 0);
    // markers
    define("MARKER_ONCE", 3); // mark spot that can only be used once per turn
    define("MARKER_SELECT", 4); // mark currently selected spot/tile/etc
    //define("MARKER_ESPIONAGE", 200 + TAP_ESPIONAGE); // mark tapestry which was originally espionage
    define("EXP_AA_FLAG", 0b100);
    define("EXP_PP_FLAG", 0b010);
    define("EXP_BA_FLAG", 0b001);
}
if ( !function_exists('str_repeat_join')) {

    function str_repeat_join($str, $count, $on = ",") {
        if ( !$count)
            return '';
        if ($count == 1)
            return $str;
        $res = str_repeat($on . $str, $count - 1);
        return $str . $res;
    }
}
$this->decision_cards = [ /* --- gen php begin decision_deck --- */
// #columns: i - income, t - topple, at - automa track, st - shadow track
// #st,at values: a - all, f - close to finish, l - landmark or finish
// #tt - track tiebreaker exp 1, sci 2, mil 3, tech 4, fav 5,
// #mt map break, start node, first increment, second increment
 1 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'f',
  'st' => 'a',
  'tt' => 53142,
  'mt' => '-3,-3,0,1,1,0',
],
 2 => [  //
  'i' => 1,
  't' => 0,
  'at' => 'a',
  'st' => 'l',
  'tt' => 31524,
  'mt' => '-3,-1,0,-1,1,0',
],
 3 => [  //
  'i' => 0,
  't' => 1,
  'at' => 'l',
  'st' => 'f',
  'tt' => 24315,
  'mt' => '-3,-3,0,1,1,0',
],
 4 => [  //
  'i' => 1,
  't' => 0,
  'at' => 'l',
  'st' => 'a',
  'tt' => 42531,
  'mt' => '-3,-1,1,1,0,-1',
],
 5 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'a',
  'st' => 'l',
  'tt' => 13425,
  'mt' => '0,-3,1,1,0,1',
],
 6 => [  //
  'i' => 0,
  't' => 1,
  'at' => 'a',
  'st' => 'a',
  'tt' => 32541,
  'mt' => '-3,-3,0,1,1,0',
],
 7 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'a',
  'st' => 'a',
  'tt' => 54132,
  'mt' => '0,3,1,0,0,-1',
],
 8 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'a',
  'st' => 'a',
  'tt' => 13452,
  'mt' => '0,3,1,0,0,-1',
],
 9 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'a',
  'st' => 'a',
  'tt' => 42513,
  'mt' => '0,3,1,0,0,-1',
],
 10 => [  //
  'i' => 0,
  't' => 1,
  'at' => 'l',
  'st' => 'a',
  'tt' => 31425,
  'mt' => '0,3,1,0,0,-1',
],
 11 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'a',
  'st' => 'l',
  'tt' => 51324,
  'mt' => '0,3,1,0,0,-1',
],
 12 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'a',
  'st' => 'a',
  'tt' => 24531,
  'mt' => '0,3,1,0,0,-1',
],
 13 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'a',
  'st' => 'l',
  'tt' => 24351,
  'mt' => '0,3,1,0,0,-1',
],
 14 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'l',
  'st' => 'a',
  'tt' => 15234,
  'mt' => '0,3,1,0,0,-1',
],
 15 => [  //
  'i' => 0,
  't' => 1,
  'at' => 'l',
  'st' => 'l',
  'tt' => 53412,
  'mt' => '0,3,1,0,0,-1',
],
 16 => [  //
  'i' => 1,
  't' => 0,
  'at' => 'a',
  'st' => 'l',
  'tt' => 41523,
  'mt' => '0,3,1,0,0,-1',
],
 17 => [  //
  'i' => 1,
  't' => 0,
  'at' => 'a',
  'st' => 'a',
  'tt' => 15432,
  'mt' => '0,3,1,0,0,-1',
],
 18 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'l',
  'st' => 'a',
  'tt' => 51324,
  'mt' => '0,3,1,0,0,-1',
],
 19 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'a',
  'st' => 'a',
  'tt' => 32415,
  'mt' => '0,3,1,0,0,-1',
],
 20 => [  //
  'i' => 0,
  't' => 0,
  'at' => 'a',
  'st' => 'a',
  'tt' => 41235,
  'mt' => '0,3,1,0,0,-1',
],
 21 => [  //
  'i' => 1,
  't' => 1,
  'at' => 'a',
  'st' => 'f',
  'tt' => 14532,
  'mt' => '0,3,1,0,0,-1',
],
 22 => [  //
  'i' => 0,
  't' => 1,
  'at' => 'f',
  'st' => 'a',
  'tt' => 31452,
  'mt' => '0,3,1,0,0,-1',
],
        /* --- gen php end decision_deck --- */
];
$this->territory_tiles = array (
        // 0 is top left vertex. Rest follow clockwise
        1 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],),
        2 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA],'benefit' => [ BE_2_TERRITORY ],),
        3 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],),
        4 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],),
        5 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],'h'=>[TERRAIN_DESERT]),
        6 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],),
        7 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],'h'=>[TERRAIN_DESERT]),
        8 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT],'benefit' => [ BE_FARM ],
                ),
        9 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA],'benefit' => [ BE_TAPESTRY ],
                ),
        10 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        11 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        12 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_DESERT],'benefit' => [ BE_RESEARCH_NB ],
                ),
        13 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],
                ),
        14 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        15 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        16 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],'h'=>[TERRAIN_FOREST]
                ),
        17 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],'h'=>[TERRAIN_FOREST]
                ),
        18 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST, TERRAIN_FOREST],'benefit' => [ BE_MARKET ],),
        19 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_SEA],'benefit' => [ BE_TAPESTRY ],
                ),
        20 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        21 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        22 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_FOREST],'benefit' => [ BE_UPGRADE_TECH ],
                ),
        23 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_SEA],'benefit' => [ BE_INVENT ],
                ),
        24 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],
                ),
        25 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_GRASS],'benefit' => [ BE_HOUSE ],
                ),
        26 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],'h'=>[TERRAIN_GRASS]
                ),
        27 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        28 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],'h'=>[TERRAIN_GRASS]
                ),
        29 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA],'benefit' => [ BE_TAPESTRY ],
                ),
        30 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        31 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        32 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_GRASS],'benefit' => [ BE_INVENT ],
                ),
        33 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA],'benefit' => [ BE_ARMORY ],
                ),
        34 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],
                ),
        35 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        36 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],
                ),
        37 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],
                ),
        38 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        39 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        40 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_COIN ],'h'=>[TERRAIN_MOUNTAIN]
                ),
        41 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],'h'=>[TERRAIN_MOUNTAIN]
                ),
        42 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN],'benefit' => [ BE_INVENT ],
                ),
        43 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_SEA],'benefit' => [ BE_TAPESTRY ],
                ),
        44 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_CULTURE ],
                ),
        45 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_WORKER ],
                ),
        46 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN],'benefit' => [ BE_RESEARCH_NB ],
                ),
        47 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_SEA],'benefit' => [ BE_TERRITORY,BE_TERRITORY ],
                ),
        48 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA],'benefit' => [ BE_GAIN_FOOD ],
                ),
        // FOLLOWING ARE NOT ACTUAL TILES, BUT STARTING TERRAIN ON MAP!
        49 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],'h'=>[TERRAIN_DESERT,TERRAIN_FOREST,TERRAIN_GRASS,TERRAIN_MOUNTAIN]),
        50 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_SEA],),
        51 => array('x'=>[TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_FOREST],),
        52 => array('x'=>[TERRAIN_MOUNTAIN,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_MOUNTAIN],),
        53 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA],),
        54 => array('x'=>[TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_MOUNTAIN,TERRAIN_SEA],),
        55 => array('x'=>[TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_GRASS],),
        56 => array('x'=>[TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],),
        57 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_SEA],),
        58 => array('x'=>[TERRAIN_GRASS,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],),
        59 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_DESERT],),
        60 => array('x'=>[TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_DESERT,TERRAIN_DESERT],),
        61 => array('x'=>[TERRAIN_MOUNTAIN,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_MOUNTAIN],),
        62 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA],),
        63 => array('x'=>[TERRAIN_DESERT,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_DESERT],),
        64 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_SEA],),
        65 => array('x'=>[TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_DESERT,TERRAIN_DESERT],),
        66 => array('x'=>[TERRAIN_GRASS,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_GRASS],),
        67 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_SEA],),
        68 => array('x'=>[TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_GRASS],),
        69 => array('x'=>[TERRAIN_SEA,TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_DESERT],),
        70 => array('x'=>[TERRAIN_MOUNTAIN,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN],),
        71 => array('x'=>[TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_DESERT,TERRAIN_DESERT,TERRAIN_SEA,TERRAIN_DESERT],),
        72 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA],),
        73 => array('x'=>[TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN,TERRAIN_SEA,TERRAIN_SEA],),
        74 => array('x'=>[TERRAIN_FOREST,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_SEA,TERRAIN_GRASS,TERRAIN_FOREST],),
        75 => array('x'=>[TERRAIN_MOUNTAIN,TERRAIN_GRASS,TERRAIN_GRASS,TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_MOUNTAIN],),
        76 => array('x'=>[TERRAIN_SEA,TERRAIN_SEA,TERRAIN_MOUNTAIN,TERRAIN_FOREST,TERRAIN_FOREST,TERRAIN_SEA],),
        77 => [ "x"=>[1,1,1,TERRAIN_GRASS,1,1] ], 
        78 => [ "x"=>[TERRAIN_MOUNTAIN,TERRAIN_GRASS,TERRAIN_GRASS,
                TERRAIN_GRASS,TERRAIN_FOREST,TERRAIN_FOREST,
                TERRAIN_FOREST, TERRAIN_DESERT, TERRAIN_DESERT, 
                TERRAIN_DESERT, TERRAIN_MOUNTAIN, TERRAIN_MOUNTAIN]], 
);
$this->map = array (
        "large" => array ("0_0" => [ 'id' => 49,'orient' => 0 ],
                // TOP LEFT
                "-1_-4" => [ 'id' => 50,'orient' => 0 ],"0_-3" => [ 'id' => 51,'orient' => 0 ],
                "1_-3" => [ 'id' => 61,'orient' => 0 ],"0_-2" => [ 'id' => 62,'orient' => 0 ],
                // BOTTOM LEFT
                "3_-1" => [ 'id' => 63,'orient' => 0 ],"3_0" => [ 'id' => 55,'orient' => 5 ],
                "2_0" => [ 'id' => 57,'orient' => 5 ],"4_1" => [ 'id' => 56,'orient' => 5 ],
                // BOTTOM
                "2_2" => [ 'id' => 64,'orient' => 0 ],"3_3" => [ 'id' => 65,'orient' => 0 ],
                "4_3" => [ 'id' => 66,'orient' => 0 ],"3_4" => [ 'id' => 67,'orient' => 0 ],
                // BOTTOM RIGHT
                "0_2" => [ 'id' => 68,'orient' => 0 ],"0_3" => [ 'id' => 76,'orient' => 5 ],
                "1_4" => [ 'id' => 69,'orient' => 0 ],"-1_3" => [ 'id' => 70,'orient' => 0 ],
                // TOP RIGHT
                "-4_-1" => [ 'id' => 77,'orient' => 0 ],"-3_0" => [ 'id' => 59,'orient' => 4 ],
                "-2_0" => [ 'id' => 58,'orient' => 4 ],"-3_1" => [ 'id' => 71,'orient' => 0 ],
                // TOP
                "-2_-2" => [ 'id' => 72,'orient' => 0 ],"-3_-3" => [ 'id' => 73,'orient' => 0 ],
                "-4_-3" => [ 'id' => 75,'orient' => 0 ],"-3_-4" => [ 'id' => 74,'orient' => 0 ], ),
        "small" => array ("0_0" => [ 'id' => 49,'orient' => 0 ],
                // TOP
                "-3_-3" => [ 'id' => 50,'orient' => 0 ],"-2_-2" => [ 'id' => 51,'orient' => 0 ],
                "-1_-2" => [ 'id' => 52,'orient' => 0 ],"-2_-1" => [ 'id' => 53,'orient' => 0 ],
                // BOTTOM LEFT
                "1_-1" => [ 'id' => 54,'orient' => 0 ],"2_0" => [ 'id' => 55,'orient' => 0 ],
                "3_0" => [ 'id' => 56,'orient' => 0 ],"2_1" => [ 'id' => 76,'orient' => 0 ],
                // BOTTOM RIGHT
                "-1_1" => [ 'id' => 58,'orient' => 0 ],"0_2" => [ 'id' => 59,'orient' => 0 ],
                "1_2" => [ 'id' => 60,'orient' => 0 ],"0_3" => [ 'id' => 77,'orient' => 2 ], ),
        "islanders" => [
                "0_0" => [ 'id' => 78,'orient' => 0],
                "0_-1" => [ 'edge' => 4],
                "0_1" => [ 'edge' => 1 ],
                "-1_-1" => ['edge' => 5 ],
                "-1_0" => ['edge' => 0 ],
                "1_0" => ['edge' => 3 ],
                "1_1" => ['edge' => 2 ],
        ]
        
        );
$this->terrain_types = array (
        0 => array ("name" => clienttranslate("Empty")),
        1 => array ("name" => clienttranslate("Sea") ),
        2 => array ("name" => clienttranslate("Desert") ),3 => array ("name" => clienttranslate("Forest") ),
        4 => array ("name" => clienttranslate("Grass") ),5 => array ("name" => clienttranslate("Mountain") ) );
$this->structure_types =// 
array (BUILDING_MARKET => array ("name" => clienttranslate("Market"),"mask" => [ "1" ] ),
        BUILDING_HOUSE => array ("name" => clienttranslate("House"),"mask" => [ "1" ] ),
        BUILDING_FARM => array ("name" => clienttranslate("Farm"),"mask" => [ "1" ] ),
        BUILDING_ARMORY => array ("name" => clienttranslate("Armory"),"mask" => [ "1" ] ),
        BUILDING_OUTPOST => array ("name" => clienttranslate("Outpost"),"mask" => [ "1" ] ),
        BUILDING_LANDMARK => [ "name" => clienttranslate("Landmark"), ],
        BUILDING_CUBE => [ "name" => clienttranslate("Token"), ], 
        BUILDING_MARKER => [ "name" => clienttranslate("Marker"), ],
        BUILDING_IMPASS => [ "name" => clienttranslate("Impasseable Land"), ],
        BUILDING_ANYINCOME => [ "name" => clienttranslate("Income Building"), ], 
);
$this->dice = array (
        'red' => array (0 => [ 505 ],1 => [ 506 ],2 => [ BE_VP_TERRITORY ],3 => [ 504 ],4 => [ 507 ],
                5 => [ BE_VP_TERRITORY ], ),
        'black' => array (0 => [ BE_GAIN_COIN ],2 => [ BE_GAIN_WORKER ],3 => [ BE_GAIN_FOOD ],
                4 => [ BE_GAIN_CULTURE ],1 => [ 'territory tile benefit' ],5 => [ 'territory tile benefit' ], ),
        'science' => array (0 => [ ],1 => [ ],2 => [ ],3 => [ ] ), );
$this->space_tiles = [//
        1 => ['benefit'=>[ 23, 48 ]],
        2 => ['benefit'=>[ 11,31 ]],
        3 => ['benefit'=>[ 9, 63 ]],
        4 => ['benefit'=>[ 'choice' => [ BE_TAPESTRY,64,71 ] ]], // 3 in sequence... tap, play tap, score tap.
        5 => ['benefit'=>[ 1,2,508 ]],
        6 => ['benefit'=>[ 18,506 ]],
        7 => ['benefit'=>[ 3,4,508 ]],
        8 => ['benefit'=>[ 4,28 ]],
        9 => ['benefit'=>[ 25, 50 ]],
        10 => ['benefit'=>[ 'choice' => [ 20,14,27 ] ]], // 3 in sequence... tech, upgrade, score tech
        11 => ['benefit'=>[ 21,29 ]],
        12 => ['benefit'=>[ 24,49 ]], // military track, vp military
        13 => ['benefit'=>[ 513 ]],
        14 => ['benefit'=>[ 10,30 ]],
	15 => ['benefit'=>[ 8,54 ]], //
];
$this->automa_civ_cards = [ 1 => [ 'name' => clienttranslate("Explorers"),"description" => clienttranslate('') ], //
        2 => [ 'name' => clienttranslate("Scientist"),"description" => clienttranslate('') ], //
        3 => [ 'name' => clienttranslate("Conquerors"),
                "description" => clienttranslate('Income Turns 2-5: If Automa controls fewer territories than the income turn number, it does a conquer action. If more than 2 territories controlled by the Automa have only 1 token, place a toppled Shadow Empire outpost of one of those.<p>Income Turns 2-5: The Automa gains 1 VP extra for each territory it controls.') ], //
        4 => [ 'name' => clienttranslate("Engineers"),"description" => clienttranslate('') ], // 
];

/**
 *
 * @var * $benefit_types - benefits definition
 *     
 *      name - translatable name
 *      r - main action type e - explore, i - invent, s - explore space, t - track advance, v - gain vp, p - pay
 *      something, a - conqier, r - research
 *      t - track number, see $this->tech_track_types, 5 - track rolled by research die
 *      adv - advance, regress or repeat
 *      flags - other action flags
 *      tap - tapestry card # when related
 *      lm - landmark id when related
 */
$this->benefit_types = [ //
        /* --- gen php begin benefit_types --- */
 1 => [  // BE_GAIN_COIN
  'name' => clienttranslate("Coin"),
  'r' => 'g',
  'id'=>'coin','tt'=>'res','ct'=>RES_COIN,
],
 2 => [  // BE_GAIN_WORKER
  'name' => clienttranslate("Worker"),
  'r' => 'g',
  'id'=>'worker','tt'=>'res','ct'=>RES_WORKER,
],
 3 => [  // BE_GAIN_FOOD
  'name' => clienttranslate("Food"),
  'r' => 'g',
  'id'=>'food','tt'=>'res','ct'=>RES_FOOD,
],
 4 => [  // BE_GAIN_CULTURE
  'name' => clienttranslate("Culture"),
  'r' => 'g',
  'id'=>'culture','tt'=>'res','ct'=>RES_CULTURE,
],
 5 => [  // BE_ANYRES
  'name' => clienttranslate("Any resource"),
  'r' => 'g',
  'tt'=>'res','ct'=>BE_ANYRES, 'state'=>'any_resource',
],
 6 => [  // BE_TERRITORY
  'name' => clienttranslate("Territory tile"),
  'r' => 'g',
  'tt'=>'card','ct'=>CARD_TERRITORY,
],
 7 => [  // BE_TAPESTRY
  'name' => clienttranslate("Tapestry card"),
  'r' => 'g',
  'tt'=>'card','ct'=>CARD_TAPESTRY,
],
 8 => [  // BE_MARKET
  'name' => clienttranslate("Market"),
  'r' => 'g',
  'tt'=>'structure','ct'=>BUILDING_MARKET,
],
 9 => [  // BE_HOUSE
  'name' => clienttranslate("House"),
  'r' => 'g',
  'tt'=>'structure','ct'=>BUILDING_HOUSE,
],
 10 => [  // BE_FARM
  'name' => clienttranslate("Farm"),
  'r' => 'g',
  'tt'=>'structure','ct'=>BUILDING_FARM,
],
 11 => [  // BE_ARMORY
  'name' => clienttranslate("Armory"),
  'r' => 'g',
  'tt'=>'structure','ct'=>BUILDING_ARMORY,
],
 12 => [  // BE_INCOME_VP
  'name' => clienttranslate("Gain VP (Income)"),
  'r' => 'v',
],
 13 => [  // BE_UPGRADE_TECH_INCOME
  'name' => clienttranslate("Upgrade (Income)"),
],
 14 => [  // BE_UPGRADE_TECH
  'name' => clienttranslate("Upgrade tech card"),
],
 15 => [  // BE_VP
  'name' => clienttranslate("Gain victory points"),
  'r' => 'v',
],
 16 => [  // BE_INCOME_RES
  'name' => clienttranslate("Gain resources (Income)"),
  'icon' => 'no',
],
 17 => [  // BE_EXPLORE
  'name' => clienttranslate("Explore"),
  'r' => 'e',
  'flags'=>0,
],
 18 => [  // BE_RESEARCH
  'name' => clienttranslate("Research"),
  'r' => 'r',
  't' => 5,
  'adv'=>1,'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 19 => [  // BE_RESEARCH_NB
  'name' => clienttranslate("Research (no benefits)"),
  'r' => 'r',
  't' => 5,
  'adv'=>1,'flags'=>0,
],
 20 => [  // BE_INVENT
  'name' => clienttranslate("Invent"),
  'r' => 'i',
  'flags'=>(FLAG_FACE_BOTH),
],
 21 => [  // BE_CONQUER
  'name' => clienttranslate("Conquer"),
  'r' => 'a',
  'flags'=>0,
],
 22 => [  // BE_ADVANCE_E
  'name' => clienttranslate("Advance - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 23 => [  // BE_ADVANCE_S
  'name' => clienttranslate("Advance - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 24 => [  // BE_ADVANCE_M
  'name' => clienttranslate("Advance - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 25 => [  // BE_ADVANCE_T
  'name' => clienttranslate("Advance - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 26 => [  // BE_TECH_CARD
  'name' => clienttranslate("Tech Card"),
  'r' => 'g',
  'tt'=>'card','ct'=>CARD_TECHNOLOGY,
],
 27 => [  // BE_VP_TECH
  'name' => clienttranslate("VP - Tech cards"),
  'r' => 'v',
],
 28 => [  // BE_VP_CAPITAL
  'name' => clienttranslate("VP - Capital score"),
  'r' => 'v',
],
 29 => [  // BE_VP_TERRITORY
  'name' => clienttranslate("VP - Controlled territories"),
  'r' => 'v',
],
 30 => [  // BE_VP_FARM
  'name' => clienttranslate("VP - Farms"),
  'r' => 'v',
],
 31 => [  // BE_VP_ARMORY
  'name' => clienttranslate("VP - Armories"),
  'r' => 'v',
],
 32 => [  // BE_TECH_CIRCLE
  'name' => clienttranslate("Gain tech card benefit - Circle"),
],
 33 => [  // BE_TECH_SQUARE
  'name' => clienttranslate("Gain tech card benefit - Square"),
],
 34 => [  //
  'name' => clienttranslate("Landmark - Forge"),
  'lm'=>10,
],
 35 => [  //
  'name' => clienttranslate("Landmark - Rubber Works"),
  'lm'=>8,
],
 36 => [  //
  'name' => clienttranslate("Landmark - Tech Hub"),
  'lm'=>1,
],
 37 => [  //
  'name' => clienttranslate("Landmark - Barracks"),
  'lm'=>6,
],
 38 => [  //
  'name' => clienttranslate("Landmark - Tank Factory"),
  'lm'=>5,
],
 39 => [  //
  'name' => clienttranslate("Landmark - Fusion Reactor"),
  'lm'=>12,
],
 40 => [  //
  'name' => clienttranslate("Landmark - Lighthouse"),
  'lm'=>9,
],
 41 => [  //
  'name' => clienttranslate("Landmark - Train Station"),
  'lm'=>4,
],
 42 => [  //
  'name' => clienttranslate("Landmark - Launch Pad"),
  'lm'=>11,
],
 43 => [  //
  'name' => clienttranslate("Landmark - Apothecary"),
  'lm'=>2,
],
 44 => [  //
  'name' => clienttranslate("Landmark - Academy"),
  'lm'=>3,
],
 45 => [  //
  'name' => clienttranslate("Landmark - Laboratory"),
  'lm'=>7,
],
 46 => [  //
  'name' => clienttranslate("Explore (anywhere)"),
  'r' => 'e',
  'flags'=>FLAG_ANYWHERE,
],
 47 => [  //
  'name' => clienttranslate("VP - Exploration"),
  'r' => 'v',
],
 48 => [  //
  'name' => clienttranslate("VP - Science"),
  'r' => 'v',
],
 49 => [  //
  'name' => clienttranslate("VP - Military"),
  'r' => 'v',
],
 50 => [  //
  'name' => clienttranslate("VP - Technology"),
  'r' => 'v',
],
 51 => [  // BE_SPACE
  'name' => clienttranslate("Gain space tile"),
],
 52 => [  // BE_EXPLORE_SPACE
  'name' => clienttranslate("Explore space"),
  'r' => 's',
  'state'=>'explore_space',
],
// #same as 143 for now
 53 => [  // BE_REFRESH
  'name' => clienttranslate("Refresh tech cards (Optional)"),
  'alias'=>['or'=>[143, 401]],
],
 54 => [  //
  'name' => clienttranslate("VP - Markets"),
  'r' => 'v',
],
 55 => [  //
  'name' => clienttranslate("Start new tech track"),
  's'=>'t','sflags'=>0,'adv'=>0,
],
 56 => [  //
  'name' => clienttranslate("Landmark - Bakery"),
  'lm'=>13,
],
 57 => [  //
  'name' => clienttranslate("Landmark - Barn"),
  'lm'=>14,
],
 58 => [  //
  'name' => clienttranslate("Landmark - Com Tower"),
  'lm'=>15,
],
 59 => [  //
  'name' => clienttranslate("Landmark - Library"),
  'lm'=>16,
],
 60 => [  //
  'name' => clienttranslate("Landmark - Stock Market"),
  'lm'=>17,
],
 61 => [  //
  'name' => clienttranslate("Landmark - Treasury"),
  'lm'=>18,
],
 62 => [  //
  'name' => clienttranslate("Repeat current position"),
  'adv'=>0,'flags'=> (FLAG_GAIN_BENFIT | FLAG_PAY_BONUS),'s'=>'c', 'sflags'=>(FLAG_SELF),
],
 63 => [  // BE_VP_HOUSE
  'name' => clienttranslate("VP - Houses"),
  'r' => 'v',
],
 64 => [  //
  'name' => clienttranslate("Overwrite tapestry card"),
],
 65 => [  // BE_GAIN_CIV
  'name' => clienttranslate("Civilization"),
],
 66 => [  // BE_VP_TILES
  'name' => clienttranslate("VP - Territory tiles"),
  'r' => 'v',
],
 67 => [  // BE_REGRESS_E
  'name' => clienttranslate("Regress - Exploration"),
  'r' => 't',
  't' => 1,
  'adv'=>-1,'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 68 => [  // BE_REGRESS_S
  'name' => clienttranslate("Regress - Science"),
  'r' => 't',
  't' => 2,
  'adv'=>-1,'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 69 => [  // BE_REGRESS_M
  'name' => clienttranslate("Regress - Military"),
  'r' => 't',
  't' => 3,
  'adv'=>-1,'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 70 => [  // BE_REGRESS_T
  'name' => clienttranslate("Regress - Technology"),
  'r' => 't',
  't' => 4,
  'adv'=>-1,'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 71 => [  // BE_VP_TAPESTY
  'name' => clienttranslate("VP - Tapestry cards"),
  'r' => 'v',
],
 72 => [  //
  'name' => clienttranslate("Research (no benefit, maxout bonus)"),
  'r' => 'r',
  'flags'=>(FLAG_MAXOUT_BONUS),
],
 73 => [  //
  'name' => clienttranslate("Conquer - both dice if toppled"),
  'r' => 'a',
  'flags'=>(FLAG_CONQ_BOTH_DICE | FLAG_CONQ_OPPONENT),
],
 74 => [  //
  'name' => clienttranslate("Conquer - both dice"),
  'r' => 'a',
  'flags'=>2,
],
 75 => [  //
  'name' => clienttranslate("Conquer (anywhere)"),
  'r' => 'a',
  'flags'=>1,
],
 76 => [  //
  'name' => clienttranslate("Advance (no benefits) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>0,
],
 77 => [  //
  'name' => clienttranslate("Advance (no benefits) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>0,
],
 78 => [  //
  'name' => clienttranslate("Advance (no benefits) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>0,
],
 79 => [  //
  'name' => clienttranslate("Advance (no benefits) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>0,
],
 80 => [  // BE_ADVANCE_EXPLORATION_BENEFIT_NOBONUS
  'name' => clienttranslate("Advance (benefit, but not bonus) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_GAIN_BENFIT),
],
 81 => [  // BE_ADVANCE_SCIENCE_BENEFIT_NOBONUS
  'name' => clienttranslate("Advance (benefit, but not bonus) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_GAIN_BENFIT),
],
 82 => [  // BE_ADVANCE_MILITARY_BENEFIT_NOBONUS
  'name' => clienttranslate("Advance (benefit, but not bonus) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_GAIN_BENFIT),
],
 83 => [  // BE_ADVANCE_TECHNOLOGY_BENEFIT_NOBONUS
  'name' => clienttranslate("Advance (benefit, but not bonus) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_GAIN_BENFIT),
],
 84 => [  // BE_ADVANCE_EXPLORATION_NOBENEFIT_OPT
  'name' => clienttranslate("Advance (choice, no benefits) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>0,
],
 85 => [  // BE_ADVANCE_SCIENCE_NOBENEFIT_OPT
  'name' => clienttranslate("Advance (optional, no benefits) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>0,
],
 86 => [  // BE_ADVANCE_MILITARY_NOBENEFIT_OPT
  'name' => clienttranslate("Advance (optional, no benefits) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>0,
],
 87 => [  // BE_ADVANCE_TECHNOLOGY_NOBENEFIT_OPT
  'name' => clienttranslate("Advance (optional, no benefits) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>0,
],
 88 => [  // BE_ADVANCE_EXPLORATION_BENEFIT_OPT
  'name' => clienttranslate("Advance (optional, benefits) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 89 => [  // BE_ADVANCE_SCIENCE_BENEFIT_OPT
  'name' => clienttranslate("Advance (optional, benefits) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 90 => [  // BE_ADVANCE_MILITARY_BENEFIT_OPT
  'name' => clienttranslate("Advance (optional, benefits) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 91 => [  // BE_ADVANCE_TECHNOLOGY_BENEFIT_OPT
  'name' => clienttranslate("Advance (optional, benefits) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS),
],
 92 => [  //
  'name' => clienttranslate("Advance (no benefit, free bonus) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_FREE_BONUS),
],
 93 => [  //
  'name' => clienttranslate("Advance (no benefit, free bonus) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_FREE_BONUS),
],
 94 => [  //
  'name' => clienttranslate("Advance (no benefit, free bonus) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_FREE_BONUS),
],
 95 => [  //
  'name' => clienttranslate("Advance (no benefit, free bonus) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_FREE_BONUS),
],
 96 => [  //
  'name' => clienttranslate("Invent and instantly upgrade"),
  'r' => 'i',
  'flags'=>6,
],
 97 => [  //
  'name' => clienttranslate("Advance (optional, no benefits, maxout bonus) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_MAXOUT_BONUS),
],
 98 => [  //
  'name' => clienttranslate("Advance (optional, no benefits, maxout bonus) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_MAXOUT_BONUS),
],
 99 => [  //
  'name' => clienttranslate("Advance (optional, no benefits, maxout bonus) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_MAXOUT_BONUS),
],
 100 => [  //
  'name' => clienttranslate("Advance (optional, no benefits, maxout bonus) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_MAXOUT_BONUS),
],
 101 => [  //
  'name' => clienttranslate("Research - all move, you gain benefits (AGE OF DISCOVERY)"),
  'tap'=>2,
],
 102 => [  //
  'name' => clienttranslate("Landmark comparison (AGE OF WONDERS)"),
  'tap'=>4,
],
 103 => [  //
  'name' => clienttranslate("Advancement comparison (BOAST OF SUPERIORITY)"),
  'tap'=>6,
],
 104 => [  //
  'name' => clienttranslate("Explore (drawn territory only) and neighbours draw (COAL BARON)"),
  'tap'=>9,
],
 105 => [  //
  'name' => clienttranslate("Gain VP for Territory Tile terrain (COLONIALISM)"),
  'tap'=>10,
],
 106 => [  //
  'name' => clienttranslate("Regress on 3 to boost 4th (DARK AGES)"),
  'tap'=>11,'s'=>'c','sflags'=>(FLAG_SELF),'adv'=>1,
],
 107 => [  //
  'name' => clienttranslate("Discard Tapestry to Gain 2 VP each (DEMOCRACY)"),
  'tap'=>12,
],
 108 => [  //
  'name' => clienttranslate("Advance with opponent block (DICTATORSHIP)"),
  'tap'=>13,'s'=>'c','sflags'=>(FLAG_SELF),'adv'=>1,
],
 109 => [  //
  'name' => clienttranslate("All gain resource (DIPLOMACY)"),
  'tap'=>14,
],
 110 => [  // BE_GAIN_ANY_INCOME_BUILDING
  'name' => clienttranslate("Gain income building"),
  'tap'=>14,'icon'=>1,
],
 111 => [  //
  'name' => clienttranslate("Gain any landmark (DYSTOPIA)"),
  'tap'=>15,
],
 112 => [  //
  'name' => clienttranslate("Tapestry Copy (ESPIONAGE)"),
  'tap'=>17,
],
 113 => [  //
  'name' => clienttranslate("Gain 3 VP per landmark (FEUDALISM)"),
  'r' => 'v',
  'tap'=>19,
],
 114 => [  //
  'name' => clienttranslate("Resource/VP trade (GUILDS)"),
  'tap'=>21,
],
 115 => [  //
  'name' => clienttranslate("Advance anywhere, advance opponents - no benefit (OIL MAGNATE)"),
  'tap'=>28,'s'=>'c','sflags'=>(FLAG_SELF),'adv'=>1,
],
 116 => [  //
  'name' => clienttranslate("Worker/VP trade (OLYMPIC HOST)"),
  'tap'=>29,
],
 117 => [  //
  'name' => clienttranslate("Advancement comparison (PLEA FOR AID)"),
  'tap'=>32,
],
 118 => [  //
  'name' => clienttranslate("Repeat bonuses but no benefits (MARRIAGE OF STATE)"),
  'tap'=>23,'s'=>'c','sflags'=>(FLAG_OPPONENT),'adv'=>0,
],
 119 => [  // BE_STANDUP_3_OUTPOSTS
  'name' => clienttranslate("Stand up 3 toppled outposts (REVOLUTION)"),
  'tap'=>35,
],
 120 => [  //
  'name' => clienttranslate("Advance/Regress (SOCIALISM)"),
  'tap'=>36,'s'=>'c','sflags'=>(FLAG_OPPONENT|FLAG_VIRTUAL_ALLOWED),'aflags'=>(FLAG_ADVANCE|FLAG_REGRESS|FLAG_POSCLOSEST),
],
 121 => [  //
  'name' => clienttranslate("Invent - face up (STEAM TYCOON)"),
  'r' => 'i',
  'tap'=>37,'flags'=>(FLAG_FACE_UP),
],
 122 => [  //
  'name' => clienttranslate("Alliance choice (ALLIANCE)"),
  'tap'=>5,'s'=>'o','sflags'=>(FLAG_OPPONENT|FLAG_VIRTUAL_ALLOWED),'state'=>'trackSelect',
],
 123 => [  //
  'name' => clienttranslate("First to era: invent and upgrade tech, else VP (TECHNOCRACY)"),
  'tap'=>38,
],
 124 => [  //
  'name' => clienttranslate("Copy opponent benefit, they get free bonus (TRADE ECONOMY)"),
  'tap'=>41,'s'=>'c','sflags'=>(FLAG_OPPONENT),'adv'=>0,'state'=>'trackSelect',
],
 125 => [  //
  'name' => clienttranslate("Rewards (OLYMPIC HOST)"),
  'tap'=>29,
],
 126 => [  //
  'name' => clienttranslate("Invent - from top of the deck"),
  'r' => 'i',
  'tap'=>37,'flags'=>(FLAG_FACE_DOWN),
],
 127 => [  //
  'name' => clienttranslate("Invent and instantly upgrade"),
  'r' => 'i',
  'flags'=>(FLAG_UPGRADE | FLAG_FACE_BOTH),
],
 128 => [  // BE_PLAY_TAPESTY_INCOME
  'name' => clienttranslate("Play tapestry card (Income)"),
],
 129 => [  //
  'name' => clienttranslate("Gain cards and tiles (Income)"),
],
 130 => [  //
  'name' => clienttranslate("Transfer tech card"),
],
 131 => [  //
  'name' => clienttranslate("Regress (no benefits) - Exploration"),
  'r' => 't',
  't' => 1,
  'adv'=>-1,'flags'=>0,
],
 132 => [  //
  'name' => clienttranslate("Regress (no benefits) - Science"),
  'r' => 't',
  't' => 2,
  'adv'=>-1,'flags'=>0,
],
 133 => [  //
  'name' => clienttranslate("Regress (no benefits) - Military"),
  'r' => 't',
  't' => 3,
  'adv'=>-1,'flags'=>0,
],
 134 => [  //
  'name' => clienttranslate("Regress (no benefits) - Technology"),
  'r' => 't',
  't' => 4,
  'adv'=>-1,'flags'=>0,
],
 135 => [  // BE_EXPLORE_SPACE_ALIEN
  'name' => clienttranslate("Explore space"),
  'r' => 's',
  'state'=>'explore_space',
],
 136 => [  //
  'name' => clienttranslate("VP - Tapestry cards (hand)"),
  'r' => 'v',
  'icon' => 'no',
],
// #tt - type of table, ct - type of card (card_type), dest - where to pay it
 137 => [  //
  'name' => clienttranslate("Tapestry card to a neighbour"),
  'r' => 'p',
  'tt'=>'card','ct'=>CARD_TAPESTRY,'dest'=>FLAG_NEIGHBOUR,
],
 138 => [  //
  'name' => clienttranslate("Tapestry card to a player"),
  'r' => 'p',
  'tt'=>'card','ct'=>CARD_TAPESTRY,'dest'=>FLAG_TARGET,
],
 140 => [  //
  'name' => clienttranslate("Play Trap"),
],
 141 => [  //
  'name' => clienttranslate("Select conquer die"),
],
 142 => [  // BE_CIV_ADJUSTMENT
  'name' => clienttranslate("Civilization adjustment"),
],
 143 => [  // BE_REFRESH_DO
  'name' => clienttranslate("Refresh tech cards"),
  'd'=>1,
],
 144 => [  //
  'name' => clienttranslate("Gain income building and place outside of the city bounds"),
],
 145 => [  //
  'name' => clienttranslate("VP - Landmarks"),
  'r' => 'v',
],
 146 => [  //
  'name' => clienttranslate("Automa Civilization Conquerors"),
],
 151 => [  //
  'name' => clienttranslate("Jump - Exploration"),
  'r' => 't',
  't' => 1,
  'adv'=>4,'flags'=>(FLAG_JUMP),
],
 152 => [  //
  'name' => clienttranslate("Jump - Science"),
  'r' => 't',
  't' => 2,
  'adv'=>4,'flags'=>(FLAG_JUMP),
],
 153 => [  //
  'name' => clienttranslate("Jump - Military"),
  'r' => 't',
  't' => 3,
  'adv'=>4,'flags'=>(FLAG_JUMP),
],
 154 => [  //
  'name' => clienttranslate("Jump - Technology"),
  'r' => 't',
  't' => 4,
  'adv'=>4,'flags'=>(FLAG_JUMP),
],
 156 => [  //
  'name' => clienttranslate("Advance (benefits, maxout bonus) - Exploration"),
  'r' => 't',
  't' => 1,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS),
],
 157 => [  //
  'name' => clienttranslate("Advance (benefits, maxout bonus) - Science"),
  'r' => 't',
  't' => 2,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS),
],
 158 => [  //
  'name' => clienttranslate("Advance (benefits, maxout bonus) - Military"),
  'r' => 't',
  't' => 3,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS),
],
 159 => [  //
  'name' => clienttranslate("Advance (benefits, maxout bonus) - Technology"),
  'r' => 't',
  't' => 4,
  'flags'=>(FLAG_GAIN_BENFIT|FLAG_PAY_BONUS|FLAG_MAXOUT_BONUS),
],
 160 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>0,
],
 161 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>1,
],
 162 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>2,
],
 163 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>3,
],
 164 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>4,
],
 165 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>5,
],
 166 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>6,
],
 167 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>7,
],
 168 => [  //
  'name' => clienttranslate("Automa income stage"),
  'i'=>8,
],
 170 => [  //
  'name' => clienttranslate("Place an outpost"),
  'civ' => CIV_INFILTRATORS, 'state' => 'moveStructureOnto','tt'=>'structure','ct'=>BUILDING_OUTPOST,
],
 171 => [  //
  'name' => clienttranslate("Place a token to gain VP"),
  'icon' => 'no', 'civ' => CIV_INFILTRATORS,
],
 172 => [  //
  'name' => clienttranslate("Draw 3, Keep 1 Civilizations"),
  'icon' => 'no','tt'=>'card','ct'=>CARD_CIVILIZATION,'draw'=>3,'keep'=>1,
],
 173 => [  //
  'name' => clienttranslate("Discard Civilization, gain another"),
  'icon' => 'no','tt'=>'card','ct'=>CARD_CIVILIZATION,'keep'=>0,'draw'=>1,
],
 174 => [  //
  'name' => clienttranslate("Keep Civilization"),
  'icon' => 'no','ct'=>CARD_CIVILIZATION,'draw'=>0,'keep'=>1,
],
 175 => [  //
  'name' => clienttranslate("Draw 3, Keep 1 Technology Card"),
  'icon' => 'no','tt'=>'card','ct'=>CARD_TECHNOLOGY,'draw'=>3,'keep'=>1,
],
 178 => [  //
  'name' => clienttranslate("Midgame setup: Islanders's Explore (No Benefit)"),
  'r' => 'e',
  'icon' => 'no','state' => 'explore','flags'=>FLAG_NO_BENEFIT,
],
 179 => [  //
  'name' => clienttranslate("Islanders's Explore (with Benefits)"),
  'r' => 'e',
  'icon' => 'no', 'state' => 'explore',
],
 180 => [  // BE_MIDGAME_SETUP
  'name' => clienttranslate("Midgame setup for Civilization"),
  'icon' => 'no',
],
 181 => [  //
  'name' => clienttranslate("Discard Tapestry card"),
  'icon' => 'icon_ben_7','tt'=>'card','ct'=>CARD_TAPESTRY, 'state'=>'playTapestryCard',
],
 190 => [  //
  'name' => clienttranslate("Gain the benefit of a neighbor's current position on any advancement track (no bonus)"),
  's'=>'c','sflags'=>(FLAG_NEIGHBOUR),'adv'=>0,'state'=>'trackSelect',
],
 191 => [  //
  'name' => clienttranslate("Gain the benefit of a territory or space tile in a neighbor's supply"),
  'icon' => 'no','tt'=>'card','keep'=>1,'flags'=>(FLAG_NEIGHBOUR),'state'=>'keepCard',
],
 192 => [  //
  'name' => clienttranslate("Gain the benefit of a neighbor's tech card, matching its current row"),
  'icon' => 'no','tt'=>'card','keep'=>1,'flags'=>(FLAG_NEIGHBOUR),'state'=>'keepCard',
],
 193 => [  //
  'name' => clienttranslate("Gain the benefits of a WHEN PLAYED tapestry card on a neighbor's income mat"),
  'icon' => 'no','tt'=>'card','keep'=>1,'flags'=>(FLAG_NEIGHBOUR),'state'=>'keepCard',
],
 194 => [  //
  'name' => clienttranslate("Request to see the chosen neighbor's hand of tapestry cards"),
],
 195 => [  // BE_TINKERERS_1
  'name' => clienttranslate("Choose 1 of your advancement tokens. Gain resources equal to the cost of advancing into its current position"),
  's'=>'c','sflags'=>(FLAG_SELF),'aflags'=>(FLAG_STAY),'state' =>'trackSelect',
],
 196 => [  // BE_TINKERERS_2
  'name' => clienttranslate("Advance on any track to match the closest opponent who is ahead of you on that track. You may gain the benefit but not the bonus."),
  's'=>'c','sflags'=>FLAG_OPPONENT,'aflags'=>FLAG_ADVANCE|FLAG_POSCLOSEST,'state' =>'trackSelect',
],
 197 => [  // BE_TINKERERS_3
  'name' => clienttranslate("Regress exactly 3 spaces on an advancement track where this is possible. You may gain the benefit and pay to gain the bonus (if any)"),
  'adv'=>-3,'s'=>'c','sflags'=>(FLAG_SELF),'aflags'=>(FLAG_REGRESS|FLAG_POSEXACT),'state' =>'trackSelect',
],
 198 => [  // BE_TINKERERS_4
  'name' => clienttranslate("Advance to the next bonus on any track and gain that bonus for free. Do not gain the benefit or any landmarks you pass"),
  's'=>'s','sflags'=>FLAG_SELF,'aflags'=>FLAG_ADVANCE,'state' =>'trackSelect',
],
 199 => [  //
  'name' => clienttranslate("Opponents gain tech card from the deck"),
  'flags'=>(FLAG_OPPONENT),
],
 200 => [  // BE_CONFIRM
  'name' => clienttranslate("Confirm"),
  'icon' => 'no',
],
 201 => [  // BE_RESUME
  'name' => clienttranslate("Resume"),
],
 401 => [  // BE_DECLINE
  'name' => clienttranslate("Decline"),
  'icon' => 'no',
],
 301 => [  //
  'name' => clienttranslate("Roll the black conquer die twice and gain one benefit of your choice"),
],
 302 => [  //
  'name' => clienttranslate("Roll the research die twice and gain one benefit of your choice"),
],
 303 => [  //
  'name' => clienttranslate("Roll the conquer dice and gain both benefits"),
],
 304 => [  //
  'name' => clienttranslate("Roll the red conquer die twice and gain both benefits"),
],
 305 => [  //
  'name' => clienttranslate("Gain tier II landmark"),
  'state'=>'buildingChoice',
],
 306 => [  // BE_ALIEN_D
  'name' => clienttranslate("Alien discard space tile"),
],
 307 => [  // BE_2_TERRITORY
  'name' => clienttranslate("Gain two territory tiles"),
  'r' => 'g',
  'alias' => [BE_TERRITORY, BE_TERRITORY],
],
 308 => [  // BE_2_INVENT
  'name' => clienttranslate("Invent two times"),
  'r' => 'i',
  'alias' => [BE_INVENT, BE_INVENT],
],
 309 => [  // BE_SPOT
  'name' => clienttranslate("Gain spot benefit"),
],
 310 => [  // BE_RENEGADES_ADV
  'name' => clienttranslate("RENEGADES Advance"),
  'civ'=>CIV_RENEGADES,
],
 311 => [  // BE_GAMBLES_PICK
  'name' => clienttranslate("Gamblers: Draw 3 Tapestry, Play 1 (WHEN PLAYED), if cannot, Keep 1"),
  'icon' => 'no','tt'=>'card','ct'=>CARD_TAPESTRY,'draw'=>3,'keep'=>1,
],
 312 => [  // BE_COLLECTORS_GRAB
  'name' => clienttranslate("COLLECTORS Collect Building or Outpost"),
],
 313 => [  // BE_COLLECTORS_CARD
  'name' => clienttranslate("COLLECTORS Collect Card"),
],
 314 => [  // BE_CARD_PLAY_TRIGGER
  'name' => clienttranslate("Card comes into play triggers"),
  'auto'=>1,
],
 315 => [  //
  'name' => clienttranslate("URBAN PLANNER starting benefit"),
  'lm'=>19,
],
 316 => [  // BE_URBANPLANNERS_GRAB
  'name' => clienttranslate("URBAN PLANNERS Collect Landmark"),
],
 317 => [  // BE_URBANPLANNERS_PUT
  'name' => clienttranslate("URBAN PLANNERS Place Landmark"),
],
 502 => [  //
  'name' => clienttranslate("2 VP"),
  'r' => 'v',
],
 503 => [  //
  'name' => clienttranslate("3 VP"),
  'r' => 'v',
],
 504 => [  //
  'name' => clienttranslate("4 VP"),
  'r' => 'v',
],
 505 => [  //
  'name' => clienttranslate("5 VP"),
  'r' => 'v',
],
 506 => [  //
  'name' => clienttranslate("6 VP"),
  'r' => 'v',
],
 507 => [  //
  'name' => clienttranslate("7 VP"),
  'r' => 'v',
],
 510 => [  //
  'name' => clienttranslate("10 VP"),
  'r' => 'v',
],
 515 => [  //
  'name' => clienttranslate("15 VP"),
  'r' => 'v',
],
 520 => [  //
  'name' => clienttranslate("20 VP"),
  'r' => 'v',
],
 601 => [  //
  'name' => clienttranslate("Adjust tracks"),
  'auto'=>1,
],
 602 => [  //
  'name' => clienttranslate("End of income"),
  'auto'=>1,
],
// #Adjust benefit - no name
 603 => [  //
  'name' => clienttranslate("-"),
  'auto'=>1,
],
        /* --- gen php end benefit_types --- */
];
$this->dice_names = [ 
        'red' => array (
                0 => [ "name" => clienttranslate("5 VP") ],
                1 => [ "name" => clienttranslate("6 VP") ],
                2 => $this->benefit_types [BE_VP_TERRITORY],
                3 => [ "name" => clienttranslate("4 VP") ],
                4 => [ "name" => clienttranslate("7 VP") ],
                5 => $this->benefit_types [BE_VP_TERRITORY], ),
        'black' => array (0 => $this->benefit_types [1],2 => $this->benefit_types [2],3 => $this->benefit_types [3],
                4 => $this->benefit_types [4],1 => [ "name" => clienttranslate("Territory Benefit") ],
                5 => [ "name" => clienttranslate("Territory Benefit") ], ), ];
$this->tech_track_types = array (
        1 => array ("name" => "exploration", // this is used in db, has to be lower case
        "description" => clienttranslate("Exploration"),"color" => "blue","top" => 87.7,"left" => 16,"rot" => 0,
                "resource" => 3,
                "overview" => clienttranslate("Pick a territory tile from your supply and place it on the map adjacent to a territory you control. Gain 1 VP per side of the tile with at least 1 aligned terrain (max 6). Then gain the benefit on the tile."), ),
        2 => array ("name" => "science","description" => clienttranslate("Science"),"color" => "green","top" => 37,
                "left" => 53.7,"rot" => 270,"resource" => 2,
                "overview" => clienttranslate("Roll the science die. You may advance on the corresponding track for free. Unless there is an X on the research icon, gain the benefit and you may pay to gain the bonus (if any)."), ),
        3 => array ("name" => "military","description" => clienttranslate("Military"),"color" => "red","top" => 0.5,
                "left" => 3.2,"rot" => 180,"resource" => 4,
                "overview" => clienttranslate("Place an outpost token from your supply onto a territory that has no more than 1 token on it and is adjacent to a territory you control. If the territory has an opponent's outpost, topple it. Also roll the 2 conquer dice and gain the benefit of 1 of them.") ),
        4 => array ("name" => "technology","description" => clienttranslate("Technology"),"color" => "orange",
                "top" => 51,"left" => -33.8,"rot" => 90,"resource" => 1,
                "overview" => clienttranslate("Gain a tech card (face up or from the top of the deck) and place it to the right of your capital city mat in the bottom row. Replenish each face-up tech card as you gain it.") ), );
$this->tech_track_data = array (
        1 => array (
                1 => array ("name" => clienttranslate("SCOUTING"),"cost" => [ RES_ANY ],
                        "benefit" => [ BE_2_TERRITORY ],
                        "description" => clienttranslate("Gain 2 territory tiles."), ),
                2 => array ("name" => clienttranslate("RAFTS"),"cost" => [ RES_ANY ],"benefit" => [ BE_EXPLORE ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "7" ],
                        "description" => clienttranslate("Explore: Place 1 territory tile from your supply on the map, gain 1 VP per aligning side, and gain the benefit on the tile."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 tapestry card."), ),
                3 => array ("name" => clienttranslate("WAGONS"),"cost" => [ RES_ANY ],
                        "benefit" => [ "or" => [ BE_EXPLORE,BE_FARM ] ],
                        "description" => clienttranslate("Explore OR gain 1 farm."), ),
                4 => array ("name" => clienttranslate("NAVIGATION"),"cost" => [ 3,5 ],
                        "benefit" => [ BE_TERRITORY,BE_EXPLORE ],
                        "description" => clienttranslate("Gain 1 territory tile, then explore."),"landmark" => [ 40 ], ),
                5 => array ("name" => clienttranslate("SHIPS"),"cost" => [ 3,5 ],"benefit" => [ BE_VP_TERRITORY ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "10" ],
                        "description" => clienttranslate("Gain 1 VP for each territory you control."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 farm."), ),
                6 => array ("name" => clienttranslate("TUNNELS"),"cost" => [ 3,5 ],
                        "benefit" => [ BE_TERRITORY,BE_FARM ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "17" ],
                        "description" => clienttranslate("Gain 1 territory tile and 1 farm."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to explore."), ),
                7 => array ("name" => clienttranslate("TRAINS"),"cost" => [ 3,5,5 ],
                        "benefit" => [ BE_2_TERRITORY, BE_EXPLORE ],
                        "description" => clienttranslate("Gain 2 territory tiles, then explore."),"landmark" => [ 41 ], ),
                8 => array ("name" => clienttranslate("CARS"),"cost" => [ 3,5,5 ],"benefit" => [ BE_FARM,BE_VP_FARM ],
                        "option" => [ "type" => 6,"quantity" => 2,"benefit" => "15,15,15,15,15" ],
                        "description" => clienttranslate("Gain 1 farm, then gain 1 VP for each farm in your capital city."),
                        "description_bonus" => clienttranslate("You may then discard 2 territory tiles to gain 5 VP."), ),
                9 => array ("name" => clienttranslate("AIRPLANES"),"cost" => [ 3,5,5 ],
                        "benefit" => [ BE_2_TERRITORY, 46 ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "7" ],
                        "description" => clienttranslate("Gain 2 territory tiles, then explore anywhere on the map."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 tapestry card."), ),
                10 => array ("name" => clienttranslate("SPACE SHUTTLE"),"cost" => [ 3,3 ],"benefit" => [ 50 ],
                        "option" => [ "type" => 6,"quantity" => 3,"benefit" => "15,15,15,15,15,15,15,15,15,15" ],
                        "description" => clienttranslate("Gain 1 VP per technology track space you've advanced."),
                        "description_bonus" => clienttranslate("You may then discard 3 territory tiles to gain 10 VP."),
                        "landmark" => [ 42 ], ),
                11 => array ("name" => clienttranslate("INTERSTELLAR TRAVEL"),"cost" => [ 3,3 ],
                        "benefit" => [ BE_SPACE,BE_SPACE,BE_SPACE,BE_EXPLORE_SPACE ],
                        "description" => clienttranslate("Gain 3 space tiles, then explore 1 of them (place explored space tiles next to your income mat)."), ),
                12 => array ("name" => clienttranslate("WARPGATES"),"cost" => [ 3,3 ],
                        "benefit" => [ BE_EXPLORE_SPACE ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "52" ],
                        "description" => clienttranslate("Explore a space tile from your supply (place it next to your income mat)."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to explore another space tile."), ),
                0 => array ("name" => clienttranslate("Exploration"),"cost" => [ ],"benefit" => [ ],
                        "description" => clienttranslate("Starting spot"), ), ),
        2 => array (
                1 => array ("name" => clienttranslate("ASTRONOMY"),"cost" => [ RES_ANY ],
                        "benefit" => [ BE_RESEARCH_NB ],
                        "description" => clienttranslate("Research: Roll the science die to advance for free (don't gain benefit & bonus)."), ),
                2 => array ("name" => clienttranslate("MATHEMATICS"),"cost" => [ RES_ANY ],
                        "benefit" => [ BE_TAPESTRY ],"option" => [ "type" => 5,"quantity" => 1,"benefit" => "9" ],
                        "description" => clienttranslate("Gain 1 tapestry card."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 house."), ),
                3 => array ("name" => clienttranslate("HERBALISM"),"cost" => [ RES_ANY ],
                        "benefit" => [ "or" => [ BE_RESEARCH_NB,BE_HOUSE ] ],
                        "description" => clienttranslate("Research (don't gain benefit & bonus) OR gain 1 house."), ),
                4 => array ("name" => clienttranslate("MEDICINE"),"cost" => [ 2,5 ],
                        "benefit" => [ BE_VP_TECH,BE_TAPESTRY ],
                        "description" => clienttranslate("Gain 1 VP for each tech card in your supply; also gain 1 tapestry card."),
                        "landmark" => [ 43 ], ),
                5 => array ("name" => clienttranslate("CHEMISTRY"),"cost" => [ 2,5 ],"benefit" => [ BE_RESEARCH ],
                        "option" => [ "type" => 7,"quantity" => 2,"benefit" => "15,15,15,15,15" ],
                        "description" => clienttranslate("Research to gain the benefit & pay to gain the bonus (if any)."),
                        "description_bonus" => clienttranslate("You may then discard 2 tapestry cards from hand to gain 5 VP."), ),
                6 => array ("name" => clienttranslate("BIOLOGY"),"cost" => [ 2,5 ],
                        "benefit" => [ "or" => [ BE_RESEARCH,BE_HOUSE ] ],
                        "description" => clienttranslate("Research to gain the benefit & pay to gain the bonus (if any) OR gain 1 house."), ),
                7 => array ("name" => clienttranslate("ACADEMIC RESEARCH"),"cost" => [ RES_WORKER,RES_ANY,RES_ANY ],
                        "benefit" => [ 62 ],
                        "description" => clienttranslate("Gain the benefit & pay to gain the bonus (if any) of your current position on any advancement track."),
                        "landmark" => [ 44 ], ),
                8 => array ("name" => clienttranslate("NUTRITION"),"cost" => [ RES_WORKER,RES_ANY,RES_ANY ],
                        "benefit" => [ BE_HOUSE,BE_VP_HOUSE ],
                        "description" => clienttranslate("Gain 1 house, then gain 1 VP for each house in your capital city."), ),
                9 => array ("name" => clienttranslate("PHYSICS"),"cost" => [ RES_WORKER,RES_ANY,RES_ANY ],
                        "benefit" => [ "or" => [ BE_ADVANCE_E,BE_ADVANCE_M,BE_ADVANCE_T ] ],
                        "description" => clienttranslate("Advance on 1 of these tracks, then gain the benefit & pay to gain the bonus (if any)."), ),
                10 => array ("name" => clienttranslate("NEUROSCIENCE"),"cost" => [ RES_WORKER,RES_WORKER ],
                        "benefit" => [ "or" => [ BE_REGRESS_M,BE_REGRESS_T ] ],
                        "description" => clienttranslate("Regress on 1 of these tracks, then gain the benefit & pay to gain the bonus (if any)."),
                        "landmark" => [ 45 ], ),
                11 => array ("name" => clienttranslate("QUANTUM PHYSICS"),"cost" => [ RES_WORKER,RES_WORKER ],
                        "benefit" => [ "or" => [ BE_ADVANCE_E,BE_ADVANCE_M,BE_ADVANCE_T ],
                                'or2' => [ BE_ADVANCE_E,BE_ADVANCE_M,BE_ADVANCE_T ] ],
                        "description" => clienttranslate("Advance on 1 of these tracks, then gain the benefit & pay to gain the bonus (if any). Then do it again (same or different track)."), ),
                12 => array ("name" => clienttranslate("ALIEN BIOLOGY"),"cost" => [ RES_WORKER,RES_WORKER ],
                        "benefit" => [ 72,72,72,72 ],
                        "description" => clienttranslate("Roll 4 science dice to advance (don't gain the benefits & bonuses). Gain 5 VP per die that would push you off a track."), ),
                0 => array ("name" => clienttranslate("Science"),"cost" => [ ],"benefit" => [ ],
                        "description" => clienttranslate("Starting spot"), ), ),
        3 => array (
                1 => array ("name" => clienttranslate("ARCHERY"),"cost" => [ RES_ANY ],"benefit" => [ BE_CONQUER ],
                        "description" => clienttranslate("Conquer: Place an outpost on a territory adjacent to a territory you control. Roll the 2 conquer dice and pick 1 of the benefits rolled."), ),
                2 => array ("name" => clienttranslate("BLADED WEAPONS"),"cost" => [ RES_ANY ],
                        "benefit" => [ BE_TAPESTRY ],"option" => [ "type" => 5,"quantity" => 1,"benefit" => "11" ],
                        "description" => clienttranslate("Gain 1 tapestry card."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 armory."), ),
                3 => array ("name" => clienttranslate("WALLS"),"cost" => [ RES_ANY ],
                        "benefit" => [ 'or' => [ BE_CONQUER,BE_ARMORY ] ],
                        "description" => clienttranslate("Conquer 1 territory OR gain 1 armory."), ),
                4 => array ("name" => clienttranslate("STANDING ARMY"),"cost" => [ 4,5 ],
                        "benefit" => [ BE_GAIN_WORKER,BE_VP_TILES ],
                        "description" => clienttranslate("Gain 1 worker and gain 1 VP per territory tile in your supply."),
                        "landmark" => [ 37 ], ),
                5 => array ("name" => clienttranslate("CAVALRY"),"cost" => [ 4,5 ],
                        "benefit" => [ 'choice' => [ BE_CONQUER,BE_ARMORY ] ],
                        "description" => clienttranslate("Conquer 1 territory and gain 1 armory."), ),
                6 => array ("name" => clienttranslate("GUNPOWDER"),"cost" => [ 4,5 ],
                        "benefit" => [ BE_CONQUER,BE_TAPESTRY ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "11" ],
                        "description" => clienttranslate("Conquer 1 territory and gain 1 tapestry card."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 armory."), ),
                7 => array ("name" => clienttranslate("TANKS"),"cost" => [ 4,5,5 ],"benefit" => [ 73 ],
                        "description" => clienttranslate("Conquer 1 territory. If that territory was controlled by an opponent, gain the benefits of both conquer dice."),
                        "landmark" => [ 38 ], ),
                8 => array ("name" => clienttranslate("WARPLANES"),"cost" => [ 4,5,5 ],"benefit" => [ 75 ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "7" ],
                        "description" => clienttranslate("Conquer 1 territory anywhere on the map."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 tapestry card."), ),
                9 => array ("name" => clienttranslate("ANTI-AIRCRAFT DEFENSE"),"cost" => [ 4,5,5 ],
                        "benefit" => [ BE_ARMORY,BE_VP_TAPESTY ],
                        "description" => clienttranslate("Gain 1 armory and gain 1 VP per tapestry card (in hand and on your income mat)."), ),
                10 => array ("name" => clienttranslate("NUCLEAR BOMB"),"cost" => [ 4,4 ],
                        "benefit" => [ 'choice' => [ 47,64 ] ],
                        "description" => clienttranslate("Gain 1 VP per exploration track space you've advanced. Also play a tapestry on top of your current tapestry. Only the new card is active."),
                        "landmark" => [ 39 ], ),
                11 => array ("name" => clienttranslate("DRONE ASSASSINS"),"cost" => [ 4,4 ],
                        "benefit" => [ BE_VP_CAPITAL ],
                        "option" => [ "type" => 7,"quantity" => 3,"benefit" => "15,15,15,15,15,15,15,15,15,15" ],
                        "description" => clienttranslate("Score your capital city."),
                        "description_bonus" => clienttranslate("You may then discard 3 tapestry cards from hand to gain 10 VP."), ),
                12 => array ("name" => clienttranslate("MECHS"),"cost" => [ 4,4 ],
                        "benefit" => [ 'choice' => [ 74,65 ] ],
                        "description" => clienttranslate("Conquer 1 territory (gain the benefits of both conquer dice). Also gain a random additional civilization."), ),
                0 => array ("name" => clienttranslate("Military"),"cost" => [ ],"benefit" => [ ],
                        "description" => clienttranslate("Starting spot"), ), ),
        4 => array (
                1 => array ("name" => clienttranslate("POTTERY"),"cost" => [ RES_ANY ],"benefit" => [ BE_INVENT ],
                        "description" => clienttranslate("Invent: Gain 1 tech card and place it to the right of your capital city mat in the bottom row. If you gained a faceup card, replenish it immediately."), ),
                2 => array ("name" => clienttranslate("CARPENTRY"),"cost" => [ RES_ANY ],"benefit" => [ BE_TAPESTRY ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "8" ],
                        "description" => clienttranslate("Gain 1 tapestry card."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to gain 1 market."), ),
                3 => array ("name" => clienttranslate("STONE TOOLS"),"cost" => [ RES_ANY ],
                        "benefit" => [ 'or' => [ BE_INVENT,BE_MARKET ] ],
                        "description" => clienttranslate("Invent 1 tech card OR gain 1 market."), ),
                4 => array ("name" => clienttranslate("METALLURGY"),"cost" => [ 1,5 ],"benefit" => ['choice' => [ BE_REFRESH, BE_INVENT]],
                        "description" => clienttranslate("You may discard all 3 face-up tech cards and replace them. Invent 1 tech card."),
                        "landmark" => [ 34 ], ),
                5 => array ("name" => clienttranslate("GLASS"),"cost" => [ 1,5 ],
                        "benefit" => [ 'or' => [ BE_HOUSE,BE_FARM,BE_ARMORY ] ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "14" ],
                        "description" => clienttranslate("Gain either a farm, house, or armory."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to upgrade 1 tech card."), ),
                6 => array ("name" => clienttranslate("STEEL"),"cost" => [ 1,5 ],
                        "benefit" => [ BE_MARKET,BE_VP_ARMORY ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "20" ],
                        "description" => clienttranslate("Gain 1 VP for each armory in your capital city and gain 1 market."),
                        "description_bonus" => clienttranslate("You may then pay any 1 resource to invent 1 tech card."), ),
                7 => array ("name" => clienttranslate("RUBBER"),"cost" => [ 1,5,5 ],"benefit" => [ 'choice' => [ BE_REFRESH, BE_2_INVENT ] ],
                        "description" => clienttranslate("You may discard all 3 face-up tech cards and replace them. Invent 2 tech cards (one at a time)."),
                        "landmark" => [ 35 ], ),
                8 => array ("name" => clienttranslate("PLASTIC"),"cost" => [ 1,5,5 ],"benefit" => [ BE_MARKET,54 ],
                        "option" => [ "type" => 5,"quantity" => 1,"benefit" => "14" ],
                        "description" => clienttranslate("Gain 1 market, then gain 1 VP for each market in your capital city."),
                        "description_bonus" => clienttranslate("You may also pay any 1 resource to upgrade 1 tech card."), ),
                9 => array ("name" => clienttranslate("ELECTRONICS"),"cost" => [ 1,5,5 ],
                        "benefit" => [ 'choice' => [ BE_UPGRADE_TECH,BE_TECH_CIRCLE ] ],
                        "description" => clienttranslate("In any order, upgrade 1 tech card and gain the circle benefit of 1 tech card in your middle row."), ),
                10 => array ("name" => clienttranslate("COMPUTERS"),"cost" => [ 1,1 ],"benefit" => [ 48,49 ],
                        "description" => clienttranslate("Gain 1 VP per military and science track space you've advanced."),
                        "landmark" => [ 36 ], ),
                11 => array ("name" => clienttranslate("NANOTECHNOLOGY"),"cost" => [ 1,1 ],
                        "benefit" => [ 'choice' => [ BE_UPGRADE_TECH,BE_TECH_SQUARE ] ],
                        "option" => [ "type" => 26,"quantity" => 3,"benefit" => "15,15,15,15,15,15,15,15,15,15" ],
                        "description" => clienttranslate("In any order, upgrade 1 tech card & gain the square benefit of 1 tech card in your top row."),
                        "description_bonus" => clienttranslate("You may then discard 3 tech cards to gain 10 VP."), ),
                12 => array ("name" => clienttranslate("AI SINGULARITY"),"cost" => [ 1,1 ],
                        "benefit" => [ BE_GAIN_COIN,BE_GAIN_WORKER,BE_GAIN_FOOD,BE_GAIN_CULTURE,55 ],
                        "description" => clienttranslate("Remove your player token from the technology track and place it on the starting space of any track. Gain 1 of each resource. This track still counts as complete. <p><i>Note: if cube was removed from last space there will be a transparent cube as an indication of the max position on that track."), ),
                0 => array ("name" => clienttranslate("Technology"),"cost" => [ ],"benefit" => [ ],
                        "description" => clienttranslate("Starting spot"), ), ), );
$this->landmark_data = array (
        1 => array ("name" => clienttranslate("Tech Hub"),"width" => 3,"height" => 2,"benefit" => 36,
                "mask" => array (0 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ], ),
                        1 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ], ) ) ),
        2 => array ("name" => clienttranslate("Apothecary"),"width" => 2,"height" => 2,"benefit" => 43,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ], ) ) ),
        3 => array ("name" => clienttranslate("Academy"),"width" => 4,"height" => 2,"benefit" => 44,
                "mask" => array (1 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ],3 => [ 1,1 ], ),
                        0 => array (0 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ], ) ) ),
        4 => array ("name" => clienttranslate("Train Station"),"width" => 3,"height" => 3,"benefit" => 41,
                "mask" => array (
                        0 => array (0 => [ 0 => 1,1 => 1,2 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1 ],
                                2 => [ 0 => 1,1 => 1,2 => 1 ] ) ) ),
        5 => array ("name" => clienttranslate("Tank Factory"),"width" => 3,"height" => 3,"benefit" => 38,
                "mask" => array (
                        0 => array (0 => [ 0 => 1,1 => 1,2 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1 ],
                                2 => [ 0 => 1,1 => 1,2 => 1 ] ) ) ),
        6 => array ("name" => clienttranslate("Barracks"),"width" => 2,"height" => 2,"benefit" => 37,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ], ) ) ),
        7 => array ("name" => clienttranslate("Laboratory"),"width" => 3,"height" => 2,"benefit" => 45,
                "mask" => array (0 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ], ),
                        1 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ] ) ) ),
        8 => array ("name" => clienttranslate("Rubber Works"),"width" => 4,"height" => 4,"benefit" => 35,
                "mask" => array (
                        0 => array (0 => [ 0 => 0,1 => 0,2 => 1,3 => 1 ],1 => [ 0 => 0,1 => 0,2 => 1,3 => 1 ],
                                2 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],3 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ] ),
                        1 => array (0 => [ 0 => 1,1 => 1,2 => 0,3 => 0 ],1 => [ 0 => 1,1 => 1,2 => 0,3 => 0 ],
                                2 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],3 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ] ),
                        2 => array (0 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],
                                2 => [ 0 => 1,1 => 1,2 => 0,3 => 0 ],3 => [ 0 => 1,1 => 1,2 => 0,3 => 0 ] ),
                        3 => array (0 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1,3 => 1 ],
                                2 => [ 0 => 0,1 => 0,2 => 1,3 => 1 ],3 => [ 0 => 0,1 => 0,2 => 1,3 => 1 ] ), ) ),
        9 => array ("name" => clienttranslate("Lighthouse"),"width" => 2,"height" => 2,"benefit" => 40,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ] ) ) ),
        10 => array ("name" => clienttranslate("Forge"),"width" => 2,"height" => 2,"benefit" => 34,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ] ) ) ),
        11 => array ("name" => clienttranslate("Launch Pad"),"width" => 2,"height" => 3,"benefit" => 42,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ] ),
                        1 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ] ) ) ),
        12 => array ("name" => clienttranslate("Fusion Reactor"),"width" => 3,"height" => 3,"benefit" => 39,
                "mask" => array (0 => array (0 => [ 0,1,1 ],1 => [ 1,1,1 ],2 => [ 1,1,1 ] ),
                        1 => array (0 => [ 1,1,0 ],1 => [ 1,1,1 ],2 => [ 1,1,1 ] ),
                        2 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ],2 => [ 1,1,0 ] ),
                        3 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ],2 => [ 0,1,1 ] ) ) ),
        13 => array ("name" => clienttranslate("Bakery"),"width" => 2,"height" => 2,"benefit" => 56,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ] ) ) ),
        14 => array ("name" => clienttranslate("Barn"),"width" => 3,"height" => 3,"benefit" => 57,
                "mask" => array (
                        0 => array (0 => [ 0 => 1,1 => 1,2 => 1 ],1 => [ 0 => 1,1 => 1,2 => 1 ],
                                2 => [ 0 => 1,1 => 1,2 => 1 ] ) ) ),
        15 => array ("name" => clienttranslate("Com Tower"),"width" => 3,"height" => 2,"benefit" => 58,
                "mask" => array (0 => array (0 => [ 1,1,1 ],1 => [ 1,1,1 ], ),
                        1 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ], ), ) ),
        16 => array ("name" => clienttranslate("Library"),"width" => 2,"height" => 4,"benefit" => 59,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ],2 => [ 1,1 ],3 => [ 1,1 ], ),
                        1 => array (0 => [ 1,1,1,1 ],1 => [ 1,1,1,1 ], ) ) ),
        17 => array ("name" => clienttranslate("Stock Market"),"width" => 2,"height" => 2,"benefit" => 60,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ] ) ) ),
        18 => array ("name" => clienttranslate("Treasury"),"width" => 2,"height" => 2,"benefit" => 61,
                "mask" => array (0 => array (0 => [ 1,1 ],1 => [ 1,1 ] ) ) ), 
        19 => array ("name" => clienttranslate("Urban Center"),"width" => 2,"height" => 2,"benefit" => 315,
                "mask" => [[[ 1,1 ],[ 1,1 ]]]), 
        );
$this->tech_card_data = array (
        1 => array ("name" => clienttranslate("Air Conditioning"),
                "circle" => array ("benefit" => [ BE_GAIN_WORKER ],"description" => clienttranslate("Gain 1 worker.") ),
                "square" => array ("benefit" => [ BE_HOUSE ],"description" => clienttranslate("Gain 1 house.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        2 => array ("name" => clienttranslate("Ammonia"),
                "circle" => array ("benefit" => [ BE_FARM ],"description" => clienttranslate("Gain 1 farm.") ),
                "square" => array ("benefit" => [ BE_VP_FARM ],
                        "description" => clienttranslate("Gain 1 VP for each farm in your capital city.") ),
                "requirement" => [ "track" => 3,"level" => 7 ] ),
        3 => array ("name" => clienttranslate("Anesthesia"),
                "circle" => array ("benefit" => [ BE_ARMORY ],"description" => clienttranslate("Gain 1 armory.") ),
                "square" => array ("benefit" => [ BE_VP_ARMORY ],
                        "description" => clienttranslate("Gain 1 VP for each armory in your capital city.") ),
                "requirement" => [ "track" => 2,"level" => 4 ] ),
        4 => array ("name" => clienttranslate("Assembly Line"),
                "circle" => array ("benefit" => [ BE_TECH_SQUARE ],
                        "description" => clienttranslate("Gain the square benefit of 1 tech card in your top row.") ),
                "square" => array ("benefit" => [ BE_TECH_CIRCLE ],
                        "description" => clienttranslate("Gain the circle benefit of 1 tech card in your middle row.") ),
                "requirement" => [ "track" => 1,"level" => 7 ] ),
        5 => array ("name" => clienttranslate("Bakery"),
                "circle" => array ("benefit" => [ 504 ],"description" => clienttranslate("Gain 4 VP.") ),
                "square" => array ("benefit" => [ 56 ],
                        "description" => clienttranslate("Place the Bakery in your capital city.") ),
                "requirement" => [ "track" => 7,"level" => 2 ],"landmark" => 13 ),
        6 => array ("name" => clienttranslate("Barn"),
                "circle" => array ("benefit" => [ 503 ],"description" => clienttranslate("Gain 3 VP.") ),
                "square" => array ("benefit" => [ 57 ],
                        "description" => clienttranslate("Place the Barn in your capital city.") ),
                "requirement" => [ "track" => 7,"level" => 3 ],"landmark" => 14 ),
        7 => array ("name" => clienttranslate("Lithium-Ion Battery"),
                "circle" => array ("benefit" => [ 504 ],"description" => clienttranslate("Gain 4 VP.") ),
                "square" => array ("benefit" => [ 62 ],
                        "description" => clienttranslate("Gain the benefit & pay to gain the bonus of your current position on any track. Use at most 1x/turn.") ),
                "requirement" => [ "track" => 1,"level" => 10 ] ),
        8 => array ("name" => clienttranslate("Calendar"),
                "circle" => array ("benefit" => [ BE_GAIN_CULTURE ],
                        "description" => clienttranslate("Gain 1 culture.") ),
                "square" => array ("benefit" => [ 507 ],"description" => clienttranslate("Gain 7 VP.") ),
                "requirement" => [ "track" => 1,"level" => 4 ] ),
        9 => array ("name" => clienttranslate("Canned Food"),
                "circle" => array ("benefit" => [ BE_GAIN_FOOD ],"description" => clienttranslate("Gain 1 food.") ),
                "square" => array ("benefit" => [ 507 ],"description" => clienttranslate("Gain 7 VP.") ),
                "requirement" => [ "track" => 3,"level" => 4 ] ),
        10 => array ("name" => clienttranslate("Clocks"),
                "circle" => array ("benefit" => [ 79 ],
                        "description" => clienttranslate("Advance on the technology track (no benefit/bonus).") ),
                "square" => array ("benefit" => [ 50 ],
                        "description" => clienttranslate("Gain 1 VP per technology track space you've advanced.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        11 => array ("name" => clienttranslate("Compass"),
                "circle" => array ("benefit" => [ BE_2_TERRITORY ],
                        "description" => clienttranslate("Gain 2 territory tiles.") ),
                "square" => array ("benefit" => [ BE_EXPLORE ],
                        "description" => clienttranslate("Explore (place 1 territory tile).") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        12 => array ("name" => clienttranslate("Com Tower"),
                "circle" => array ("benefit" => [ 15,504 ],"description" => clienttranslate("Gain 5 VP.") ),
                "square" => array ("benefit" => [ 58 ],
                        "description" => clienttranslate("Place the Com Tower in your capital city.") ),
                "requirement" => [ "track" => 6,"level" => 4 ],"landmark" => 15 ),
        13 => array ("name" => clienttranslate("Concrete"),
                "circle" => array ("benefit" => [ BE_MARKET ],"description" => clienttranslate("Gain 1 market.") ),
                "square" => array ("benefit" => [ 54 ],
                        "description" => clienttranslate("Gain 1 VP for each market in your capital city.") ),
                "requirement" => [ "track" => 1,"level" => 4 ] ),
        14 => array ("name" => clienttranslate("Dynamite"),
                "circle" => array ("benefit" => [ BE_CONQUER ],
                        "description" => clienttranslate("Conquer 1 territory.") ),
                "square" => array ("benefit" => [ BE_VP_TERRITORY ],
                        "description" => clienttranslate("Gain 1 VP for each territory you control.") ),
                "requirement" => [ "track" => 3,"level" => 4 ] ),
        15 => array ("name" => clienttranslate("Eyeglasses"),
                "circle" => array ("benefit" => [ BE_INVENT ],"description" => clienttranslate("Invent 1 tech card.") ),
                "square" => array ("benefit" => [ BE_VP_TECH ],
                        "description" => clienttranslate("Gain 1 VP for each of your tech cards.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        16 => array ("name" => clienttranslate("Irrigation"),
                "circle" => array ("benefit" => [ BE_GAIN_FOOD ],"description" => clienttranslate("Gain 1 food.") ),
                "square" => array ("benefit" => [ BE_FARM ],"description" => clienttranslate("Gain 1 farm.") ),
                "requirement" => [ "track" => 2,"level" => 4 ] ),
        17 => array ("name" => clienttranslate("Library"),
                "circle" => array ("benefit" => [ 15,BE_VP,BE_VP ],"description" => clienttranslate("Gain 3 VP.") ),
                "square" => array ("benefit" => [ 59 ],
                        "description" => clienttranslate("Place the Library in your capital city.") ),
                "requirement" => [ "track" => 6,"level" => 3 ],"landmark" => 16 ),
        18 => array ("name" => clienttranslate("Light Bulb"),
                "circle" => array ("benefit" => [ BE_GAIN_COIN ],"description" => clienttranslate("Gain 1 coin.") ),
                "square" => array ("benefit" => [ BE_MARKET ],"description" => clienttranslate("Gain 1 market.") ),
                "requirement" => [ "track" => 3,"level" => 4 ] ),
        19 => array ("name" => clienttranslate("Paper"),
                "circle" => array ("benefit" => [ BE_HOUSE ],"description" => clienttranslate("Gain 1 house.") ),
                "square" => array ("benefit" => [ BE_VP_HOUSE ],
                        "description" => clienttranslate("Gain 1 VP for each house in your capital city.") ),
                "requirement" => [ "track" => 2,"level" => 4 ] ),
        20 => array ("name" => clienttranslate("Penicillin"),
                "circle" => array ("benefit" => [ BE_RESEARCH_NB ],
                        "description" => clienttranslate("Research (no benefit/bonus).") ),
                "square" => array ("benefit" => [ 15,504 ],"description" => clienttranslate("Gain 5 VP.") ),
                "requirement" => [ "track" => 2,"level" => 4 ] ),
        21 => array ("name" => clienttranslate("Printing Press"),
                "circle" => array ("benefit" => [ BE_TAPESTRY ],
                        "description" => clienttranslate("Gain 1 tapestry card.") ),
                "square" => array ("benefit" => [ 64 ],
                        "description" => clienttranslate("Play a tapestry card on top of your current tapestry.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        22 => array ("name" => clienttranslate("Radio"),
                "circle" => array ("benefit" => [ 503 ],"description" => clienttranslate("Gain 3 VP.") ),
                "square" => array ("benefit" => [ 65 ],
                        "description" => clienttranslate("Gain a random additional civilization.") ),
                "requirement" => [ "track" => 2,"level" => 10 ] ),
        23 => array ("name" => clienttranslate("Sewage & Plumbing"),
                "circle" => array ("benefit" => [ BE_GAIN_CULTURE ],
                        "description" => clienttranslate("Gain 1 culture.") ),
                "square" => array ("benefit" => [ BE_ARMORY ],"description" => clienttranslate("Gain 1 armory.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        24 => array ("name" => clienttranslate("Siegecraft"),
                "circle" => array ("benefit" => [ 78 ],
                        "description" => clienttranslate("Advance on the military track (no benefit/bonus).") ),
                "square" => array ("benefit" => [ 49 ],
                        "description" => clienttranslate("Gain 1 VP per military track space you've advanced.") ),
                "requirement" => [ "track" => 3,"level" => 4 ] ),
        25 => array ("name" => clienttranslate("Stock Market"),
                "circle" => array ("benefit" => [ 505 ],"description" => clienttranslate("Gain 5 VP.") ),
                "square" => array ("benefit" => [ 60 ],
                        "description" => clienttranslate("Place the Stock Market in your capital city.") ),
                "requirement" => [ "track" => 5,"level" => 3 ],"landmark" => 17 ),
        26 => array ("name" => clienttranslate("Telescope"),
                "circle" => array ("benefit" => [ BE_GAIN_CULTURE,BE_TERRITORY ],
                        "description" => clienttranslate("Gain 1 culture and 1 territory tile.") ),
                "square" => array ("benefit" => [ BE_VP_TILES ],
                        "description" => clienttranslate("Gain 1 VP per territory tile in your supply.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        27 => array ("name" => clienttranslate("The Nail"),
                "circle" => array ("benefit" => [ BE_TAPESTRY ],
                        "description" => clienttranslate("Gain 1 tapestry card.") ),
                "square" => array ("benefit" => [ 'or' => [ BE_MARKET,BE_HOUSE,BE_FARM ] ],
                        "description" => clienttranslate("Gain 1 market, house, or farm.") ),
                "requirement" => [ "track" => 4,"level" => 4 ] ),
        28 => array ("name" => clienttranslate("Time Travel"),
                "circle" => array ("benefit" => [ BE_GAIN_COIN ],"description" => clienttranslate("Gain 1 coin.") ),
                "square" => array ("benefit" => [ 'or' => [ 131,133 ] ],
                        "description" => clienttranslate("Regress on 1 of these tracks (no benefit/bonus).") ),
                "requirement" => [ "track" => 4,"level" => 10 ] ),
        29 => array ("name" => clienttranslate("Transistors"),
                "circle" => array ("benefit" => [ BE_GAIN_COIN ],"description" => clienttranslate("Gain 1 coin.") ),
                "square" => array ("benefit" => [ BE_GAIN_CULTURE,504 ],
                        "description" => clienttranslate("Gain 1 culture and 4 VP.") ),
                "requirement" => [ "track" => 3,"level" => 10 ] ),
        30 => array ("name" => clienttranslate("Treasury"),
                "circle" => array ("benefit" => [ 504 ],"description" => clienttranslate("Gain 4 VP.") ),
                "square" => array ("benefit" => [ 61 ],
                        "description" => clienttranslate("Place the Treasury in your capital city.") ),
                "requirement" => [ "track" => 5,"level" => 2 ],"landmark" => 18 ),
        31 => array ("name" => clienttranslate("Vaccines"),
                "circle" => array ("benefit" => [ 77 ],
                        "description" => clienttranslate("Advance on the science track (no benefit/bonus).") ),
                "square" => array ("benefit" => [ 48 ],
                        "description" => clienttranslate("Gain 1 VP per science track space you've advanced.") ),
                "requirement" => [ "track" => 2,"level" => 4 ] ),
        32 => array ("name" => clienttranslate("Warships"),
                "circle" => array ("benefit" => [ BE_GAIN_WORKER ],"description" => clienttranslate("Gain 1 worker.") ),
                "square" => array ("benefit" => [ BE_CONQUER ],
                        "description" => clienttranslate("Conquer 1 territory.") ),
                "requirement" => [ "track" => 1,"level" => 7 ] ),
        33 => array ("name" => clienttranslate("Zeppelins"),
                "circle" => array ("benefit" => [ 76 ],
                        "description" => clienttranslate("Advance on the exploration track (no benefit/bonus).") ),
                "square" => array ("benefit" => [ 47 ],
                        "description" => clienttranslate("Gain 1 VP per exploration track space you've advanced.") ),
                "requirement" => [ "track" => 1,"level" => 4 ] ), );
$this->tapestry_card_data = array (
        0 => array ("name" => clienttranslate("FACE DOWN CARD"),"type" => "era",
                "description" => clienttranslate("This card was played face down during an income turn when the player did not have one in their hand."), ),
        1 => array ("name" => clienttranslate("ACADEMIA"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you invent Technology or gain Tapestry, gain 3 [VP]") ),
        2 => array ("name" => clienttranslate("AGE OF DISCOVERY"),"type" => "now","benefit" => [ 101 ],
                "description" => clienttranslate("WHEN PLAYED: Roll the science die. All players must advance on the resulting track; only you gain the benefit (you may pay to gain the bonus)."), ),
        3 => array ("name" => clienttranslate("AGE OF SAIL"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you explore, draw a total of 3 Territory tiles. Before you place a Territory tile, you may give 1 Territory tile to an opponent to gain 3 [VP].") ),
        4 => array ("name" => clienttranslate("AGE OF WONDERS"),"type" => "now","benefit" => [ 102 ],
                "description" => clienttranslate("WHEN PLAYED: Gain 2 [ANY RESOURCE] if you have the fewest landmarks. If you have the most landmarks, gain 12 [VP]. Otherwise (and for ties), gain 1 [ANY RESOURCE] and 6 [VP].") ),
        5 => array ("name" => clienttranslate("ALLIANCE"),"type" => "era","benefit" => [ 122 ],
                "description" => clienttranslate("THIS ERA: Choose an opponent. You cannot conquer each other's territories. Whenever they upgrade a Technology, you gain the benefit after they do.") ),
        6 => array ("name" => clienttranslate("BOAST OF SUPERIORITY"),"type" => "now","benefit" => [ 103 ],
                "description" => clienttranslate("WHEN PLAYED: For each advancement track where there are no further-advanced tokens than yours, gain 4 [VP].") ),
        7 => array ("name" => clienttranslate("BROKER OF PEACE"),"type" => "era",
                "description" => clienttranslate("THIS ERA: You cannot advance on the military track. Each time an opponent advances on the military track, you gain 3 [VP].") ),
        8 => array ("name" => clienttranslate("CAPITALISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: When you gain income, gain 2 [VP] per [COIN] earned. Whenever you gain Market, also gain [COIN].") ),
        9 => array ("name" => clienttranslate("COAL BARON"),"type" => "now","benefit" => [ 104 ],
                "description" => clienttranslate("WHEN PLAYED: Gain 1 Territory and explore with it. Your neighbours then each get 1 Territory.") ),
        10 => array ("name" => clienttranslate("COLONIALISM"),"type" => "now","benefit" => [ 105 ],
                "description" => clienttranslate("WHEN PLAYED: Choose a territory tile you control and gain each of the following resources based on its land terrain(s): mountain([WORKER]), grassland([CULTURE]), forest([FOOD]), desert([COIN])."),
                "rulings" => clienttranslate("Printed hexes are NOT considered TILES") ),
        11 => array ("name" => clienttranslate("DARK AGES"),"type" => "now","benefit" => [ 106 ],
                "description" => clienttranslate("WHEN PLAYED: Regress once on 3 difference advancement tracks if possible, then advance three times on the remaining track. Do not gain any benefits or bonuses.<br><b>Rulings: If you are on the beginning of a track, you can still use that as regressing without actually moving the token.</b"), ),
        12 => array ("name" => clienttranslate("DEMOCRACY"),"type" => "now","benefit" => [ 107 ],
                "description" => clienttranslate("WHEN PLAYED: Draw 3 Tapestry, then discard any number of Tapestry from your hand to gain 2 [VP] each.") ),
        13 => array ("name" => clienttranslate("DICTATORSHIP"),"type" => "now",
                "description" => clienttranslate("WHEN PLAYED: Advance on any track and gain the benefit (you may pay to gain the bonus). Opponents may not advance on that track until after your next turn."),
                "benefit" => [ 108 ], ),
        14 => array ("name" => clienttranslate("DIPLOMACY"),"type" => "now","benefit" => [ 110,109 ],
                "description" => clienttranslate("WHEN PLAYED: Each player gains [ANY RESOURCE]. You also gain 1 building from your income mat.") ),
        15 => array ("name" => clienttranslate("DYSTOPIA"),"type" => "now","benefit" => [ 111 ],
                "description" => clienttranslate("WHEN PLAYED: Gain any landmark from the landmark board and place it in your capital city.") ),
        16 => array ("name" => clienttranslate("EMPIRICISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: If you advance on the science track, gain 2 [VP]. Whenever you roll the science die, instead roll it twice and choose 1 result.") ),
        17 => array ("name" => clienttranslate("ESPIONAGE"),"type" => "now","benefit" => [ 112 ],
                "description" => clienttranslate("WHEN PLAYED: Copy the ability of any Tapestry on an income mat.") ),
        18 => array ("name" => clienttranslate("EXPLOITATION"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you place a territory tile on the board, you may gain double the benefit on the tile, but no [VP]") ),
        19 => array ("name" => clienttranslate("FEUDALISM"),"type" => "now","benefit" => [ "or" => [ BE_ANYRES,113 ] ],
                "description" => clienttranslate("WHEN PLAYED: Either gain [ANY RESOURCE] OR gain 3[VP] for each of your landmarks.") ),
        20 => array ("name" => clienttranslate("GOLDEN AGE"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Ignore upgrade prerequisites on tech cards. Whenever you upgrade, gain 3 [VP].") ),
        21 => array ("name" => clienttranslate("GUILDS"),"type" => "now","benefit" => [ 114 ],
                "description" => clienttranslate("WHEN PLAYED: Pay 5 [VP] / 10 [VP] / 15 [VP] to gain 1 / 2 / 3 [ANY RESOURCE] or pay 1 / 2 / 3 [ANY RESOURCE] to gain 5 [VP] / 10 [VP] / 15 [VP]. You cannot spend VP you don't have.") ),
        22 => array ("name" => clienttranslate("INDUSTRIALISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you invent a Technology, gain the Circle benefit on it. The card remains in the bottom row for now.") ),
        23 => array ("name" => clienttranslate("MARRIAGE OF STATE"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Choose a track and an opponent. After they gain any benefit on that track, you gain it too (do not gain the bonus)."),
                "benefit" => [ 118 ], ),
        24 => array ("name" => clienttranslate("MERCANTILISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: When you gain income, you may convert [FOOD] to any other resource. For each [FOOD] you convert, gain 2 [VP].") ),
        25 => array ("name" => clienttranslate("MERITOCRACY"),"type" => "now",
                "benefit" => [ BE_INVENT,BE_VP_TECH,BE_VP_TECH ],
                "description" => clienttranslate("WHEN PLAYED: Invent Technology, then gain 2 [VP] for each of your Technology.") ),
        26 => array ("name" => clienttranslate("MILITARISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you explore a territory, you may place an outpost on the newly explored territory.") ),
        27 => array ("name" => clienttranslate("MONARCHY"),"type" => "era",
                "description" => clienttranslate("THIS ERA: whenever you place an income building in your capital city, gain 3 [VP].") ),
        28 => array ("name" => clienttranslate("OIL MAGNATE"),"type" => "now","benefit" => [ 115 ],
                "description" => clienttranslate("WHEN PLAYED: Choose an advancement track. Your neighbours and you may advance once on that track; only you gain the benefit & may pay to gain the bonus (if any).") ),
        29 => array ("name" => clienttranslate("OLYMPIC HOST"),"type" => "now","benefit" => [ 116 ],
                "description" => clienttranslate("WHEN PLAYED: In order starting with you, each player may pay [WORKER] to gain 10 [VP]. If at least 1 opponent does this, you gain 1 building from your income mat. If no opponents do this, you gain [COIN]") ),
        30 => array ("name" => clienttranslate("PILLAGE AND PLUNDER"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you topple an opponent's outpost, gain the results of both conquer dice. Traps cannot be played against you.") ),
        31 => array ("name" => clienttranslate("PIRATE RULE"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you conquer a territory tile, gain the benefit on that tile (in addition to the benefit of the conquer dice)") ),
        32 => array ("name" => clienttranslate("PLEA FOR AID"),"type" => "now","benefit" => [ 117 ],
                "description" => clienttranslate("WHEN PLAYED: For each track where all tokens are more advanced than yours, gain 2 [VP] and [ANY RESOURCE].") ),
        33 => array ("name" => clienttranslate("RENAISSANCE"),"type" => "now",
                "benefit" => [ 'choice' => [ 92,93,94,95 ] ],
                "description" => clienttranslate("WHEN PLAYED: Advance on each track exactly once. Do not gain the benefits. You may gain any resulting bonuses for free.") ),
        34 => array ("name" => clienttranslate("REVISIONISM"),"type" => "era",
                "description" => clienttranslate("THIS ERA: Whenever you gain a landmark on a track, also gain [ANY RESOURCE]. If you reach an already-claimed landmark on a track, gain Armory.") ),
        35 => array ("name" => clienttranslate("REVOLUTION"),"type" => "now",
                "benefit" => [ "or" => [ BE_ANYRES,BE_STANDUP_3_OUTPOSTS ] ],
                "description" => clienttranslate("WHEN PLAYED: Gain [ANY RESOURCE] OR choose up to 3 territories containing your toppled outposts. Stand up those outposts and topple those opponent outposts.") ),
        36 => array ("name" => clienttranslate("SOCIALISM"),"type" => "now","benefit" => [ 120 ],
                "description" => clienttranslate("WHEN PLAYED: Choose an advancement track. Advance or regress (your choice) to match the closest opponent. Do not gain the benefit/bonus.") ),
        37 => array ("name" => clienttranslate("STEAM TYCOON"),"type" => "now","benefit" => [ 121 ],
                "description" => clienttranslate("WHEN PLAYED: Invent a face-up Technology and upgrade it. Your neighbours (right, then left) then each invent Technology from top of deck.") ),
        38 => array ("name" => clienttranslate("TECHNOCRACY"),"type" => "now","benefit" => [ 123 ],
                "description" => clienttranslate("WHEN PLAYED: If you are the first player to enter this era, gain Technology and upgrade it. If you are not the first player to enter this era, gain 3 [VP] per opponent in the game.") ),
        39 => array ("name" => clienttranslate("TERRAFORMING"),"type" => "era",
                "description" => clienttranslate("THIS ERA: You may construct buildings on impassable land in your capital city. Whenever you do, gain 5 [VP].") ),
        40 => array ("name" => clienttranslate("THEOCRACY"),"type" => "era",
                "description" => clienttranslate("THIS ERA: You cannot advance on the science track. Whenever you enter any new advancement tier (I-IV), gain 4 [VP].") ),
        41 => array ("name" => clienttranslate("TRADE ECONOMY"),"type" => "now","benefit" => [ 124 ],
                "description" => clienttranslate("WHEN PLAYED: Choose the current position of an opponent on any advancement track. You gain the corresponding benefit. If there is a bonus, that opponent gains it for free.") ),
        42 => array ("name" => clienttranslate("TRAP"),"type" => "now","benefit" => [ 510 ],
                "description" => clienttranslate("You may discard this card from your hand when an opponent tries to conquer your territory. If you do, gain [ANY RESOURCE] and topple their outpost.<hr>WHEN PLAYED: If played as a tapestry, gain 10 [VP].") ),
        43 => array ("name" => clienttranslate("TYRANNY"),"type" => "era",
                "description" => clienttranslate("THIS ERA: If you gain a Tapestry, you may immediately play it on top of this card. The first and only time you do, gain 5 [VP].") ),
        44 => array ("name" => clienttranslate("WARTIME ECONOMY"),"type" => "era",
                "description" => clienttranslate("THIS ERA: All bonuses that cost [ANY RESOURCE] are free. Example: [ANY RESOURCE]: Tapestry.") ), );
// CIVILIZATIONS
$this->civilizations = array (
        CIV_ALCHEMISTS => array ("name" => clienttranslate("ALCHEMISTS"),
                "description" => array (
                        clienttranslate("The Alchemists love to push their luck in the hopes of accelerated advancement. <b>At the beginning of your income turns (2-5)</b>, roll the science die and mark the result here with a player token. You may then roll again or stop."),
                        clienttranslate("If you roll an advancement you've already rolled, you bust. Remove all tokens and gain [ANY RESOURCE] as consolation for your terrible luck. Otherwise, when you stop, you may advance on each of the advancement tracks you rolled (don't gain benefits or bonuses). Then remove all tokens."),
                        clienttranslate("Each time the final results of this ability would push you off the end of a track, gain 5 [VP].") ),
                "description@a3" => array (
                        clienttranslate("The Alchemists love to push their luck in the hopes of accelerated advancement. <b>At the beginning of your income turns (2-5)</b>, roll the science die and mark the result here with a player token. You may then roll again or stop."),
                        clienttranslate("<b>RULE CHANGE</b>"),
                        clienttranslate("If you roll an advancement you've already rolled, you bust and may advance once on the duplicated track (gain benefit/bonus). Otherwise, when you stop, you may advance once on any track you rolled (gain benefit/bonus) and you may also advance once on either other track you rolled (no benefits or bonuses). Either way, remove all tokens."),
                        clienttranslate("Each time the final results of this ability would push you off the end of a track, gain 5 [VP].") ),
                "description@a4" => array (
                        clienttranslate("The Alchemists love to push their luck in the hopes of accelerated advancement. <b>At the beginning of your income turns (2-5)</b>, roll the science die and mark the result here with a player token. You may then roll again or stop."),
                        clienttranslate("<b>RULE CHANGE</b>"),
                        clienttranslate("If you roll an advancement you've already rolled, you bust and may regress once on the duplicated track (gain benefit/bonus). Otherwise, when you stop, you may advance once on your choice of one track you rolled (gain benefit/bonus). Either way, remove all tokens."),
                        clienttranslate("Each time the final results of this ability would push you off the end of a track, gain 5 [VP].") ),
                "adjustment" => clienttranslate("When starting the game with the Alchemists, gain [ANY RESOURCE] [ANY RESOURCE] and 10 VP"),
                "adjustment@a4" => clienttranslate("rules changed"), //
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "slots_description" => clienttranslate('counter clockwise'),
                "slots" => [ //
                        1 => [ "top" => 66,"left" => 15.75,"w" => 9,"h" => 6, 'benefit'=>97 ],
                        2 => [ "top" => 71.75,"left" => 15.75,"w" => 9,"h" => 6, 'benefit'=>98 ],
                        3 => [ "top" => 71.75,"left" => 24.5,"w" => 9,"h" => 6, 'benefit'=>99 ],
                        4 => [ "top" => 66,"left" => 24.5,"w" => 9,"h" => 6 , 'benefit'=>100], // 
                ],
                "slots@a4" => [ //
                        1 => [ 'benefit'=>156 ],
                        2 => [ 'benefit'=>157 ],
                        3 => [ 'benefit'=>158 ],
                        4 => [ 'benefit'=>159 ], //
                ]
        ),
        CIV_ARCHITECTS => array ( //2
        "name" => clienttranslate("ARCHITECTS"),
                "description" => array (
                        clienttranslate("<i>The Architects value an organized and structured capital city.</i>"),
                        '',
                        clienttranslate("Each time you complete a district, if all income buildings in it are identical, gain +1 [ANY RESOURCE]. There must be at least 1 income building in that district."),
                        clienttranslate("Whenever you score your capital city, for each complete row/column where all the income buildings are identical (min. 1), that row/column is worth 1 VP extra."),
                   
                        clienttranslate("<i>If you gain this civilization in the middle of the game, you may immediately swap the positions of 2 income buildings in your capital city. You may then repeat this up to 2 more times.</i>") ),
                "midgame_setup" => true,
                "adjustment" => clienttranslate("When starting the game with the Architects in a game with 3 or more total players, gain 10 VP per opponent."),
                "adjustment@a4a8" => clienttranslate("rules changed"),
                "description@a4a8" => [ 
                        1 => clienttranslate( "When starting the game with the Architects, place 1 cube per opponent on your capital city to create an impassable plot. Each cube must be placed in a different district.") 
                ],
                "al" => 8
        ),
        CIV_CRAFTSMEN => array ( //3
        "name" => clienttranslate("CRAFTSMEN"),
                "description" => array (
                        clienttranslate("The Craftsmen want to create a wonder of the world, a massive statue. You may place income buildings here as an alternative to your capital city. You must place each building on the lowest available space in a column of your choice (you don't need to finish a column before starting another). As you cover a benefit, gain it immediately."),
                        clienttranslate("This grid counts as part of your capital city for building related benefits, but it does not score for completed rows and columns here.") ),
                "slots" => array (
                        1 => [ "top" => 86,"left" => 12,"benefit" => [ BE_GAIN_WORKER ],"w" => 13,"h" => 8.5 ],
                        2 => [ "top" => 78.125,"left" => 12,"benefit" => [ BE_TERRITORY ],"w" => 13,"h" => 8.5 ],
                        3 => [ "top" => 70.25,"left" => 12,"benefit" => [ 15,504 ],"w" => 13,"h" => 8.5 ],
                        4 => [ "top" => 86,"left" => 24,"benefit" => [ BE_GAIN_COIN ],"w" => 13,"h" => 8.5 ],
                        5 => [ "top" => 78.125,"left" => 24,"benefit" => [ BE_INVENT ],"w" => 13,"h" => 8.5 ],
                        6 => [ "top" => 70.25,"left" => 24,"benefit" => [ BE_UPGRADE_TECH ],"w" => 13,"h" => 8.5 ],
                        7 => [ "top" => 62.375,"left" => 24,"benefit" => [ 510 ],"w" => 13,"h" => 8.5 ],
                        8 => [ "top" => 86,"left" => 36,"benefit" => [ BE_GAIN_FOOD ],"w" => 13,"h" => 8.5 ],
                        9 => [ "top" => 78.125,"left" => 36,"benefit" => [ BE_TAPESTRY ],"w" => 13,"h" => 8.5 ],
                        10 => [ "top" => 70.25,"left" => 36,"benefit" => [ BE_GAIN_CULTURE ],"w" => 13,"h" => 8.5 ],
                        11 => [ "top" => 62.375,"left" => 36,"benefit" => [ BE_RESEARCH ],"w" => 13,"h" => 8.5 ],
                        12 => [ "top" => 54.5,"left" => 36,"benefit" => [ 513,BE_VP,BE_VP ],"w" => 13,"h" => 8.5 ], ),
                "adjustment" => clienttranslate("When starting the game with the Craftsmen, lose [ANY RESOURCE] during your first income turn"),
                "adjustment@a4" => clienttranslate("no changes") ),
        CIV_ENTERTAINERS => array ( // #4
        "name" => clienttranslate("ENTERTAINERS"),
                "description" => array (
                        clienttranslate("The Entertainers seek to spread their influence by entertaining various sectors of society. Start with a player token on the far left. <b>At the beginning of your income turns (2-5)</b>, advance the token one space to the right along a path and gain the benefit."),
                        clienttranslate("<i>If you gain this civilization in the middle of the game, start with a player token on any space here and gain the benefit.</i>") ),
                "description@a4" => array (
                        clienttranslate("The Entertainers seek to spread their influence by entertaining various sectors of society. Start with a player token on the far left. <b>At the beginning of your income turns (1-4)</b>, advance the token one space to the right along a path and gain the benefit."),
                        clienttranslate("<i>If you gain this civilization in the middle of the game, start with a player token on any space here and gain the benefit.</i>") ),
                "midgame_setup" => true,
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "income_trigger@a4" => [ "from" => 1,"to" => 4 ], //
                "tokens_count" => 1,
                "slots" => array (
                        1 => array ("top" => 64.25,"left" => 24,"benefit" => [ ],"link" => [ 2,3,4,5 ],"w" => 8.5,
                                "h" => 5.5 ),
                        2 => [ "top" => 56,"left" => 34.5,"benefit" => [ BE_GAIN_WORKER ],"link" => [ 6,7 ],
                                "w" => 8.5,"h" => 5.5 ],
                        3 => [ "top" => 61.5,"left" => 34.5,"benefit" => [ BE_GAIN_CULTURE ],"link" => [ 7,8 ],
                                "w" => 8.5,"h" => 5.5 ],
                        4 => [ "top" => 67,"left" => 34.5,"benefit" => [ BE_GAIN_FOOD ],"link" => [ 7,8 ],"w" => 8.5,
                                "h" => 5.5 ],
                        5 => [ "top" => 72.5,"left" => 34.5,"benefit" => [ BE_GAIN_COIN ],"link" => [ 8,9 ],
                                "w" => 8.5,"h" => 5.5 ],
                        6 => [ "top" => 56,"left" => 45,"benefit" => [ BE_FARM ],"link" => [ 10,11 ],"w" => 8.5,
                                "h" => 5.5 ],
                        7 => [ "top" => 61.5,"left" => 45,"benefit" => [ BE_MARKET ],"link" => [ 10,11 ],"w" => 8.5,
                                "h" => 5.5 ],
                        8 => [ "top" => 67,"left" => 45,"benefit" => [ BE_HOUSE ],"link" => [ 12,13 ],"w" => 8.5,
                                "h" => 5.5 ],
                        9 => [ "top" => 72.5,"left" => 45,"benefit" => [ BE_ARMORY ],"link" => [ 12,13 ],"w" => 8.5,
                                "h" => 5.5 ],
                        10 => [ "top" => 56,"left" => 55.5,"benefit" => [ BE_CONQUER ],"link" => [ 14,15 ],
                                "w" => 8.5,"h" => 5.5 ],
                        11 => [ "top" => 61.5,"left" => 55.5,"benefit" => [ BE_RESEARCH_NB ],"link" => [ 15,16 ],
                                "w" => 8.5,"h" => 5.5 ],
                        12 => [ "top" => 67,"left" => 55.5,"benefit" => [ BE_INVENT ],"link" => [ 15,16 ],"w" => 8.5,
                                "h" => 5.5 ],
                        13 => [ "top" => 72.5,"left" => 55.5,"benefit" => [ BE_EXPLORE ],"link" => [ 16,17 ],
                                "w" => 8.5,"h" => 5.5 ],
                        14 => [ "top" => 56,"left" => 66,"benefit" => [ BE_ADVANCE_T ],"link" => [ ],"w" => 8.5,
                                "h" => 5.5 ],
                        15 => [ "top" => 61.5,"left" => 66,"benefit" => [ BE_ADVANCE_E ],"link" => [ ],"w" => 8.5,
                                "h" => 5.5 ],
                        16 => [ "top" => 67,"left" => 66,"benefit" => [ BE_ADVANCE_M ],"link" => [ ],"w" => 8.5,
                                "h" => 5.5 ],
                        17 => [ "top" => 72.5,"left" => 66,"benefit" => [ BE_ADVANCE_S ],"link" => [ ],"w" => 8.5,
                                "h" => 5.5 ], ),
                "adjustment" => clienttranslate("When starting the game with the Entertainers, gain [ANY RESOURCE] during your first income turn."),
                "adjustment@a4" => clienttranslate("rule changes"), ),
        CIV_FUTURISTS => array ("name" => clienttranslate("FUTURISTS"),
                "description" => array (
                        clienttranslate("The Futurists mysteriously begin their civilization with significant advancements."),
                        clienttranslate("<b>When you gain this civilization:</b> Advance on each track by exactly 4 spaces (with the end of each track as the limit). Do not gain benefits, bonuses, or landmarks. Also, gain 1 of each resource.") ),
                "adjustment" => clienttranslate("When starting the game with the Futurists, lose [CULTURE] and [ANY RESOURCE]."),//
                "midgame_ben" => [173],
                "midgame_ben@a4" => [174],
                "midgame_setup@a4" => true,
                "description@a4" => array (
                        clienttranslate("The Futurists mysteriously begin their civilization with significant advancements."),
                        clienttranslate("At the end of your first income turn (or immediately if you gain the Futurists during the game), you may advance on up to two different tracks by exactly 4 spaces (with the end of each track as a limit). Do not gain benefits, bonuses, or landmarks. Also, gain any 2 resources.") ),//
                "adjustment@a4" => clienttranslate("rules changed"),
                "start_benefit" => [ BE_GAIN_COIN, BE_GAIN_CULTURE, BE_GAIN_FOOD, BE_GAIN_WORKER],
                "start_benefit@a4" => [ BE_ANYRES, BE_ANYRES, 0, 0 ],
                "automa" => false, ),
        6 => array ("name" => clienttranslate("HERALDS"),
                "description" => array (
                        clienttranslate("The Heralds proclaim their achievements worldwide. Start with 4 player tokens here."),
                        clienttranslate("If you start the game with this civilization, at the end of your first income turn, you may play a tapestry card on top of Maker of Fire."),
                        clienttranslate("<b>At the beginning of your income turns (2-5)</b>, you may move a player token from here to any “when played” tapestry card (yours or an opponent's). Gain the benefits of that card. Each tapestry may have at most 1 token.") ),
                "description@a4" => [ 
                        1 => clienttranslate("If you started the game with the Heralds, at the end of your first income turn, draw tapestry cards until you find a \"When Played\" or \"Continuous\" card. Discard the others, and you may play \"When Played\" or \"Continuous\" tapestry card on top of Maker of Fire (only place it; do not gain its benefit). The Heralds may not play the Renaissance card over Maker of Fire.") ],
                "tokens_count" => 4,
                "slots_description" => '',
                "slots" => array (1 => [ "top" => 86,"left" => 15,"w" => 8.5,"h" => 5.5 ],
                        2 => [ "top" => 86,"left" => 23.5,"w" => 8.5,"h" => 5.5 ],
                        3 => [ "top" => 86,"left" => 32,"w" => 8.5,"h" => 5.5 ],
                        4 => [ "top" => 86,"left" => 40.5,"w" => 8.5,"h" => 5.5 ], ),
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "adjustment" => clienttranslate("no change"),
                "adjustment@a3" => clienttranslate("When starting the game with the Heralds, gain 4 tapestry cards."),
                "adjustment@a4" => clienttranslate("rules changed. Note: benefit of card placed on Marker of Fire can be used with Herald ability"),
                "automa" => false, ),
        7 => array ("name" => clienttranslate("HISTORIANS"), //CIV_HISTORIANS
                "description" => array (
                        clienttranslate("The Historians want to witness the achievements of other civilizations. Start with these 4 squares covered with your player tokens. <b>At the beginning of your income turns (2-5)</b>, you may discard 1 territory tile from your supply to give a token to any opponent (even if they already have a token). This represents a historian you're sending to that civilization."),
                        clienttranslate("Whenever any opponent with your token gains a landmark, gain all of these exposed benefits."),
                        clienttranslate("<i>If you gain this civilization in the middle of the game, immediately give 4 of your player tokens to opponents, leaving the squares exposed on this mat.</i>") ),
                //
                "midgame_setup" => true,
                "description@a4" => [ 
                        0 => clienttranslate("The Historians want to witness the achievements of other civilizations. Start with these 4 squares covered with your player tokens and 1 Territory tile. <b>At the beginning of your income turns (2-5) in 2-3 player game or (1-4) in 4-5 player game</b>, you may discard 1 territory tile from your supply to give a token to any opponent (even if they already have a token). This represents a historian you're sending to that civilization."),
                        1 => clienttranslate("Whenever any opponent with at least one of your 'historians' gains a landmark from an advancement track, you gain all of these exposed benefits."),
                        2 => clienttranslate('<i>If you gain this civilization in the middle of the game and you are in era 1 or 2, discard it and draw another. Otherwise, immediately give 4 of your player tokens to opponents, leaving the squares exposed on this mat.</i>') ],
                "start_benefit" => [ ],
                "start_benefit@a4" => [ BE_TERRITORY ],
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "income_trigger@a4p4" => [ "from" => 1,"to" => 4 ], //
                "income_trigger@a4p5" => [ "from" => 1,"to" => 4 ], //
                "tokens_count" => 4,
                "slots" => array ( // 
                        1 => [ "top" => 61,"left" => 30.5,"w" => 8.5,"h" => 5.5,"benefit" => [ 503 ] ],
                        2 => [ "top" => 66.5,"left" => 30.5,"w" => 8.5,"h" => 5.5,"benefit" => [ RES_WORKER ] ],
                        3 => [ "top" => 61,"left" => 39,"w" => 8.5,"h" => 5.5,"benefit" => [ RES_FOOD ] ],
                        4 => [ "top" => 66.5,"left" => 39,"w" => 8.5,"h" => 5.5,"benefit" => [ 503 ] ], ),
                "slots@a4" => array ( //
                        1 => [ "top" => 57,"left" => 29, "benefit" => [ BE_RESEARCH_NB ] ],
                        2 => [ "top" => 63,"left" => 29,"benefit" => [ 'p' => BE_TAPESTRY,'g' => BE_INVENT,0 => 0 ]],
                        3 => [ "top" => 57,"left" => 38,"benefit" => [ RES_FOOD ] ], //  
                        4 => [ "top" => 63,"left" => 38,"benefit" => [ BE_VP_TERRITORY ]], ),
                "adjustment" => clienttranslate("no change"),
                "adjustment@a4" => clienttranslate("rule changes"), ),
        8 => array ("name" => clienttranslate("INVENTORS"),
                "description" => array (
                        clienttranslate("The Inventors start with 4 player tokens here. <b>At the beginning of your income turns (2-5)</b>, you may move a player token from here to any tech card (yours or an opponent's). That card is instantly upgraded, and it ignores top-row prerequisites. If it is an opponent's tech card, you may gain the benefit after they do."),
                        clienttranslate("Whenever an opponent's tech card with your token on it is upgraded to the top row, the opponent gains the benefit and then you gain the tech card, placing it in your bottom row. Your token remains on it. Landmarks on tech cards already gained by the opponent do nothing for you."),
                        clienttranslate("<b>Rulings:</b> You can put your token on the card which already has your inventor token. You cannot put token on the card in the top row - card in that row cannot be upgraded") ),
                "slots" => array (1 => [ "top" => 80,"left" => 18,"w" => 8.5,"h" => 5.5 ],
                        2 => [ "top" => 86,"left" => 18,"w" => 8.5,"h" => 5.5 ],
                        3 => [ "top" => 80,"left" => 27.5,"w" => 8.5,"h" => 5.5 ],
                        4 => [ "top" => 86,"left" => 27.5,"w" => 8.5,"h" => 5.5 ], ),
                "adjustment" => clienttranslate("no change"), //
                "tokens_count" => 4,
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "automa" => false, ),
        CIV_ISOLATIONISTS => array ( // 9
        "name" => clienttranslate("ISOLATIONISTS"),
                "description" => array (
                        clienttranslate("The Isolationists just want to be left alone. Start with 4 player tokens here."),
                        clienttranslate("Whenever you conquer an empty territory, you may place 1 of these tokens there in addition to your outpost, preventing that territory from being conquered in the future. If you do, gain [ANY RESOURCE]."),
                        '',
                        clienttranslate("On your final income turn, score [VP] for the largest connected land mass of the same terrain type you control:"),
                        "<table><tr><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td></tr><tr><td>5[VP]</td><td>10[VP]</td><td>16[VP]</td><td>23[VP]</td><td>31[VP]</td><td>40[VP]</td></tr></table>" ),
                "description@a4a8" => [
                        clienttranslate("The Isolationists just want to be left alone. Start with 4 player tokens here (add 1 extra in 4-5 player games) and 2 territory tiles."),
                        clienttranslate("Whenever you conquer an empty territory, you may place 1 of these tokens there in addition to your outpost; if you do, gain [ANY RESOURCE]."),
                        clienttranslate("Opponents cannot conquer this territory")
                ],
                "slots_description" => '',
                "tokens_count" => 4,
                "slots" => array (
                        1 => [ "top" => 58.75,"left" => 20,"w" => 8.5,"h" => 5.5 ],
                        2 => [ "top" => 64.5,"left" => 20,"w" => 8.5,"h" => 5.5 ],
                        3 => [ "top" => 58.75,"left" => 28.5,"w" => 8.5,"h" => 5.5 ],
                        4 => [ "top" => 64.5,"left" => 28.5,"w" => 8.5,"h" => 5.5 ],
                        5 => [ "top" => 58.75,"left" => 37,"w" => 8.5,"h" => 5.5 ], ),
                "adjustment" => clienttranslate("no change"), 
                "al" => 8
        ),
        10 => array ("name" => clienttranslate("LEADERS"),
                "description" => array (
                        clienttranslate("The Leaders excel at cultivating the leadership abilities of their citizens."),
                        clienttranslate("<b>At the beginning of your income turns (2-5)</b>, you may place a player token on an uncovered leader (square on this mat) to advance for free on the corresponding track. Gain the benefit, but you may not gain the bonus.") ),
                "slot_choice"=>"unoccupied",
                "slots_description" => clienttranslate('counterclockwise'),
                "slots" => array (1 => [ "top" => 73.5,"left" => 17,"w" => 12,"h" => 8,"benefit" => [ BE_ADVANCE_EXPLORATION_BENEFIT_NOBONUS] ],
                        2 => [ "top" => 82,"left" => 17,"w" => 12,"h" => 8,"benefit" => [BE_ADVANCE_SCIENCE_BENEFIT_NOBONUS] ],
                        3 => [ "top" => 82,"left" => 30,"w" => 12,"h" => 8 ,"benefit" => [ BE_ADVANCE_MILITARY_BENEFIT_NOBONUS ]],
                        4 => [ "top" => 73.5,"left" => 30,"w" => 12,"h" => 8,"benefit" => [ BE_ADVANCE_TECHNOLOGY_BENEFIT_NOBONUS ] ], ),
                "adjustment" => clienttranslate("no change"),"income_trigger" => [ "from" => 2,"to" => 5 ], //
        ),
        11 => array ("name" => clienttranslate("MERRYMAKERS"),
                "description" => array (
                        clienttranslate("The Merrymakers frequently celebrate love and life; the longer the festival, the better."),
                        clienttranslate("Start with 1 player token at the bottom of each track. <b>At the beginning of your income turns (2-5)</b>, select 1 token to advance upwards and gain the first benefit it lands on.") ),
                "midgame_setup" => true,
                "slots" => array (
                        1 => [ "top" => 87,"left" => 19,"benefit" => [ ],"link" => [ BE_GAIN_WORKER ],"w" => 11,
                                "h" => 6 ],
                        2 => [ "top" => 81.6,"left" => 19,"benefit" => [ BE_GAIN_COIN ],"link" => [ BE_GAIN_FOOD ],
                                "w" => 11,"h" => 6 ],
                        3 => [ "top" => 76.3,"left" => 19,"benefit" => [ BE_GAIN_WORKER,BE_GAIN_FOOD ],
                                "link" => [ BE_GAIN_CULTURE ],"w" => 11,"h" => 6 ],
                        4 => array ("top" => 71,"left" => 19,"benefit" => [ BE_GAIN_CULTURE,BE_ARMORY ],"link" => [ ],
                                "w" => 11,"h" => 6 ),
                        5 => [ "top" => 87,"left" => 29,"benefit" => [ ],"link" => [ BE_TERRITORY ],"w" => 11,
                                "h" => 6 ],
                        6 => [ "top" => 81.6,"left" => 29,"benefit" => [ BE_TERRITORY ],"link" => [ BE_TAPESTRY ],
                                "w" => 11,"h" => 6 ],
                        7 => [ "top" => 76.3,"left" => 29,"benefit" => [ BE_INVENT,BE_TAPESTRY ],
                                "link" => [ BE_MARKET ],"w" => 11,"h" => 6 ],
                        8 => array ("top" => 71,"left" => 29,"benefit" => [ BE_EXPLORE,BE_RESEARCH ],"link" => [ ],
                                "w" => 11,"h" => 6 ),
                        9 => [ "top" => 87,"left" => 39,"benefit" => [ ],"link" => [ BE_FARM ],"w" => 11,"h" => 6 ],
                        10 => [ "top" => 81.6,"left" => 39,"benefit" => [ 505 ],"link" => [ BE_ARMORY ],"w" => 11,
                                "h" => 6 ],
                        11 => [ "top" => 76.3,"left" => 39,"benefit" => [ 510 ],"link" => [ BE_INCOME_VP ],"w" => 11,
                                "h" => 6 ],
                        12 => array ("top" => 71,"left" => 39,"benefit" => [ 515 ],"link" => [ ],"w" => 11,"h" => 6 ), ),
                "description@a4" => [ 
                        1 => clienttranslate("Start with 1 player token on any benefit and another player token on another benefit. At the beginning of your income turns (2-5), you must move each token once orthogonally and gain the resulting benefit. Tokens can never share the same space."), ],
                "slots@a4" => array (
                        1 => [ "top" => 90,"left" => 14,"benefit" => [ ],"link" => [ 2 ],"w" => 11,"h" => 6 ],
                        2 => array ("benefit" => [ BE_INVENT ],"link" => [ 3,6 ],"top" => 84,"left" => 14 ), //
                        3 => array ("benefit" => [ RES_WORKER,0 ],"link" => [ 2,4,7 ],"top" => 76,"left" => 14 ), //
                        4 => array ("benefit" => [ BE_EXPLORE,0 ],"link" => [ 3,8 ],"top" => 69,"left" => 14 ), //
                        5 => [ "top" => 90,"left" => 25,"benefit" => [ ],"link" => [ 6 ],"w" => 11,"h" => 6 ],
                        6 => array ("benefit" => [ RES_FOOD,0 ],"link" => [ 2,7,10 ],"top" => 84,"left" => 25 ), //
                        7 => array ("benefit" => [ BE_VP_CAPITAL,0 ],"link" => [ 3, 6,8,11 ],"top" => 76,"left" => 25 ), //
                        8 => array ("benefit" => [ RES_CULTURE,0 ],"link" => [ 7,4,12 ],"top" => 69,"left" => 25 ), //
                        9 => [ "top" => 90,"left" => 37,"benefit" => [ ],"link" => [ 10 ],"w" => 11,"h" => 6 ],
                        10 => array ("benefit" => [ BE_CONQUER ],"link" => [ 11,6 ],"top" => 84,"left" => 37 ), //
                        11 => array ("benefit" => [ BE_GAIN_COIN ],"link" => [ 10,12,7 ],"top" => 76,"left" => 37 ), //
                        12 => array ("benefit" => [ BE_RESEARCH_NB ],"link" => [ 11,8 ],"top" => 69,"left" => 37 ), //
                ),//
                "adjustment" => clienttranslate("When starting the game with the Merrymakers, gain [ANY RESOURCE]"),
                "adjustment@a4" => clienttranslate("rules changed"), //
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
        ),
        12 => array ("name" => clienttranslate("MILITANTS"),
                "description" => array (
                        clienttranslate("The Militants seek to reap the benefits of the territories they control. Start with 8 outposts covering the spaces on this card. Whenever you conquer, take an outpost from the far left of either row here. At the beginning of your income turns, gain all of the exposed benefits."),
                        '',
                        clienttranslate("<i>If you gain this civilization in the middle of the game, place your remaining outposts on these spaces from right to left (you choose the row for each outpost).</i>") ),
                "income_trigger" => [ "from" => 1,"to" => 5 ], //
                "midgame_setup" => true,
                "slots_description" => clienttranslate('left to right, top to bottom'),
                "slots" => array (1 => [ "top" => 60,"left" => 30,"w" => 10,"h" => 7,"benefit" => [ BE_GAIN_COIN ] ],
                        2 => [ "top" => 60,"left" => 39,"w" => 10,"h" => 7,"benefit" => [ 502 ] ],
                        3 => [ "top" => 60,"left" => 49,"w" => 10,"h" => 7,"benefit" => [ BE_GAIN_FOOD ] ],
                        4 => [ "top" => 60,"left" => 59,"w" => 10,"h" => 7,"benefit" => [ 504 ] ],
                        5 => [ "top" => 67,"left" => 30,"w" => 10,"h" => 7,"benefit" => [ BE_VP ] ],
                        6 => [ "top" => 67,"left" => 39,"w" => 10,"h" => 7,"benefit" => [ BE_GAIN_WORKER ] ],
                        7 => [ "top" => 67,"left" => 49,"w" => 10,"h" => 7,"benefit" => [ 503 ] ],
                        8 => [ "top" => 67,"left" => 59,"w" => 10,"h" => 7,"benefit" => [ BE_GAIN_CULTURE ] ], ),
                "adjustment" => clienttranslate("no change"),
                //1 gold, 2 VP, 1 food, and 1 VP per territory controlled.
                // 3 VP, 1 worker, 5 VP, and a conquer benefit*
                "slots@a4" => array (1 => [ "benefit" => [ BE_GAIN_COIN ] ],2 => [ "benefit" => [ 502 ] ],
                        3 => [ "benefit" => [ BE_GAIN_FOOD ] ],
                        4 => [ "benefit" => [ BE_VP_TERRITORY ],"cl" => "paintover" ], //1 VP per Terr
                        5 => [ "benefit" => [ 503 ],"cl" => "paintover" ],
                        6 => [ "benefit" => [ BE_GAIN_WORKER ],"cl" => "paintover" ],
                        7 => [ "benefit" => [ 505 ],"cl" => "paintover" ],
                        8 => [ "benefit" => [ BE_CONQUER ],"cl" => "paintover" ], //conquier
                ), //
                "adjustment@a4" => clienttranslate("rules changed"),
                "description@a4" => [ 
                        1 => clienttranslate('If the Militants exhaust their supply of outposts, they may use player tokens as outposts.') ], ),
        CIV_MYSTICS => array ("name" => clienttranslate("MYSTICS"),
                "description" => array (
                        clienttranslate("The Mystics boast of their ability to predict the future (they're actually just good planners). When you gain this civilization, use player tokens to select a number in each of these categories."),
                        clienttranslate("When you reach that number the first time, gain [ANY RESOURCE]. On your final income turn, for each category you predicted exactly correct, gain 10 [VP]."),
                        clienttranslate("<i>If you gain this civilization in the middle of the game, discard it and gain another.</i>"),
                        clienttranslate("Categories: Technology cards, Complete districts, Controlled territories, Completed tracks") ),
                "description@a4" => [ 
                        1 => clienttranslate("When you reach that number the first time, gain [ANY RESOURCE]. On your final income turn, for each category you predicted exactly correct, gain 20 [VP]."),
                        2 => clienttranslate("<i>If you gain this civilization in the middle of the game, make a number of predictions equal to the number of remaining eras: four in Era 1, three in Era 2, two in Era 3 and one in Era 4.</i>") ],
                "midgame_ben" => [173], // discard and gain another
                "midgame_ben@a4" => [174],
                "midgame_setup@a4" => true,
                "slots_description" => "",
                "slots" => array (1 => [ "top" => 57,"left" => 28,"w" => 6,"h" => 5 ],
                        2 => [ "top" => 57,"left" => 34.125,"w" => 6,"h" => 5 ],
                        3 => [ "top" => 57,"left" => 40.25,"w" => 6,"h" => 5 ],
                        4 => [ "top" => 57,"left" => 46.375,"w" => 6,"h" => 5 ],
                        5 => [ "top" => 57,"left" => 52.5,"w" => 6,"h" => 5 ],
                        6 => [ "top" => 57,"left" => 58.625,"w" => 6,"h" => 5 ],
                        7 => [ "top" => 57,"left" => 64.75,"w" => 6,"h" => 5 ],
                        8 => [ "top" => 57,"left" => 70.875,"w" => 6,"h" => 5 ],
                        9 => [ "top" => 57,"left" => 77,"w" => 6,"h" => 5 ],
                        10 => [ "top" => 62,"left" => 28,"w" => 6,"h" => 5 ],
                        11 => [ "top" => 62,"left" => 34.125,"w" => 6,"h" => 5 ],
                        12 => [ "top" => 62,"left" => 40.25,"w" => 6,"h" => 5 ],
                        13 => [ "top" => 62,"left" => 46.375,"w" => 6,"h" => 5 ],
                        14 => [ "top" => 62,"left" => 52.5,"w" => 6,"h" => 5 ],
                        15 => [ "top" => 62,"left" => 58.625,"w" => 6,"h" => 5 ],
                        16 => [ "top" => 62,"left" => 64.75,"w" => 6,"h" => 5 ],
                        17 => [ "top" => 62,"left" => 70.875,"w" => 6,"h" => 5 ],
                        18 => [ "top" => 62,"left" => 77,"w" => 6,"h" => 5 ],
                        19 => [ "top" => 66,"left" => 28,"w" => 6,"h" => 5 ],
                        20 => [ "top" => 66,"left" => 34.125,"w" => 6,"h" => 5 ],
                        21 => [ "top" => 66,"left" => 40.25,"w" => 6,"h" => 5 ],
                        22 => [ "top" => 66,"left" => 46.375,"w" => 6,"h" => 5 ],
                        23 => [ "top" => 66,"left" => 52.5,"w" => 6,"h" => 5 ],
                        24 => [ "top" => 66,"left" => 58.625,"w" => 6,"h" => 5 ],
                        25 => [ "top" => 66,"left" => 64.75,"w" => 6,"h" => 5 ],
                        26 => [ "top" => 66,"left" => 70.875,"w" => 6,"h" => 5 ],
                        27 => [ "top" => 66,"left" => 77,"w" => 6,"h" => 5 ],
                        28 => [ "top" => 70,"left" => 28,"w" => 6,"h" => 5 ],
                        29 => [ "top" => 70,"left" => 34.125,"w" => 6,"h" => 5 ],
                        30 => [ "top" => 70,"left" => 40.25,"w" => 6,"h" => 5 ],
                        31 => [ "top" => 70,"left" => 46.375,"w" => 6,"h" => 5 ], ),
                "adjustment" => clienttranslate("When starting the game with the Mystics, gain [ANY RESOURCE]"),
                "adjustment@a4" => clienttranslate("no change"), ),
        14 => array ("name" => clienttranslate("NOMADS"),
                "description" => array (
                        clienttranslate("The Nomads prefer to expand their civilization outwards rather than focusing on their capital city."),
                        clienttranslate("Whenever you gain a building or landmark, you may place it either in your capital city or on the map in 1 of 2 locations:"),
                        clienttranslate("<b>On a territory you control containing exactly 1 other token (outpost or building).</b> If you do, gain [ANY RESOURCE]. Opponents can't conquer these territories."),
                        clienttranslate("<b>On an empty territory adjacent to one you control.</b> This gives you control of that territory, but it is not a conquer action. It is considered an outpost if an opponent conquers it."),
                        clienttranslate("You still gain [VP] for buildings whether they're on the map or in your capital city.") ),
                "adjustment" => clienttranslate("no change"),"adjustment@a4" => clienttranslate("no change"), ),
        CIV_CHOSEN => array ("name" => clienttranslate("THE CHOSEN"),
                "description" => array (
                        clienttranslate("The Chosen seek glory unmatched by other civilizations, whom they look down upon with a mixture of bewilderment and pity."),
                        clienttranslate("<b>At the beginning of your income turns (2-5):</b>"),
                        clienttranslate("For each advancement track you've completed or where there are no further-advanced tokens than yours, gain 1 [VP] per opponent."),
                        clienttranslate("For each achievement you've earned, gain [ANY RESOURCE]. If it's your final income turn, instead gain 5 [VP] per achievement earned.") ),
                "adjustment" => clienttranslate("When starting the game with THE CHOSEN, gain 15 VP per opponent"),
                "adjustment@a4" => clienttranslate("rule changes"),
                //The first change is, "Whenever you earn an achievement, gain double the VP." The second change is: "At the beginning of your income turns (2-5):
                //--If there is at least one advancement track on which there are no further-advanced tokens than yours, gain any 1 resource.
                //--For each advancement track you've completed or where there are no further-advanced tokens than yours, gain 1 VP per opponent."
                "achi" => [ ],"income_trigger" => [ "from" => 2,"to" => 5 ], //
                "income_trigger@a4" => [ "from" => 0,"to" => 0 ], //
                "achi@a4" => array (
                        1 => array ("top" => 76,"left" => 18,"w" => 8.5,"h" => 5.5,
                                'tooltip' => clienttranslate('Have 6+ of the same resource.') ),
                        2 => array ("top" => 76,"left" => 32,"w" => 8.5,"h" => 5.5,
                                'tooltip' => clienttranslate('Have 5+ tapestry cards in hand at end of turn.') ),
                        3 => array ("top" => 76,"left" => 47,"w" => 8.5,"h" => 5.5,
                                'tooltip' => clienttranslate('Have 5+ territory tiles.') ),
                        4 => array ("top" => 76,"left" => 62,"w" => 8.5,"h" => 5.5,
                                'tooltip' => clienttranslate('Have at least 1 tech card in each row.') ),
                        5 => array ("top" => 76,"left" => 77,"w" => 8.5,"h" => 5.5,
                                'tooltip' => clienttranslate('Have at least 1 complete district with 3+ house income buildings.') ), ),
                "description@a4" => [ 
                        1 => clienttranslate('Start with a player token to the left of this track. When you complete any achievement (public or private), advance the token to gain either the benefit underneath or 5 VP.'),
                        2 => clienttranslate('You have access to five personal Achievements (in addition to the three public achievements); you may complete each of them once in any order.'),
                        3 => '',
                        4 => clienttranslate('<i>If you gain this civilization in the middle of the game, account for public and private achievements you have completed by advancing your token on this track and gaining the benefits.</i>'), ],
                
                "midgame_setup" => true,
            
                "slots" => [ ],
                //Worker -> Money -> Tech Card -> Food -> Any Income Building -> Culture -> Score Your City -> Science Die (full benefit/bonus)
                "slots@a4" => [// 
                        0 => [ "top" => 62,"left" => 6,"w" => 9,"h" => 5,"link" => [ 1 ] ],
                        1 => [ "benefit" => [ BE_GAIN_WORKER ],"top" => 62,"left" => 15,"w" => 9,"h" => 5,
                                "link" => [ 2 ] ],
                        2 => [ "benefit" => [ BE_GAIN_COIN ],"top" => 62,"left" => 24,"w" => 9,"h" => 5,
                                "link" => [ 3 ] ],
                        3 => [ "benefit" => [ 20 /* Invent */ ],"top" => 62,"left" => 33,"w" => 9,"h" => 5,
                                "link" => [ 4 ] ],
                        4 => [ "benefit" => [ BE_GAIN_FOOD ],"top" => 62,"left" => 42,"w" => 9,"h" => 5,
                                "link" => [ 5 ] ],
                        5 => [ "benefit" => [ 110 /* any inc build */ ],"top" => 62,"left" => 51,"w" => 9,"h" => 5,
                                "link" => [ 6 ] ],
                        6 => [ "benefit" => [ BE_GAIN_CULTURE ],"top" => 62,"left" => 60,"w" => 9,"h" => 5,
                                "link" => [ 7 ] ],
                        7 => [ "benefit" => [ 28 /* vp city */ ],"top" => 62,"left" => 69,"w" => 9,"h" => 5,
                                "link" => [ 8 ] ],
                        8 => [ "benefit" => [ 18 /* research */ ],"top" => 62,"left" => 78,"w" => 9,"h" => 5,
                                "link" => [ 0 ] ], ], ),
        16 => array ("name" => clienttranslate("TRADERS"),
                "description" => array (
                        clienttranslate("The Traders prefer to connect with other civilizations economically, not militarily. Start with 4 player tokens here."),
                        clienttranslate("<b>At the beginning of your income turns (2-5)</b>, you may place a player token from here on any territory on the map matching one of these descriptions:"),
                        clienttranslate("<b>Empty territory:</b> Gain 1 [VP] per adjacent territory controlled by opponents. If an opponent later conquers this territory, you may gain the benefit of any unselected conquer die as if you rolled it."),
                        clienttranslate("<b>Territory with exactly 1 opponent outpost token and nothing else:</b> Gain the benefit on the territory (if any)."),
                        clienttranslate("Remember that a territory with 2 tokens (of any type) on it cannot be conquered.") ),
                "description@a4" => array (
                        1 => clienttranslate("At the beginning of your income turns (2-5), choose one:"),
                        2 => clienttranslate("<li>Gain an income building and place it on a territory with exactly 1 opponent outpost token and nothing else; the opponent immediately gains the benefit revealed by the income building. The opponent controls the territory."),
                        3 => clienttranslate("<li>Place a player token on a territory with exactly 1 opponent outpost token and nothing else: Gain the benefit on the territory (if any); you both share control of this territory for scoring purposes."),
                        4 => clienttranslate("<li>Place a player token on a territory you control with exactly 1 outpost token and nothing else: Gain the benefit on the territory (if any).") ),
                "tokens_count" => 4,
                "slots_description" => '',
                "slots" => array (1 => [ "top" => 71,"left" => 76.3,"w" => 8.5,"h" => 5.5 ],
                        2 => [ "top" => 76.6,"left" => 76.3,"w" => 8.5,"h" => 5.5 ],
                        3 => [ "top" => 82.3,"left" => 76.3,"w" => 8.5,"h" => 5.5 ],
                        4 => [ "top" => 88,"left" => 76.3,"w" => 8.5,"h" => 5.5 ], ),
                "adjustment" => clienttranslate("When starting the game with the Traders, gain [ANY RESOURCE] [ANY RESOURCE] and 10 VP."),
                "adjustment@a4" => clienttranslate("rules changes"),
                "automa" => false,
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
        ),
        // AA
        CIV_COLLECTORS => [ "name" => clienttranslate("COLLECTORS"),
                "description" => [ 
                        clienttranslate("The collectors proudly display a variety of objects and buildings."),
                        clienttranslate("When you gain at least one of the following during an advancement turn, you may place one of them on this civilization mat if there isn't already one of that type here. Then gain [ANY RESOURCE]."),
                        clienttranslate("[OUTPOST]* [HOUSE] [LANDMARK] [TAPESTRY] [TERRITORY] [TECHNOLOGY]"),
                        clienttranslate("At the end of the game, gain VP [1/3/6/10/15/21] according to the number of different objects on this civilization mat [1/2/3/4/5/6]."),
                        clienttranslate("Items on your mat still count as yours for the purposes of scoring, but may no longer be used, upgraded, played, or discarded.  All cards and tiles are kept face down."),
                        clienttranslate("* Gain outpost via conquer benefit; place on civilization mat instead of the map; do not roll dice"),
                ],
                "slots_description" => '',
                "slots" => [
               
                        1 => [ "ct" => BUILDING_OUTPOST, 'tt'=>'structure'  ], //
                        2 => [ "ct" => BUILDING_HOUSE, 'tt'=>'structure' ], // 2
                        3 => [ "ct" => BUILDING_LANDMARK,  'tt'=>'structure' ], // 3
                        4 => [ "ct" => CARD_TAPESTRY,  'tt'=>'card'], // 4
                        5 => [ "ct" => CARD_TERRITORY,  'tt'=>'card'],
                        6 => [ "ct" => CARD_TECHNOLOGY,  'tt'=>'card'],
                ], //
                "exp" => "AA", ],
        CIV_GAMBLERS => [ "name" => clienttranslate("GAMBLERS"),
                "description" => [ clienttranslate("The Gamblers know when to hold 'em and know when to fold 'em."),
                        clienttranslate("If you start the game with this civilization, at the end of your first income turn reveal 3 tapestry cards. Play one with a WHEN PLAYED ability on top of Maker of Fire, resolve the ability, and discard the other revealed cards."),
                        clienttranslate("If you reveal no valid cards, add 1 of the revealed cards to your hand and discard the rest."),
                        clienttranslate("At the beginning of your income turns (2-4), reveal 3 tapestry cards. Play one with a WHEN PLAYED ability on top of the current era, resolve the ability, and discard the other revealed cards."),
                        clienttranslate("If you reveal no valid cards, add 1 of the revealed cards to your hand and discard the rest."),
                        clienttranslate("Then proceed to play your tapestry card for this era as normal (on top of it)."), ],
                "income_trigger" => [ "from" => 1,"to" => 4,"decline" => false ], // 
                "slots_description" => '',
                "slots" => [ 0 => ['benefit'=> [311] ]],
                "slot_choice"=>"any",
                "exp" => "AA", ],
        CIV_RELENTLESS => [ "name" => clienttranslate("RELENTLESS"),
                "description" => [ 
                        clienttranslate("The Relentless are always pushing for improvement, rushing forward without pause."),
                        clienttranslate("At the end of each of your income and advancement turns, if you gained at least one building (income or landmark) during that turn, place a player token on this civilization mat."),
                        clienttranslate("At the end of each of your advancement turns, if you did not gain at least one building on that turn, remove all player tokens from this mat, and gain rewards based on the following chart ('/' means 'or'):"),
                        clienttranslate("<li>1 token | 1 VP"), //
                        clienttranslate("<li>2 tokens | 3 VP + [TAPESTRY]"),
                        clienttranslate("<li>3 tokens | 6 VP + [TAPESTRY]/[ANY RESOURCE]"),
                        clienttranslate("<li>4 tokens | 10 VP + [TAPESTRY]/[ANY RESOURCE]/[EXPLORE]"),
                        clienttranslate("<li>5+ tokens | 15 VP + [TAPESTRY]/[ANY RESOURCE]/[EXPLORE]/[INVENT]"), ],
                "slots_description" => '',
                "slots" => [
                        0 => [ "benefit" => [ ] ], // start
                        1 => [ "benefit" => [ 501 ] ], // 1
                        2 => [ "benefit" => [ 503, BE_TAPESTRY] ], // 2
                        3 => [ "benefit" => [ 506, 'or'=>[ BE_TAPESTRY, BE_ANYRES] ]], // 3
                        4 => [ "benefit" => [ 510, 'or'=>[ BE_TAPESTRY, BE_ANYRES, BE_EXPLORE] ] ], // 4 
                        5 => [ "benefit" => [ 515, 'or'=>[ BE_TAPESTRY, BE_ANYRES, BE_EXPLORE, BE_INVENT] ] ], 
                ], //
                "exp" => "AA", ],
        CIV_RENEGADES => [ "name" => clienttranslate("RENEGADES"),
                "description" => [ 
                        clienttranslate("The Renegades choose to forge their own path, advancing in ways that other civilizations cannot and finding shortcuts along the way."),
                        clienttranslate("Start with a player token on the start position of the Renegades' track."),
                        clienttranslate("When advancing on a track where you would gain the benefit, you may ignore its benefit (and bonus, if applicable) to advance on this track and gain its benefit instead. You still gain applicable landmarks. The tier you're advancing into or within must match the tier you are advancing into on this mat. This is the only way to advance on this track, and it is not considered an advancement track for other cards, abilities, achievements, etc."),  
                        clienttranslate("Reaching the end of this track counts toward the end-of-track achievement."), // 
                ], // end of desc
                "description@a8" => [
                        3 => '',
                        4 => clienttranslate("If you gain this civilization in the middle of the game, place a player token in a tier that matches a tier on the board containing one of your player tokens. Gain the benefit immediately.")
                ],
                "tokens_count" => 1,
                "slots_description" => '',
                "slots" => [ 
                        0 => [ "benefit" => [ ] ], // start
                        1 => [ "benefit" => ['choice'=>[BE_TAPESTRY, BE_GAIN_ANY_INCOME_BUILDING]]], // 1
                        2 => [ "benefit" => ['choice'=>[BE_RESEARCH, BE_INVENT]]], // 2
                        3 => [ "benefit" => ['choice'=>[BE_EXPLORE, BE_CONQUER, BE_UPGRADE_TECH]]], // 3
                        4 => [ "benefit" => [ 520 ] ], // 4 - 20 VP
                ], //
                "exp" => "AA", 
                "al" => 8
                ],
        CIV_URBAN_PLANNERS => [ "name" => clienttranslate("URBAN PLANNERS"),
                "description" => [ 
                        clienttranslate("<i>The Urban Planners want a neat, orderly city. They see a place for everything and everything in its place.</i>"),
                        clienttranslate("When you gain this civilization, place a 2x2 landmark (formed of your cubes) in your capital city (This BGA Special Rule since we don't have landmark cards)."),
                        clienttranslate("When you gain a landmark, you may place it on this mat. If you do, you may place it in your capital city on a future turn."),
                        "",
                        clienttranslate("On your final income turn, gain VP according to the chart below for the largest group of connected landmarks in your capital city (where each landmark touches at least one other landmark orthogonally)."),
                        
                        clienttranslate("1, 2, 3, 4, 5, 6, 7 => 1 VP, 4 VP, 9 VP, 16 VP, 25 VP, 36 VP, 49 VP"), ], //
                "description@a4a8" => [
                        3=> clienttranslate("If you place 2+ landmarks from this mat at the same time, also gain [ANY RESOURCE] and [TAPESTRY]")
                ],

                "exp" => "AA", //
                "al" => 8,
                "start_benefit" => [ 315 ], //
        ],
        // PP
        CIV_ADVISORS => [ "name" => clienttranslate("ADVISORS"),
                "description" => [ 
                        clienttranslate("The Advisors dispense an endless wealth of unsolicited advice to other civilizations."),
                        clienttranslate("Start with 3 tapestry cards."),
                        clienttranslate("At the beginning of your income turns (2-5), gain 1 VP per tapestry card in your hand. Then, if you have more than 4 tapestry cards in hand, you must discard down to exactly 4. If this causes you to discard at least 1 card, gain [ANY RESOURCE]."),
                        clienttranslate("During advancement turns (not income turns) whenever a neighbor would gain a tapestry card, instead of them drawing from the deck, you may give them a card of your choice from your hand. If you do, after you give them a card, gain a tapestry card."),
                        clienttranslate("<it>If you gain this civilization in the middle of the game, you may immediately give any/all of the starting 3 tapestry cards to neighbors, gaining 5 per card you give</it>"), ],
                "start_benefit" => [ "m" => 3,"g" => BE_TAPESTRY ], //
                "income_trigger" => [ "from" => 2,"to" => 5,"decline" => false ], //
                "midgame_setup" => true,
                "slot_choice"=>"any",
                "slots_description" => '',
                "slots" => [ 1 => [ "top" => 80,"left" => 80,"benefit" => [ 136 ],"w" => 13,"h" => 8.5 ], ], //
                "exp" => "PP", ],
        CIV_ALIENS => [ "name" => clienttranslate("ALIENS"),
                "description" => [ 
                        clienttranslate("The Aliens have settled on this planet, but their desire to explore the wonders of the galaxy never ceases."),
                        clienttranslate("Start with 4 face-up space tiles in your supply (not on this mat)."),
                        clienttranslate("At the beginning of your income turns (2-5), if there is a space tile on this civilization mat, discard it. Then choose one of these options:"),
                        clienttranslate("<li>Explore 1 space tile from your supply, placing it on this civilization mat. If you discarded a space tile from your civilization mat this turn, you must first pay [ANY RESOURCE]"),
                        clienttranslate("<li>Gain [ANY RESOURCE]</li>"), ],"exp" => "PP","slots_description" => '',
                "slots_description" => '',
                "slot_choice"=>"any",
                "slots" => [ // 
                        1 => [ "top" => 80,"left" => 80,"benefit" => [ BE_EXPLORE_SPACE_ALIEN ],"w" => 13,"h" => 8.5 ],
                        2 => [ "top" => 90,"left" => 80,"benefit" => [ BE_ANYRES, 'h'=> BE_ALIEN_D ],"w" => 13,"h" => 8.5 ], ],
                "start_benefit" => [ "m" => 4,"g" => BE_SPACE ], //
                "income_trigger" => [ "from" => 2,"to" => 5,"decline" => false ], // 
        ],
        CIV_INFILTRATORS => [ "name" => clienttranslate("INFILTRATORS"),
                "description" => [ 
                        clienttranslate("The Infiltrators fortify and influence other civilizations."),
                        clienttranslate("Start with 1 [CULTURE]."),
                        clienttranslate("At the beginning of your income turns (2-5), you may do one of the following:"),
                        clienttranslate("<li>Place an outpost token on any territory with exactly 1 opponent outpost and nothing else. This is not a conquer action, and both outposts remain upright (control of the territory is shared). Gain the benefit on the territory (if any). There are now 2 tokens on this territory, so it cannot be conquered. It does not count for the middle island achievement."),
                        clienttranslate("<li>Place a player token on an opponent's capital city territory and gain 1 VP for each outpost token that opponent hasn't placed on the board. If it's your third player token there, gain another civilization."),
                        clienttranslate("Whenever you would gain a new civilization, draw 3 and keep 1 (discard the others)"), ],
                "exp" => "PP", //
                "start_benefit" => [ BE_GAIN_CULTURE ], //
                "slots_description" => '',
                "slot_choice"=>"any",
                "slots" => [ 
                        0 => [ "benefit" => [ 170 ] ],
                        1 => [ "benefit" => [ 171 ] ],
                ], //
                "income_trigger" => [ "from" => 2,"to" => 5 ], // 
        ],
        CIV_ISLANDERS => [ "name" => clienttranslate("ISLANDERS"),
                "description" => [ 
                        clienttranslate("The Islanders are mystified by a massive island on the horizon, and they devote their resources to exploring it."),
                        clienttranslate("Start with 4 territory tiles in your supply."),
                        clienttranslate("At the beginning of your income turns (2-5), you may explore 1 of the hexes on this mat (place a territory tile here and gain the exploration benefit and, just as you would on the board). These hexes cannot be conquered, but they can be explored on advancement turns via exploration actions."),
                        "",
                        clienttranslate("On step 3 of your final income turn, gain 4 VP for each of these territory tiles that contain only water on the outer edges."),
                        "",
                        clienttranslate("<it>If you gain this civilization in the middle of the game, you may discard it and gain another. If you keep it, immediately place up to 4 of your territory tiles on these hexes (don't gain exploration benefits from this)</it>"), ],
                "start_benefit" => [ "m" => 4,"g" => BE_TERRITORY ], //
                "income_trigger" => [ "from" => 2,"to" => 5 ], //
                "midgame_ben" => ["or"=>[174,173]],
                "midgame_setup" => true,
                "slot_choice"=>"any",
                "slots" => [
                        1 => [ "benefit" => [ 179 ] ],
                ], //
                "exp" => "PP" ],
        CIV_RECYCLERS => [ "name" => clienttranslate("RECYCLERS"),//34
                "description" => [ 
                        clienttranslate("The Recyclers take used technology and make it new again. Start by gaining 3 tech cards from the deck; discard 2 and keep 1, placing it in your bottom row. You may not upgrade it during your first income turn."),
                        "",
                        clienttranslate("At the beginning of your income turns (2-5), you may upgrade a tech card. This is in addition to the standard upgrade during each of those income turns."),
                        "",
                        clienttranslate("When upgrading, the Recyclers may upgrade a card from the top row to the bottom row. Whenever you do this, gain 5 VP."),
                        clienttranslate("When gaining a tech card, you may choose from the tech discard pile."), ],
                "income_trigger" => [ "from" => 2,"to" => 5 ],
                "exp" => "PP",
                "start_benefit" => [ 175 ],
                "slot_choice"=>"any",
                "slots" => [
                        1 => [ "benefit" => [ 14 ] ],
                ], //
                //
        ],
        CIV_RIVERFOLK => [ "name" => clienttranslate("RIVERFOLK"),
                "description" => [ 
                        clienttranslate("The Riverfolk view flowing water as a more viable place for building than firm land."),
                        clienttranslate("Start with 4 VP per district in your capital city with at least 2 impassable plots."),
                        clienttranslate("You may place buildings on impassable plots of land in your capital city. Impassable plots still help the Riverfolk complete districts, rows, and columns."),
                        "",
                        clienttranslate("Whenever you cover the last impassable plot in a district, gain [ANY RESOURCE]. This benefit does not apply in districts with no impassable plots."),
                        clienttranslate("On your final income turn, lose 1 VP per visible impassable plot."),
                        "",
                        clienttranslate("<it>If you gain this civilization in the middle of the game, you may discard it and gain another</it>"), ],
 
                "midgame_ben" => ["or"=>[174,173]], //
                "exp" => "PP" ],
        CIV_SPIES => [ "name" => clienttranslate("SPIES"),
                "description" => [ 
                        clienttranslate("The Spies use subterfuge and deception to leech information from neighboring nations."),
                        "",
                        clienttranslate("At the beginning of your income turns (2-5), you may place a player token on one of the unselected options here to gain the corresponding benefit from a neighboring player of your choice."),
                        clienttranslate("If you do, you may request to see the chosen neighbor's hand of tapestry cards (they must show it to you)."),
                //        
                ],
                "income_trigger" => [ "from" => 2,"to" => 5 ],
                "slot_choice"=>"unoccupied",
                "midgame_setup" => false,
                "slots" => [
                        1 => [ "benefit" => [ 193 ],"w" => 7,"h" => 5,"top" => 78,"left" => 43 ],
                        2 => [ "benefit" => [ 191 ],"w" => 7,"h" => 5,"top" => 78,"left" => 81  ],
                        3 => [ "benefit" => [ 190 ],"w" => 7,"h" => 5,"top" => 89.5,"left" => 43  ],
                        4 => [ "benefit" => [ 192 ],"w" => 7,"h" => 5,"top" => 89.5,"left" => 81  ],
                ], //
                "exp" => "PP" 
        ],
        CIV_TINKERERS => [ "name" => clienttranslate("TINKERERS"),
                "description" => [ clienttranslate("The Tinkerers are masters of time and technology."),
                        clienttranslate("If you start the game with the Tinkerers, each opponent gains a tech card from the deck, placing it in their bottom row. They may not upgrade tech cards during their first income turn."),
                        clienttranslate("At the beginning of your income turns (2-5), you may place a player token on one of the unselected options here and act accordingly."),
                        clienttranslate("Unless otherwise noted, do not gain the advancement benefits or bonuses as a result of these player token manipulations."),
                        "",
                        //
                ],
                "slots" => [
                        1 => [ "benefit" => [ BE_TINKERERS_1 ],"w" => 7,"h" => 5,"top" => 78,"left" => 43 ],
                        2 => [ "benefit" => [ BE_TINKERERS_2 ],"w" => 7,"h" => 5,"top" => 78,"left" => 81  ],
                        3 => [ "benefit" => [ BE_TINKERERS_3 ],"w" => 7,"h" => 5,"top" => 89.5,"left" => 43  ],
                        4 => [ "benefit" => [ BE_TINKERERS_4 ],"w" => 7,"h" => 5,"top" => 89.5,"left" => 81  ],
                ], //
                "start_benefit" => [ 199 ], //
                "slot_choice"=>"unoccupied", //
                "midgame_setup" => false, //
                "income_trigger" => [ "from" => 2,"to" => 5 ],
                "exp" => "PP" ],
        CIV_TREASURE_HUNTERS => [ "name" => clienttranslate("TREASUREHUNTERS"),
                "description" => [ 
                        clienttranslate("The Treasure Hunters look for riches wherever they go, and they seek to protect their valuables from other civilizations."),
                        clienttranslate("Start with 4 player tokens here and gain 2 territory tiles."),
                        "",
                        clienttranslate("At the beginning of your income turns (2-5), you may place a player token from here on an empty territory adjacent to a territory you control that displays the listed terrain type (this is not a conquer action, but you now control the territory). Gain the corresponding treasure."),
                        "",
                        clienttranslate("<li>Desert: Gain 1 [COIN], then roll the black conquer die twice and gain one benefit of your choice."),
                        clienttranslate("<li>Grassland: Gain 1 [CULTURE], then roll the research die twice and gain one benefit of your choice."),
                        clienttranslate("<li>Mountain: Gain 1 [WORKER], then roll the conquer dice and gain both benefits."),
                        clienttranslate("<li>Forest: Gain 1 [FOOD], then roll the red conquer die twice and gain both benefits."), ],
                "income_trigger" => [ "from" => 2,"to" => 5 ],
                "start_benefit" => [ BE_TERRITORY, BE_TERRITORY ], //
                "slot_choice"=>"occupied", //
                "tokens_count" => 4,
                "slots" => [
                        1 => [ "benefit" => [ RES_COIN, 301 ], "ter"=>TERRAIN_DESERT,"w" => 7,"h" => 5,"top" => 80,"left" => 15 ],
                        2 => [ "benefit" => [ RES_CULTURE, 302 ], "ter"=>TERRAIN_GRASS,"w" => 7,"h" => 5,"top" => 80,"left" => 53  ],
                        3 => [ "benefit" => [ RES_WORKER, 303 ], "ter"=>TERRAIN_MOUNTAIN,"w" => 7,"h" => 5,"top" => 89.5,"left" => 15  ],
                        4 => [ "benefit" => [ RES_FOOD, 304 ], "ter"=>TERRAIN_FOREST,"w" => 7,"h" => 5,"top" => 89.5,"left" => 53  ],
                ], //
                "exp" => "PP" // 
        ],
        CIV_UTILITARIENS => [ "name" => clienttranslate("UTILITARIANS"),
                "description" => [ 
                        clienttranslate("The Utilitarians aren't satisfied simply memorializing their advancements."),
                        clienttranslate("If you start the game with the Utilitarians, choose a tier II landmark and place it in your capital city."),
                        "",
                        clienttranslate("Start with 2 player tokens here (1 on the first landmark you selected). After you gain a landmark on this list, you may place or move 1 of the player tokens on this mat next to that landmark's name to activate the corresponding ability."),
                        "",
                        clienttranslate("<it>If you gain this civilization in the middle of the game, you may immediately place up to 2 player tokens next to the corresponding landmark abilities on this mat if you have those landmarks</it>"), ],
                "tokens_count" => 2,
                "slots" => [ 
                        1 => [ "lm" => 10,"w" => 7,"h" => 5,"top" => 59,"left" => 8,
                                "title" => clienttranslate("Forge: Whenever upgrade a tech card to the top row, also gain [ANY RESOURCE]") ],
                        2 => [ "lm" => 8,"w" => 7,"h" => 5,"top" => 59,"left" => 53,
                                "title" => clienttranslate("Rubber Works: Whenever you you advance on tiers III and IV of the Technology track, gain 4 VP") ],
                        3 => [ "lm" => 6,"w" => 7,"h" => 5,"top" => 66,"left" => 8,
                                "title" => clienttranslate("Barracks: Whenever you conquer, gain the result of the red die (even if you also chose that die's benefit)") ],
                        4 => [ "lm" => 5,"w" => 7,"h" => 5,"top" => 66,"left" => 53,
                                "title" => clienttranslate("Tank Factory: Whenever you advance on tiers III and IV of the Military track, gain 4 VP") ],
                        5 => [ "lm" => 9,"w" => 7,"h" => 5,"top" => 74,"left" => 8, "cl"=>"activatable",
                                "title" => clienttranslate("Lighthouse: Once on each of your advancement turns you may spend 2 [TERRITORY TILE] to gain [ANY REOURCE]") ],
                        
                        6 => [ "lm" => 4,"w" => 7,"h" => 5,"top" => 74,"left" => 53,
                                "title" => clienttranslate("Train Station: Whenever you advance on tiers III and IV of the Exploration track, gain 4 VP") ],
                        7 => [ "lm" => 2,"w" => 7,"h" => 5,"top" => 82,"left" => 8,
                                "title" => clienttranslate("Apothecary: Whenever you gain another landmark, also gain [ANY RESOURCE]") ],
                        8 => [ "lm" => 3,"w" => 7,"h" => 5,"top" => 82,"left" => 53,
                                "title" => clienttranslate("Academy: Whenever you advance on tiers III and IV of the Science track, gain 4 VP") ],
                        9 => [ "lm" => 0,"w" => 7,"h" => 5,"top" => 30,"left" => 53,
                                "title" => clienttranslate("Empty slot") ],
                        10 => [ "lm" => 0,"w" => 7,"h" => 5,"top" => 35,"left" => 53,
                                "title" => clienttranslate("Empty slot") ], ], //
                "exp" => "PP" ], );
$this->capitals = array (
        1 => array ("name" => clienttranslate("Mountain"),"start" => [ "large" => "-3_-3","small" => "-2_-2" ], // 1/6
                "grid" => [ "000000000","110001110","000111000","000111110","000001100","011100001","001000000",
                        "000000010","001000000" ], ),
        2 => array ("name" => clienttranslate("Wetland"),"start" => [ "large" => "-3_0","small" => "0_2" ], // 2/4
                "grid" => [ "000000001","110000111","000000000","011100110","001100000","111000010","000001100",
                        "011000000","000001000" ], ),
        3 => array ("name" => clienttranslate("Tropical"),"start" => [ "large" => "0_3","small" => "2_0" ], // 3/5
                "grid" => [ "000010011","000100011","001000001","000000000","010001000","000000000","101101010",
                        "011110000","001110000" ], ),
        4 => array ("name" => clienttranslate("Desert"),"start" => [ "large" => "3_3","small" => "0_2" ], // 2/4
                "grid" => [ "000110000","101000000","100000110","000000000","010010011","011000001","011111000",
                        "001100000","000001000" ], ),
        5 => array ("name" => clienttranslate("Grassland"),"start" => [ "large" => "3_0","small" => "2_0" ], // 3/5
                "grid" => [ "000000000","111000010","111100000","000111100","000000111","111000001","001000000",
                        "000000100","000001000" ], ),
        6 => array ("name" => clienttranslate("Forest"),"start" => [ "large" => "0_-3","small" => "-2_-2" ], // 1/6
                "grid" => [ "000000000","001100100","000100010","011000001","000100000","001111100","010000100",
                        "001000111","000000011" ], ), );
$this->income_tracks = array (
        1 => array ("name" => clienttranslate("Markets"),"field" => "markets","color" => "yellow","resource" => "coin",
                1 => array ("name" => clienttranslate("Barter"),"benefit" => [ BE_GAIN_COIN,BE_VP_TECH ], ),
                2 => array ("name" => clienttranslate("Currency"),"benefit" => [ BE_GAIN_COIN ] ),
                3 => array ("name" => clienttranslate("Banking"),"benefit" => [ BE_VP_TECH ] ),
                4 => array ("name" => clienttranslate("Credit Cards"),"benefit" => [ BE_GAIN_COIN ] ),
                5 => array ("name" => clienttranslate("E-Commerce"),"benefit" => [ BE_GAIN_COIN,BE_VP_TECH ] ),
                6 => array ("name" => clienttranslate("Biometrics"),"benefit" => [ 510 ] ), ),
        2 => array ("name" => clienttranslate("Houses"),"field" => "houses","color" => "grey","resource" => "worker",
                1 => array ("name" => clienttranslate("Symbology"),"benefit" => [ BE_GAIN_WORKER,BE_VP_CAPITAL ] ),
                2 => array ("name" => clienttranslate("Language"),"benefit" => [ BE_GAIN_WORKER ] ),
                3 => array ("name" => clienttranslate("Writing"),"benefit" => [ BE_VP_CAPITAL ] ),
                4 => array ("name" => clienttranslate("Telephone"),"benefit" => [ BE_GAIN_WORKER ] ),
                5 => array ("name" => clienttranslate("Email"),"benefit" => [ BE_GAIN_WORKER,BE_VP_CAPITAL ] ),
                6 => array ("name" => clienttranslate("Neural Implants"),"benefit" => [ 510 ] ), ),
        3 => array ("name" => clienttranslate("Farms"),"field" => "farms","color" => "brown","resource" => "food",
                1 => array ("name" => clienttranslate("Hunting"),"benefit" => [ BE_GAIN_FOOD,BE_TERRITORY ] ),
                2 => array ("name" => clienttranslate("Farming"),"benefit" => [ BE_GAIN_FOOD ] ),
                3 => array ("name" => clienttranslate("Breeding"),"benefit" => [ 504 ] ),
                4 => array ("name" => clienttranslate("Preservation"),"benefit" => [ BE_GAIN_FOOD ] ),
                5 => array ("name" => clienttranslate("Fertilization"),"benefit" => [ BE_GAIN_FOOD,507 ] ),
                6 => array ("name" => clienttranslate("Food Printing"),"benefit" => [ 510 ] ), ),
        4 => array ("name" => clienttranslate("Armories"),"field" => "armories","color" => "red",
                "resource" => "culture",
                1 => array ("name" => clienttranslate("Ceremony"),"benefit" => [ BE_GAIN_CULTURE,BE_TAPESTRY ] ),
                2 => array ("name" => clienttranslate("Racing"),"benefit" => [ BE_GAIN_CULTURE ] ),
                3 => array ("name" => clienttranslate("Team Sports"),"benefit" => [ BE_VP_TERRITORY ] ),
                4 => array ("name" => clienttranslate("Tabletop Games"),"benefit" => [ BE_GAIN_CULTURE ] ),
                5 => array ("name" => clienttranslate("Video Games"),"benefit" => [ BE_GAIN_CULTURE,BE_VP_TERRITORY ] ),
                6 => array ("name" => clienttranslate("Virtual Reality"),"benefit" => [ 510 ] ), ), );

$this->card_types = array (
        CARD_TERRITORY => array ("deck" => "deck_territory","name" => clienttranslate("Territory"),
                "data" => $this->territory_tiles ),
        CARD_SPACE => ["deck" => "deck_space","name" => clienttranslate("Space"), 
                "data"=>$this->space_tiles ],
        CARD_TAPESTRY => array ("deck" => "deck_tapestry","name" => clienttranslate("Tapestry"),
                "data"=>$this->tapestry_card_data
        ),
        CARD_TECHNOLOGY => array ("deck" => "deck_tech","name" => clienttranslate("Technology"),
                "data"=>$this->tech_card_data
        ),
        CARD_CIVILIZATION => ["deck" => "deck_civ",
                "name" => clienttranslate("Civilization"),
                "data"=> $this->civilizations],
        CARD_CAPITAL => array ("deck" => "deck_capital",
                "name" => clienttranslate("Capital"),
                "data"=>$this->capitals
        ),
        CARD_DECISION => [ "deck" => "deck_decision","name" => clienttranslate("Decision Card"),
                "data" => $this->decision_cards ],
        CARD_AUTOMACIV => [ "deck" => "deck_automaciv","name" => clienttranslate("Automa Civilization"),
                "data" => $this->automa_civ_cards ], );

