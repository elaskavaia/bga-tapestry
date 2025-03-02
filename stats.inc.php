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
 * stats.inc.php
 *
 * tapestry game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/

$stats_type = array(

    // Statistics global to table
    "table" => array(

        "turns_number" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),
        "game_turns_total_1" => [ "id" => 110,"name" => totranslate("Number of turns (Automa)"),"type" => "int" ],
        "automa_score" => [ "id" => 111,"name" => totranslate("Automa score"),"type" => "int" ],
        "game_winner_score" => [ "id" => 112,"name" => totranslate("Best score"),"type" => "int" ],
        "game_loser_score" => [ "id" => 113,"name" => totranslate("Worst score"),"type" => "int" ],
        "automa_civ" => [ "id" => 114,"name" => totranslate("Automa civilization"),"type" => "int" ],
    ),
    
    // Statistics existing for each player
    "player" => array(

        "turns_total" => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),

        "turns_era_1" => array("id"=> 11,
                    "name" => totranslate("Number of advances (Era 1)"),
                    "type" => "int" ),

        "turns_era_2" => array("id"=> 12,
                    "name" => totranslate("Number of advances (Era 2)"),
                    "type" => "int" ),

        "turns_era_3" => array("id"=> 13,
                    "name" => totranslate("Number of advances (Era 3)"),
                    "type" => "int" ),

        "turns_era_4" => array("id"=> 14,
                    "name" => totranslate("Number of advances (Era 4)"),
                    "type" => "int" ),

        "bonuses" => array("id"=> 15,
                    "name" => totranslate("Number of claimed bonuses"),
                    "type" => "int" ),

        "track1" => array("id"=> 16,
                    "name" => totranslate("Exploration track spaces"),
                    "type" => "int" ),

        "track2" => array("id"=> 17,
                    "name" => totranslate("Science track spaces"),
                    "type" => "int" ),

        "track3" => array("id"=> 18,
                    "name" => totranslate("Military track spaces"),
                    "type" => "int" ),

        "track4" => array("id"=> 19,
                    "name" => totranslate("Technology track spaces"),
                    "type" => "int" ),

        "capital" => array("id"=> 20,
                    "name" => totranslate("Starting capital mat"),
                    "type" => "int" ),

        "civ" => array("id"=> 21,
                    "name" => totranslate("Starting civilization"),
                    "type" => "int" ),
            
            "game_income_1" => array("id"=> 22,
                    "name" => totranslate("Income track Markets"),
                    "type" => "int" ),
            "game_income_2" => array("id"=> 23,
                    "name" => totranslate("Income track Houses"),
                    "type" => "int" ),
            "game_income_3" => array("id"=> 24,
                    "name" => totranslate("Income track Farms"),
                    "type" => "int" ),
            "game_income_4" => array("id"=> 25,
                    "name" => totranslate("Income track Armories"),
                    "type" => "int" ),
            
            "game_resource_spent" => array("id"=> 30,
                    "name" => totranslate("Resources spent"),
                    "type" => "int" ),
            "game_building_income" => array("id"=> 31,
                    "name" => totranslate("Placed income buildings"),
                    "type" => "int" ),
            "game_building_landmark" => array("id"=> 32,
                    "name" => totranslate("Claimed landmarks"),
                    "type" => "int" ),
            "game_building_outpost" => array("id"=> 33,
                    "name" => totranslate("Placed outposts"),
                    "type" => "int" ),
            "game_card_civ" => array("id"=> 34,
                    "name" => totranslate("Civilizations"),
                    "type" => "int" ),
            
            "game_points_reason_spot" => array("id"=> 40,
                    "name" => totranslate("Points from tracks"),
                    "type" => "int" ),
            "game_points_reason_tech" => array("id"=> 41,
                    "name" => totranslate("Points from tech cards"),
                    "type" => "int" ),
            "game_points_reason_civ" => array("id"=> 42,
                    "name" => totranslate("Points from civilizations"),
                    "type" => "int" ),
            "game_points_reason_tapestry" => array("id"=> 43,
                    "name" => totranslate("Points from tapestry cards"),
                    "type" => "int" ),
            "game_points_reason_tile" => array("id"=> 44,
                    "name" => totranslate("Points from exploring"),
                    "type" => "int" ),
            "game_points_reason_space" => array("id"=> 45,
                    "name" => totranslate("Points from space tiles"),
                    "type" => "int" ),
            "game_points_reason_inspot" => array("id"=> 46,
                    "name" => totranslate("Points from income mat"),
                    "type" => "int" ),
            "game_points_reason_die" => array("id"=> 47,
                    "name" => totranslate("Points from conquer die"),
                    "type" => "int" ),
            "game_points_reason_achi" => array("id"=> 48,
                    "name" => totranslate("Points from achievements"),
                    "type" => "int" ),
            "game_points_reason_other" => array("id"=> 49,
                    "name" => totranslate("Points from other sources"),
                    "type" => "int" ),
            
 
   
            'game_points_be_27' => ['id'=> 63, 'name' => totranslate('VP - Tech cards'), 'type' => 'int' ],
            'game_points_be_28' => ['id'=> 64, 'name' => totranslate('VP - Capital score'), 'type' => 'int' ],
            'game_points_be_29' => ['id'=> 65, 'name' => totranslate('VP - Controlled territories'), 'type' => 'int' ],
            'game_points_be_54' => ['id'=> 66, 'name' => totranslate('VP - Markets'), 'type' => 'int' ],
            'game_points_be_63' => ['id'=> 67, 'name' => totranslate('VP - Houses'), 'type' => 'int' ],
            'game_points_be_30' => ['id'=> 68, 'name' => totranslate('VP - Farms'), 'type' => 'int' ],
            'game_points_be_31' => ['id'=> 69, 'name' => totranslate('VP - Armories'), 'type' => 'int' ],
            'game_points_be_47' => ['id'=> 70, 'name' => totranslate('VP - Exploration'), 'type' => 'int' ],
            'game_points_be_48' => ['id'=> 71, 'name' => totranslate('VP - Science'), 'type' => 'int' ],
            'game_points_be_49' => ['id'=> 72, 'name' => totranslate('VP - Military'), 'type' => 'int' ],
            'game_points_be_50' => ['id'=> 73, 'name' => totranslate('VP - Technology'), 'type' => 'int' ],
            'game_points_be_66' => ['id'=> 74, 'name' => totranslate('VP - Territory tiles'), 'type' => 'int' ],
            'game_points_be_71' => ['id'=> 75, 'name' => totranslate('VP - Tapestry cards'), 'type' => 'int' ],
            'game_points_be_15' => ['id'=> 76, 'name' => totranslate('VP - Plain'), 'type' => 'int' ],
            'game_points_be_113' => ['id'=> 77, 'name' => totranslate('VP - Landmarks'), 'type' => 'int' ],
            
            'game_points_era_1' => ['id'=> 81, 'name' => totranslate('Points during Income/Era 1'), 'type' => 'int' ],
            'game_points_era_2' => ['id'=> 82, 'name' => totranslate('Points during Income/Era 2'), 'type' => 'int' ],
            'game_points_era_3' => ['id'=> 83, 'name' => totranslate('Points during Income/Era 3'), 'type' => 'int' ],
            'game_points_era_4' => ['id'=> 84, 'name' => totranslate('Points during Income/Era 4'), 'type' => 'int' ],
            'game_points_era_5' => ['id'=> 85, 'name' => totranslate('Points during Income 5'), 'type' => 'int' ],
            'game_points_era_6' => ['id'=> 86, 'name' => totranslate('Points after end of game'), 'type' => 'int' ],
            
            'game_points_total' => ['id'=> 87, 'name' => totranslate('Points total'), 'type' => 'int' ],
    ),
        "value_labels" => array(
                21 => array(
                        0 => totranslate("None"),
                        1 => clienttranslate('ALCHEMISTS'),
                        2 => clienttranslate('ARCHITECTS'),
                        3 => clienttranslate('CRAFTSMEN'),
                        4 => clienttranslate('ENTERTAINERS'),
                        5 => clienttranslate('FUTURISTS'),
                        6 => clienttranslate('HERALDS'),
                        7 => clienttranslate('HISTORIANS'),
                        8 => clienttranslate('INVENTORS'),
                        9 => clienttranslate('ISOLATIONISTS'),
                        10 => clienttranslate('LEADERS'),
                        11 => clienttranslate('MERRYMAKERS'),
                        12 => clienttranslate('MILITANTS'),
                        13 => clienttranslate('MYSTICS'),
                        14 => clienttranslate('NOMADS'),
                        15 => clienttranslate('THE CHOSEN'),
                        16 => clienttranslate('TRADERS'),
                        21 => clienttranslate('COLLECTORS'),
                        22 => clienttranslate('GAMBLERS'),
                        23 => clienttranslate('RELENTLESS'),
                        24 => clienttranslate('RENEGADES'),
                        25 => clienttranslate('URBAN PLANNERS'),
                        30 => clienttranslate('ADVISORS'),
                        31 => clienttranslate('ALIENS'),
                        32 => clienttranslate('INFILTRATORS'),
                        33 => clienttranslate('ISLANDERS'),
                        34 => clienttranslate('RECYCLERS'),
                        35 => clienttranslate('RIVERFOLK'),
                        36 => clienttranslate('SPIES'),
                        37 => clienttranslate('TINKERERS'),
                        38 => clienttranslate('TREASUREHUNTERS'),
                        39 => clienttranslate('UTILITARIANS'),
                ),
                114 => [
                        0 => totranslate("None"),
                        1 => totranslate("Explorers"),
                        2 => totranslate("Scientist"),
                        3 => totranslate("Conquerors"),
                        4 => totranslate("Engineers"),
                ]
        )

);
