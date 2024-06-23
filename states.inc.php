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


$machinestates = array(

    // The initial state. Please do not modify.
    1 => array(
        "name" => "gameSetup",
        "description" => "",
        "type" => "manager",
        "action" => "stGameSetup",
        "transitions" => array("" => 2) // 2, 92 for debug
    ),
   
        92 => array( // debug state
                "name" => "startGameDebug",
                "description" => clienttranslate('${actplayer} must start the game (debug state)'),
                "descriptionmyturn" => clienttranslate('${you} must start the game (debug state)'),
                "type" => "activeplayer",
                "possibleactions" => array("actionConfirm"),
                "transitions" => array("next" => 2)
        ),

    // Note: ID=2 => your first state
    2 => array(
        "name" => "setupChoice",
        "description" => clienttranslate('Everyone must choose a civilization'),
        "descriptionmyturn" => clienttranslate('${you} must choose a civilization'),
        "type" => "multipleactiveplayer",
        "action" => "stSetupChoice",
        "possibleactions" => array("chooseCivilization"),
        "transitions" => array("next" => 3),
    ),

    3 => array(
        "name" => "finishSetup",
        "description" => "",
        "type" => "game",
        "action" => "stFinishSetup",
        "transitions" => array("next" => 18)
    ),

    12 => array(
        "name" => "transition",
        "description" => '',
        "type" => "game",
        "action" => "stTransition",
        "updateGameProgression" => true,
        "transitions" => array("endGame" => 99, "next" => 13, "benefit" => 18)
    ),

    13 => array(
        "name" => "playerTurn",
        "description" => clienttranslate('${actplayer} must advance or take income'),
        "descriptionmyturn" => clienttranslate('${you} must pick Advance or Income'),
        "action" => "stPlayerTurn",
        "type" => "activeplayer",
        "args" => "argPlayerTurn",
        "possibleactions" => array("advance", "takeIncome"),
        "transitions" => array("advance" => 18, "benefit" => 18, "next" => 18)
    ),


    // INCOME SEQUENCE

    14 => array(
        "name" => "civAbility",
        "description" => clienttranslate('${actplayer} may use their civilization ability'),
        "descriptionmyturn" => clienttranslate('${you} may use your civilization ability'),
        "type" => "activeplayer",
        "args" => "argCivAbility",
        "possibleactions" => array("civTokenAdvance", "civTokenExtra" ,"civDecline", "tapestryChoice", "sendHistorian", "mystic", "sendInventor"),
        "transitions" => array("benefit" => 18, "next" => 18)
    ),

    15 => array(
        "name" => "playTapestryCard",
        "description" => clienttranslate('${actplayer} must play a tapestry card'),
        "descriptionmyturn" => clienttranslate('${you} must play a tapestry card'),
        "action" => "stTapestryCard",
        "args" => "argTapestryCard",
        "type" => "activeplayer",
        "possibleactions" => array("playCard", "decline_tapestry", "tapestryChoice"),
        "transitions" => array("next" => 18, "benefit" => 18)
    ),

    16 => array(
        "name" => "upgradeTechnology",
        "description" => clienttranslate('${actplayer} may upgrade a technology'),
        "descriptionmyturn" => clienttranslate('${you} may upgrade a technology'),
        "action" => "stUpgradeTechnology",
        "type" => "activeplayer",
        "args" => "argUpgradeTechnology",
        "possibleactions" => array("upgrade", "decline"),
        "transitions" => array("next" => 18, "benefit" => 18)
    ),

    // ADVANCE SEQUENCES
    18 => array(
        "name" => "benefitManager",
        "description" => '',
        "type" => "game",
        "action" => "stBenefitManager",
            "transitions" => array("benefitOption" => 19, "finish" => 36,  "confirm" => 37,"explore" => 20, "invent" => 21, "conquer" => 22, 
        "research" => 25, "structure" => 26,  "explore_space" => 27, "benefitChoice" => 29, "upgradeTech" => 16, "techBenefit" => 30, "tapestryChoice" => 15, 
        "any_resource" => 31, "civReturn" => 14, "trackChoice" => 33, "trackSelect"=>33, "buildingChoice" => 34, "bonus" => 35,
                    "trap" => 23, "conquer_die" => 24,"next" => 18,"nextPlayer" => 12,
        "loopback" => 18,
                    "moveStructureOnto" => 38,
                    "keepCard" => 39,
                    "playTapestryCard"=>15
            )
    ),

    19 => array(
        "name" => "benefitOption",
        "description" => clienttranslate('${actplayer} may choose a benefit'),
        "descriptionmyturn" => clienttranslate('${you} may choose one of these benefits'),
        "type" => "activeplayer",
        "possibleactions" => array("choose_benefit"),
        "args" => "argBenefitOption",
        "transitions" => array("next" => 18)
    ),

    20 => array(
        "name" => "explore",
        "description" => clienttranslate('${actplayer} is exploring'),
        "descriptionmyturn" => clienttranslate('EXPLORE: ${you} must select an unexplored hex adjacent to a territory you control'),
        "type" => "activeplayer",
        "args" => "argExplore",
        "action" => "stExplore",
        "possibleactions" => array("explore", "ageOfSail", "colonialism", "decline"),
        "transitions" => array("next" => 18, 'loopback' => 20)
    ),

    21 => array(
        "name" => "invent",
        "description" => clienttranslate('${actplayer} is inventing'),
        "descriptionmyturn" => clienttranslate('${you} must invent'),
        "type" => "activeplayer",
        "action" => "stInvent",
        "args" => "argInvent",
        "possibleactions" => array("invent", "decline"),
        "transitions" => array("next" => 18, 'loopback'=> 21, "benefit" => 18)
    ),

    22 => array(
        "name" => "conquer",
        "description" => clienttranslate('${actplayer} is conquering'),
        "descriptionmyturn" => clienttranslate('CONQUER: ${you} must select a hex adjacent to a territory you control'),
        "type" => "activeplayer",
        "args" => "argConquer",
        "possibleactions" => array("conquer", "standup", "decline"),
        "transitions" => array("next" => 18, "standup" => 18, "decline" => 18)
    ),

    23 => array(
        "name" => "conquer_trap",
        "description" => clienttranslate('${actplayer} may play a trap card'),
        "descriptionmyturn" => clienttranslate('${you} may play a trap card'),
        "type" => "activeplayer",
        "possibleactions" => array("trap", "decline_trap"),
        "transitions" => array("next" => 18)
    ),

    24 => array(
        "name" => "conquer_roll",
        "description" => clienttranslate('${actplayer} must choose a benefit from one die'),
        "descriptionmyturn" => clienttranslate('${you} must choose a benefit from one die'),
        "type" => "activeplayer",
        "args"=>"argConquerRoll",
        "possibleactions" => array("choose_die"),
        "transitions" => array("next" => 18)
    ),

    25 => array(
        "name" => "research",  // advancement choice
        "description" => clienttranslate('${actplayer} may choose track to advance'),
        "descriptionmyturn" => clienttranslate('${you} may choose track to advance'),
        "type" => "activeplayer",
        "args" => "argResearch",
        "possibleactions" => array("research_decision","trackChoice"),
        "transitions" => array("next" => 18, "trackChoice" => 33)
    ),

    26 => array(
        "name" => "placeStructure",
        "description" => clienttranslate('${actplayer} must place a structure'),
        "descriptionmyturn" => clienttranslate('${you} must place a structure [${structure_name}]'),
        "type" => "activeplayer",
        "args" => "argPlaceStructure",
        "possibleactions" => array("place_structure", "placeCraftsmen", "conquer_structure"),
        "transitions" => array("next" => 18)
    ),

    27 => array(
        "name" => "spaceExploration",
        "description" => clienttranslate('${actplayer} must explore a space tile'),
        "descriptionmyturn" => clienttranslate('${you} must explore a space tile'),
        "type" => "activeplayer",
        "action" => "stSpaceExploration",
        "possibleactions" => array("explore_space"),
        "transitions" => array("next" => 18)
    ),

    29 => array(
        "name" => "benefitChoice",
        "description" => clienttranslate('${actplayer} must choose which benefit to take first'),
        "descriptionmyturn" => clienttranslate('${you} must choose which benefit to take first'),
        "type" => "activeplayer",
        "possibleactions" => array("first_benefit"),
        "args" => "argBenefitChoice",
        "transitions" => array("next" => 18)
    ),

    30 => array(
        "name" => "techBenefit",
        "description" => clienttranslate('${actplayer} may choose which ${circlesquare} benefit to take'),
        "descriptionmyturn" => clienttranslate('${you} may choose which ${circlesquare} benefit to take'),
        "action" => "stTechBenefit",
        "type" => "activeplayer",
        "possibleactions" => array("techBenefit"),
        "args" => "argTechBenefit",
        "transitions" => array("next" => 18)
    ),

    31 => array(
        "name" => "resourceChoice",
        "description" => clienttranslate('${actplayer} may choose their resources'),
        "descriptionmyturn" => clienttranslate('${you} may choose your resources'),
        "type" => "activeplayer",
        "possibleactions" => array("choose_resources","decline"),
        "args" => "argResourceChoice",
        "transitions" => array("next" => 18,"benefit" => 18)
    ),

    //32 used above.

    33 => array(
        "name" => "trackSelect",
        "description" => clienttranslate('${actplayer} must select a cube on an advancement track ${reason}'),
        "descriptionmyturn" => clienttranslate('${you} must select a cube on an advancement track'),
        "type" => "activeplayer",
        "args" => "argTrackSelect",
        "possibleactions" => array("selectTrackSpot", "select_cube", "formAlliance","choose_benefit","decline"),
        "transitions" => array("next" => 18 /* "trackChoice"=>33 */)
    ),

    34 => array(
        "name" => "buildingSelect",
        "description" => clienttranslate('${actplayer} may choose a building'),
        "descriptionmyturn" => clienttranslate('${you} may choose a building'),
        "type" => "activeplayer",
        "args" => "argBuildingSelect",
        "possibleactions" => array("selectBuilding","selectIncomeBuilding","selectLandmark","selectOutpost"),
        "transitions" => array("next" => 18)
    ),

    35 => array(
        "name" => "bonus",
        "description" => clienttranslate('${actplayer} may apply the bonus'),
        "descriptionmyturn" => clienttranslate('${you} may select payment for the bonus [${bonus_name}]'),
        "type" => "activeplayer",
        "args" => "argBonus",
        "action" => "stBonus",
        "possibleactions" => array("acceptBonus", "declineBonus"),
        "transitions" => array("next" => 18)
    ),
        36 => array(
                "name" => "playerTurnEnd",
                "description" => clienttranslate('${actplayer} must confirm the turn or undo'),
                "descriptionmyturn" => clienttranslate('${you} must confirm the turn or undo'),
                "action" => "stPlayerTurnEnd",
                "type" => "activeplayer",
                "args" => "argPlayerTurnEnd",
                "possibleactions" => array("actionConfirm", "actionUndo"),
                "transitions" => array("next" => 12)
        ),
        37 => array(
                "name" => "playerTurnConfirm",
                "description" => clienttranslate('${actplayer} must confirm or undo'),
                "descriptionmyturn" => clienttranslate('${you} must confirm or undo'),
                "type" => "activeplayer",
                "args" => "argReason",
                "possibleactions" => array("actionConfirm", "actionUndo"),
                "transitions" => array("next" => 18)
        ),
        38 => array(
                "name" => "moveStructureOnto",
                "description" => clienttranslate('${actplayer} is placing structure'),
                "descriptionmyturn" => clienttranslate('${you} must place a structure'),
                "type" => "activeplayer",
                "args" => "arg_moveStructureOnto",
                "possibleactions" => array("moveStructureOnto", "decline"),
                "transitions" => array("next" => 18,"decline" => 18)
        ),
        39 => array(
                "name" => "keepCard",
                "description" => clienttranslate('${actplayer} must pick a card to keep'),
                "descriptionmyturn" => clienttranslate('${you} must pick a card to keep'),
                "type" => "activeplayer",
                "args" => "arg_keepCard",
                "possibleactions" => array("keepCard"),
                "transitions" => array("next" => 18)
        ),

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    99 => array(
        "name" => "gameEnd",
        "description" => clienttranslate("End of game"),
        "type" => "manager",
        "action" => "stGameEnd",
        "args" => "argGameEnd"
    )

);
