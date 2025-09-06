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
 * states.inc.php
 *
 * tapestry game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = [
    // The initial state. Please do not modify.
    1 => [
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => ["" => 2], // 2, 92 for debug
    ],

    92 => [
        // debug state
        "name" => "startGameDebug",
        "description" => clienttranslate('${actplayer} must start the game (debug state)'),
        "descriptionmyturn" => clienttranslate('${you} must start the game (debug state)'),
        "type" => "activeplayer",
        "possibleactions" => ["actionConfirm"],
        "transitions" => ["next" => 2],
    ],

    // Note: ID=2 => your first state
    2 => [
        "name" => "setupChoice",
        "description" => clienttranslate("Everyone must choose a civilization"),
        "descriptionmyturn" => clienttranslate('${you} must choose a civilization'),
        "type" => "multipleactiveplayer",
        "action" => "stSetupChoice",
        "possibleactions" => ["chooseCivilization"],
        "transitions" => ["next" => 3],
    ],

    3 => [
        "name" => "finishSetup",
        "description" => "",
        "type" => "game",
        "action" => "stFinishSetup",
        "transitions" => ["next" => 18],
    ],

    12 => [
        "name" => "transition",
        "description" => "",
        "type" => "game",
        "action" => "stTransition",
        "updateGameProgression" => true,
        "transitions" => ["endGame" => 99, "next" => 13, "benefit" => 18],
    ],

    13 => [
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must advance or take income'),
        "descriptionmyturn" => clienttranslate('${you} must pick Advance or Income'),
        "action" => "stPlayerTurn",
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => ["advance", "takeIncome"],
        "transitions" => ["advance" => 18, "benefit" => 18, "next" => 18],
    ],

    // INCOME SEQUENCE

    14 => [
        "name" => "civAbility",
        "description" => clienttranslate('${actplayer} may use their civilization ability'),
        "descriptionmyturn" => clienttranslate('${you} may use your civilization ability'),
        "type" => "activeplayer",
        "args" => "argCivAbility",
        "possibleactions" => ["civTokenAdvance", "civDecline", "tapestryChoice", "sendHistorian", "sendInventor"],
        "transitions" => ["benefit" => 18, "next" => 18],
    ],

    15 => [
        "name" => "playTapestryCard",
        "description" => clienttranslate('${actplayer} must play a tapestry card'),
        "descriptionmyturn" => clienttranslate('${you} must play a tapestry card'),
        "action" => "stTapestryCard",
        "args" => "argTapestryCard",
        "type" => "activeplayer",
        "possibleactions" => ["playCard", "decline_tapestry", "tapestryChoice"],
        "transitions" => ["next" => 18, "benefit" => 18],
    ],

    16 => [
        "name" => "upgradeTechnology",
        "description" => clienttranslate('${actplayer} may upgrade a technology'),
        "descriptionmyturn" => clienttranslate('${you} may upgrade a technology'),
        "action" => "stUpgradeTechnology",
        "type" => "activeplayer",
        "args" => "argUpgradeTechnology",
        "possibleactions" => ["upgrade", "decline"],
        "transitions" => ["next" => 18, "benefit" => 18],
    ],

    // ADVANCE SEQUENCES
    18 => [
        "name" => "benefitManager",
        "description" => "",
        "type" => "game",
        "action" => "stBenefitManager",
        "transitions" => [
            "benefitOption" => 19,
            "finish" => 36,
            "confirm" => 37,
            "explore" => 20,
            "invent" => 21,
            "conquer" => 22,
            "research" => 25,
            "structure" => 26,
            "explore_space" => 27,
            "benefitChoice" => 29,
            "upgradeTech" => 16,
            "techBenefit" => 30,
            "tapestryChoice" => 15,
            "any_resource" => 31,
            "civReturn" => 14,
            "trackChoice" => 33,
            "trackSelect" => 33,
            "buildingChoice" => 34,
            "bonus" => 35,
            "trap" => 23,
            "conquer_die" => 24,
            "next" => 18,
            "nextPlayer" => 12,
            "loopback" => 18,
            "moveStructureOnto" => 38,
            "keepCard" => 39,
            "playTapestryCard" => 15,
        ],
    ],

    19 => [
        "name" => "benefitOption",
        "description" => clienttranslate('${actplayer} may choose a benefit'),
        "descriptionmyturn" => clienttranslate('${you} may choose one of these benefits'),
        "type" => "activeplayer",
        "possibleactions" => ["choose_benefit"],
        "args" => "argBenefitOption",
        "transitions" => ["next" => 18],
    ],

    20 => [
        "name" => "explore",
        "description" => clienttranslate('${actplayer} is exploring'),
        "descriptionmyturn" => clienttranslate('EXPLORE: ${you} must select an unexplored hex adjacent to a territory you control'),
        "type" => "activeplayer",
        "args" => "argExplore",
        "action" => "stExplore",
        "possibleactions" => ["explore", "ageOfSail", "colonialism", "decline"],
        "transitions" => ["next" => 18, "loopback" => 20],
    ],

    21 => [
        "name" => "invent",
        "description" => clienttranslate('${actplayer} is inventing'),
        "descriptionmyturn" => clienttranslate('${you} must invent'),
        "type" => "activeplayer",
        "action" => "stInvent",
        "args" => "argInvent",
        "possibleactions" => ["invent", "decline"],
        "transitions" => ["next" => 18, "loopback" => 21, "benefit" => 18],
    ],

    22 => [
        "name" => "conquer",
        "description" => clienttranslate('${actplayer} is conquering'),
        "descriptionmyturn" => clienttranslate('CONQUER: ${you} must select a hex adjacent to a territory you control'),
        "type" => "activeplayer",
        "args" => "argConquer",
        "possibleactions" => ["conquer", "standup", "decline"],
        "transitions" => ["next" => 18, "standup" => 18, "decline" => 18],
    ],

    23 => [
        "name" => "conquer_trap",
        "description" => clienttranslate('${actplayer} may play a trap card'),
        "descriptionmyturn" => clienttranslate('${you} may play a trap card'),
        "type" => "activeplayer",
        "possibleactions" => ["trap", "decline_trap"],
        "transitions" => ["next" => 18],
    ],

    24 => [
        "name" => "conquer_roll",
        "description" => clienttranslate('${actplayer} must choose a benefit from one die'),
        "descriptionmyturn" => clienttranslate('${you} must choose a benefit from one die'),
        "type" => "activeplayer",
        "args" => "argConquerRoll",
        "possibleactions" => ["choose_die"],
        "transitions" => ["next" => 18],
    ],

    25 => [
        "name" => "research", // advancement choice
        "description" => clienttranslate('${actplayer} may choose track to advance'),
        "descriptionmyturn" => clienttranslate('${you} may choose track to advance'),
        "type" => "activeplayer",
        "args" => "argResearch",
        "possibleactions" => ["research_decision", "trackChoice"],
        "transitions" => ["next" => 18, "trackChoice" => 33],
    ],

    26 => [
        "name" => "placeStructure",
        "description" => clienttranslate('${actplayer} must place a structure'),
        "descriptionmyturn" => clienttranslate('${you} must place a structure [${structure_name}]'),
        "type" => "activeplayer",
        "args" => "argPlaceStructure",
        "possibleactions" => ["place_structure", "placeCraftsmen", "conquer_structure"],
        "transitions" => ["next" => 18],
    ],

    27 => [
        "name" => "spaceExploration",
        "description" => clienttranslate('${actplayer} must explore a space tile'),
        "descriptionmyturn" => clienttranslate('${you} must explore a space tile'),
        "type" => "activeplayer",
        "action" => "stSpaceExploration",
        "possibleactions" => ["explore_space"],
        "transitions" => ["next" => 18],
    ],

    29 => [
        "name" => "benefitChoice",
        "description" => clienttranslate('${actplayer} must choose which benefit to take first'),
        "descriptionmyturn" => clienttranslate('${you} must choose which benefit to take first'),
        "type" => "activeplayer",
        "possibleactions" => ["first_benefit"],
        "args" => "argBenefitChoice",
        "transitions" => ["next" => 18],
    ],

    30 => [
        "name" => "techBenefit",
        "description" => clienttranslate('${actplayer} may choose which ${circlesquare} benefit to take'),
        "descriptionmyturn" => clienttranslate('${you} may choose which ${circlesquare} benefit to take'),
        "action" => "stTechBenefit",
        "type" => "activeplayer",
        "possibleactions" => ["techBenefit"],
        "args" => "argTechBenefit",
        "transitions" => ["next" => 18],
    ],

    31 => [
        "name" => "resourceChoice",
        "description" => clienttranslate('${actplayer} may choose their resources'),
        "descriptionmyturn" => clienttranslate('${you} may choose your resources'),
        "type" => "activeplayer",
        "possibleactions" => ["choose_resources", "decline"],
        "args" => "argResourceChoice",
        "transitions" => ["next" => 18, "benefit" => 18],
    ],

    //32 used above.

    33 => [
        "name" => "trackSelect",
        "description" => clienttranslate('${actplayer} must select a cube on an advancement track ${reason}'),
        "descriptionmyturn" => clienttranslate('${you} must select a cube on an advancement track'),
        "type" => "activeplayer",
        "args" => "argTrackSelect",
        "possibleactions" => ["selectTrackSpot", "select_cube", "formAlliance", "choose_benefit", "decline"],
        "transitions" => ["next" => 18 /* "trackChoice"=>33 */],
    ],

    34 => [
        "name" => "buildingSelect",
        "description" => clienttranslate('${actplayer} may choose a building'),
        "descriptionmyturn" => clienttranslate('${you} may choose a building'),
        "type" => "activeplayer",
        "args" => "argBuildingSelect",
        "possibleactions" => ["selectBuilding", "selectIncomeBuilding", "selectLandmark", "selectOutpost"],
        "transitions" => ["next" => 18, "benefit" => 18],
    ],

    35 => [
        "name" => "bonus",
        "description" => clienttranslate('${actplayer} may apply the bonus'),
        "descriptionmyturn" => clienttranslate('${you} may select payment for the bonus [${bonus_name}]'),
        "type" => "activeplayer",
        "args" => "argBonus",
        "action" => "stBonus",
        "possibleactions" => ["acceptBonus", "declineBonus"],
        "transitions" => ["next" => 18, "benefit" => 18],
    ],
    36 => [
        "name" => "playerTurnEnd",
        "description" => clienttranslate('${actplayer} must confirm the turn or undo'),
        "descriptionmyturn" => clienttranslate('${you} must confirm the turn or undo'),
        "action" => "stPlayerTurnEnd",
        "type" => "activeplayer",
        "args" => "argPlayerTurnEnd",
        "possibleactions" => ["actionConfirm", "actionUndo"],
        "transitions" => ["next" => 12],
    ],
    37 => [
        "name" => "playerTurnConfirm",
        "description" => clienttranslate('${actplayer} must confirm or undo'),
        "descriptionmyturn" => clienttranslate('${you} must confirm or undo'),
        "type" => "activeplayer",
        "args" => "argReason",
        "possibleactions" => ["actionConfirm", "actionUndo"],
        "transitions" => ["next" => 18],
    ],
    38 => [
        "name" => "moveStructureOnto",
        "description" => clienttranslate('${actplayer} is placing structure'),
        "descriptionmyturn" => clienttranslate('${you} must place a structure'),
        "type" => "activeplayer",
        "args" => "arg_moveStructureOnto",
        "possibleactions" => ["moveStructureOnto", "decline"],
        "transitions" => ["next" => 18, "decline" => 18],
    ],
    39 => [
        "name" => "keepCard",
        "description" => clienttranslate('${actplayer} must pick a card to keep'),
        "descriptionmyturn" => clienttranslate('${you} must pick a card to keep'),
        "type" => "activeplayer",
        "args" => "arg_keepCard",
        "possibleactions" => ["keepCard"],
        "transitions" => ["next" => 18],
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => [
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd",
    ],
];
