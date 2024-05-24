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
 * gameoptions.inc.php
 *
 * tapestry game options description
 * 
 * In this file, you can define your game options (= game variants).
 *   
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in tapestry.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = [
    100 => [
        'name' => totranslate('Civilization Adjustments'),
        'values' => [
            2 => ['name' => totranslate('no Adjustments'), 'description' => totranslate('original rules')],
            1 => ['name' => totranslate('with Adjustments'), 'description' => totranslate('latest official adjustments')],
            4 => ['name' => totranslate('with Experimental Adjustments Nov 2022'),'beta'=>true, 'description' => totranslate('experimental rules')],
 //           8 => ['name' => totranslate('Adjustment Civilization Pack r8'),'beta'=>true, 'description' => totranslate('official expansion')],
        ],
//             'startcondition' => [
//                     1 => [ [ 'type' => 'minplayers', 'value' => 32, 'message' => totranslate('Maintenance in progress.  Table creation for Tapestry is disabled.') ] ],
//                     2 => [ [ 'type' => 'minplayers', 'value' => 32, 'message' => totranslate('Maintenance in progress.  Table creation for Tapestry is disabled.') ] ],
//                     4 => [ [ 'type' => 'minplayers', 'value' => 32, 'message' => totranslate('Maintenance in progress.  Table creation for Tapestry is disabled.') ] ],
//             ],
        'default' => 1   
       ],
        101 => [
                'name' => totranslate('Tapestry: Marriage of State'),
                'values' => [
                        0 => ['name' => totranslate('Keep'), 'description' => totranslate('original rules')],
                        1 => ['name' => totranslate('Remove'), 'beta'=>true, 'tmdisplay' => totranslate('without Marriage of State'), 'description' => totranslate('house rules')],
                ],
                'default' => 0,
                'tap_remove' => [[3,23]],
        ],
        102 => [
                'name' => totranslate('Tapestry: Renaissance'),
                'values' => [
                        0 => ['name' => totranslate('Keep'), 'description' => totranslate('original rules')],
                        1 => ['name' => totranslate('Remove'), 'beta'=>true, 'tmdisplay' => totranslate('without Renaissance'), 'description' => totranslate('house rules')],
                ],
                'tap_remove' => [[3,33]],
                'default' => 0
        ],
        140 => [
                'name' => totranslate('Civilization Set'),
                'values' => [
                        0b111 => ['name' => totranslate('All (Original + PP + AA)'),
                                'tmdisplay' => totranslate('Civilation Set: All'), 'beta'=>true ],
                        0b001 => ['name' => totranslate('Original Only'), ],
                        0b011 => ['name' => totranslate('Original + Plans & Ploys'), 
                                'tmdisplay' => totranslate('Plans & Ploys'), 'beta'=>true ],
                       // 0b101 => ['name' => totranslate('Original + Arts & Architecture'), ],
                        0b010 => ['name' => totranslate('Plans & Ploys Only - Do not use'), 
                                'tmdisplay' => totranslate('Plans & Ploys'), 'beta'=>true ],
                        0b110 => ['name' => totranslate('Only Expansions (PP + AA) - For Testing'), 
                                'tmdisplay' => totranslate('Civilation Set: Only Expansions'), 'beta'=>true ]
                ],
                'default' => 0b001,
                'displaycondition' => [
                        [ 'type' => 'otheroptionisnot',
                                'id' => 100, // adjustment is not set to 4
                                'value' => 4,
                                
                        ]
                ],
                'startcondition' => [
                        0b010 => [ [ 'type' => 'minplayers', 'value' => 32,
                                'message' => totranslate('This option can no longer be used (remains for backward compatibiolity), use All') ] ],
                        
                ],
                'notdisplayedmessage' => totranslate('Original Only - Civilization Set selection is not available with Experimental Adjustments')               
        ],
        151 => [
                'name' => totranslate('Automa Level'),
                'values' =>
                [
                        //0 => [ 'name' => totranslate('0 - None')],
                        1 => [ 'name' => totranslate('1 - Automa the Underachiever'),'beta' => true ],
                        2 => [ 'name' => totranslate('2 - Automa the Average'),'beta' => true ],
                        3 => [ 'name' => totranslate('3 - Automa the Slightly Intimidating'),'beta' => true ],
                        4 => [ 'name' => totranslate('4 - Automa the Somewhat Awesome'),'beta' => true ],
                        5 => [ 'name' => totranslate('5 - Automa the Definitely Awesome'),'beta' => true ],
                        6 => [ 'name' => totranslate('6 - Automa the Crusher of Dreams'),'beta' => true ],
                ],
                'displaycondition' => [
                        // Note: do not display this option unless these conditions are met
                        [ 
                                'type' => 'maxplayers',
                                'value' => 1,
                                'message' => totranslate('Automa difficulty level available only for 1 player')
                                
                        ]
                ],

                'notdisplayedmessage' => totranslate('Automa difficulty level available only for 1 player'),
        
                'default' => 2
                
           
        ],
        152 => [
                'name' => totranslate('Shadow Empire'),
                'values' =>
                [
                        0 => [ 'name' => totranslate('No')],
                        1 => [ 'name' => totranslate('Yes'),'beta' => true, 'description' => totranslate('Only affects 2 player game')],
                ],
                'displaycondition' => [
                        // Note: do not display this option unless these conditions are met
                        [
                                'type' => 'maxplayers',
                                'value' => 2,
                                'message' => totranslate('Shadow Empire variant available only for 2 player games')
                                
                        ],
                        [
                                'type' => 'minplayers',
                                'value' => 2,
                                'message' => totranslate('Shadow Empire variant available only for 2 player games')
                                
                        ]
                ],
                'notdisplayedmessage' => totranslate('Shadow Empire variant available only for 2 player games'),
                'default' => 0
                
                
        ],
];
if (!defined('PREF_AUTO_CONFIRM')) { // guard since this included multiple times
    define("PREF_AUTO_CONFIRM", 150);
    define("PREFVALUE_AUTO_CONFIRM_OFF", 0);
    define("PREFVALUE_AUTO_CONFIRM_ON", 1);
    define("PREFVALUE_AUTO_CONFIRM_TIMER", 2);
    define("PREF_SHOW_STACK", 151);
}


$game_preferences = array(
        PREF_AUTO_CONFIRM => array(
                'name' => totranslate('Auto Confirm'),
                //'needReload' => true, // after user changes this preference game interface would auto-reload
                'values' => array(
                        PREFVALUE_AUTO_CONFIRM_OFF => array( 'name' => totranslate( 'No Auto Confirm' ), 'cssPref' => 'tap_confirm_manual' ),
                        PREFVALUE_AUTO_CONFIRM_ON => array( 'name' => totranslate( 'Auto Confirm' ), 'cssPref' => 'tap_confirm_auto' ),
                        PREFVALUE_AUTO_CONFIRM_TIMER => array( 'name' => totranslate( 'Auto Confirm with Timer' ), 'cssPref' => 'tap_confirm_timer' ),
                ),
                'default' => PREFVALUE_AUTO_CONFIRM_TIMER,
                'server_sync' => false
        ),
        PREF_SHOW_STACK => array(
                'name' => totranslate('Show Benefit Queue'),
                'needReload' => true, // after user changes this preference game interface would auto-reload
                'values' => array(
                        0 => array( 'name' => totranslate( 'No' ), 'cssPref' => 'show_breadcrumbs_no' ),
                        1 => array( 'name' => totranslate( 'Yes' ), 'cssPref' => 'show_breadcrumbs_yes' ),
                ),
                'default' => 0,
                'server_sync' => false
        )
);

