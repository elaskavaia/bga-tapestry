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
 * tapestry.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in tapestry_tapestry.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
  
  require_once( APP_BASE_PATH."view/common/game.view.php" );
  
  class view_tapestry_tapestry extends game_view
  {
    function getGameName() {
        return "tapestry";
    }    
  	function build_page( $viewArgs )
  	{		
  	    // Get players & players number
        $players = $this->game->loadPlayersBasicInfosWithBots();
   

        /*********** Place your code below:  ************/


        

   
        $g_player_id = $this->game->getCurrentPlayerId();

        if( isset( $players[ $g_player_id ] ) )
        {
            // Order to display boards
            $player_order = array($g_player_id);
            $player_order_beforeplayer = array();
            $bBeforePlayer = true;
            foreach( $players as $player )
            {
                if( $player['player_id'] == $g_player_id )
                    $bBeforePlayer = false;
                else if( $bBeforePlayer )
                    $player_order[] = $player['player_id']; // Push at the end
                else if( ! $bBeforePlayer )
                    $player_order_beforeplayer[] = $player['player_id']; // Push at the end
            }

            $player_order = array_merge( $player_order_beforeplayer, $player_order );
        }
        else
        {
            $player_order = array_keys( $players );
        }

        $this->page->begin_block( "tapestry_tapestry", "playerArea" );
            // Put the current player's board first.
        if (isset($players [$g_player_id])) {
            $player = $players [$g_player_id];
            $this->page->insert_block("playerArea", array ("PLAYER_NAME" => $player ['player_name'],
                    "X" => $player ['player_id'],"C" => $player ['player_color'] ));
        }
        foreach ( $player_order as $player_id ) {
            $player = $players [$player_id];
            if ($g_player_id != $player_id) {
                $this->page->insert_block("playerArea", array ("PLAYER_NAME" => $player ['player_name'],
                        "X" => $player ['player_id'],"C" => $player ['player_color'] ));
            }
        }

        /*********** Do not change anything below this line  ************/
  	}
  }
  

