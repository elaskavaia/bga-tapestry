{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- tapestry implementation : © Adam Dewbery <adam@dewbs.co.uk>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

    tapestry_tapestry.tpl
    
    This is the HTML template of your game.
    
    Everything you are writing in this file will be displayed in the HTML page of your game user interface,
    in the "main game zone" of the screen.
    
    You can use in this template:
    _ variables, with the format {MY_VARIABLE_ELEMENT}.
    _ HTML block, with the BEGIN/END format
    
    See your "view" PHP file to check how to set variables and control blocks
    
    Please REMOVE this comment before publishing your game on BGA
-->
<script type="text/javascript">

// Javascript HTML templates

/*
// Example:
var jstpl_some_game_item='<div class="my_game_item" id="my_game_item_${MY_ITEM_ID}"></div>';

*/
var jstpl_tech_track='<div class="tech_track" id="tech_track_${ttid}" style="top: ${top}%; left: ${left}%; transform: rotate(${rot}deg);"></div>';
var jstpl_tech_age='<div class="tech_age" id="tech_age_${ttid}_${aid}" style="width: ${width}%;"></div>';
var jstpl_tech_upgrade='<div class="tech_upgrade" id="tech_upgrade_${ttid}_${uid}"></div>';
var jstpl_tech_spot='<div class="tech_spot" id="tech_spot_${ttid}_${sid}"></div>';
var jstpl_building='<div class="building building${type}" id="building_${bid}"></div>';
var jstpl_landmark='<div class="landmark landmark${lid} rot${rot}" id="landmark_${lid}"></div>';
var jstpl_resource_token='<div class="resource resource${type} restype_${type}" id="resource_${rid}" data-restype="${type}"></div>';
var jstpl_cube='<div class="cube cube${type} cube_${type}" id="cube_${cid}"></div>';
var jstpl_cube_holder='<div class="cube_holder ${chid}" id="${chid}"></div>';
var jstpl_outpost='<div class="outpost outpost${type}" id="outpost_${oid}"></div>';
var jstpl_civilization='<div class="civilization civilization_${cid}" id="civilization_${cid}"></div>';
var jstpl_tapestry_card='<div class="tapestry_card card tapestry_${type}" id="tapestry_${tid}"></div>';
var jstpl_land='<div class="land_wrapper" id="land_wrapper_${lid}" style="top: ${top}%; left: ${left}%;"><div class="land" id="land_${lid}"><div id="land_${lid}_1" class="land_slot1 land_slot"></div><div id="land_${lid}_2" class="land_slot2 land_slot"></div></div></div>';
var jstpl_land_other='<div class="land_wrapper ${type}_wrapper" id="${type}_wrapper_${lid}" style="top: ${top}%; left: ${left}%;"><div class="land ${type}" id="${type}_${lid}"></div></div>';
var jstpl_territory='<div class="territory_tile territory_tile_${tid}" id="territory_${tid}"></div>';
var jstpl_space='<div class="space_tile space_tile_${tid}" id="space_tile_${tid}"></div>';
var jstpl_payment_panel='<div id="payment_panel"><h3>${title}</h3><div class="payment_line" id="payment_line"></div><div id="payment_buttons"><div id="payment_cancel" class="bgabutton bgabutton_blue"></div><div id="payment_confirm" class="bgabutton bgabutton_blue"></div></div></div>';
var jstpl_resource_line='<div class="payment_line resource_line" id="resource_line"></div>';
var jstpl_territory_panel='<div id="territory_panel"><h3>${title}</h3><div id="territory_select"></div><div id="territory_buttons"><div id="territory_cancel" class="bgabutton bgabutton_blue"></div></div></div>';
var jstpl_capital_cell='<div id="capital_cell_${cid}" class="capital_cell" style="left:${left}px; top: ${top}px;"></div>';
var jstpl_resource_zone='<div id="resource_zone_${rzid}" class="resource_zone"><div id="resource_holder_${rzid}_1" class="resource_holder resource_holder1"></div><div id="resource_holder_${rzid}_2" class="resource_holder resource_holder2"></div><div id="resource_holder_${rzid}_3" class="resource_holder resource_holder3"></div><div id="resource_holder_${rzid}_4" class="resource_holder resource_holder4"></div>';
var jstpl_income_wrapper='<div id="income_wrapper_${wid}" class="income_wrapper"></div>';
var jstpl_cancel='<div id="icon_cancel_${cid}" class="icon_cancel"></div>';
var jstpl_decision='<div id="decision_${cid}" class="decision decision_${cid}"></div>';
var jstpl_automa_civ='<div id="automaciv_${cid}" class="automaciv automaciv_${cid}"></div>';
</script>

<div id="setup_choices">
    <div id="civilizations"></div>
    <div id="capitals"></div>
</div>

<div id="breadcrumbs" class="breadcrumbs"></div>

<div id="zoom-wrapper" class="zoom-wrapper">
<div id="draw" class="draw"></div>
<div id="thething" class="thething">
<div id="top_selector">
                <div id="capital_helper">
                    <div id="capital_structure" class="capital_structure"></div>
                    <div id="capital_rotator" class="helper_button"></div>
                    <div id="capital_confirm" class="helper_button"></div>
                    <div id="capital_cancel" class="helper_button"></div>
                </div>
</div>
<div id="game_wrapper">

    <div id="accessories">

        <div id="decks">
            <div id="civilization_deck"></div>
            <div id="tech_deck_visible">
                 <div id='tech_deck' class='card tech_card tech_card_0 tech_deck'></div>
                 <div id='tech_discard' class='card tech_card_0 tech_discard'></div>
            </div>
            <div id="tapestry_deck" class="tapestry_deck"></div>
            <div id="territory_deck"></div>
            <div id="space_deck" class="space_deck"></div>
            <div id="tokens"></div>
        </div>
    </div>
    <div id="board">
        <div id="selected_tile" class="tile_selected">
            <div id="rotator_left" class="rotate_left"></div>
            <div id="rotator_right" class="rotate_right"></div>
            <div id="icon_holder" class="icon_holder">
                <div id="icon_confirm" class="icon_confirm"></div>
                <div id="icon_cancel" class="icon_cancel"></div>
            </div>
        </div>

			<div class="die-scene" id="die_scene">
			   <div class="die_wrapper">
				<div id="black_die" class="die shape black">
					<div class="die-face  die-face-front"></div>
					<div class="die-face  die-face-back"></div>
					<div class="die-face  die-face-left"></div>
					<div class="die-face  die-face-right"></div>
					<div class="die-face  die-face-top"></div>
					<div class="die-face  die-face-bottom"></div>
				</div>
				</div>
				<div class="die_wrapper">
				<div id="red_die" class="die shape red">
					<div class="die-face  die-face-front"></div>
					<div class="die-face  die-face-back"></div>
					<div class="die-face  die-face-left"></div>
					<div class="die-face  die-face-right"></div>
					<div class="die-face  die-face-top"></div>
					<div class="die-face  die-face-bottom"></div>
				</div>
				</div>
			</div>

			<div id="dice_holder2">
				<div id="science_die" class="science_die">
					<div id="science_die_3d" class="die12-wrapper camera">
						<div class="die12 die">
							<div class="die12-facebox1 die12-facebox">
								<div class="face1 die12-face"></div>
								<div class="face2 die12-face"></div>
								<div class="face3 die12-face"></div>
								<div class="face4 die12-face"></div>
								<div class="face5 die12-face"></div>
								<div class="face6 die12-face"></div>
							</div>
							<div class="die12-facebox2 die12-facebox">
								<div class="face1 die12-face"></div>
								<div class="face2 die12-face"></div>
								<div class="face3 die12-face"></div>
								<div class="face4 die12-face"></div>
								<div class="face5 die12-face"></div>
								<div class="face6 die12-face"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="achievement_holder">
            <div id="achievement_1" class="achievement">
                <div id="achievement_1_1" class="cube_holder achievement1"></div>
                <div id="achievement_1_2" class="cube_holder achievement2"></div>
                <div id="achievement_1_3" class="cube_holder achievement3"></div>
            </div>
            <div id="achievement_2" class="achievement">
                <div id="achievement_2_1" class="cube_holder achievement1"></div>
                <div id="achievement_2_2" class="cube_holder achievement2"></div>
                <div id="achievement_2_3" class="cube_holder achievement3"></div>
            </div>
            <div id="achievement_3" class="achievement">
                <div id="achievement_3_1" class="cube_holder achievement1"></div>
                <div id="achievement_3_2" class="cube_holder achievement2"></div>
            </div>
        </div>
    </div>
    <button id="rotator_board2" class="fa fa-repeat fa-2x config-control rotator-control"></button>
</div>
<div id="playerArea_wrapper" class="playerArea_wrapper">
<!-- BEGIN playerArea -->
    <div id="playerArea_{X}" class="playerArea">
     
        <div id="playerBoard_{X}" class="playerBoard">
			<div id="miniboard_{X}" class="miniboard">
			        <div class="icon_era counter counter_card" id="counter_era_{X}"></div>
					<div class="icon_coins counter counter_resource"
						id="counter_resource_1_{X}"></div>
					<div class="icon_worker counter counter_resource"
						id="counter_resource_2_{X}"></div>
					<div class="icon_food counter counter_resource"
						id="counter_resource_3_{X}"></div>
					<div class="icon_culture counter counter_resource"
						id="counter_resource_4_{X}"></div>
					<div class="icon_tapestry counter counter_card" id="counter_tapestry_{X}"></div>
                  
                    <div class="icon_terra counter counter_card" id="counter_terra_{X}"></div>
                    <div class="icon_space counter counter_card" id="counter_space_{X}"></div>
			
			</div>
			<div id="civilization_holder_{X}" class="civilization_holder"></div>
            <div id="income_mat_{X}" class="income_mat">
                <div id="income_help_{X}" class="income_help"></div>
                <div id="income_track_{X}_1" class="income_track income_track1">
                    <div id="income_track_{X}_1_1" class="income_track_space income_track_1_1"></div>
                    <div id="income_track_{X}_1_2" class="income_track_space income_track_1_2"></div>
                    <div id="income_track_{X}_1_3" class="income_track_space income_track_1_3"></div>
                    <div id="income_track_{X}_1_4" class="income_track_space income_track_1_4"></div>
                    <div id="income_track_{X}_1_5" class="income_track_space income_track_1_5"></div>
                    <div id="income_track_{X}_1_6" class="income_track_space income_track_1_6"></div>
                </div>
                <div id="income_track_{X}_2" class="income_track income_track2">
                    <div id="income_track_{X}_2_1" class="income_track_space income_track_2_1"></div>
                    <div id="income_track_{X}_2_2" class="income_track_space income_track_2_2"></div>
                    <div id="income_track_{X}_2_3" class="income_track_space income_track_2_3"></div>
                    <div id="income_track_{X}_2_4" class="income_track_space income_track_2_4"></div>
                    <div id="income_track_{X}_2_5" class="income_track_space income_track_2_5"></div>
                    <div id="income_track_{X}_2_6" class="income_track_space income_track_2_6"></div>
                </div>
                <div id="income_track_{X}_3" class="income_track income_track3">
                    <div id="income_track_{X}_3_1" class="income_track_space income_track_3_1"></div>
                    <div id="income_track_{X}_3_2" class="income_track_space income_track_3_2"></div>
                    <div id="income_track_{X}_3_3" class="income_track_space income_track_3_3"></div>
                    <div id="income_track_{X}_3_4" class="income_track_space income_track_3_4"></div>
                    <div id="income_track_{X}_3_5" class="income_track_space income_track_3_5"></div>
                    <div id="income_track_{X}_3_6" class="income_track_space income_track_3_6"></div>
                </div>
                <div id="income_track_{X}_4" class="income_track income_track4">
                    <div id="income_track_{X}_4_1" class="income_track_space income_track_4_1"></div>
                    <div id="income_track_{X}_4_2" class="income_track_space income_track_4_2"></div>
                    <div id="income_track_{X}_4_3" class="income_track_space income_track_4_3"></div>
                    <div id="income_track_{X}_4_4" class="income_track_space income_track_4_4"></div>
                    <div id="income_track_{X}_4_5" class="income_track_space income_track_4_5"></div>
                    <div id="income_track_{X}_4_6" class="income_track_space income_track_4_6"></div>
                </div>
                <div id="pb_{X}" class="player_board_income"></div>
                <div id="tapestry_slot_{X}_1" class="tapestry_slot tapestry_slot1"></div>
                <div id="tapestry_slot_{X}_2" class="tapestry_slot tapestry_slot2"></div>
                <div id="tapestry_slot_{X}_3" class="tapestry_slot tapestry_slot3"></div>
                <div id="tapestry_slot_{X}_4" class="tapestry_slot tapestry_slot4"></div>
                <div id="tapestry_slot_{X}_6" class="tapestry_slot tapestry_slot6"></div>
                <div id="resource_track_{X}" class="resource_track"></div>
            </div>
            <div class="capital_wrapper">
            <div id="capital_mat_{X}" class="capital">
                <span id="player_mat_title_{X}" class="player-name player-title player-{C}" style="color: #{C}">{PLAYER_NAME}</span>
                <div id="capital_grid_{X}" class="capital_grid"></div>
            </div>
            <div id="tech_holder_{X}" class="tech_holders">             
                <div id="tech_holder_{X}_2" class="tech_slot tech_slot_2"></div>
                <div id="tech_holder_{X}_1" class="tech_slot tech_slot_1"></div>
                <div id="tech_holder_{X}_0" class="tech_slot tech_slot_0"></div>
            </div>
            </div>
        </div>
        <div id="player_extras_{X}" class="player_extra hand">
            <div id="tapestry_cards_{X}" class="tapestry_cards"></div>
            <div id="territory_tiles_{X}" class="territory_tiles"></div>
            <div id="space_tiles_{X}" class="space_tiles"></div>
            <div id="space_explored_{X}" class="space_explored"></div>
        </div>
    </div>
<!-- END playerArea -->
</div>
    <div id="game_wrapper_bottom">
        <div id="landmark_mat" class="landmark_mat">
            <div id="landmark_mat_slot1" class="plot3_2 landmark_slot"></div>
            <div id="landmark_mat_slot2" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot3" class="plot4_2 landmark_slot"></div>
            <div id="landmark_mat_slot4" class="plot3_3 landmark_slot"></div>
            <div id="landmark_mat_slot5" class="plot3_3 landmark_slot"></div>
            <div id="landmark_mat_slot6" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot7" class="plot3_2 landmark_slot"></div>
            <div id="landmark_mat_slot8" class="plot4_4 landmark_slot"></div>
            <div id="landmark_mat_slot9" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot10" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot11" class="plot2_3 landmark_slot"></div>
            <div id="landmark_mat_slot12" class="plot3_3 landmark_slot"></div>
        </div>
        <div id="landmark_extra" class="landmark_extra">
            <div id="landmark_mat_slot13" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot14" class="plot3_3 landmark_slot"></div>
            <div id="landmark_mat_slot15" class="plot3_2 landmark_slot"></div>
            <div id="landmark_mat_slot16" class="plot2_4 landmark_slot"></div>
            <div id="landmark_mat_slot17" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot18" class="plot2_2 landmark_slot"></div>
            <div id="landmark_mat_slot19" class="plot2_2 landmark_slot hidden"></div>
        </div>
            <div id="income_mat_automa" class="income_mat_automa" data-level="2">
                <div id="slot_automa_civ"></div>
                <div id="slot_automa_level" class="automalevel" ></div>
                <div id="slot_automa_landmark"></div>
                <div id="tapestry_slot_1_1" class="tapestry_slot tapestry_slot1"></div>
                <div id="tapestry_slot_1_2" class="tapestry_slot tapestry_slot2"></div>
                <div id="tapestry_slot_1_3" class="tapestry_slot tapestry_slot3"></div>
                <div id="tapestry_slot_1_4" class="tapestry_slot tapestry_slot4"></div>
                <div id="tapestry_slot_1_6" class="tapestry_slot tapestry_slot6"></div>
            </div>
            <div id="decision_pair" class="decision_pair"></div>
       
            <div id="deck_decision" class="deck_decision deck">
                 <div id="discard_decision" class="discard discard_decision"></div>
            </div>
            <div id="deck_progress" class="deck_progress deck"></div>
    </div>
    <div id="allcards" class="allcards">
        <div id="allcards_tech" class="allcards_tech expandable">
            <div class="expandabletitle">	
				<a href="#" id="allcards_tech_toggle" class="expandabletoggle expandablearrow">
					<div class="icon20 icon20_expand"></div>
                    <span id="allcards_tech_title"></span>
				</a>
		   </div>
            <div class="expandablecontent "  id="allcards_tech_content">
            </div>
        </div>
        <div id="allcards_tap" class="allcards_tap expandable">
            <div class="expandabletitle">	
				<a href="#" id="allcards_tap_toggle" class="expandabletoggle expandablearrow">
					<div class="icon20 icon20_expand"></div>
                    <span id="allcards_tap_title"></span>
				</a>
		   </div>
            <div class="expandablecontent "  id="allcards_tap_content">
            </div>
        </div>
        <div id="allcards_civ" class="allcards_civ expandable">
            <div class="expandabletitle">	
				<a href="#" id="allcards_civ_toggle" class="expandabletoggle expandablearrow">
					<div class="icon20 icon20_expand"></div>
                    <span id="allcards_civ_title"></span>
				</a>
		   </div>
            <div class="expandablecontent "  id="allcards_civ_content">
            </div>
        </div>
        
       <div id="allcards_terr" class="allcards_terr expandable">
				<div class="expandabletitle">
				<a href="#" id="allcards_terr_toggle" class="expandabletoggle expandablearrow">
					<div class="icon20 icon20_expand"></div>
					<span id="allcards_terr_title"></span>
				</a>
				</div>

				<div class="expandablecontent "  id="allcards_terr_content">
            </div>
        </div>
               <div id="allcards_space" class="allcards_space expandable">
				<div class="expandabletitle">
				<a href="#" id="allcards_space_toggle" class="expandabletoggle expandablearrow">
					<div class="icon20 icon20_expand"></div>
					<span id="allcards_space_title"></span>
				</a>
				</div>

				<div class="expandablecontent "  id="allcards_space_content">
            </div>
        </div>
    </div>
	<div id="limbo" class="limbo">
		<!-- tech cards -->

		<div id='tech_card_1' class='card tech_card tech_card_1'></div>
		<div id='tech_card_2' class='card tech_card tech_card_2'></div>
		<div id='tech_card_3' class='card tech_card tech_card_3'></div>
		<div id='tech_card_4' class='card tech_card tech_card_4'></div>
		<div id='tech_card_5' class='card tech_card tech_card_5'></div>
		<div id='tech_card_6' class='card tech_card tech_card_6'></div>
		<div id='tech_card_7' class='card tech_card tech_card_7'></div>
		<div id='tech_card_8' class='card tech_card tech_card_8'></div>
		<div id='tech_card_9' class='card tech_card tech_card_9'></div>
		<div id='tech_card_10' class='card tech_card tech_card_10'></div>
		<div id='tech_card_11' class='card tech_card tech_card_11'></div>
		<div id='tech_card_12' class='card tech_card tech_card_12'></div>
		<div id='tech_card_13' class='card tech_card tech_card_13'></div>
		<div id='tech_card_14' class='card tech_card tech_card_14'></div>
		<div id='tech_card_15' class='card tech_card tech_card_15'></div>
		<div id='tech_card_16' class='card tech_card tech_card_16'></div>
		<div id='tech_card_17' class='card tech_card tech_card_17'></div>
		<div id='tech_card_18' class='card tech_card tech_card_18'></div>
		<div id='tech_card_19' class='card tech_card tech_card_19'></div>
		<div id='tech_card_20' class='card tech_card tech_card_20'></div>
		<div id='tech_card_21' class='card tech_card tech_card_21'></div>
		<div id='tech_card_22' class='card tech_card tech_card_22'></div>
		<div id='tech_card_23' class='card tech_card tech_card_23'></div>
		<div id='tech_card_24' class='card tech_card tech_card_24'></div>
		<div id='tech_card_25' class='card tech_card tech_card_25'></div>
		<div id='tech_card_26' class='card tech_card tech_card_26'></div>
		<div id='tech_card_27' class='card tech_card tech_card_27'></div>
		<div id='tech_card_28' class='card tech_card tech_card_28'></div>
		<div id='tech_card_29' class='card tech_card tech_card_29'></div>
		<div id='tech_card_30' class='card tech_card tech_card_30'></div>
		<div id='tech_card_31' class='card tech_card tech_card_31'></div>
		<div id='tech_card_32' class='card tech_card tech_card_32'></div>
		<div id='tech_card_33' class='card tech_card tech_card_33'></div>

			<div id="player_board_config">
				<div id="player_config">
					<div id="player_config_row">
						<button id="rotator_board" class="fa fa-repeat fa-2x config-control rotator-control"></button>

						<button id="zoom-out" class=" fa fa-search-minus fa-2x config-control"></button>
						<button id="zoom-in" class=" fa fa-search-plus fa-2x config-control"></button>
						
						<button id="help-mode-switch" class="fa fa-question-circle fa-2x config-control help-mode-switch"></button>

						<button id="show-settings" class="config-control fa fa-cog fa-2x">	</button>
					</div>
					<div class='settingsControlsHidden'
						id="settings-controls-container">
					</div>
				</div>
			</div>

		</div>
    <div id="oversurface"></div>
</div>
</div>

{OVERALL_GAME_FOOTER}
