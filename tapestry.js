const { coords, xhr } = require("dojo/main");

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * tapestry implementation : © Adam Dewbery <adam@dewbs.co.uk>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * tapestry.js
 *
 * Tapestry user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */
var SYM_RIGHTARROW = " &rarr; ";

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "ebg/stock",
  g_gamethemeurl + "modules/tapantistock.js"
], function (dojo, declare) {
  return declare("bgagame.tapestry", ebg.core.gamegui, {
    constructor: function () {
      console.log("tapestry constructor");

      // Here, you can init the global variables of your user interface
      // Example:
      // this.myGlobalValue = 0;
      this.defaultAnimationDuration = 500;
      //this.landscape = true;
      //this.portrait = false;
      //this.playzoneCoords = null;
      //this.gameboardCoords = null;
      //this.accessoryCoords = null;
      //this.ratio = 1;
      //this.techtrack_ratio = 0.8;
      this.board_side = "";

      this.tapestry = [];
      this.territory = [];
      this.space = [];

      this.cap_choice = ebg.stock();
      this.tech_track = [];
      this.tech_track_types = [];

      // DATA
      this.tech_track_data = [];
      this.income_track_data = [];
      this.tapestry_data = [];
      this.landmark_data = [];

      this.paymentPopup = null;
      this.territoryPopup = null;
      //exploring
      this.selectedland = null;
      this.selectedTile = null;
      this.selectedRot = 0;
      this.conquerSlot = null;
      // Adding structure to capital
      this.structure_id = null;
      this.capitalRot = 0;
      this.capitalx = null;
      this.capitaly = null;
      this.capitalRotOptions = [];
      this.resourceCount = 0;

      this.bonusdata = [];
      // Log
      this.lastMoveId = null;
      this.rejectCount = 0;
    },

    /*
				setup:
			    
				This method must set up the game user interface according to current game situation specified
				in parameters.
			    
				The method is called each time the game interface is displayed to a player, ie:
				_ when the game starts
				_ when a player refreshes the game page (F5)
			    
				"gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
			*/

    setup: function (gamedatas) {
      console.log("Starting game setup tapestry", gamedatas);
      this.inSetup = true;
      try {
        var strzoom = localStorage.getItem("tapestry_zoom");
        if (!strzoom) strzoom = 1;
        this.zoom = Number(strzoom);
        this.setZoom(this.zoom);

        // add button to disable zombie header

        if ($("neutralized_explanation")) {
          dojo.create(
            "div",
            {
              class: "bgabutton bgabutton_blue",
              innerHTML: "hide",
              id: "zombiehide"
            },
            "neutralized_game_panel",
            "last"
          );
          this.connect($("zombiehide"), "onclick", (e) => {
            dojo.style("neutralized_game_panel", "display", "none");
          });
        }

        this.clientStateArgs = {
          action: "none"
        };

        var player_count = 0;
        for (var player_id in gamedatas.players) {
          player_count++;
          if (this.player_color === undefined) this.player_color = this.gamedatas.players[player_id].color;
        }

        if (this.gamedatas.players[this.player_id]) {
          this.player_color = this.gamedatas.players[this.player_id].color;
        }

        for (i in this.gamedatas.variants) {
          dojo.addClass("ebd-body", `${i}_${this.gamedatas.variants[i]}`);
        }

        // Load correct board
        if (player_count > 3) {
          this.dontPreloadImage("board_small.jpg");
          this.board_side = "board_large";
          this.setupLand(4);
        } else {
          this.dontPreloadImage("board_large.jpg");
          this.board_side = "board_small";
          this.setupLand(3);
        }
        dojo.addClass("board", this.board_side);

        //const alevel = this.this.getAdjustmentLevel();
        this.CON = gamedatas.constants;
        this.civilizations = gamedatas.card_types[this.CON.CARD_CIVILIZATION].data;
        this.tapestry_data = gamedatas.card_types[this.CON.CARD_TAPESTRY].data;
        this.tech_card_data = gamedatas.card_types[this.CON.CARD_TECHNOLOGY].data;
        this.tech_track_types = gamedatas.tech_track_types;
        this.tech_track_data = gamedatas.tech_track_data;
        this.income_track_data = gamedatas.income_track_data;
        this.landmark_data = gamedatas.landmark_data;

        this.benefitQueueList = gamedatas.benefitQueueList;
        this.benefit_types = gamedatas.benefit_types;
        //{};
        //for (var key in gamedatas.constants) {
        //	this.CON[key] = parseInt(gamedatas.constants[key]);
        //}

        if (gamedatas.variants.shadow_in_play) {
          let automa_level = gamedatas.variants.automa_level;
          dojo.addClass("ebd-body", "shadow_in_play");
          dojo.place("player_mat_title_2", "playerBoard_2", "first");
          dojo.place("pb_2", "player_extras_2", "last");
          if (gamedatas.variants.automa_in_play) {
            dojo.place("deck_decision", "playerBoard_1", "last");
            dojo.place("decision_pair", "playerBoard_1", "last");
            dojo.setAttr("income_mat_automa", "data-level", automa_level);
            dojo.addClass("ebd-body", "automa_in_play");
            dojo.place("income_mat_automa", "civilization_holder_1", "after");
            dojo.place("player_mat_title_1", "income_mat_automa", "first");
            var player_data = gamedatas.players[1];
            var hand = player_data["hand"];

            var civ = player_data["automa_civ"];
            var div = dojo.place(this.format_block("jstpl_automa_civ", { cid: civ }), "slot_automa_civ");
            this.addTooltipForToken("automaciv", civ, div.id);
            dojo.place("pb_1", "slot_automa_landmark", "after");
          } else {
            dojo.place("decision_pair", "playerBoard_2", "last");
            dojo.place("income_mat_automa", "limbo");
          }

          this.addTooltip("deck_decision", _("Decision Cards (shown number of cards in Deck/Discard)"), "");

          for (var tid in gamedatas["decision_pair"]) {
            var card = gamedatas["decision_pair"][tid];
            this.placeCard(card, 1);
          }
        } else {
          dojo.destroy("income_mat_automa");
        }

        if (gamedatas.setup != null) {
          // SETUP CHOICE - CIVS
          for (var sid in gamedatas.setup.civilizations) {
            var civ = gamedatas.setup.civilizations[sid];
            var civ_id = civ.card_type_arg;
            var div = this.setupCivCard(civ);
            this.connect(div, "onclick", (event) => {
              dojo.query("#civilizations .selected").removeClass("selected");
              dojo.addClass(event.target, "selected");
            });

            if (civ.card_location == "hand") {
              dojo.addClass(div, "selected");
            }
          }

          // SETUP CHOICE - CAPITALS
          if (player_count < 4) {
            this.cap_choice.create(this, $("capitals"), 380, 420);
            this.cap_choice.image_items_per_row = 6;
            this.cap_choice.autowidth = true;
            this.cap_choice.selectable = 1;
            this.cap_choice.selectionApparance = "class";
            this.cap_choice.jstpl_stock_item =
              '<div id="${id}" class="stockitem" style="top:${top}px;left:${left}px;width:${width}px;height:${height}px;z-index:${position};background-image:url(\'${image}\');background-size:600% 100%;border-radius: 5px;"></div>';
            for (var type = 1; type <= 6; type++) {
              this.cap_choice.addItemType(type, type, g_gamethemeurl + "img/capitals.png", type - 1);
            }
            for (var sid in gamedatas.setup.capitals) {
              var cap = gamedatas.setup.capitals[sid];
              this.cap_choice.addToStockWithId(cap.card_type_arg, cap.card_type_arg);
              if (cap.card_location == "hand") {
                this.cap_choice.selectItem(cap.card_type_arg);
              }
            }
            this.cap_choice.centerItems = true;
          }
        }

        const tapgame = this;
        dojo.extend(bgagame.tapantistock, {
          slide: dojo.hitch(tapgame, "placeToken")
        });

        // card manager (hold antistocks per card type)
        this.cardsman = [];

        stock = this.cardsman[this.CON.CARD_TERRITORY] = new bgagame.tapantistock("territory").setAttribute("discard", "territory_deck");
        stock.onItemCreate = dojo.hitch(this, "setupNewTerritoryTile");

        stock = this.cardsman[this.CON.CARD_SPACE] = new bgagame.tapantistock("space").setAttribute("discard", "space_deck");
        stock.onItemCreate = dojo.hitch(this, "setupNewSpaceTile");

        stock = this.cardsman[this.CON.CARD_TAPESTRY] = new bgagame.tapantistock("tapestry").setAttribute("discard", "tapestry_deck");
        stock.onItemCreate = dojo.hitch(this, "setupNewTapestryCard");

        // TECH CARDS

        this.moveCards(this.player_id, gamedatas.tech_deck_visible);

        // LANDMARKS
        try {
          for (lid in gamedatas.landmarks) {
            var landmark = gamedatas.landmarks[lid];
            dojo.place(this.format_block("jstpl_building", { type: landmark.card_type, bid: landmark.card_id }), landmark.card_location);
            dojo.addClass("building_" + landmark.card_id, "landmark landmark" + landmark.card_location_arg2);
            this.addTooltipForToken("landmark", landmark.card_location_arg2, "building_" + landmark.card_id);
          }
        } catch (e) {
          console.error(e);
        }

        // TECH TRACKS
        for (var ttid in gamedatas.tech_track_types) {
          this.tech_track[ttid] = [];
          var tech_track = gamedatas.tech_track_types[ttid];
          dojo.place(
            this.format_block("jstpl_tech_track", { ttid: ttid, top: tech_track.top, left: tech_track.left, rot: tech_track.rot }),
            "board"
          );
          for (var age = 0; age < 5; age++) {
            var w = age == 0 ? 10.85 : 22.25;
            dojo.place(this.format_block("jstpl_tech_age", { ttid: ttid, aid: age, width: w }), "tech_track_" + ttid);
          }
          dojo.place(
            this.format_block("jstpl_tech_spot", { ttid: ttid, sid: 0, width: 40, height: 35, top: 60 }),
            "tech_age_" + ttid + "_0"
          );
          dojo.connect($("tech_spot_" + ttid + "_0"), "onclick", this, "onAdvance");

          for (var upgrade = 1; upgrade <= 12; upgrade++) {
            var aid = Math.floor((upgrade + 2) / 3);
            var inage = (upgrade - 1) % 3;
            dojo.place(this.format_block("jstpl_tech_upgrade", { ttid: ttid, uid: upgrade }), "tech_age_" + ttid + "_" + aid);

            var tech_track = "tech_upgrade_" + ttid + "_" + upgrade;

            var div = dojo.place(this.format_block("jstpl_tech_spot", { ttid: ttid, sid: upgrade }), tech_track);
            if (inage == 0 && aid != 1) {
              dojo.addClass(div, "withlandmark");
            }
            dojo.addClass(tech_track, "col_" + inage);
            dojo.connect(div, "onclick", this, "onAdvance");
          }
          dojo.place('<div id="track_fav_' + ttid + '" class="track_fav"></div>', "tech_track_" + ttid);
        }

        // TECH TRACK TOOLTIPS
        for (var ttid in gamedatas.tech_track_types) {
          for (var upgrade = 1; upgrade <= 12; upgrade++) {
            var tech_track = "tech_upgrade_" + ttid + "_" + upgrade;
            var save = this.defaultTooltipPosition;
            this.defaultTooltipPosition = undefined;
            this.addTooltipHtml(tech_track, this.getTechSpotTooltip(ttid, upgrade), 800);
            this.defaultTooltipPosition = save;
          }
        }

        // Setting up player boards
        let any_player_id = this.player_id;
        if (this.isReadOnly()) {
          for (var player_id in gamedatas.players) {
            var over = $("overall_player_board_" + player_id);
            if (over) {
              any_player_id = player_id;
              break;
            }
          }
        }

        for (var player_id in gamedatas.players) {
          var player_data = gamedatas.players[player_id];
          player_id = parseInt(player_id);
          var player = player_data["basic"];
          if (!player) continue;
          var hand = player_data["hand"];
          var over = $("overall_player_board_" + player_id);

          if (player_id <= 2) {
            // bot non exitsing bot panel
            if (over) dojo.destroy(over);

            let active_id = any_player_id; // real player
            let active_info = gamedatas.players[active_id];

            var xclone = $("overall_player_board_" + active_id).outerHTML;
            xclone = xclone.replaceAll(String(active_id), player_id);

            xclone = xclone.replaceAll(active_info.name, player.name);
            xclone = xclone.replaceAll(active_info.color, player.color);
            let div = dojo.place(xclone, "player_boards");
            dojo.destroy("avatar_" + player_id);
            let avatar_color = player.color;
            dojo.place(
              `<div class='avatar fa fa-solid fa-heart' style='font-size: xx-large;color: #${avatar_color}' />`,
              `avatarwrap_${player_id}`,
              "first"
            );
            dojo.addClass(div, "player_bot");

            dojo.place(`<div id='counter_fav_${player_id}' class='fav_track trackicon'  />`, `counter_era_${player_id}`, "after");
            over = $("overall_player_board_" + player_id);
          }

          var playerBoardDiv = dojo.byId("player_board_" + player_id);

          if (player_id == gamedatas.starting_player) {
            const no = 1;
            dojo.place(`<div id='pno_${player_id}' data-player-no="${no}" class='player_no'></div>`, over);
            this.addTooltip(`pno_${player_id}`, _("First player"), "");
          }

          if (!player_data["alive"]) this.updateEliminatedPlayer(player_id);

          dojo.place("miniboard_" + player_id, playerBoardDiv);

          // CIVILIZATION(S) MAT
          if (player_data.civilizations != null) {
            var civs = player_data.civilizations;
            for (var cid in civs) {
              var civ = civs[cid];
              this.setupCivCard(civ);
            }
          }

          // TAPESTRY CARDS
          this.tapestry[player_id] = this.cardsman[this.CON.CARD_TAPESTRY]
            .fork()
            .bind(this, $("tapestry_cards_" + player_id))
            .setAttribute("counter_id", "counter_tapestry_" + player_id);

          if (player_id == this.player_id) {
            this.moveCards(player_id, hand.tapestry);
          } else {
            dojo.addClass("tapestry_cards_" + player_id, "tapestry_deck");
          }

          var counters = player_data["counters"];
          for (var counter_name in counters) {
            this.setPlayerCounter(counter_name, player_id, counters[counter_name]);
          }

          // TERRITORY TILES

          this.territory[player_id] = this.cardsman[this.CON.CARD_TERRITORY]
            .fork()
            .bind(this, $("territory_tiles_" + player_id))
            .setAttribute("counter_id", "counter_terra_" + player_id)
            .setSelectionMode(1);

          this.moveCards(player_id, hand.territory);
          this.territory[player_id].updateCounter();

          // SPACE TILES
          this.space[player_id] = this.cardsman[this.CON.CARD_SPACE]
            .fork()
            .bind(this, $("space_tiles_" + player_id), "card", "space")
            .setAttribute("counter_id", "counter_space_" + player_id);
          this.moveCards(player_id, hand.space);
          this.space[player_id].updateCounter();

          // EXPLORED SPACE TILES
          this.moveCards(player_id, player_data.space);

          // ADD TAPESTRY ERA CARDS
          this.moveCards(player_id, player_data.tapestry);

          // Income mat resource zones
          for (var a = 0; a < 9; a++) {
            dojo.place(this.format_block("jstpl_resource_zone", { rzid: player_id + "_" + a }), "resource_track_" + player_id);
          }
          for (var a = 1; a < 5; a++) {
            var v = player["res" + a];
            dojo.place(
              this.format_block("jstpl_resource_token", { type: a, rid: player_id + "_" + a }),
              "resource_holder_" + player_id + "_" + v + "_" + a
            );
            this.updateResourceCounter(player_id, a, v);
          }

          // CAPITAL MAT

          this.setupCapitalMat(player_data.capital, player_id);

          // TECHNOLOGY CARDS
          this.moveCards(player_id, player_data.technology);

          var slots = player_data["technology_updates"];

          dojo.query("#tech_holder_" + player_id + " .tech_card").removeClass("update_possible");

          for (var c in slots) {
            dojo.query(".tech_slot_1 #tech_card_" + slots[c]).addClass("update_possible");
          }
        }

        if (gamedatas.cards) {
          for (var id in gamedatas.cards) {
            this.moveCard(gamedatas.cards[id]);
          }
        }

        var main_id = gamedatas.main_player;
        var income = gamedatas.income_turn_phase;
        // DO STRUCTURES LAST (MIGHT NEED TO BE PLACED ON OTHER PLAYERS CARDS)
        for (var player_id in gamedatas.players) {
          var player_data = gamedatas.players[player_id];
          var player = player_data["basic"];
          //console.log(player_data['structures']);
          // STRUCTURES
          this.setupStructures(player_data["structures"]);
          // income
          var inhelp = $("income_help_" + player_id);
          if (inhelp) {
            dojo.setAttr(inhelp, "data-income", 0);
            if (main_id == player_id) dojo.setAttr(inhelp, "data-income", income);
          }

          var era = player.player_income_turns;
          this.updateCurrentEra(player_id, era);
        }
        dojo.setAttr("page-title", "data-income", income);

        this.updateDeckCounters(gamedatas.deck_counters);

        this.extraMarkup();

        // HELP SHEETS
        this.setupHelpSheets();

        // DICE
        this.updateConquerDice(this.gamedatas.dice.red, this.gamedatas.dice.black);
        this.updateScienceDie(this.gamedatas.dice.science);

        // CONNECTIONS
        //	this.connectClass("building", "onmouseover", "onRaiseBuilding");
        this.connectClass("landmark_slot", "onclick", "onLandmarkSlotClick");

        this.connectClass("income_track_space", "onclick", "onIncomeTrackClick");
        dojo.connect($("rotator_left"), "onclick", this, "onRotateTileLeft");
        dojo.connect($("rotator_right"), "onclick", this, "onRotateTileRight");
        dojo.connect($("icon_confirm"), "onclick", this, "onTileConfirm");
        dojo.connect($("icon_cancel"), "onclick", this, "onTileCancel");

        dojo.connect($("red_die"), "onclick", this, "onDieClick");
        dojo.connect($("black_die"), "onclick", this, "onDieClick");
        dojo.connect($("science_die"), "onclick", this, "onDieClick");

        this.connect($("tech_age_3_0"), "onclick", (event) => this.boardRotate(2, event));
        this.connect($("tech_age_1_0"), "onclick", (event) => this.boardRotate(0, event));
        this.connect($("tech_age_2_0"), "onclick", (event) => this.boardRotate(1, event));
        this.connect($("tech_age_4_0"), "onclick", (event) => this.boardRotate(3, event));

        // TOOLTIPS
        // MAP
        dojo.query(".land").forEach((node) => {
          // if tile is there should not have tooltip
          this.addTooltipForToken("territory_hex", "", node);
        });
        this.addTooltip("science_die", _("Science die"), "");
        this.addTooltip("tapestry_deck", _("Tapestry Cards (shown number of cards in Deck/Discard)"), "");
        this.addTooltip("civilization_deck", _("Civilizations Cards (shown number of cards in Deck/Discard)"), "");
        this.addTooltip("territory_deck", _("Territory Tiles (shown number of tiles in Deck/Discard)"), "");
        this.addTooltip("space_deck", _("Space Tiles (shown number of tiles in Deck/Discard)"), "");
        this.addTooltip("tech_deck", _("Technology Cards (shown number of cards in Deck/Discard)"), "");

        //this.addTooltip('landmark_mat', _('Landmarks'), '');
        var incomeHelp = "<b>" + _("Income") + "</b><p>";
        incomeHelp += "<ol>";
        incomeHelp += _("<li>Activate civilization abilities (if applicable) [era 2-5].");
        incomeHelp += _("<li>Play a tapestry card onto the leftmost blank space on your income mat [era 2-4].");
        incomeHelp += _(
          "<li>Upgrade 1 tech card (optional) and gain victory points from all exposed VP icons on your income mat tracks [era 2-5]."
        );
        incomeHelp += _(
          "<li>Gain income from all exposed icons for resources, territortray tiles, and tapestry cards on your income tracks [era 1-4]."
        );
        incomeHelp += "</ol>";
        dojo.query(".income_help").forEach((node) => {
          this.addTooltipHtml(node.id, incomeHelp);
        });

        for (var track = 1; track <= 4; track++) {
          for (var level = 1; level <= 6; level++) {
            var spot_data = this.income_track_data[track][level];
            dojo.query(".income_track_" + track + "_" + level).forEach((node) => {
              this.addTooltip(node.id, this.getTr(spot_data.name), "");
            });
          }
        }

        // TECH CARD CONNECTION AND TIPS

        this.connect($("tech_deck"), "onclick", "onTechCardClick");
        dojo.query(".tech_card").forEach((node) => {
          if (node.id.startsWith("tech_card")) {
            var type = getIntPart(node.id, 2);
            this.addTooltipForToken("tech", type, node.id);
            this.connect(node, "onclick", "onTechCardClick");
          }
        });

        // CAPITAL HELPER
        this.connect($("capital_rotator"), "onclick", "onCapitalRotate");
        this.connect($("capital_cancel"), "onclick", "onCancelStructure");
        this.connect($("capital_confirm"), "onclick", "onConfirmStructure");

        this.updateBreadCrumbs(true);

        // info panel
        this.setupInfoPanel();
      } catch (e) {
        this.showError("Error while loading " + e);
        console.error(e);
      } finally {
        this.inSetup = false;
      }

      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      console.log("Ending game setup");
    },

    setupHelpSheets: function () {
      var helpnode = document.querySelector("#allcards_tech .expandablecontent");
      var title = $("allcards_tech_title");
      title.innerHTML = _("All Tech Cards");

      for (let i = 1; i <= 33; i++) {
        var div = this.format_string("<div id='tech_card_${num}_help' class='card tech_card tech_card_${num}'></div>", { num: i });
        dojo.place(div, helpnode);
      }

      var helpnode = document.querySelector("#allcards_tap .expandablecontent");
      var title = $("allcards_tap_title");
      title.innerHTML = _("All Tapestry Cards");

      for (let i = 1; i <= 44; i++) {
        var div = this.format_string("<div id='tapestry_${num}_help' class='card tapestry_card tapestry_${num}'></div>", { num: i });
        dojo.place(div, helpnode);
        this.addTooltipForToken("tapestry", i, "tapestry_" + i + "_help");
      }

      var helpnode = document.querySelector("#allcards_civ .expandablecontent");
      var title = $("allcards_civ_title");
      title.innerHTML = _("All Civilization Cards");

      for (let i in this.civilizations) {
        const civ_id = i;
        var div = this.format_string("<div id='civilization_${num}_help' class='card civilization civilization_${num}'></div>", { num: i });
        div = dojo.place(div, helpnode);
        dojo.addClass(div, "exp_" + this.civilizations[civ_id]["exp"]);
        this.addTooltipForToken("civilization", i, "civilization_" + i + "_help");
      }

      // terrain
      var helpnode = document.querySelector("#allcards_terr .expandablecontent");
      var title = $("allcards_terr_title");
      title.innerHTML = _("All Territory Tiles");

      for (let i = 1; i <= 48; i++) {
        var div = this.format_string("<div id='territory_tile_${num}_help' class='tile territory_tile territory_tile_${num}'></div>", {
          num: i
        });
        dojo.place(div, helpnode);
        this.addTooltipForToken("territory_tile", i, "territory_tile_" + i + "_help");
      }
      // space
      var helpnode = document.querySelector("#allcards_space .expandablecontent");
      var title = $("allcards_space_title");
      title.innerHTML = _("All Space Tiles");

      for (let i = 1; i <= 15; i++) {
        var div = this.format_string("<div id='space_tile_${num}_help' class='tile space_tile space_tile_${num}'></div>", { num: i });
        dojo.place(div, helpnode);
        this.addTooltipForToken("space_tile", i, "space_tile_" + i + "_help");
      }

      //showTooltipDialog

      dojo.query(".expandablecontent > *").connect("onclick", this, (event) => {
        var id = event.currentTarget.id;
        if (this.showHelp(id)) return;
        if (this.gamedatas.testenv && id.startsWith("civ")) {
          civ = getPart(id, 1);
          var name = this.getTr(this.civilizations[civ]["name"]);
          var message = this.format_string(_("You are about to GAIN civilzation ${name} (This only available for testing purposes)"), {
            name: name
          });

          this.confirmationDialog(
            message,
            () => {
              this.ajaxcallwrapper("acdebug", { a: JSON.stringify({ civ: civ }) }, undefined, true);
            },
            () => {
              this.tooltips[id].open(id);
            }
          );
          return;
        }
        this.tooltips[id].open(id);
      });
      dojo.query("#allcards .expandabletoggle").connect("onclick", this, "onToggleAllCards");
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName, args);
      this.gamestate = stateName;
      this.errorCount = 0;

      switch (stateName) {
        case "playerTurn":
          this.extraMarkup();

          var slots = args.args.technology_updates;
          dojo.query("#tech_holder_" + this.getActivePlayerId() + " .tech_card").removeClass("update_possible");

          for (var c in slots) {
            dojo.query(".tech_slot_1 #tech_card_" + slots[c]).addClass("update_possible");
          }

          break;

        case "bonus":
          this.bonusdata = args.args;
          break;
      }

      if (stateName == "setupChoice") {
        dojo.style("setup_choices", "display", "block");
      } else {
        dojo.style("setup_choices", "display", "none");
      }
      dojo.query(".permanent_active_slot").addClass("active_slot");
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "explore":
          break;
        case "placeStructure":
          dojo.query(".tap_visible").removeClass("tap_visible");
          dojo.removeClass("capital_helper", "income_placement");
          break;
        case "bonus":
          if (this.isCurrentPlayerActive()) {
            this.territory[this.player_id].setSelectionMode(1);
            this.tapestry[this.player_id].setSelectionMode(1);
          }
          break;

        case "trackSelect":
          dojo.query(".tech_spot .cube").style("pointer-events", "none");
          dojo.query(".tech_spot .cube").style("cursor", "auto");
          break;
        case "invent":
          const args = this.gamedatas.gamestate.args;

          if (args.discard) {
            for (let i in args.discard) {
              const card = args.discard[i];
              const card_type_arg = card.card_type_arg;
              const div_id = `tech_card_${card_type_arg}`;
              if ($(div_id)?.parentNode.id == "draw") {
                card.card_location = "discard";
                this.moveCard(card);
              }
            }
          }
          break;
      }
      dojo.query(".active_slot").removeClass("active_slot");
      dojo.query(".elevated").removeClass("elevated");
      dojo.query(".illegal_slot").removeClass("illegal_slot");
      dojo.query(".selected").removeClass("selected");

      //dojo.query(".clicked").removeClass('clicked');
      if (!this.on_client_state) {
        dojo.query(".possible").removeClass("possible");
        this.disconnectAllTemp();
      }
    },

    setClientStateUpd: function (name, onUpdate, timeout, args) {
      this[`onUpdateActionButtons_${name}`] = onUpdate;
      if (!timeout) timeout = 0;
      setTimeout(() => this.setClientState(name, args), timeout);
    },

    onUpdateActionButtons_keepCard: function (args) {
      const cards = args.cards;
      const benrules = this.benefit_types[args.bid];
      this.clientStateArgs.action = "keepCard";
      if (benrules.keep == 1) {
        this.clientStateArgs.selmode = 1;
      } else {
        this.clientStateArgs.selmode = 2;
      }
      if (args.title) {
        this.setDescriptionOnMyTurn(this.getTr(args.title));
      }
      for (card_id in cards) {
        const card = cards[card_id];
        const div = document.querySelector(`*[data-card-id='${card.card_id}']`);
        if (div) dojo.addClass(div, "active_slot");
      }

      this.addActionButton("button_confirm", _("Confirm"), () => {
        const ids = Array.from(document.querySelectorAll(".selected")).map((node) => node.getAttribute("data-card-id"));
        if (ids.length == 0) {
          this.showError(_("Nothing is selected"));
          return;
        }
        if (this.clientStateArgs.selmode == 1 && ids.length > 1) {
          this.showError(_("You must select one or Decline"));
          return;
        }
        this.ajaxcallwrapper("keepCard", {
          ids: ids.join(",")
        });
      });

      if (args.decline) {
        this.addActionButton("button_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
      }
    },

    onUpdateActionButtons_moveStructureOnto: function (args) {
      for (var cid in args.targets) {
        dojo.addClass(args.targets[cid], "active_slot");
      }
      if (args.title) {
        this.setDescriptionOnMyTurn(args.title, args);
      }
      if (this.ownsCiv(this.CON.CIV_MILITANTS)) {
        this.updateCivMilitiantAbility("");
      }

      if (args.structures.length == 0) this.addActionButton("button_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
      else if (args.targets.length == 0)
        this.addActionButton("button_decline", _("No valid target"), () => this.ajaxcallwrapper("decline"));
    },

    onUpdateActionButtons_civAbility: function (args) {
      var benefits = args.benefits;
      var decline = true;

      var foundCiv = false;
      if (this.selectedCiv) {
        for (var i in benefits) {
          let civ = parseInt(benefits[i].benefit_type);
          if (this.selectedCiv == civ) {
            foundCiv = true;
            break;
          }
        }
      }
      if (!foundCiv) {
        this.selectedCiv = 0;
      }
      for (var i in benefits) {
        let civ = parseInt(benefits[i].benefit_type);
        if (!this.selectedCiv) {
          this.selectedCiv = civ;
        }
        if (this.selectedCiv != civ) {
          continue;
        }
        let bene = benefits[i];

        args.slots = bene.slots;
        var data = bene.benefit_data;
        let name = this.getTr(bene.reason);

        if (bene.title) {
          this.setDescriptionOnMyTurn(name + ": " + this.getTr(bene.title), bene);
        } else {
          this.setDescriptionOnMyTurn(name + ": " + _("Use ability"));
        }
        if (bene.decline == false) {
          decline = false;
        }
        if (bene.slots) {
          this.activateCivSlots(civ, bene.slots);
        }
        if (bene.slots_choice) {
          this.clientStateArgs.action = "civTokenAdvance";
          this.clientStateArgs.cid = civ;
          this.clientStateArgs.spot = 0;
          this.clientStateArgs.bid = i;
          this.clientStateArgs.extra = '';
          for (let slot in bene.slots_choice) {
            const info = bene.slots_choice[slot];
            if (!info.ben_icons) info.ben_icons = this.getBenIcon(info.benefit);
            if (!info.title) info.title = "${ben_icons}";
            if (!info.tooltip) info.tooltip = this.getBenTooltipStr(info.benefit, true);

            if (info.player_id) {
              info.player_name = this.divPlayerName(info.player_id, info.player_name);
            }

            const message = this.format_string_recursive(info.title, info);

            this.addImageActionButton("button_" + slot, message, "onCivSpotHandler", undefined, info.tooltip);
          }
        }

        switch (civ) {
          case this.CON.CIV_ALCHEMISTS:
            if (this.getAdjustmentLevel() < 8) {
              this.setDescriptionOnMyTurn(_("ALCHEMISTS: Choose to roll or stop"));
              this.addActionButton("button_alchemistroll", _("Roll"), () => this.ajaxcallwrapper("civTokenAdvance", { spot: 1, cid: civ }));
              this.addActionButton("button_alchemistrollstop", _("Stop"), () =>
                this.ajaxcallwrapper("civTokenAdvance", { spot: 0, cid: civ })
              );
            }
            decline = false;
            break;
          case this.CON.CIV_ARCHITECTS:
            if (data == "midgame") {
              this.setClientState("client_ArchitectsSwap", {
                descriptionmyturn: _("ARCHITECTS: You may swap two Income buildings in your capital")
              });
            } else {
              this.setDescriptionOnMyTurn(_("ARCHITECTS: Place a cube in your capital"));

              this.setClientStateUpd(
                "client_ArchitectsCube",
                () => {
                  this.capitalRotOptions = bene.capital.options;
                  this.updateCapitalRot();
                },
                300
              );
            }

            break;

          case this.CON.LEADERS:
            this.setDescriptionOnMyTurn(_("LEADERS: You may select a leader to advance on that track"));
            dojo.query(".civilization_10 .cube_holder").addClass("active_slot");
            break;
          case this.CON.CIV_HISTORIANS:
            this.setDescriptionOnMyTurn(_("HISTORIANS: Give a historian token to a player (select cube and territory tile first)"));
            // add buttons for each player...
            for (var pid in this.gamedatas.players) {
              var player = this.gamedatas.players[pid];
              if (pid != this.player_id) {
                this.addPlayerActionButton(pid, "onSelectHistorian", "button_historian_");
              }
            }

            break;
          case this.CON.CIV_HERALDS:
            this.setDescriptionOnMyTurn(_("HERALDS: You may place a player token to WHEN PLAYED tapestry card"));
            //tapestry_data
            dojo.query(".tapestry_slot:not(.tapestry_slot6) > .tapestry_card").forEach((node) => {
              var type = dojo.getAttr(node, "data-type-arg");
              if (type) {
                if (this.tapestry_data[type].type == "now") dojo.addClass(node, "active_slot");
              } else {
                dojo.addClass(node, "active_slot");
              }
            });
            break;
          case 7:
            break;
          case 8:
            this.setDescriptionOnMyTurn(_("INVENTORS: You may place a player token on a Technology card"));
            dojo.query(".tech_slot_0 .tech_card,.tech_slot_1 .tech_card").addClass("active_slot");
            break;
          case this.CON.CIV_TRADERS:
            if (this.getAdjustmentLevel() == 4) {
              this.setDescriptionOnMyTurn(_("TRADERS: You may place trader token or income building on half-occupied territory."));
            } else if (this.getAdjustmentLevel() == 8) {
              this.setDescriptionOnMyTurn(
                _(
                  "TRADERS: You may place trader token or income building on half-occupied territory. If opponent controls it you also need a select a Territory Tile to give them"
                )
              );
            } else {
              this.setDescriptionOnMyTurn(_("TRADERS: You may place trader token on empty or half-occupied territory"));
            }
            const targets = bene.targets;
            for (var li in targets) {
              const type = targets[li];
              const node = "land_" + li;
              dojo.addClass(node, "active_slot");
              if (type == 2) dojo.addClass(node, "own_land");
            }

            break;
          case this.CON.CIV_MILITANTS:
            //dojo.query("#civilization_12 .cube_holder").addClass("active_slot");
            this.updateCivMilitiantAbility(data);
            break;
          case this.CON.CIV_MYSTICS:
            this.setDescriptionOnMyTurn(_("MYSTICS: Place your bets"));
            this.addActionButton("button_mystic", _("Confirm"), "onMystic");
            decline = false;
            break;
          case this.CON.CIV_ENTERTAINERS:
            //dojo.query("#civilization_4 .cube_holder").addClass("illegal_slot");

            this.activateCivSlots(civ, args.slots);

            if (data == "midgame") {
              this.setDescriptionOnMyTurn(_("ENTERTAINERS: Place a player token on any space and gain the benefit (midgame setup)"));
            } else this.setDescriptionOnMyTurn(_("ENTERTAINERS: Advance token along the lines and gain the benefit"));
            break;
          case this.CON.CIV_MERRYMAKERS:
            if (data == "midgame") {
              this.setDescriptionOnMyTurn(_("MERRYMAKERS: Place a player token on any space to start (no benefit)"));
            } else this.setDescriptionOnMyTurn(_("MERRYMAKERS: Advance token along the lines and gain the benefit"));
            this.activateCivSlots(civ, args.slots);

            break;

          case this.CON.CIV_COLLECTORS:
            if (this.getAdjustmentLevel() >= 8) {
              const targets = bene.targets;
              for (var li in targets) {
                const card = "card_" + targets[li];
                dojo.addClass(card, "active_slot multi-select");
              }
              this.clientStateArgs.action = "civTokenAdvance";
              this.clientStateArgs.cid = civ;
              this.clientStateArgs.spot = 0;
              this.addActionButton("button_confirm", _("Confirm"), () => {
                const selected1 = document.querySelectorAll(".tapestry.selected");
                const selected2 = document.querySelectorAll(".territory.selected");
                if (selected1.length == 0 && selected2.length == 0) {
                  this.showError(_("Nothing is selected"));
                  return;
                }
                if (selected1.length > 1 || selected2.length > 1 || selected1.length + selected2.length > 2) {
                  this.showError(_("Too many cards are selected"));
                  return;
                }
                let tapId = 0;
                if (selected1.length == 1) {
                  tapId = getPart(selected1[0].id, 1);
                }
                let cardId = 0;
                if (selected2.length == 1) {
                  cardId = getPart(selected2[0].id, 1);
                }

                this.ajaxcallwrapper("civTokenAdvance", {
                  cid: civ,
                  spot: tapId,
                  extra: cardId
                });
              });
            }
            break;
          case this.CON.CIV_CHOSEN:
            this.clientStateArgs.action = "civTokenAdvance";
            this.clientStateArgs.cid = civ;
            this.clientStateArgs.spot = 0;
            if (this.getAdjustmentLevel() >= 4) {
              if (args.slots.length == 0) break;
              const info = this.civilizations[civ];
              const index = args.slots[0];
              const ben = info.slots[index].benefit;

              this.clientStateArgs.spot = index;
              decline = false;
              this.setClientStateUpd(
                "client_benvpchoice",
                () => {
                  this.setDescriptionOnMyTurn(_("CHOSEN: ${you} must choose to gain 5VP or benefit"));
                  this.addImageActionButton("button_5", this.getBenIcon(505), "ajaxClientStateHandler");
                  if (ben) this.addImageActionButton("button_1", this.getBenIcon(ben), "ajaxClientStateHandler");
                  if (data == "midgame") {
                    this.addImageActionButton("button_0", _("Forfeit"), "ajaxClientStateHandler");
                  }
                  //this.addCancelButton();
                  //this.addActionButton("button_civDecline", _("Decline"), "onCivDecline");
                },
                300
              );
            } else {
              this.setClientStateUpd(
                "client_choosenorig",
                () => {
                  this.setDescriptionOnMyTurn(_("CHOSEN: ${you} must use civilization ability"));
                  this.addActionButton("button_1", _("Yes"), "ajaxClientStateHandler");
                  this.addCancelButton();
                },
                300
              );
            }
          case this.CON.CIV_RENEGADES:
            if (data == "midgame") {
              this.setDescriptionOnMyTurn(
                _("RENEGADES: Place a player token in a tier that matches a tier on the board containing one of your player tokens")
              );
              this.activateCivSlots(civ, args.slots);
            }

            break;
          default:
            break;
        }
      }

      for (var i in benefits) {
        let civ = parseInt(benefits[i].benefit_type);

        if (this.selectedCiv != civ) {
          dojo.addClass("civilization_" + civ, "active_slot");
          this.addActionButton(
            "button_" + civ,
            _("Switch") + ": " + this.getTr(benefits[i].reason),
            () => {
              this.selectedCiv = civ;
              dojo.empty("generalactions");
              this.onUpdateActionButtons("civAbility", args);
              return;
            },
            undefined,
            undefined,
            "gray"
          );
          continue;
        }
      }
      if ($("button_civDecline")) {
        dojo.destroy("button_civDecline");
      }

      if (decline) {
        this.addActionButton("button_civDecline", _("Decline"), "onCivDecline");
      }
    },

    sendTrader: function (type) {
      const al = this.getAdjustmentLevel();
      let tile = 0;
      const coords = this.clientStateArgs.land_id;
      if (al >= 8) {
        if (!dojo.hasClass(coords, "own_land")) {
          const tileNode = $(`territory_tiles_${this.player_id}`).querySelector(".selected");
          if (!tileNode) {
            this.showError(_("Select a tile to give first"));
            $(`territory_tiles_${this.player_id}`)
              .querySelectorAll(".territory")
              .forEach((node) => dojo.addClass(node, "active_slot"));
            return;
          }
          tile = getPart(tileNode.id, 1);
        }
      }
      this.clientStateArgs.cid = this.CON.CIV_TRADERS;
      this.clientStateArgs.spot = 0;

      this.clientStateArgs.extra = {
        bt: type,
        tile: tile,
        coords: coords
      };
      this.ajaxClientStateAction("civTokenAdvance");
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName, args);
      this.gamestate = stateName;
      var subtitle = "subtitle_bar";
      dojo.destroy(subtitle);
      if (args && args.reason) {
        // update reason
        var message = this.getTr(args.reason);
        var div = dojo.create("div", { id: subtitle, innerHTML: message, class: "log" });
        //dojo.place(div, 'pagemaintitle_wrap');

        dojo.place(div, "maintitlebar_content", "after");
      }
      // SETUP CHOICE - allow to change mind after selection whilst others thinking.
      if (stateName == "setupChoice") {
        if (this.isCurrentPlayerActive()) {
          this.addActionButton("button_confirm", _("Confirm"), "onSetupConfirm");
          var cap = document.querySelector("#capitals > *");
          if (cap) {
            this.setDescriptionOnMyTurn(_("${you} must choose a Civilization and a Capital mat"));
          }
          dojo.query("#civilizations > *").addClass("active_slot");
        } else {
          this.addActionButton("button_confirm", _("Change"), "onSetupConfirm");
        }
        return;
      }
      if (!this.isCurrentPlayerActive()) return;

      if (stateName != "playerTurn" && stateName != "playerTurnEnd" && stateName != "playerTurnConfirm") {
        this.addActionButton("button_undo", _("Undo"), "onUndo", undefined, undefined, "red");
      }

      const upMethod = this[`onUpdateActionButtons_${stateName}`];
      if (upMethod) {
        this[`onUpdateActionButtons_${stateName}`](args);
        return;
      }

      switch (stateName) {
        case "playerTurn":
          for (var adv in args.all_advances) {
            var allowed = args.all_advances[adv];
            var spot = "tech_spot_" + adv;
            if (!$(spot)) continue;
            if (allowed == 1) {
              dojo.addClass(spot, "active_slot");
              var track = getPart(adv, 0);
              var slot = getPart(adv, 1);
              this.addTrackSlotActionButton(adv, "button_benefit", "onAdvance");
            } else if (allowed == 0) dojo.addClass(spot, "illegal_slot");
            else if (allowed == -1) dojo.addClass(spot, "illegal_slot");
          }
          this.addActionButton("button_income", _("Income"), "onIncomeTurn");

          break;
        case "playerTurnEnd":
        case "playerTurnConfirm":
          this.addActionButton("button_confirm", _("Confirm"), () => this.ajaxcallwrapper("actionConfirm"));
          this.addActionButton("button_undo_x", _("Undo"), "onUndo", undefined, undefined, "red");
          //this.addButtonTimer('button_confirm', undefined, 20);

          break;
        case "startGameDebug":
          this.addActionButton("button_confirm", _("Confirm"), () => this.ajaxcallwrapper("actionConfirm"));

          this.addButtonTimer("button_confirm");
          break;

        case "explore":
          if (args.anywhere) this.setDescriptionOnMyTurn(_("EXPLORE: ${you} must select an unexplored hex ANYWHERE"));
          var noTiles = args.tiles.length == 0;
          if (args.ageOfSail && !noTiles) {
            this.setDescriptionOnMyTurn(_("AGE OF SAIL: Select an opponent to give a territory tile for 3 VP"));
            for (var pid in this.gamedatas.players) {
              var player = this.gamedatas.players[pid];
              if (pid != this.player_id) {
                this.addPlayerActionButton(pid, "onSelectHistorian", "button_aos_");
              }
            }
            this.territory[this.player_id].getChildrenDivs().forEach((node) => node.classList.add("active_slot"));
            this.addActionButton("button_nope", _("Nobody"), () => {
              this.setDescriptionOnMyTurn(_("EXPLORE: ${you} must select an unexplored hex adjacent to a territory you control"));
              args.ageOfSail = 0;
              dojo.empty("generalactions");
              this.onUpdateActionButtons(stateName, args);
            });
            break;
          } else {
            args.ageOfSail = 0;
          }

          for (var cid in args.exploration_targets) {
            dojo.addClass(args.exploration_targets[cid], "active_slot");
          }
          if (args.exploration_targets.length == 0) {
            this.addActionButton("button_decline", _("No valid targets"), () => this.ajaxcallwrapper("decline"));
          } else if (noTiles) {
            this.addActionButton("button_decline", _("No tiles"), () => this.ajaxcallwrapper("decline"));
          } else {
            if (this.ownsCiv(this.CON.CIV_MILITANTS) && this.gamedatas.gamestate.args.militarism) {
              this.updateCivMilitiantAbility("");
            }
            this.addActionButton("button_tileconfirm", _("Confirm Tile"), "onTileConfirm");
          }

          if (args.decline && !$("button_decline")) {
            this.addActionButton("button_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
          }

          if (args.title) {
            this.setDescriptionOnMyTurn(_(args.title), args);
          }

          if (args.coal_baron) {
            var tt = args.coal_baron.card_type_arg;
            var tile = document.querySelector(".territory_tile_" + tt);
            if (tile) {
              tile.classList.add("selected");
            }
          }

          this.addCancelButton(undefined, "onTileCancel");

          break;
        case "spaceExploration":
          dojo.query("#space_tiles_" + this.player_id + " .space_tile").addClass("active_slot");
          break;
        case "client_trader":
          const land = $(this.clientStateArgs.land_id);
          dojo.addClass(land.parentNode, "selected");

          this.addActionButton("button_0", _("Trader"), () => this.sendTrader(0));
          if (!dojo.hasClass(land, "own_land")) {
            var mat = $("income_mat_" + this.player_id);
            for (i = 1; i <= 4; i++) {
              const type = i;
              var sel = mat.querySelector(".building" + type);
              if (sel) {
                dojo.addClass(sel.parentNode, "active_slot");
                var div = this.format_block("jstpl_building", { type: type, bid: "x" });
                this.addImageActionButton("button_" + type, div, () => this.sendTrader(type));
              }
            }
          }
          this.addCancelButton();
          break;

        case "client_exploitation":
          this.addActionButton("button_exploitation_yes", _("Yes"), "onExploitationClick");
          this.addActionButton("button_exploitation_no", _("No"), "onExploitationClick");
          this.addCancelButton();
          break;

        case "client_militarism":
          if (this.ownsCiv(this.CON.CIV_MILITANTS)) {
            this.updateCivMilitiantAbility();
          }
          this.setDescriptionOnMyTurn(_("MILITARISM: ${you} can also place an outpost"));

          this.addActionButton("button_militarism_yes", _("Yes"), "onMilitarismClick");
          this.addActionButton("button_militarism_no", _("No"), "onMilitarismClick");
          this.addCancelButton();
          break;
        case "client_standup":
          this.setDescriptionOnMyTurn(_("Select up to ${num} of your outposts to stand up"), {
            num: this.clientStateArgs.num
          });
          for (var cid in args.targets) {
            var bid = args.targets[cid]["card_id"];
            var bnode = dojo.byId("building_" + bid) == null ? "outpost_" + bid : "building_" + bid;
            this.connectClickTemp($(bnode), "onOutpost");
          }

          this.addActionButton("button_done", _("Confirm"), () => {
            var selected = document.querySelectorAll(".selected");
            var outposts = Array.from(selected)
              .map((node) => getPart(node.id, 1))
              .join(",");
            console.log(selected, outposts);
            this.ajaxcallwrapper("standup", { outposts: outposts });
          });
          this.addActionButton(
            "button_decline",
            _("Decline"),
            () => this.ajaxcallwrapper("standup", { outposts: "0" }),
            undefined,
            undefined,
            "red"
          );
          this.addCancelButton();
          break;
        case "conquer":
          if (args.bid == this.CON.BE_STANDUP_3_OUTPOSTS) {
            this.clientStateArgs.action = "standup";
            this.clientStateArgs.num = 3;
            this.clientStateArgs.choices = "";
            this.setClientState("client_standup");
            return;
          }
          if (args.anywhere) this.setDescriptionOnMyTurn(_("CONQUER: ${You} must select a hex ANYWHERE"));

          for (var cid in args.targets) {
            dojo.addClass("land_" + args.targets[cid], "active_slot");
          }
          if (this.ownsCiv(this.CON.CIV_MILITANTS)) {
            this.updateCivMilitiantAbility("");
          }

          if (args.decline) {
            if (args.targets.length > 0)
              this.addActionButton("button_decline", _("No more outposts"), () => this.ajaxcallwrapper("decline"));
            else this.addActionButton("button_decline", _("No valid target"), () => this.ajaxcallwrapper("decline"));
          }
          break;
        case "conquer_roll":
          dojo.query("die_wrapper").addClass("active_slot");
          var x = { black: args.die_black, red: args.die_red };
          for (const color in x) {
            if (Object.hasOwnProperty.call(x, color)) {
              const r = x[color];
              dojo.setAttr($(color + "_die"), "data-num", r);

              this.addImageActionButton(
                "button_" + color,
                this.getConqDieDiv(color, r),
                "onDieClick",
                undefined,
                this.getTr(this.gamedatas.dice_names[color][r].name)
              );
            }
          }

          break;

        case "research":
          this.setDescriptionOnMyTurn(_("${benefit}: ${You} must confirm"), {
            benefit: this.getBenTooltipStr(args.bid, true)
          });
          for (var adv in args.all_advances) {
            var allowed = args.all_advances[adv];
            var spot = "tech_spot_" + adv;
            if (allowed == 1) {
              if ($(spot)) dojo.addClass(spot, "active_slot");
              this.addTrackSlotActionButton(adv, "button_research_science", "onResearchDecision");
            }
          }

          this.addActionButton("button_research_decline", _("Decline"), "onResearchDecision", undefined, undefined, "red");

          // add roll
          var rolltemp = '<div class="research_icon"></div> ${verb}: ${roll}';
          var roll1 = this.format_string(rolltemp, { verb: _("Roll"), roll: this.divTrackSlot(args.science) });

          var roll = roll1;
          if (args.empiricism && parseInt(args.empiricism)) {
            var roll2 = this.format_string(rolltemp, { verb: _("Empiricism"), roll: this.divTrackSlot(args.empiricism) });
            var roll = this.format_string(_("${roll1} / ${roll2}"), { roll1: roll1, roll2: roll2 });
          }

          var sub = $("subtitle_bar");
          if (sub) sub.innerHTML = roll + " " + sub.innerHTML;

          break;
        //onUpdateActionButtons
        case "invent":
          if (this.ownsCiv(34)) {
            // recyclers

            if (args.bid == 326) {
              // invent from discard only
              if (args.discard) {
                for (let i in args.discard) {
                  const card = args.discard[i];
                  card.card_location = "draw";
                  this.moveCard(card, "discard");
                }
              }
              document.querySelectorAll("#draw > .tech_card").forEach((node) => node.classList.add("active_slot"));
              this.addActionButton("button_invent_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
              this.setDescriptionOnMyTurn("${you} may invent from top of discard");
              if (!args.discard) {
                this.setDescriptionOnMyTurn("${you} may invent from top of discard, but nothing is in discard");
              }
              break;
            }

            const al = this.getAdjustmentLevel();
            let button = _("Inspect top card of discard");
            if (!args.discard) button = _("Nothing in discard");
            if (al < 8) {
              button = _("Inspect discard pile");
            }
            this.addActionButton("button_discard", button, () => {
              if (al < 8) {
                this.ajaxcallwrapper("invent", { id: -1 });
              } else {
                // show discard
                if (args.discard) {
                  for (let i in args.discard) {
                    const card = args.discard[i];
                    card.card_location = "draw";
                    this.moveCard(card, "discard");
                  }
                }
                document.querySelectorAll("#draw > .tech_card").forEach((node) => node.classList.add("active_slot"));
              }
            });
            this.addTooltip(
              "button_discard",
              "Inspect discard pile to Invent, you can still Invent from tech display if you don't like your choices",
              ""
            );
          }
          setTimeout(() => {
            let dtech = dojo.query("#draw > .tech_card");
            dtech.addClass("active_slot");
            if (dtech.length > 0) {
              dojo.destroy("button_discard");
            }
          }, 300);

          dojo.query(".tech_card").removeClass("active_slot");
          dojo.query("#tech_deck_visible > .tech_card").addClass("active_slot");

          break;

        case "benefitOption":
        case "benefitChoice":
          if (stateName == "benefitOption") this.clientStateArgs.action = "choose_benefit";
          else this.clientStateArgs.action = "first_benefit";
        // fallthough
        case "client_benefitChoice":
          var num = 0;
          for (var bid in args.options) {
            var type = args.options[bid];
            num++;
            this.updateToolbarForBenefit(type);
          }
          if (args.count > 1) {
            this.setMainTitle("x "+args.count, "after");
          }
          if (args.tracks_change) {
            for (var type in args.tracks) {
              var bd = this.benefit_types[type];
              if (bd) break;
            }

            if (num == 1) {
              this.setDescriptionOnMyTurn(_("${you} must confirm track to ${advance_or_regress}"), {
                advance_or_regress: args.tracks_change > 0 ? _("advance") : _("regress")
              });
            } else
              this.setDescriptionOnMyTurn(_("${you} must choose track to ${advance_or_regress}"), {
                advance_or_regress: args.tracks_change > 0 ? _("advance") : _("regress")
              });
            if (bd) {
              this.setMainTitle(this.getAdvanceTypeStr(bd.flags), "after");
            }
          }
          if (stateName == "client_benefitChoice") {
            this.addCancelButton();
          }
          break;

        case "upgradeTechnology":
          this.addActionButton("button_upgrade_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
          var slots = args.possible;
          dojo.query("#tech_holder_" + this.player_id + " .tech_card").addClass("illegal_slot");

          for (var c in slots) {
            var type = slots[c];
            dojo.removeClass("tech_card_" + type, "illegal_slot");
            dojo.addClass("tech_card_" + type, "active_slot");
          }
          this.scrollIntoViewAfter("tech_holder_" + this.player_id, this.defaultAnimationDuration);
          break;

        case "techBenefit":
          var slots = args.cards;
          dojo.query("#tech_holder_" + this.player_id + " .tech_card").addClass("illegal_slot");

          for (var c in slots) {
            var type = slots[c];
            dojo.removeClass("tech_card_" + type, "illegal_slot");
            dojo.addClass("tech_card_" + type, "active_slot");
          }
          break;

        case "placeStructure":
          this.capitalRot = 0;
          this.capitalx = null;
          this.capitaly = null;
          this.structure_id = "building_" + args.id;
          this.stripPosition(this.structure_id);
          this.capitalRotOptions = args.options;
          if (this.ownsCiv(3)) {
            // CRAFTSMEN
            var slots = args.slots;
            // Clear any possible values, then update new ones.
            dojo.query(".cube_holder.possible").removeClass("possible");
            for (var c in slots) {
              var coord = slots[c];
              dojo.addClass("civ_3_" + coord, "possible");
            }
          }
          this.updateCapitalRot();
          if (this.ownsCiv(14)) {
            // NOMADS
            for (var cid in args.conquer_targets) {
              dojo.addClass("land_" + args.conquer_targets[cid], "active_slot");
            }
          }

          dojo.addClass("capital_helper", "tap_visible");
          var res = dojo.query("#capital_helper .income_building");
          if (res.length > 0) {
            dojo.addClass("capital_helper", "income_placement");
          }
          var mat = "capital_mat_" + this.player_id;
          dojo.place("capital_helper", mat);
          dojo.addClass($("capital_helper").parentNode, "tap_visible");

          this.scrollIntoViewAfter(mat, this.defaultAnimationDuration);

          this.addActionButton("button_reset", _("Reset"), "onCancelStructure", null, false, "gray");
          let rotlen = 0;
          for (var i = 0; i < 4; i++) {
            if (this.capitalRotOptions[i]) rotlen++;
          }
          if (rotlen > 1) this.addActionButton("button_rotate", _("Rotate"), "onCapitalRotate");
          this.addActionButton("button_confirm", _("Confirm"), "onConfirmStructure");
          if (args.anyoptions == 0 || (rotlen == 1 && this.capitalRotOptions[0].length == 0))
            this.addActionButton("button_out", _("Place outside of Capital Mat"), (e) => {
              this.capitalx = 100;
              this.capitaly = 100;
              this.onConfirmStructure(e);
            });
          dojo.addClass("button_confirm", "disabled");
          break;

        case "conquer_trap":
          this.addActionButton("button_trap_trap", _("Trap"), () => {
            const div = this.tapestry[this.player_id].findDivByType(42);
            if (!div) {
              this.showError(_("Cannot find a Trap card"));
              return;
            }
            this.ajaxcallwrapper("trap", { card_id: this.tapestry[this.player_id].getIdFromDiv(div) });
          });
          this.addActionButton("button_trap_pass", _("Pass"), "onPassTrap");

          break;

        case "bonus":
          this.bonusdata = args;
          dojo.destroy("button_confirm");

          var type = parseInt(this.bonusdata.pay);
          var bonus_name = this.getBenTooltipStr(args.benefits, true);
          args.pay_name = this.getBenTooltipStr(type, true);
          args.bonus_name = bonus_name;
          this.resourceCount = parseInt(args.benefit_quantity);

          var prefix = "";
          let payNum = this.resourceCount;
          if (payNum <= 0) payNum = 1;
          const payArr = Array(payNum).fill(this.bonusdata.pay);
          const pgIcon = this.getBenIcon({
            p: payArr,
            g: args.benefits
          });
          if (args.benefit_category == "bonus") prefix = _("BONUS:") + " ";
          if (args.benefit_quantity == -1) {
            this.setDescriptionOnMyTurn(prefix + _("${you} may choose to pay any number of ${pay_name} for ${bonus_name} each") + pgIcon);
          } else if (args.benefit_quantity == 1)
            this.setDescriptionOnMyTurn(prefix + _("${you} may pay ${pay_name} to gain ${bonus_name}") + pgIcon);
          else {
            this.setDescriptionOnMyTurn(prefix + _("${you} may pay ${pay_name} x ${benefit_quantity} to gain ${bonus_name}") + pgIcon);
          }

          let cannotDecline = false;
          var bd = this.benefit_types[type];

          if (!bd.dest) {
            this.addActionButton("button_confirm", _("Confirm"), "onConfirmBonus");
          } else if (bd.dest == this.CON.FLAG_NEIGHBOUR) {
            const num = this.gamedatas.playerorder_withbots.length;
            const index = this.gamedatas.playerorder_withbots.findIndex((item) => item == this.player_id);
            const left = (index - 1) % num;
            const right = (index + 1) % num;
            this.addPlayerActionButton(this.gamedatas.playerorder_withbots[left], "onConfirmBonus");
            if (left != right) this.addPlayerActionButton(this.gamedatas.playerorder_withbots[right], "onConfirmBonus");
          } else if (bd.dest == this.CON.FLAG_TARGET) {
            const target_player_id = args.benefit_data.split(":")[3];
            this.addPlayerActionButton(target_player_id, "onConfirmBonus");
          } else {
            this.showError("Unsupported player type");
          }
          this.addCancelButton();

          if (bd.tt == "card") {
            if (bd.ct == this.CON.CARD_TAPESTRY) {
              this.tapestry[this.player_id].setSelectionMode(this.resourceCount == 1 ? 1 : 2);
              dojo.query("#tapestry_cards_" + this.player_id + " .tapestry_card").addClass("active_slot");
            } else if (bd.ct == this.CON.CARD_TERRITORY) {
              this.territory[this.player_id].setSelectionMode(this.resourceCount == 1 ? 1 : 2);
              dojo.query("#territory_tiles_" + this.player_id + " .card").addClass("active_slot");
            } else if (bd.ct == this.CON.CARD_TERRITORY) {
              dojo.query("#tech_holder_" + this.player_id + " .tech_card").addClass("active_slot");
            }
          }
          if (type == this.CON.BE_ANYRES) {
            this.setupResourceLine(false);
          }

          if (cannotDecline == false) this.addActionButton("button_bonusDecline", _("Decline"), "onPassBonus", null, false, "red");

          break;

        case "resourceChoice":
          // THIS IS THE TRADING STATE... DEPENDS ON BID AS TO WHAT IS ON OFFER!
          switch (args.bid) {
            case "5":
              // Take X resources
              this.addActionButton("button_confirm", _("Confirm"), "onResourceChoiceConfirm");
              if (args.data == ":tapestry:24") {
                //mercantilism
                this.setDescriptionOnMyTurn(_("${you} may convert Food (up to ${count}) to other resources (for 2 VP each)"));
                this.setMainTitle(_(". Select Food if you want to keep it"), "after");
                this.addActionButton("button_decline", _("Decline"), () => this.ajaxcallwrapper("decline"), undefined, undefined, "red");
              } else {
                this.setDescriptionOnMyTurn(_("${you} may choose your resources to gain"));
              }

              this.resourceCount = args.count;
              this.addCancelButton();
              this.setupResourceLine(true);
              break;
            case "114":
              // Allowed to exchange 5VP per res (up to 3) either direction.
              this.setDescriptionOnMyTurn(_("${you} must choose"));
              this.addTradeActionButton(1, 5, this.CON.RES_VP, 1, this.CON.RES_ANY);
              this.addTradeActionButton(2, 10, this.CON.RES_VP, 2, this.CON.RES_ANY);
              this.addTradeActionButton(3, 15, this.CON.RES_VP, 3, this.CON.RES_ANY);
              this.addTradeActionButton(4, 1, this.CON.RES_ANY, 5, this.CON.RES_VP);
              this.addTradeActionButton(5, 2, this.CON.RES_ANY, 10, this.CON.RES_VP);
              this.addTradeActionButton(6, 3, this.CON.RES_ANY, 15, this.CON.RES_VP);
              break;

            default:
              alert("trade for benefit " + args.bid + " not coded yet.");
          }

          break;
        case "client_ArchitectsSwap":
          this.selectedCiv = this.CON.CIV_ARCHITECTS;
          this.addActionButton("button_civDecline", _("Decline"), "onCivDecline");
          //this.addActionButton('button_cancel', _('Cancel'), ()=>this.cancelLocalStateEffects());
          break;
        //onUpdateActionButtons
        case "civAbility":
          // see onUpdationActionButtons_civAbility
          break;

        case "playTapestryCard": //onUpdateActionButtons
          if (args.bid == 112) {
            this.setDescriptionOnMyTurn(_("${you} must choose a tapestry ability to copy"));
            dojo.query(".tapestry_slot:not(.tapestry_slot6) > .tapestry_card").addClass("active_slot");
          } else if (args.bid == 64) {
            if (args.decline) this.setDescriptionOnMyTurn(_("${you} may choose a tapestry card to play over your current one"));
            else this.setDescriptionOnMyTurn(_("${you} must choose a tapestry card to play over your current one"));
            dojo.query("#tapestry_cards_" + this.player_id + " > .tapestry_card").addClass("active_slot");
          } else if (args.bid == 128) {
            // income tapestry
            dojo.query("#tapestry_cards_" + this.player_id + " > .tapestry_card").addClass("active_slot");
          } else if (args.bid == 181) {
            // discard
            this.setDescriptionOnMyTurn(_("${you} must discard a tapestry card from your hand"));
            dojo.query("#tapestry_cards_" + this.player_id + " > .tapestry_card").addClass("active_slot");
          } else {
            dojo.query("#tapestry_cards_" + this.player_id + " > .tapestry_card").addClass("active_slot");
          }
          if (args.title) {
            this.setDescriptionOnMyTurn(this.getTr(args.title));
          }
          if (args.bid == 64 && args.tyranny && args.just_played) {
            var info = this.tapestry_data[parseInt(args.just_played)];
            if (!info) console.log(this.tapestry_data);
            var name = info["name"];
            this.setDescriptionOnMyTurn(_("TYRANNY: ${you} may choose to play ${card_name} on top"), { card_name: name });
            this.addActionButton("button_yes", _("Yes"), () => {
              this.ajaxcallwrapper("playCard", { card_id: args.data });
            });
          }
          if (args.decline) this.addActionButton("button_tapDecline", _("Decline"), "onTapDecline");
          this.scrollIntoViewAfter("tapestry_cards_" + this.player_id, this.defaultAnimationDuration);

          break;
        case "buildingSelect":
          var mat = $("income_mat_" + this.player_id);
          for (var index in args.choices) {
            var building = args.choices[index];

            if (args.bid == 110 || args.bid == 144) {
              let type = building.card_type;
              var sel = mat.querySelector(".building" + type);
              if (sel) {
                dojo.addClass(sel.parentNode, "active_slot");
                var div = this.format_block("jstpl_building", { type: type, bid: "x" });
                this.addImageActionButton("button_" + type, div, (node) => {
                  this.ajaxcallwrapper("selectIncomeBuilding", { type: type });
                });
              }
            } else {
              if (args.title) this.setDescriptionOnMyTurn(_(args.title));
              if (building.card_type == this.CON.BUILDING_OUTPOST) {
                var sel = document.querySelector("#outpost_" + building.card_id);
                if (sel) {
                  const i = getPart(sel.parentNode.id, 2);
                  this.tagAndBagMilitianOutpost(i);
                }
              } else {
                var sel = document.querySelector("#building_" + building.card_id);
              }
              if (sel) dojo.addClass(sel.parentNode, "active_slot");
            }
          }

          break;

        case "client_isolationist":
          this.addActionButton("button_isolationist_yes", _("Yes"), "onIsolationistClick");
          this.addActionButton("button_isolationist_no", _("No"), "onIsolationistClick");
          this.addCancelButton();
          break;

        //onUpdateActionButtons
        case "trackSelect":
          var ben = parseInt(args.bid);

          dojo.query(".tech_spot .cube").style("pointer-events", "auto");
          dojo.query(".tech_spot .cube_" + this.player_color).addClass("active_slot");
          switch (ben) {
            case 120: // SOCIALISM
            case 118: {
              // Will need to select cube of opponent, not just space...
              dojo.query(".tech_spot .cube").addClass("active_slot");
              dojo.query(".tech_spot .cube" + this.player_color).removeClass("active_slot");
              this.setDescriptionOnMyTurn(_("${you} must select an opponent cube on a track"));
              break;
            }
            case 106: {
              // dark ages
              this.setDescriptionOnMyTurn(_("${you} must select your cube on a track to Advance (the rest will regress)"));
              break;
            }
            case 115: // oil magnat
            case 108: {
              // dictatorship
              this.setDescriptionOnMyTurn(_("${you} must select your cube on a track to Advance"));
              break;
            }
            case 62: {
              dojo.query(".tech_spot .cube[data-type-arg=1]").removeClass("active_slot");
              this.setDescriptionOnMyTurn(_("${you} must select your cube on a track to repeat the benefit"));
              break;
            }
            case 55: {
              //dojo.query("#tech_spot_1_0,#tech_spot_2_0,#tech_spot_3_0,#tech_spot_4_0").addClass('active_slot');
              dojo.query(".tech_spot .cube" + this.player_color).removeClass("active_slot");
              this.setDescriptionOnMyTurn(_("Select a track to start from"));

              for (let i = 1; i <= 4; i++) {
                let track = i;
                this.addTrackSlotActionButton(i + "_0", "but_spot", () => {
                  this.ajaxcallwrapper("selectTrackSpot", {
                    track: track,
                    spot: 0
                  });
                });
              }
              break;
            }
            case 122: {
              // ALLIANCE
              // add buttons for each player... XXX add cube handlers too
              dojo.query(".tech_spot .cube").addClass("active_slot");
              dojo.query(".tech_spot .cube" + this.player_color).removeClass("active_slot");
              this.setDescriptionOnMyTurn(_("With whom would ${you} like to form an alliance?"));
              for (var pid in this.gamedatas.players) {
                var player = this.gamedatas.players[pid];
                if (pid != this.player_id) this.addActionButton("button_alliance_" + pid, player.name, "onAlliance");
              }
              break;
            }

            case 124: // trade economy
            default:
              var bd = this.benefit_types[ben];
              this.clientStateArgs.action = "choose_benefit";

              var name = this.getTr(bd.name);
              this.setDescriptionOnMyTurn(_("${You} do not have any valid choices: ${ben_name}"), { ben_name: name });

              dojo.query(".tech_spot .cube").removeClass("active_slot");
              if (args.cubes.length == 0 && args.tracks && args.tracks[ben]) {
                args.cubes = args.tracks[ben].cubes;
              }
              if (args.cubes.length > 0) {
                this.setDescriptionOnMyTurn(_("${You} must select a cube: ${ben_name}"), { ben_name: name });
                for (let i in args.cubes) {
                  let cube = args.cubes[i];
                  dojo.addClass(cube, "active_slot");
                }
              }
              if (args.cubes.length == 0 && args.spots.length == 0 && args.tracks && args.tracks[ben]) {
                args.spots = args.tracks[ben].spots;
              }
              if (args.spots.length > 0) {
                this.setDescriptionOnMyTurn(_("${You} must select a track slot: ${ben_name}"), { ben_name: name });
                for (var spot of args.spots) {
                  var tspot = "tech_spot_" + spot;
                  dojo.addClass(tspot, "active_slot");
                }
              }
              //var id = "button_benefit_" + ben + "_0_0";
              //this.addActionButton(id, _("Confirm"), "onOptionBenefitClick");

              break;
          }
          if (args.decline) {
            this.addActionButton("button_decline", _("Decline"), () => this.ajaxcallwrapper("decline"));
          }
          break; //trackSelect
      }
    },
    activateCivSlots(civ, slots) {
      for (var c in slots) {
        var type = slots[c];
        const id = `civ_${civ}_${type}`;
        dojo.removeClass(id, "illegal_slot");
        dojo.addClass(id, "active_slot");
      }
    },
    tagAndBagMilitianOutpostReverse: function (i) {
      var id = "civ_12_" + i;
      var node = $(id);
      if (node.querySelector(".outpost")) {
        return false;
      }
      node.classList.add("active_slot");
      return true;
    },
    tagAndBagMilitianOutpost: function (i) {
      var id = "civ_12_" + i;
      var node = $(id);
      var outpost = node.querySelector(".outpost");
      if (outpost) {
        node.classList.add("active_slot");
        this.addOutpostButton(i, outpost);
        return true;
      }

      return false;
    },
    singleSelectButton(button_id, auxId) {
      if (!auxId) auxId = button_id;
      dojo.query(".clicked").removeClass("clicked");
      dojo.addClass(auxId, "clicked");
      dojo.query("#generalactions .bgabutton_selected").removeClass("bgabutton_selected");
      dojo.addClass(button_id, "bgabutton_selected");
    },
    addOutpostButton: function (i, outpost) {
      try {
        const id = "civ_12_" + i;
        var civ_id = "12";
        var slots = this.civilizations[civ_id]["slots"];
        var slot = slots[i];
        var info = this.getBenTooltipStr(slot.benefit, true);
        var icon = this.getBenIcon(slot.benefit);
        var node = dojo.clone(outpost);
        node.id = "tmp_" + node.id;
        this.stripPosition(node);
        const button_id = "button_" + id;
        this.addImageActionButton(button_id, node.outerHTML + icon, () => {
          this.singleSelectButton(button_id, id);
        });
        this.addTooltip(button_id, info, "");
        this.singleSelectButton(button_id, id);
      } catch (e) {
        console.error("Exception thrown", e.stack);
      }
    },
    updateCivMilitiantAbility: function (data) {
      var i;

      if (data == "midgame") {
        for (i = 4; i >= 1; i--) {
          if (this.tagAndBagMilitianOutpostReverse(i)) break;
        }
        for (i = 8; i >= 5; i--) {
          if (this.tagAndBagMilitianOutpostReverse(i)) break;
        }
        this.setDescriptionOnMyTurn(_("MILITANTS: Place an output on any space (midgame setup)"));
      } else {
        for (i = 1; i <= 4; i++) {
          if (this.tagAndBagMilitianOutpost(i)) break;
        }
        for (i = 5; i <= 8; i++) {
          if (this.tagAndBagMilitianOutpost(i)) break;
        }

        var outpost_id = this.getMilitantOutpost(false);
        if (outpost_id == 0 && data != "milizarism") {
          this.setDescriptionOnMyTurn(_("MILITANTS CONQUER: Select an outpost then select a hex on the map"));
        }
      }
    },

    updateToolbarForBenefit: function (type, selectCubes) {
      var ben = type;
      if (Array.isArray(ben)) {
        var firstItem = ben.find((x) => x !== undefined);
        ben = firstItem;
      }
      var args = this.gamedatas.gamestate.args;
      var name = this.getTr(this.benefit_types[ben]["name"]);
      if (args.tracks && args.tracks[ben] && args.tracks[ben].spots) {
        var spots = args.tracks[ben].spots;

        for (var spot in spots) {
          var tspot = "tech_spot_" + spot;

          this.addTrackSlotActionButton(spot, "button_benefit_" + ben, "onOptionBenefitClick");
          if (!selectCubes) {
            if (!$(tspot)) continue;
            dojo.addClass(tspot, "active_slot");
          }
        }

        var cubes = args.tracks[ben].cubes;
        if (cubes && selectCubes) {
          for (var i in cubes) {
            var cube = cubes[i];
            dojo.addClass(cube, "active_slot");
          }
        }
      } else if (type == 317) {
        // urban planners action do not allow to click
        this.setDescriptionOnMyTurn(name + ". " + _("Click on landmark to place or click Decline."));
        // If you decline now placing another landmark right after won't trigger the benefit
      } else {
        dojo.query(".tech_spot .cube").addClass("active_slot");
        var id = "button_benefit_" + ben + "_0_0";
        if ($(id)) {
          id += "_x";
        }
        var div = this.getBenIcon(type);
        this.addImageActionButton(id, div, "onOptionBenefitClick", undefined, name != div ? name : undefined);
      }
      //				for (let i=1;i<=64;i++){
      //							var id = 'button_benefit_' + i + "_0_0";
      //					var div = this.getBenIcon(i);
      //									var name = this.getTr(this.benefit_types[i]['name']);
      //					this.addImageActionButton(id, div, 'onOptionBenefitClick', undefined, (name != div) ? name : undefined);
      //				}
    },
    showTooltipDialog: function (html) {
      if (this.myDlg) this.myDlg.destroy();

      this.myDlg = new ebg.popindialog();
      this.myDlg.create("dynto");
      this.myDlg.setTitle(_("Tooltip"));
      this.myDlg.setMaxWidth(500); // Optional

      // Show the dialog
      this.myDlg.setContent(html); // Must be set before calling show() so that the size of the content is defined before positioning the dialog
      this.myDlg.show();
    },
    createPaymentBox: function (boxNum, resourceNum, handler, gain) {
      var box = dojo.create("div", { id: "payment_box_" + boxNum, class: "payment_box" });
      if (!handler) handler = "onSelectPayment";
      gain = !!gain;
      for (var rtype = 1; rtype <= 4; rtype++) {
        var rid = "payment_" + boxNum + "_" + rtype;
        if (rtype == resourceNum || resourceNum == this.CON.RES_ANY) {
          var resdiv = dojo.place(this.format_block("jstpl_resource_token", { type: rtype, rid: rid }), box);
          resdiv.setAttribute("data-gain", gain);
          dojo.addClass(resdiv, "payment_resource");
          this.connect(resdiv, "onclick", handler);

          var count = this.getResourceCounter(this.player_id, rtype);
          resdiv.innerHTML = count;
          if (!gain) {
            var count = this.getResourceCounter(this.player_id, rtype);
            if (!count) {
              dojo.addClass(resdiv, "unchosen_resource");
            }
            if (resourceNum != this.CON.RES_ANY && count) {
              this.selectPaymentResource(resdiv, true);
            }
          }
        }
      }
      return box;
    },
    setupResourceLine: function (gain) {
      if ($("resource_line")) {
        this.restoreResources();
      }
      var butt = $("button_confirm");
      if (butt) butt.dataTimerCounter = 0;

      dojo.place(this.format_block("jstpl_resource_line", {}), "generalactions", "first");
      for (var a = 0; a < this.resourceCount; a++) {
        var box = this.createPaymentBox(a, 5, undefined, gain);
        dojo.place(box, $("resource_line"));
      }
      // XXX timer?
    },
    setupResourceLineForAdvance: function () {
      if ($("resource_line")) {
        this.restoreResources();
      }
      dojo.empty("generalactions");

      dojo.place(this.format_block("jstpl_resource_line", {}), "generalactions", "first");

      var level = 1 + Math.floor((this.clientStateArgs.spot - 1) / 3);
      var boxes = level > 3 ? 2 : level;
      for (var a = 0; a < boxes; a++) {
        var specific = (a == 0 && level > 1) || (a == 1 && level == 4);
        var restype = 5; // ANY
        if (specific) restype = parseInt(this.tech_track_types[this.clientStateArgs.track]["resource"]);
        var box = this.createPaymentBox(a, restype, undefined, false);
        dojo.place(box, "resource_line");
      }
      this.setDescriptionOnMyTurn(_("Select payment for Advance"));
      this.addCancelButton();
      this.addActionButton("button_confirm", _("Confirm"), "onPaymentConfirm");
    },

    onAlliance: function (event) {
      dojo.stopEvent(event);
      if (!this.checkAction("formAlliance")) {
        return;
      }
      var pid = event.currentTarget.id.split("_")[2];

      this.ajaxcall(
        "/tapestry/tapestry/formAlliance.html",
        {
          lock: true,
          pid: pid
        },
        this,
        function (result) {},
        function (is_error) {}
      );
    },

    onSelectHistorian: function (event) {
      dojo.stopEvent(event);
      // make sure there is a territory tile selected, then send ajax call.

      var token_id = 1;
      var selected = null;
      var action = "sendHistorian";
      switch (this.gamestate) {
        case "civAbility":
          selected = document.querySelector("#civilization_7 .cube_holder.clicked");
          if (selected) token_id = parseInt(getPart(selected.id, 2));
          else {
            this.showError(_("You must select a single historian token to send first"));
            return;
          }

          break;
        case "explore":
          action = "ageOfSail";
          break;
        default:
          return;
      }

      var tiles = this.territory[this.player_id].getSelectedItems();
      if (tiles.length != 1) {
        this.showError(_("You must select a single territory tile first"));
        return;
      }

      var tid = tiles[0].type;
      var pid = event.currentTarget.id.split("_")[2];

      this.ajaxcallwrapper(
        action,
        {
          pid: pid,
          tid: tid,
          token_id: token_id
        },
        () => {
          if (selected) dojo.removeClass(selected, "clicked");
        }
      );
    },

    checkPaymentComplete: function (showError) {
      var a = 0;
      var payment = ""; // Needs to be CSV of resources.
      while (dojo.byId("payment_box_" + a) != null) {
        // Box a exists.
        var count = 0;
        for (var b = 1; b < 5; b++) {
          var node = $("resource_payment_" + a + "_" + b);
          if (node && dojo.hasClass(node, "chosen_resource")) {
            count++;
            payment += payment.length > 0 ? "," + b : b;
          }
        }
        if (count != 1) {
          // Should be 1 resource per box!
          if (showError) this.showError(_("Please select the resources you wish to spend"));
          return false;
        }
        a++;
      }
      if (!payment) {
        if (showError) this.showError(_("Nothing is selected"));
        return false;
      }

      return payment;
    },

    getResourcePayment: function () {
      return this.checkPaymentComplete(true);
    },

    onResourceChoiceConfirm: function (event) {
      dojo.stopEvent(event);
      var payment = this.getResourcePayment();
      if (payment === false) return;
      var type = 0;
      var id = event.currentTarget.id;
      if (id != "button_confirm") type = id.split("_")[3];
      this.ajaxcallwrapper("choose_resources", {
        resources: payment,
        type: type
      });
    },

    onRaiseBuilding: function (event) {
      var node = event.currentTarget;
      var node_coords = dojo.coords(node.id);
      var top = node_coords.t - node_coords.h;
      var left = node_coords.l + Math.floor(node_coords.w / 3);
      var node_zindex = dojo.style(node, "zIndex");
      if (node_zindex === "1") {
        dojo.style(node, "zIndex", "2");
        dojo
          .animateProperty({
            node: node,
            duration: 500,
            properties: {
              top: { start: node_coords.t, end: top },
              left: { start: node_coords.l, end: left }
            },
            onEnd: function () {
              dojo
                .animateProperty({
                  node: node,
                  duration: 500,
                  delay: 500,
                  properties: {
                    top: { start: top, end: node_coords.t },
                    left: { start: left, end: node_coords.l }
                  },
                  onEnd: function () {
                    dojo.style(node, "zIndex", "1");
                  }
                })
                .play();
            }
          })
          .play();
      }
    },

    onIsolationistClick: function (event) {
      dojo.stopEvent(event);
      if (!this.checkAction("conquer")) {
        return;
      }
      var choice = event.currentTarget.id.split("_")[2] == "yes";
      this.processConquer(choice);
    },

    onCivDecline: function (event) {
      dojo.stopEvent(event);

      if (this.selectedCiv == 0) {
        this.showError("Cannot find selected civilization, try reloading");
        return;
      }

      let civ = this.selectedCiv;

      var name = this.getTr(this.civilizations[civ]["name"]);
      var message = this.format_string(_("You are about to DECLINE ability of ${name}"), { name: name });

      this.confirmationDialog(message, () => {
        this.ajaxcallwrapper("civDecline", { cid: civ });
        this.selectedCiv = 0;
      });
    },

    onTapDecline: function (event) {
      dojo.stopEvent(event);
      if (!this.checkAction("decline_tapestry")) {
        return;
      }
      this.ajaxcall(
        "/tapestry/tapestry/decline_tapestry.html",
        {
          lock: true
        },
        this,
        function (result) {},
        function (is_error) {}
      );
    },

    onMystic: function (event) {
      dojo.stopEvent(event);
      var ids = [];
      dojo.forEach(dojo.query(".clicked"), function (node) {
        var coords = getPart(node.id,2);
        ids.push(coords);
      });
      this.ajaxcallwrapper('civTokenAdvance', {
        cid: this.CON.CIV_MYSTICS,
        spot: 0,
        extra_js:JSON.stringify(ids) 
      });
    },

    ownsCiv: function (civ_id, player_id = this.player_id) {
      var civ = dojo.byId("civilization_" + civ_id);
      if (civ != null) {
        var pid = civ.parentNode.id.split("_")[2];
        if (pid == player_id) return true;
      }
      return false;
    },

    emptyTerritory: function () {
      var slot = this.conquerSlot;
      if ($(slot).parentNode.querySelectorAll(".land_slot > * ").length == 0) return true;
      return false;
    },

    hasIsolationTokens: function () {
      return dojo.query("#civilization_9 .cube").length > 0;
    },

    getMilitantOutpost: function (showError) {
      var count = 0;
      var outpost_id = 0;
      for (var a = 1; a <= 8; a++) {
        if (dojo.hasClass("civ_12_" + a, "clicked")) {
          count++;
          outpost_id = a;
        }
      }
      if (count == 0) {
        return this.gamedatas.gamestate.args.outpost;
      }
      if (count != 1) {
        if (showError) this.showError(_("You must select an outpost to use first"));
        return 0;
      }
      return outpost_id;
    },

    processConquer: function (isol) {
      var land_coords = this.conquerSlot.split("_");
      var u = land_coords[1];
      var v = land_coords[2];

      var outpost_id = 0;
      if (this.ownsCiv(this.CON.CIV_MILITANTS)) {
        outpost_id = this.getMilitantOutpost(true);
        if (outpost_id == 0) return;
      }

      this.ajaxcallwrapper(
        "conquer",
        {
          U: u,
          V: v,
          isol: isol,
          outpost: outpost_id
        },
        () => {
          dojo.query(".possible").removeClass("possible");
          dojo.query(".clicked").removeClass("clicked");
        }
      );
    },

    onCancelStructure: function () {
      this.capitalx = null;
      this.capitaly = null;
      if (this.structure_id) this.placeToken(this.structure_id, "capital_structure");
      dojo.destroy("button_confirm");
      this.addActionButton("button_confirm", _("Confirm"), "onConfirmStructure");
      dojo.addClass("button_confirm", "disabled");
    },

    onConfirmStructure: function () {
      dojo.destroy("button_confirm"); // to cancel timer
      if (this.capitalx === undefined || this.capitalx === null || this.capitalx === "") {
        this.showError(_("You have to place the building on your capital mat first"));
        return;
      }

      if (this.capitalx >= 12 && this.capitaly >= 12) {
        this.confirmationDialog(_("Proceed placing building out of city bounds?"), () => {
          this.structure_id = null;
          this.ajaxcallwrapper("place_structure", {
            x: this.capitalx,
            y: this.capitaly,
            rot: this.capitalRot
          });
        });
        return;
      }
      this.structure_id = null;
      this.ajaxcallwrapper("place_structure", {
        x: this.capitalx,
        y: this.capitaly,
        rot: this.capitalRot
      });
    },

    onCapitalRotate: function (event) {
      dojo.stopEvent(event);
      console.log("rotate");
      do {
        this.capitalRot = (this.capitalRot + 1) % 4;
      } while (!(this.capitalRot in this.capitalRotOptions));
      for (var i = 0; i < 4; i++) {
        dojo.removeClass(this.structure_id, "rot" + i);
      }
      dojo.addClass(this.structure_id, "rot" + this.capitalRot);
      this.updateCapitalRot();
    },

    onRotateTileLeft: function (event) {
      dojo.stopEvent(event);
      this.rotateTile(false);
    },
    onRotateTileRight: function (event) {
      dojo.stopEvent(event);
      this.rotateTile(true);
    },

    boardRotate: function (pos, ev) {
      var board = $("board");
      dojo.removeClass(board, "boardrotate_1");
      dojo.removeClass(board, "boardrotate_2");
      dojo.removeClass(board, "boardrotate_3");
      dojo.removeClass(board, "boardrotate_0");
      if (pos == 4) {
        var prev = dojo.attr(board, "data-rotate");
        if (prev === null) pos = 1;
        else pos = (parseInt(prev) + 1) % 4;
      }
      dojo.addClass(board, "boardrotate_" + pos);
      dojo.attr(board, "data-rotate", pos);
      if (ev) {
        var node = ev.target;
        this.scrollIntoViewAfter(node, this.defaultAnimationDuration);
      }
    },
    rotateTile: function (clockwise) {
      var change = clockwise ? 1 : -1;
      var tile = this.cardsman[this.CON.CARD_TERRITORY].findDivByType(this.selectedTile);
      //this.rotate(tile, 60 * this.selectedRot, 60 * (this.selectedRot + change));

      //   dojo.style('territory_' + this.selectedTile, 'transform', 'rotate(' + (60*(this.selectedRot+change)) + 'deg)');
      this.selectedRot = (6 + this.selectedRot + change) % 6;
      tile.setAttribute("data-orientation", this.selectedRot);
    },
    applyRotationTile: function () {
      var tile = this.cardsman[this.CON.CARD_TERRITORY].findDivByType(this.selectedTile);
      if (tile) {
        //this.rotate(tile, 0, 60 * (this.selectedRot));
        tile.setAttribute("data-orientation", this.selectedRot);
      }
    },

    scrollIntoViewAfter: function (node, delay) {
      if (this.instantaneousMode || this.inSetup) {
        return;
      }
      if (typeof g_replayFrom != "undefined") {
        $(node).scrollIntoView();
        return;
      }
      if (!delay) delay = 0;
      setTimeout(() => {
        $(node).scrollIntoView({ behavior: "smooth", block: "center" });
      }, delay);
    },

    onConfirmBonus: function (event) {
      if (!this.checkAction("acceptBonus")) {
        return;
      }
      let dest = 0;
      if (event) {
        dojo.stopEvent(event);
        let id = event.currentTarget.id;
        if (id) id = id.replace("button_", "");

        dest = parseInt(id) || 0;
      }

      this.cancelConfirmTimer();

      // For territory, tapestry and tech will be a list of ids.. for res, will be a list of res types.
      var ids = "";
      var count = this.bonusdata.pay_quantity;
      var type = parseInt(this.bonusdata.pay);

      switch (type) {
        case 2:
          ids = "2";
          break;

        case 5:
          ids = this.getResourcePayment();
          if (!ids) return;
          break;

        case 6:
          var stock_ids = this.territory[this.player_id].getSelectedItems().map((item) => item.type);
          if (stock_ids.length != count) {
            this.showError(_("Select the correct number of tiles ${count}"), { count: count });
            return;
          }
          ids = stock_ids.join(",");
          break;

        case 7:
        case 137:
        case 138:
          var stock_ids = this.tapestry[this.player_id].getSelectedItems().map((item) => item.id);
          if (count > 0 && stock_ids.length != count) {
            this.showError(_("Select the correct number of cards ${count}"), { count: count });
            return;
          }
          ids = stock_ids.join(",");

          break;

        case 26:
          var sel_count = 0;
          dojo.query(".tech_slot .tech_card.selected").forEach((node) => {
            var card_id = getPart(node.id, 2);
            ids += ids.length > 0 ? "," + card_id : card_id;
            sel_count++;
          });

          if (sel_count != count) {
            this.showError(_("Select the correct number of cards ${count}"), { count: count });
            return;
          }

          break;

        default:
          alert(this.bonusdata.benefit_type + " coming soon!");
          return;
      }
      this.ajaxcallwrapper("acceptBonus", { ids: ids, dest: dest });
    },

    onPassBonus: function (event) {
      dojo.stopEvent(event);
      this.ajaxcallwrapper("declineBonus");
    },

    onPassTrap: function (event) {
      dojo.stopEvent(event);
      this.ajaxcallwrapper("decline_trap");
    },

    onIncomeTrackClick: function (event) {
      dojo.stopEvent(event);
      var tid = event.currentTarget.id;
      if (this.showHelp(tid)) return;
      if (!this.checkActiveSlot(tid)) return;
      const type = getPart(tid, 3);
      if (this.getStateName() == "client_trader") {
        this.clientStateArgs.spot = this.clientStateArgs.building_type = type;
        this.ajaxClientStateAction();
        return;
      }
      this.ajaxcallwrapper("selectIncomeBuilding", { type: type });
    },

    onLandmarkSlotClick: function (event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;
      if (this.showHelp(id)) return;

      var type = id.substring(17, id.length); // landmark_mat_slotXX
      if (!this.checkActiveSlot(id)) return;
      this.ajaxcallwrapper("selectLandmark", { type: type });
    },

    onCapitalCellClick: function (event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;

      if (this.showHelp(id)) return;
      // store coordinates and place structure on cell.
      var coords = id.split("_");
      this.capitalx = coords[3];
      this.capitaly = coords[4];

      if (this.getStateName() == "client_ArchitectsCube") {
        if (dojo.hasClass(id, "clicked")) {
          dojo.removeClass(id, "clicked");
          return;
        }
        dojo.addClass(id, "clicked");
        this.ajaxcallwrapper(
          "civTokenAdvance",
          {
            cid: this.CON.CIV_ARCHITECTS,
            spot: this.capitalx,
            extra: this.capitaly
          },
          () => {
            dojo.removeClass(id, "clicked");
          }
        );

        return;
      }
      if (this.getStateName() == "client_ArchitectsSwap") {
        if (dojo.hasClass(id, "clicked")) {
          dojo.removeClass(id, "clicked");
          return;
        }
        var incomebuilding = $(id).querySelector(".building1,.building2,.building3,.building4");
        if (incomebuilding == null) {
          this.showError(_("Can only swap income buildings"));
          return;
        }
        var prev = document.querySelector(".capital_cell.clicked");
        if (prev == null) {
          dojo.addClass(id, "clicked");
          return;
        }
        var prevbuilding = prev.querySelector(".building1,.building2,.building3,.building4");
        dojo.removeClass(prev, "clicked");
        this.ajaxcallwrapper("civTokenAdvance", {
          cid: this.CON.CIV_ARCHITECTS,
          spot: getIntPart(prevbuilding.id, 1),
          extra: getIntPart(incomebuilding.id, 1)
        });

        return;
      }

      if (this.structure_id == null) return;

      this.placeToken(this.structure_id, id);

      dojo.destroy("button_confirm");
      this.addActionButton("button_confirm", _("Confirm"), "onConfirmStructure");

      if (!dojo.hasClass(id, "possible") && !dojo.hasClass(id, "active_slot")) {
        this.showError(_("Invalid structure placement"));
        dojo.addClass("button_confirm", "disabled");
        return;
      }
      var incomebuilding = dojo.hasClass(this.structure_id, "income_building");
      if (incomebuilding) this.addButtonTimer("button_confirm", undefined, 5);
    },

    onTileConfirm: function (event) {
      dojo.stopEvent(event);
      // Might need to check militarism or exploitation here.
      if (!this.selectedTile) {
        this.showError(_("Tile is not selected"));
        return;
      }
      if (this.gamedatas.gamestate.args.militarism) {
        this.setClientState("client_militarism");
      } else if (this.gamedatas.gamestate.args.exploitation) {
        this.setClientState("client_exploitation", {
          descriptionmyturn: _("EXPLOITATION: ${you} can forgo the exploration VP for a doubled benefit")
        });
      } else {
        this.processExplore(false, false);
      }
    },

    onExploitationClick: function (event) {
      dojo.stopEvent(event);
      var coords = event.currentTarget.id.split("_");
      var exploitation = coords[2] == "yes";
      this.processExplore(false, exploitation);
    },

    onMilitarismClick: function (event) {
      dojo.stopEvent(event);
      var coords = event.currentTarget.id.split("_");
      var militarism = coords[2] == "yes";
      this.processExplore(militarism, false);
    },

    onTileCancel: function (event) {
      if (this.selectedTile) {
        // move back to stock
        var tile = this.cardsman[this.CON.CARD_TERRITORY].findDivByType(this.selectedTile);
        this.attachToStock(tile.id, this.territory[this.player_id], 0);
      }
      this.selectedTile = null;
      this.selectedRot = 0;
      this.selectedland = null;
      dojo.query(".selected").removeClass("selected");
      this.hideRotators();
      this.cancelLocalStateEffects();
    },

    processExplore: function (militarism, exploitation) {
      if (!this.checkAction("explore")) {
        return;
      }
      if (!this.selectedTile) {
        this.showError(_("Tile is not selected"));
        return;
      }

      var outpost_id = 0;
      if (militarism && this.ownsCiv(12)) {
        outpost_id = this.getMilitantOutpost(true);
        if (outpost_id == 0) return;
      }

      this.ajaxcallwrapper("explore", {
        location: this.selectedland,
        tid: this.selectedTile,
        rot: this.selectedRot,
        militarism: militarism,
        exploitation: exploitation,
        outpost_id: outpost_id
      });
      this.selectedTile = 0;
      this.hideRotators();
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    // this _UTILS__ is here so I can use it in navigation bar in IDE
    __UTILS__: function () {},

    showError: function (log, args) {
      if (log === undefined) {
        console.error("error message with undefined log");
        this.showMessage("?", "error");
        return;
      }
      if (args === undefined) {
        args = {};
      }
      //args.you = this.divYou();
      var message = this.format_string_recursive(log, args);
      this.showMessage(message, "error");
      console.error(log, args, message);
      return;
    },
    getStateName: function () {
      return this.gamedatas.gamestate.name;
    },

    isActiveSlot: function (id) {
      if (!dojo.hasClass(id, "active_slot")) {
        return false;
      }
      return true;
    },
    checkActiveSlot: function (id) {
      if (!this.isActiveSlot(id)) {
        this.showMoveUnauthorized();
        return false;
      }
      return true;
    },
    connectClickTemp: function (node, handler) {
      dojo.addClass(node, "active_slot");
      dojo.addClass(node, "temp_click_handler");
      this.connect(node, "click", handler);
    },

    disconnectClickTemp: function (node) {
      dojo.removeClass(node, "active_slot");
      dojo.removeClass(node, "temp_click_handler");
      this.disconnect(node, "click");
    },

    disconnectAllTemp: function (query) {
      if (typeof query == "undefined") query = ".temp_click_handler";
      dojo.query(query).forEach((node) => {
        console.log("disconnecting => " + node.id);
        this.disconnectClickTemp(node);
      });
    },

    /**
     * Using of this method require scorenumber_anim defined in css
     */
    animSpinCounter: function (animNodeId, inc, playerId) {
      var value = inc > 0 ? "+" + inc : inc;
      var classes = "scorenumber tmp_obj";
      var jstpl_score = '<div class="${classes}" id="${id}">${value}</div>';
      if (!playerId) playerId = 0;

      var no = $(animNodeId).querySelectorAll(".tmp_obj").length;

      var scoring_marker_id = "scorenumber_" + animNodeId + "_" + playerId + "_" + no;
      var div = this.format_string_recursive(jstpl_score, {
        id: scoring_marker_id,
        value: value,
        classes: classes
      });

      dojo.place(div, animNodeId);

      this.placeOnObject(scoring_marker_id, animNodeId);
      if (playerId) {
        dojo.style(scoring_marker_id, "color", "#" + this.gamedatas.players[playerId].color);
      }

      if (no) {
        var x = parseInt($(scoring_marker_id).style.left);
        x += no * 20;
        $(scoring_marker_id).style.left = x + "px";
      }
      setTimeout(() => {
        if (!$(scoring_marker_id)) return;
        dojo.addClass(scoring_marker_id, "scorenumber_anim");
        this.fadeOutAndDestroy(scoring_marker_id, 2000, 300);
      }, 1000 * no);

      return scoring_marker_id;
    },

    /**
     * This method will remove all inline style added to element that affect positioning
     */
    stripPosition: function (token) {
      var token = $(token);
      // console.log(token + " STRIPPING");
      // remove any added positioning style
      if (!token) return;
      token.style.removeProperty("display");
      token.style.removeProperty("top");
      token.style.removeProperty("left");
      token.style.removeProperty("right");
      token.style.removeProperty("bottom");
      token.style.removeProperty("position");
      token.style.removeProperty("opacity");
    },
    stripTransition: function (token) {
      $(token).style.removeProperty("transition");
    },
    setTransition: function (token, value) {
      $(token).style.transition = value;
    },
    /**
     * This method will attach mobile to a new_parent without destroying, unlike original attachToNewParent which destroys mobile and
     * all its connectors (onClick, etc)
     */
    attachToNewParentNoDestroy: function (mobile_in, new_parent_in, relation, place_position) {
      //console.log("attaching ",mobile,new_parent,relation);
      const mobile = $(mobile_in);
      const new_parent = $(new_parent_in);
      const old_parent = mobile.parentNode;
      if (!mobile) throw new Error("Cannot find mobile: " + mobile_in);
      if (!new_parent) throw new Error("Cannot find parent: " + new_parent_in);

      var src = dojo.position(mobile);
      if (place_position) mobile.style.position = place_position;
      dojo.place(mobile, new_parent, relation);

      mobile.offsetTop; //force re-flow
      var tgt = dojo.position(mobile);
      var box = dojo.marginBox(mobile);
      var cbox = dojo.contentBox(mobile);
      var left = box.l + src.x - tgt.x;
      var top = box.t + src.y - tgt.y;

      if (place_position != "relative") mobile.style.position = "absolute";

      if (old_parent == mobile.parentNode) {
        // parent did not change
      } else {
        mobile.style.left = left + "px";
        mobile.style.top = top + "px";
        mobile.offsetTop; //force re-flow
      }
      box.l += box.w - cbox.w;
      box.t += box.h - cbox.h;

      return box;
    },

    /*
     * This method is similar to slideToObject but works on object which do not use inline style positioning. It also attaches object to
     * new parent immediately, so parent is correct during animation
     */
    slideToObjectRelative: function (token, finalPlace, duration, delay, onEnd, relation) {
      if (this.instantaneousMode) {
        duration = 0;
      } else if (duration === undefined) {
        duration = this.defaultAnimationDuration;
      }

      token = $(token);
      this.delayedExec(
        () => {
          token.style.transition = "none";
          token.classList.add("moving_token");
          var box = this.attachToNewParentNoDestroy(token, finalPlace, relation, "static");
          token.offsetHeight; // re-flow
          token.style.transition = "all " + duration + "ms ease-in-out";
          token.style.left = box.l + "px";
          token.style.top = box.t + "px";
        },
        () => {
          token.style.removeProperty("transition");
          this.stripPosition(token);
          token.classList.remove("moving_token");
          if (onEnd) onEnd(token);
        },
        duration,
        delay
      );
    },
    slideToObjectAbsolute: function (token, finalPlace, x, y, duration, delay, onEnd, relation, position) {
      token = $(token);
      this.delayedExec(
        () => {
          token.style.transition = "none";
          token.classList.add("moving_token");

          this.attachToNewParentNoDestroy(token, finalPlace, relation, position ? position : "absolute");
          token.offsetHeight; // re-flow
          token.style.transition = "all " + duration + "ms ease-in-out";
          token.style.left = x + "px";
          token.style.top = y + "px";
        },
        () => {
          token.style.removeProperty("transition");
          token.classList.remove("moving_token");
          if (position) token.style.position = position;
          if (onEnd) onEnd(token);
        },
        duration,
        delay
      );
    },

    positionObjectDirectly: function (mobileObj, x, y) {
      x = parseInt(x);
      y = parseInt(y);
      // do not remove this "dead" code it forces reflow
      mobileObj.offsetLeft; // force re-flow
      // console.log("place " + x + "," + y);
      dojo.style(mobileObj, {
        left: x + "px",
        top: y + "px"
      });
      mobileObj.offsetLeft; // force re-flow
    },
    delayedExec: function (onStart, onEnd, duration, delay) {
      if (typeof duration == "undefined") {
        duration = this.defaultAnimationDuration;
      }
      if (typeof delay == "undefined") {
        delay = 0;
      }
      if (this.instantaneousMode) {
        delay = Math.min(1, delay);
        duration = Math.min(1, duration);
      }
      if (delay) {
        setTimeout(function () {
          onStart();
          if (onEnd) {
            setTimeout(onEnd, duration);
          }
        }, delay);
      } else {
        onStart();
        if (onEnd) {
          setTimeout(onEnd, duration);
        }
      }
    },

    projectObject: function (from, postfix) {
      var elem = $(from);
      if (!elem) throw new Error("Cannot find mobile: " + from);
      var over = $("oversurface"); // this div has to exists with pointer-events: none and cover all area with high zIndex
      var par = elem.parentNode;
      var elemRect = elem.getBoundingClientRect();

      var centerY = elemRect.y + elemRect.height / 2;
      var centerX = elemRect.x + elemRect.width / 2;

      //console.log("elemRect", elemRect);

      var offsetY = 0;
      var offsetX = 0;

      var newId = elem.id + postfix;
      var old = $(newId);
      if (old) old.parentNode.removeChild(old);

      var clone = elem.cloneNode(true);
      clone.id = newId;

      var fullmatrix = "";
      while (par != over.parentNode && par != null && par instanceof Element) {
        var style = window.getComputedStyle(par);
        var matrix = style.transform; //|| "matrix(1,0,0,1,0,0)";

        if (matrix && matrix != "none") fullmatrix += " " + matrix;
        par = par.parentNode;
        //console.log("tranform  ",fullmatrix,par.id);
      }

      // Doing this now means I can use getBoundingClientRect
      over.appendChild(clone);

      var cloneRect = clone.getBoundingClientRect();

      // centerX/Y is where the center point must be
      // I need to calculate the offset from top and left
      // Therefore I remove half of the dimensions + the existing offset
      var offsetY = centerY - cloneRect.height / 2 - cloneRect.y;
      var offsetX = centerX - cloneRect.width / 2 - cloneRect.x;

      // Then remove the clone's parent position (since left/top is from tthe parent)
      //console.log("cloneRect", cloneRect);

      clone.style.left = offsetX + "px";
      clone.style.top = offsetY + "px";
      clone.style.transform = fullmatrix;

      return clone;
    },

    phantomMove: function (mobileId, newparentId, duration, delay, onEnd) {
      if (this.instantaneousMode) {
        duration = 0;
        delay = 0;
      } else {
        if (duration === undefined) {
          duration = this.defaultAnimationDuration;
        }
        if (delay === undefined) {
          delay = 0;
        }
      }

      var mobile = $(mobileId);
      var newparent = $(newparentId);
      if (!newparent) {
        console.error("Invalid parent " + newparentId + " for a move");
        return;
      }
      if (!duration && !delay) {
        newparent.appendChild(mobile);
        if (onEnd) onEnd(mobile);
        return;
      }
      var clone = this.projectObject(mobile.id, "_temp");
      mobile.style.opacity = 0;
      newparent.appendChild(mobile);

      var desti = this.projectObject(mobile.id, "_temp2");

      setTimeout(() => {
        clone.style.transitionProperty = "all";
        clone.style.transitionDuration = duration + "ms";
        //clone.offsetTop;
        clone.style.left = desti.style.left;
        clone.style.top = desti.style.top;
        clone.style.transform = desti.style.transform;
        //console.log(desti.style.top, clone.style.top);
        if (desti.parentNode) desti.parentNode.removeChild(desti);
        setTimeout(() => {
          mobile.style.removeProperty("opacity");
          if (clone.parentNode) clone.parentNode.removeChild(clone);
          if (onEnd) onEnd(mobile);
        }, duration);
      }, delay);
    },

    ajaxcallwrapper: function (action, args, handler, nocheck) {
      if (!args) {
        args = [];
      }
      if (nocheck === undefined) nocheck = false;
      delete args.action;

      if (typeof args.lock == "undefined" || args.lock !== false) {
        args.lock = true;
      } else {
        delete args.lock;
      }

      if (handler == null) {
        handler = "callbackErrorHandler";
      }

      if (nocheck || this.checkAction(action)) {
        this.ajaxcall(
          "/" + this.game_name + "/" + this.game_name + "/" + action + ".html",
          args, // SUPPRESS args.lock
          this,
          (result) => {},
          handler
        );
      }
    },
    callbackErrorHandler: function (err) {
      if (err) {
        this.errorCount++;
        if (this.errorCount >= 3) {
          if (!$("button_unblock"))
            this.addActionButton(
              "button_unblock",
              _("Unblock"),
              (e) => {
                this.confirmationDialog(_("Are you sure you want to remove current benefit? Only use this to unblock stuck game"), () => {
                  this.ajaxcallwrapper("unblock", {}, undefined, true);
                });
              },
              undefined,
              undefined,
              "red"
            );
        }
      }
    },
    setMainTitle: function (text, position) {
      var main = $("pagemaintitletext");
      if (position === "before") main.innerHTML = text + " " + main.innerHTML;
      else if (position === "after") main.innerHTML = main.innerHTML + " " + text;
      else main.innerHTML = text;
    },

    addNonButton: function (id, text, tooltip) {
      const div = dojo.place(`<span id="${id}" class="bgabutton bgaimagebutton bgabutton_gray">${text}</span>`, "generalactions");
      if (tooltip) dojo.attr(div, "title", tooltip);
      return div;
    },
    /**
     * This method can be used instead of addActionButton, to add a button which is an image (i.e. resource). Can be useful when player
     * need to make a choice of resources or tokens.
     */
    addImageActionButton: function (id, div, handler, bcolor, tooltip) {
      if (typeof bcolor == "undefined") {
        bcolor = "gray";
      }
      // this will actually make a transparent button
      this.addActionButton(id, div, handler, "", false, bcolor);
      // remove boarder, for images it better without
      dojo.style(id, "border", "none");
      // but add shadow style (box-shadow, see css)
      dojo.addClass(id, "shadow bgaimagebutton");
      // you can also add addition styles, such as background
      if (tooltip) dojo.attr(id, "title", tooltip);
      return $(id);
    },
    addPlayerActionButton: function (player_id, handler, button_prefix, tooltip) {
      if (!player_id) return;
      var id = (button_prefix ?? "button_") + player_id;
      const player = this.gamedatas.players[player_id];
      if (!player) {
        console.error("Cannot find player matching " + player_id);
        return;
      }
      this.addActionButton(id, player.name, handler, undefined, false, "gray");
      dojo.style(id, "border", "none");
      dojo.addClass(id, "shadow bgaimagebutton");
      dojo.setStyle(id, "color", "#" + player.color);
      if (player.background_color) dojo.setStyle(id, "background-color", "#" + player.color_back);
      if (tooltip) dojo.attr(id, "title", tooltip);
      return $(id);
    },
    addCancelButton: function (name, handler) {
      if (!name) name = _("Cancel");
      if (!handler) handler = () => this.cancelLocalStateEffects();
      if ($("button_cancel")) dojo.destroy("button_cancel");
      this.addActionButton("button_cancel", name, handler, null, false, "gray");
    },
    addTradeActionButton: function (index, pay, payType, gain, gainType) {
      var div = this.divResourceCount(payType, pay);
      div += ": ";
      div += this.divResourceCount(gainType, gain);

      var button_id = "button_" + index;

      this.addActionButton(
        button_id,
        div,
        () => {
          dojo.empty("generalactions");
          this.payType = payType;
          if (payType == this.CON.RES_ANY) {
            this.setDescriptionOnMyTurn(_("${you} must choose resources to pay"));
            this.resourceCount = pay;
            this.setupResourceLine(0);
          } else if (gainType == this.CON.RES_ANY) {
            this.setDescriptionOnMyTurn(_("${you} must choose resources to gain"));
            this.resourceCount = gain;
            this.setupResourceLine(1);
          }
          let type = payType == this.CON.RES_ANY ? 1 : 0;
          this.addCancelButton();
          this.addActionButton("button_confirm_0_" + type, _("Confirm"), "onResourceChoiceConfirm");
        },
        undefined,
        undefined,
        "gray"
      );
      dojo.addClass(button_id, "bgaimagebutton");
    },
    addTrackSlotActionButton: function (track_slot, prefix, handler) {
      var adv = track_slot;
      var div = this.divTrackSlot(track_slot);
      var div_id = prefix + "_" + adv;

      if ($(div_id)) {
        div_id += "_x";
      }

      this.addActionButton(div_id, div, handler, undefined, undefined, "gray");
      dojo.addClass(div_id, "bgaimagebutton");
    },
    divTrackSlot: function (track_slot) {
      var adv = track_slot;
      if (typeof track_slot === "number") {
        adv = track_slot + "_0";
      }
      var track = getIntPart(adv, 0);
      var spot = getIntPart(adv, 1);

      var track_name = this.getTr(this.tech_track_types[track]["description"]);
      var track_icon = dojo.create("div", { class: "trackicon trackicon_" + track });
      var div = track_icon.outerHTML;
      if (spot) {
        var info = this.tech_track_data[track][spot];
        if (info) div += " <span class='track_color_" + track + "'>" + this.getTr(info.name) + "/" + spot + "</span>";
        else if (spot == 13) div += " " + _("MAXED OUT");
      } else {
        div += " " + track_name;
      }
      return div;
    },
    divResourceCount: function (type, count) {
      var div = "";
      if (type == this.CON.RES_ANY) {
        for (let index = 0; index < count; index++) {
          div += "<div class='icon_anyres'></div>";
        }
      } else {
        div = "<div class='icon_VP'>" + count + "</div>";
      }
      return div;
    },
    divPlayerName: function (player_id, name) {
      var color = "black";
      var color_bg = "";

      if (this.gamedatas.players[player_id]) {
        var basic = this.gamedatas.players[player_id];
        color = basic.color;
        if (basic.color_back) {
          color_bg = "background-color:#" + basic.color_back + ";";
        }
        if (name === undefined) {
          if (player_id == this.player_id) name = __("lang_mainsite", "You");
          else name = basic.name;
        }
      } else {
        if (name === undefined) name = __("lang_mainsite", "You");
      }

      var you = "<span class='playername' style=\"color:#" + color + ";" + color_bg + '">' + name + "</span>";
      return you;
    },
    divYou: function () {
      return this.divPlayerName(this.player_id);
    },

    setDescriptionOnMyTurn: function (text, moreargs) {
      this.gamedatas.gamestate.descriptionmyturn = text;
      // this.updatePageTitle();
      //console.log('in',   this.gamedatas.gamestate.args, moreargs);
      var tpl = dojo.clone(this.gamedatas.gamestate.args);

      if (!tpl) {
        tpl = {};
      }
      if (typeof moreargs != "undefined") {
        for (var key in moreargs) {
          if (moreargs.hasOwnProperty(key)) {
            tpl[key] = moreargs[key];
          }
        }
      }
      // console.log('tpl', tpl);
      var title = "";
      if (text !== null) {
        tpl.you = this.divYou();
      }
      if (text !== null) {
        title = this.format_string_recursive(text, tpl);
      }
      if (title == "") {
        this.setMainTitle("&nbsp;");
      } else {
        this.setMainTitle(title);
      }
    },

    /* Game Specific Utils */
    /** @Override */
    format_string_recursive: function (log, args) {
      try {
        //console.trace("format_string_recursive(" + log + ")", args);
        if (args.log_others !== undefined && this.player_id != args.player_id) {
          log = args.log_others;
        }

        if (log && args && !args._p) {
          args._p = true;

          if (args.you || log.includes("${you}")) args.you = this.divYou(); // will replace ${you} with colored version
          if (log.includes("${You}")) args.You = this.divYou(); // will replace ${You} with colored version
          // TODO...
          //if (log=='-')							log='';
          if (args.track_name && args.track) {
            args.track_name = this.getPrettyTrackSpotName(args.track, 0);
          }
          if (args.spot_name && args.track && args.spot) {
            args.spot_name = this.getPrettyTrackSpotName(args.track, args.spot);
          }
          if (args.black_name && args.die_black !== undefined) {
            args.black_name = this.getConqDieDiv("black", args.die_black) + " " + args.black_name;
          }
          if (args.red_name && args.die_red !== undefined) {
            args.red_name = this.getConqDieDiv("red", args.die_red) + " " + args.red_name;
          }
        }
      } catch (e) {
        console.error(log, args, "Exception thrown", e.stack);
      }
      return this.inherited(arguments);
    },
    /** @Override */
    showMessage: function (msg, type) {
      if (type == "error" && msg.indexOf("Internal Error") >= 0) {
        var url = this.metasiteurl + "/bug?id=0&table=" + this.table_id;
        this.notifqueue.addChatToLog(
          "<b>" +
            dojo.string.substitute(
              __("lang_mainsite", 'Found a bug? Please report it using <a href="${url}">BGA bug reporting system</a>.'),
              { url: url + '" target="_blank' }
            ) +
            "</b>"
        );
      }
      return this.inherited(arguments);
    },

    /**
     * Add ticking timer to a button, when timeout expires it clicks it automatically
     * @param {string} id - id of the button, default is 'button_confirm'
     * @param {string} name - optional name of button, default is innerHTML of current one
     * @param {number} timeout - timeout for clicking in seconds, default is 10 seconds
     * @returns nothing
     */
    addButtonTimer: function (id, name, timeout) {
      if (id === undefined) id = "button_confirm";
      var butt = $(id);
      if (!butt) return;

      if (name === undefined) name = butt.innerHTML;
      if (timeout === undefined) timeout = 10; // 10 seconds

      if (butt.dataTimerCounter > 0) {
        butt.dataTimerCounter = timeout;
        return; // timer already running
      }

      if (this.instantaneousMode || typeof g_replayFrom != "undefined" || this.inSetup) {
        return;
      }

      if (this.prefs[150].value == 0) {
        // auto confirm off
        return;
      }
      if (this.prefs[150].value == 1) {
        // auto confirm instant
        butt.click();
        return;
      }

      butt.innerHTML = name + " (" + timeout + ")";
      butt.dataTimerCounter = timeout;

      // Reduce the seconds every second, and if we reach 0 click the button
      var passInterval = window.setInterval(() => {
        if (!butt || butt.parentNode == null) {
          clearInterval(passInterval);
        } else if (!butt.dataTimerCounter) {
          clearInterval(passInterval);
          butt.innerHTML = name;
          butt.dataTimerCounter = 0;
        } else {
          var seconds = butt.dataTimerCounter;
          seconds -= 1;
          if (seconds <= 0) {
            clearInterval(passInterval);
            butt.innerHTML = name + " (!!!)";
            butt.click();
          } else {
            butt.innerHTML = name + " (" + seconds + ")";
          }
          butt.dataTimerCounter = seconds;
        }
      }, 1000);
    },

    updateResourceCounter: function (player_id, type, count) {
      //console.log("uddate counter", player_id, type, count);
      if (count < 0) count = 0;
      if (count > 8) count = 8;
      var token_id = "resource_" + player_id + "_" + type;
      var holder = "resource_holder_" + player_id + "_" + count + "_" + type;
      this.placeToken(token_id, holder);
      $("counter_resource_" + type + "_" + player_id).innerHTML = count;

      // have to find prompt resources abd update too
      var line = $("resource_line");
      if (line) {
        line.querySelectorAll(".restype_" + type + ".payment_resource").forEach((res) => {
          res.innerHTML = count;
        });
      }
    },
    incResourceCounter: function (player_id, type, increase) {
      var token_id = "resource_" + player_id + "_" + type;
      if (!$(token_id)) {
        console.error("inc counter", player_id, type, increase, token_id);
        return;
      }
      var current_value = getIntPart($(token_id).parentNode.id, 3);
      var count = current_value + parseInt(increase);
      this.updateResourceCounter(player_id, type, count);
    },

    getResourceCounter: function (player_id, rtype) {
      var counter = "counter_resource_" + rtype + "_" + player_id;
      var count = parseInt($(counter).innerHTML);
      return count;
    },

    toggleTopple: function (bid, value) {
      var oid = dojo.byId("outpost_" + bid) == null ? "building_" + bid : "outpost_" + bid;
      if (!$(oid)) return; // stuff like cube not need to be toppled
      if (value === undefined) dojo.toggleClass(oid, "toppled");
      else this.setTopple(oid, value);
    },

    setTopple: function (div, value) {
      div = $(div);
      if (value === 0 || value === "0") dojo.removeClass(div, "toppled");
      else dojo.addClass(div, "toppled");
    },

    updateCapitalRot: function () {
      // Clear any possible values, then update new ones.
      dojo.query(".capital_cell.possible").removeClass("possible");
      var rot_data = this.capitalRotOptions[this.capitalRot];
      for (var c in rot_data) {
        var coord = rot_data[c];
        dojo.addClass("capital_cell_" + this.player_id + "_" + coord, "possible");
      }
      dojo.addClass("capital_cell_" + this.player_id + "_12_12", "possible"); //out of bounds - always on
    },

    addCapitalGrid: function (player_id) {
      var cell = 26;
      for (var a = 0; a < 15; a++) {
        for (var b = 0; b < 15; b++) {
          var div = dojo.place(
            this.format_block("jstpl_capital_cell", {
              cid: player_id + "_" + a + "_" + b,
              left: b * cell,
              top: a * cell
            }),
            "capital_grid_" + player_id
          );
          this.connect(div, "onclick", "onCapitalCellClick");
        }
      }
    },
    updateConquerDice: function (red, black) {
      this.rolldie("black", black);
      this.rolldie("red", red);
    },

    getConqDieDiv: function (color, roll) {
      if (roll === undefined) roll = this.gamedatas.dice[color];
      var image_types = color + " die-flat die-face die-face-" + roll;
      return '<div class="' + image_types + '"></div>';
    },

    rolldie: function (color, num) {
      if (num === undefined) return;
      var die = $(color + "_die");

      var r = num;
      var sides = 6;
      if (color == "science") sides = 4;
      if (r < 0 || r >= sides) {
        r = Math.floor(Math.random() * sides);
      } else r = parseInt(num);
      //console.log("the result is " + (r + 1));
      dojo.removeClass(die, "rolling");
      dojo.setAttr(die, "data-num", r);
      if (sides == 6) {
        var tooltip = this.getTr(this.gamedatas.dice_names[color][r].name);
        var tooltipx = this.getTooltipTitle(_("Conquer die")) + this.getTooltipMessage(_("Roll:") + " " + tooltip);

        var image = this.getConqDieDiv(color, r);

        tooltipx += this.getTooltipMessage(_("Side Distribution:"));
        for (let index = 0; index < 6; index++) {
          //const node = this.gamedatas.dice_names[color][index];
          tooltipx += this.getConqDieDiv(color, index);
          //if (index<5) tooltip += ', ';
        }

        this.addTooltipHtml(die.id, this.getTooptipHtml(tooltipx, image), 800);
      } else {
        if (this.gamedatas.tech_track_types[r + 1]) {
          var tooltip = _("Science die");
          tooltip += ":<br>" + _("Roll:") + " " + this.getTr(this.gamedatas.tech_track_types[r + 1].name);
          this.addTooltipHtml(die.id, tooltip, 400);
        }
      }

      if (this.instantaneousMode || typeof g_replayFrom != "undefined" || this.inSetup) {
        dojo.addClass(die, "rolled");
      } else {
        dojo.removeClass(die, "rolled");
        die.offsetTop;
        dojo.addClass(die, "rolling");
        setTimeout(() => {
          dojo.removeClass(die, "rolling");
          dojo.addClass(die, "rolled");
          if (r != num) this.showMessage(_("You rolled a die for fun: ") + tooltip, "only_to_log");
        }, 2000);
      }
    },

    setZoom: function (zoom) {
      if (zoom === 0 || zoom < 0.1 || zoom > 10) {
        zoom = 1;
      }
      this.zoom = zoom;

      //var newIndex = ZOOM_LEVELS.indexOf(this.zoom);
      //dojo.toggleClass('zoom-in', 'disabled', newIndex === ZOOM_LEVELS.length - 1);
      //dojo.toggleClass('zoom-out', 'disabled', newIndex === 0);
      var inner = document.getElementById("thething");
      var div = document.getElementById("zoom-wrapper");
      if (zoom == 1) {
        inner.style.removeProperty("transform");
        inner.style.removeProperty("width");
        div.style.removeProperty("height");
      } else {
        inner.style.transform = "scale(" + zoom + ")";
        inner.style.transformOrigin = "0 0";
        inner.style.width = 100 / zoom + "%";
        div.style.height = inner.offsetHeight * zoom + "px";
      }
      localStorage.setItem("tapestry_zoom", "" + this.zoom);
      this.onScreenWidthChange();
    },

    setupInfoPanel() {
      //dojo.place('player_board_config', 'player_boards', 'first');

      dojo.connect($("show-settings"), "onclick", () => this.toggleSettings());
      this.addTooltip("show-settings", "", _("Display game preferences"));

      let chk = $("help-mode-switch");
      dojo.setAttr(chk, "bchecked", false);
      dojo.connect(chk, "onclick", () => {
        console.log("on check", chk);
        dojo.setAttr(chk, "bchecked", !chk.bchecked);
        this.toggleHelpMode(chk.bchecked);
      });
      this.addTooltip(chk.id, "", _("Toggle help mode"));

      // ZOOM

      this.connect($("zoom-out"), "onclick", () => this.setZoom(this.zoom - 0.2));
      this.connect($("zoom-in"), "onclick", () => this.setZoom(this.zoom + 0.2));
      this.connectClass("rotator-control", "onclick", () => this.boardRotate(4)); // to the right

      //$('help-mode-switch').style.display='none';
      this.setupSettings();
      this.setupPreference();
      //this.setupHelper();
      //this.setupTour();

      this.addTooltip("zoom-in", "", _("Zoom in"));
      this.addTooltip("zoom-out", "", _("Zoom out"));
      this.addTooltip("rotator_board", "", _("Rotate main board"));
    },

    /* @Override */
    updatePlayerOrdering() {
      this.inherited(arguments);
      dojo.place("player_board_config", "player_boards", "first");
    },

    setupSettings: function () {
      var panels = document.querySelectorAll("#player_board_config");
      if (panels.length > 1) {
        panels[0].parentNode.removeChild(panels[0]);
      }
      dojo.place("player_board_config", "player_boards", "first");
      for (let index = 150; index <= 151; index++) {
        const element = $("preference_control_" + index);
        if (element) dojo.place(element.parentNode.parentNode, "settings-controls-container");
      }

      var but = $("unblock_button");
      if (!but) {
        but = dojo.create("a", { id: "unblock_button", class: "action-button bgabutton bgabutton_gray", innerHTML: "Unblock stuck game" });

        this.connect(but, "onclick", () =>
          this.confirmationDialog(_("Are you sure you want to remove current benefit? Only use this to unblock stuck game"), () => {
            this.ajaxcallwrapper("unblock", {}, undefined, true);
          })
        );
      }
      dojo.place(but, "settings-controls-container", "last");

      var bug = $("bug_button");
      if (!bug) {
        var url = this.metasiteurl + "/bug?id=0&table=" + this.table_id;
        bug = dojo.create("a", { id: "bug_button", class: "action-button bgabutton bgabutton_gray", innerHTML: "Send BUG", href: url });
      }
      dojo.place(bug, "settings-controls-container", "last");
    },

    setupPreference: function () {
      // Extract the ID and value from the UI control
      var _this = this;
      function onchange(e) {
        var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
        if (!match) {
          return;
        }
        var prefId = +match[1];
        var prefValue = +e.target.value;
        _this.prefs[prefId].value = prefValue;
        _this.onPreferenceChange(prefId, prefValue);
      }

      dojo.query(".preference_control").connect("onchange", onchange);
      // Call onPreferenceChange() now
      dojo.query("#ingame_menu_content .preference_control").forEach((el) => onchange({ target: el }));
    },

    onPreferenceChange: function (prefId, prefValue) {
      console.log("Preference changed", prefId, prefValue);
    },

    toggleSettings() {
      console.log("toggle setting");
      dojo.toggleClass("settings-controls-container", "settingsControlsHidden");

      this.setupSettings();

      // Hacking BGA framework
      if (dojo.hasClass("ebd-body", "mobile_version")) {
        dojo.query(".player-board").forEach((elt) => {
          if (elt.style.height != "auto") {
            dojo.style(elt, "min-height", elt.style.height);
            elt.style.height = "auto";
          }
        });
      }
    },

    toggleHelpMode(b) {
      if (b) this.activateHelpMode();
      else this.deactivateHelpMode();
    },

    activateHelpMode() {
      let chk = $("help-mode-switch");
      dojo.setAttr(chk, "bchecked", true);
      this._helpMode = true;
      dojo.addClass("ebd-body", "help-mode");
      this._displayedTooltip = null;
      document.body.addEventListener("click", this.closeCurrentTooltip);
      this.setDescriptionOnMyTurn(_("HELP MODE Activated. Click on game elements to get tooltips"));
      dojo.empty("generalactions");
      this.addCancelButton(undefined, () => this.deactivateHelpMode());

      let handler = this.onClickForHelp;
      document.querySelectorAll(".withtooltip").forEach((node) => {
        node.addEventListener("click", handler, false);
      });
    },

    deactivateHelpMode() {
      let chk = $("help-mode-switch");
      dojo.setAttr(chk, "bchecked", false);
      this.closeCurrentTooltip();
      this._helpMode = false;
      dojo.removeClass("ebd-body", "help-mode");
      document.body.removeEventListener("click", this.closeCurrentTooltip);
      let handler = this.onClickForHelp;
      document.querySelectorAll(".withtooltip").forEach((node) => {
        node.removeEventListener("click", handler, false);
      });

      this.cancelLocalStateEffects();
    },

    closeCurrentTooltip() {
      if (!gameui._helpMode) return;

      if (gameui._displayedTooltip == null) return;

      gameui._displayedTooltip.destroy();
      gameui._displayedTooltip = null;
    },

    onClickForHelp(event) {
      console.trace("onhelp", event);
      if (!gameui._helpMode) return false;
      event.stopPropagation();
      event.preventDefault();
      gameui.showHelp(event.currentTarget.id);
      return true;
    },

    showHelp: function (id, force) {
      if (!force) if (!this._helpMode) return false;
      if (this.tooltips[id]) {
        dijit.hideTooltip(id);
        this._displayedTooltip = new ebg.popindialog();
        this._displayedTooltip.create("current_tooltip");
        var html = this.tooltips[id].getContent($(id));
        this._displayedTooltip.setContent(html);
        this._displayedTooltip.show();
      }
      return true;
    },

    updateScienceDie: function (die) {
      this.rolldie("science", die - 1);
    },

    updateTapestryCount: function (player_id, delta) {
      this.setPlayerCounter("tapestry", player_id, null, delta);
    },

    updateDeckCounters: function (counters) {
      for (var deck in counters) {
        if (deck == "players") {
          for (player_id in counters[deck]) {
            for (counter_type in counters.players[player_id]) {
              this.setPlayerCounter(counter_type, player_id, counters.players[player_id][counter_type]);
            }
          }
          continue;
        }

        var pre = getPart(deck, 0);
        if (pre != "deck") continue;
        var type = getPart(deck, 1);
        var count = counters[deck];
        var dscount = counters["discard_" + type];
        if (type == "civ") type = "civilization";
        var jsdeck = type + "_" + pre;

        //	console.error(deck+"="+count+" -> "+jsdeck);
        let target = $(deck) ?? $(jsdeck);
        if (!target) continue;

        dojo.setAttr(target, `data-deck-count`, count);
        if (dscount!==undefined) dojo.setAttr(target, `data-discard-count`, dscount);
      }
      console.log("counters=", counters);
    },

    setCounter: function (id, count, delta) {
      if (!delta) delta = 0;

      var node = $(id);
      if (!node) {
        console.trace("invalid counter: " + id);
        this.showError("invalid counter: " + id);
        return;
      }
      if (count === undefined || count === null) {
        count = parseInt(node.innerHTML);
      }
      if (!count) count = 0;
      node.innerHTML = parseInt(count) + parseInt(delta);
    },

    setPlayerCounter: function (key, player_id, count, delta) {
      var id = "counter_" + key + "_" + player_id;
      this.setCounter(id, count, delta);
    },

    normCard: function (card) {
      card.card_type = parseInt(card.card_type ?? card.type);
      card.card_type_arg = parseInt(card.card_type_arg ?? card.type_arg);
      card.card_location = card.card_location ?? card.location;
      card.card_location_arg = card.card_location_arg ?? card.location_arg;
      card.card_location_arg2 = card.card_location_arg2 ?? card.location_arg2;
      card.card_id = parseInt(card.card_id ?? card.id);
      return card;
    },

    setupCivCard: function (card, location) {
      card = this.normCard(card);
      const player_id = card.card_location_arg;
      const civ_id = card.card_type_arg;
      const card_id = card.card_id;
      location = location || card.card_location;

      if (location == "hand") location = "civilization_holder_" + player_id;
      else if (location == "choice") location = "civilizations";
      if (!$(location)) {
        return;
      }

      var div_id = "civilization_" + civ_id;
      var card_div = $(div_id);
      if (card_div) dojo.destroy(card_div);

      var card_div = dojo.place(this.format_block("jstpl_civilization", { cid: civ_id }), location);
      dojo.addClass(card_div, "exp_" + this.civilizations[civ_id]["exp"]);
      dojo.addClass(card_div, "card");
      dojo.setAttr(card_div, "data-card-id", card_id);
      dojo.setAttr(card_div, "data-type-arg", civ_id);
      dojo.setAttr(card_div, "data-type", this.CON.CARD_CIVILIZATION);
      this.addTooltipForToken("civilization", civ_id, "civilization_" + civ_id, 1000);

      this.connect(card_div, "onclick", "onCivilizationClick");

      // Add civ spots.

      var slots = this.civilizations[civ_id]["slots"];
      if (slots) {
        for (var sid in slots) {
          this.setupCivSlot(civ_id, sid, slots);
        }
      }
      var slots = this.civilizations[civ_id]["achi"];
      if (slots) {
        for (var sid in slots) {
          var slot = slots[sid];
          var chid = "civa_" + civ_id + "_" + sid;
          var divs = this.format_block("jstpl_cube_holder", { chid: chid });
          var div = dojo.place(divs, card_div);
          if (slot.top) div.style.top = slot.top + "%";
          if (slot.left) div.style.left = slot.left + "%";
          if (slot.w) div.style.width = slot.w + "%";
          if (slot.h) div.style.height = slot.h + "%";

          dojo.addClass(chid, "achi_slot");
          if (slot.tooltip) {
            var bentml = this.getTooltipMessage(slot.tooltip);
            this.addTooltipHtml(chid, bentml);
          }
        }
      }

      if (civ_id == this.CON.CIV_ISLANDERS) {
        dojo.place('<div id="islanders" class="islanders_map"></div>', div_id);
        this.setupLandOther(1, "islanders", "islanders");
      }
      if (civ_id == this.CON.CIV_MYSTICS && this.getAdjustmentLevel()>=8) {
        dojo.place('<div id="deck_13" class="tapestry_deck"></div>', div_id);
      }

      return card_div;
    },

    setupCivSlot: function (civ_id, sid, slots) {
      var slot = slots[sid];
      const civ = "civilization_" + civ_id;
      var chid = "civ_" + civ_id + "_" + sid;

      const div = dojo.place(this.format_block("jstpl_cube_holder", { chid: chid }), civ);
      if (slot.top) div.style.top = slot.top + "%";
      if (slot.left) div.style.left = slot.left + "%";
      if (slot.w) div.style.width = slot.w + "%";
      if (slot.h) div.style.height = slot.h + "%";
      this.connect(div, "onclick", "onCubeHolderClick");
      if (slot.cl) dojo.addClass(chid, slot.cl);
      if (slot.tooltip) {
        var bentml = this.getTooltipMessage(slot.tooltip);
        this.addTooltipHtml(chid, bentml);
      }
      if (slot.benefit) {
        var bentml = this.getTooltipMessage(_("Benefit") + ": " + this.getBenTooltipStr(slot.benefit));
        this.addTooltipHtml(chid, bentml);
      }
      if (slot.cl == "paintover") {
        const divs = this.getBenIcon(slot.benefit);
        $(chid).innerHTML = divs;
      }
    },

    setupCapitalMat: function (cap_id, player_id) {
      if (!cap_id) {
        dojo.style("capital_mat_" + player_id, "display", "none");
      } else {
        dojo.style("capital_mat_" + player_id, "display", "inline-block");
        var posx = (cap_id - 1) * 20;
        dojo.style("capital_mat_" + player_id, "background-position-x", posx + "%");
        this.addCapitalGrid(player_id);
      }
    },

    setupLand: function (size) {
      // Need to add land hexes to the board.
      for (var a = -size; a <= size; a++) {
        for (var b = -size; b <= size; b++) {
          if (Math.abs(a - b) <= size) {
            dojo.place(
              this.format_block("jstpl_land", { lid: a + "_" + b, top: 50 + 3.885 * (a + b - 1), left: 50.5 + 6.75 * (b - a - 0.75) }),
              "board"
            );
            dojo.connect($("land_" + a + "_" + b), "onclick", this, "onLandClick");
          }
        }
      }
    },
    setupLandOther: function (size, type, location) {
      const dd = 1.1547005; // ratio d:D of hexagon (or h:W in our case)
      const n = size * 2 + 1; // how many hexes in diameter of map, if it has give size (i.e. for size 1 its 3)
      // const h = 100 / nd; // height of each hex base of outer shape in percent

      const ah = 100 / (n - 1); // top let in percentanhe in css is mind bending first element is not at 100 / n its 100 / (n-1)

      const dy = ah / 3;
      const dx = (ah * dd) / 2;

      // Need to add land hexes to the board.
      for (var a = -size; a <= size; a++) {
        for (var b = -size; b <= size; b++) {
          if (Math.abs(a - b) <= size) {
            const coord = a + "_" + b;
            const x = dx * (b - a - 0.75);
            const y = dy * (b + a - 1);
            dojo.place(
              this.format_block("jstpl_land_other", {
                type: type,
                lid: coord,
                top: 3 * dy + y,
                left: 1.75 * dx + x //
              }),
              location
            );
            dojo.connect($(`${type}_${coord}`), "onclick", this, "onLandClick");
          }
        }
      }
    },

    getAdjustmentLevel: function () {
      return parseInt(this.gamedatas.variants.variant_adjustments);
    },

    getPlayerColor: function (id) {
      for (var playerId in this.gamedatas.players) {
        var playerInfo = this.gamedatas.players[playerId];
        if (id == playerId) {
          return playerInfo.color;
        }
      }
      return "000000";
    },

    extraMarkup: function () {
      dojo.query(".marriage").removeClass("marriage");
      dojo.query(".alliance").removeClass("alliance");

      for (var playerId in this.gamedatas.players) {
        if (!this.gamedatas.players[playerId].alive) continue;
        for (let index = 2; index <= 6; index++) {
          var slot = "tapestry_slot_" + playerId + "_" + index;

          var has = false;
          if ($(slot)) {
            has = $(slot).querySelector(".tapestry_card");
          }

          if (has && index != 6) continue;

          var slot1 = "tapestry_slot_" + playerId + "_" + (index - 1);
          if (index == 6) {
            var her = document.querySelector("#civilization_holder_" + playerId + " #civilization_6");
            if (her) slot1 = "civilization_6";
            else continue;
          }
          if (!$(slot1)) continue;
          // MARRIAGE OF STATE
          var card_div = $(slot1).querySelector(".tapestry_23");
          if (card_div) {
            var data = dojo.getAttr(card_div, "data-extra");
            if (!data) continue;
            var other = getPart(data, 0);
            var track = getPart(data, 1);

            var othercolor = this.getPlayerColor(other);
            var cubes = document.querySelectorAll("#tech_track_" + track + " .cube_" + othercolor);
            var player = dojo.getAttr(card_div, "data-locaton-arg");
            var color = this.getPlayerColor(player);

            cubes.forEach((node) => {
              dojo.addClass(node, "marriage");
              dojo.setAttr(node, "marriage-to", color);
              dojo.setStyle(node, "color", "#" + color);
            });
          }

          var card_div = $(slot1).querySelector(".tapestry_5"); // ALIANCE
          if (card_div) {
            var data = dojo.getAttr(card_div, "data-extra");
            if (!data) continue;
            var othercolor = this.getPlayerColor(data);
            var cubes = document.querySelectorAll("#tech_track_4 .cube_" + othercolor);
            var color = this.getPlayerColor(playerId);
            cubes.forEach((node) => {
              dojo.addClass(node, "alliance");
              dojo.setAttr(node, "alliance-to", color);
              dojo.setStyle(node, "color", "#" + color);
            });
          }
        }
      }
    },

    setupNewTapestryCard: function (card_div, card_type_id, div_card_id, real_id, card) {
      const card_id = real_id;
      dojo.addClass(card_div, "tapestry_card tapestry_" + card_type_id);
      dojo.addClass(card_div, "card");
      dojo.setAttr(card_div, "data-card-id", card_id);
      dojo.setAttr(card_div, "data-type-arg", card_type_id);
      dojo.setAttr(card_div, "data-type", this.CON.CARD_TAPESTRY);
      this.addTooltipForToken("tapestry", card_type_id, card_div.id);

      if (!card) card = {};

      if (card.card_location_arg2 !== undefined) {
        dojo.setAttr(card_div, "data-extra", card.card_location_arg2);
        if (card.card_location_arg2 == "espionage") card.espionage = true;
      }
      if (card.card_location_arg !== undefined) {
        dojo.setAttr(card_div, "data-locaton-arg", card.card_location_arg);
      }
      const loc = card?.card_location;

      if (loc.startsWith("tap") || loc.startsWith("civ")) {
        card.espionage = true;
      }
      if (card.espionage) {
        dojo.addClass(card_div, "espionage");
        return;
      }
      return dojo.connect(card_div, "onclick", this, "onTapestryCardClick");
    },

    setupNewTerritoryTile: function (card_div, card_type_id, card_div_id, real_card_id) {
      const card_id = real_card_id;

      dojo.addClass(card_div, "territory_tile territory_tile_" + card_type_id);
      dojo.addClass(card_div, "card");
      dojo.setAttr(card_div, "data-card-id", card_id);
      dojo.setAttr(card_div, "data-type-arg", card_type_id);
      dojo.setAttr(card_div, "data-type", this.CON.CARD_TERRITORY);
      this.addTooltipForToken("territory_tile", card_type_id, card_div.id);
      if (card_type_id >= 49)
        // printed tiles
        dojo.addClass(card_div, "board_printed");
      return dojo.connect(card_div, "onclick", this, "onTerritoryTileClick");
    },

    setupNewSpaceTile: function (card_div, card_type_id, card_div_id, real_card_id) {
      const card_id = real_card_id;
      dojo.addClass(card_div, "space_tile space_tile_" + card_type_id);
      dojo.addClass(card_div, "card");
      dojo.setAttr(card_div, "data-card-id", card_id);
      dojo.setAttr(card_div, "data-type-arg", card_type_id);
      dojo.setAttr(card_div, "data-type", this.CON.CARD_SPACE);

      this.addTooltipForToken("space_tile", card_type_id, card_div.id);
      return dojo.connect(card_div, "onclick", this, "onSpaceTileClick");
    },

    getStockItemIdByDivId: function (div_id) {
      return getPart(div_id, -1);
    },

    addTooltipForToken: function (mainType, typeId, attachTo, delay) {
      if (!delay) delay = 800;
      var fullType = mainType + "_" + typeId;
      if (!attachTo) attachTo = mainType + "_" + typeId;
      attachToNode = $(attachTo);
      if (!attachToNode) {
        console.error("Cannot attach tooltip " + attachTo);
        return;
      }
      dojo.addClass(attachToNode, "withtooltip");

      var dyn = "?";
      var clone = attachToNode.cloneNode(false);
      clone.id = attachToNode.id + "_tt";
      this.stripPosition(clone);
      clone.style.removeProperty("width");
      clone.style.removeProperty("height");
      dojo.removeClass(clone, "stockitem");

      switch (mainType) {
        case "civilization":
          dyn = this.getCivTooltip(typeId);
          break;
        case "tapestry":
          dyn = this.getTapestryTooltip(typeId);
          break;
        case "tech":
          dyn = this.getTechTooltip(typeId);
          break;
        case "decision":
          dyn = this.getDecisionTooltip(typeId);
          break;
        case "territory_hex":
          var parentHex = attachToNode.id;
          let y = getPart(parentHex, 1);
          let x = getPart(parentHex, 2);

          var title = this.getTooltipTitle(_("Territory Hex"));
          dyn = title;
          var coord = "(" + y + "," + x + ")";
          dyn += this.getTooltipMessage(this.getTr("Location") + ": " + coord);
          break;
        case "territory_tile":
          var info = this.gamedatas.card_types[this.CON.CARD_TERRITORY].data[typeId];
          let terrains = info.x;
          var tileben = info["benefit"];
          var title = this.getTooltipTitle(_("Territory Tile") + " " + typeId);
          var desc = "";
          if (typeId >= 49) {
            title = this.getTooltipTitle(_("Territory Hex (Printed)") + " " + typeId);
            clone = dojo.create("div");
          } else {
            desc += this.getTooltipMessage(_("Benefit") + ": " + this.getBenTooltipStr(tileben));
            clone = dojo.create("div", { class: mainType + " " + fullType });
          }
          dyn = title + desc;
          var o = dojo.getAttr(attachToNode, "data-orientation");
          var orient = o ? parseInt(o) : 0;

          if (terrains.length > 6) {
          } else {
            for (let i = 0; i < 6; i++) {
              let terindex = terrains[(i + 6 - orient) % 6];
              if (!terindex) debugger;
              var terr = this.gamedatas.terrain_types[terindex].name;
              dyn += this.getTr(terr) + " ";
            }
          }
          var parentHex = attachToNode.parentNode.id;
          if (parentHex && parentHex.startsWith("land")) {
            let y = getPart(parentHex, 1);
            let x = getPart(parentHex, 2);
            var coord = "(" + y + "," + x + ")";
            dyn += this.getTooltipMessage(this.getTr("Location") + ": " + coord);
            this.removeTooltip(parentHex);
            dojo.removeClass(parentHex, "withtooltip");
          }

          break;
        case "space_tile":
          var tileben = this.gamedatas.card_types[this.CON.CARD_SPACE].data[typeId].benefit;
          var title = this.getTooltipTitle(_("Space Tile"));
          var desc = this.getTooltipMessage(this.getBenTooltipStr(tileben));
          dyn = title + desc;
          clone = dojo.create("div", { class: mainType + " " + fullType });
          break;
        case "landmark":
          var info = this.landmark_data[typeId];
          if (!info) {
            console.error("no landmark " + typeId);
            break;
          }
          var title = this.getTooltipTitle(info["name"]);
          var desc = this.getTooltipMessage(_("Landmark:") + " " + info["width"] + " x " + info["height"]);
          dyn = title + desc;
          //clone.style.width = (30 * info['width']) + "px";
          //clone.style.height = (30 * info['height']) + "px";
          break;
        case "automaciv":
        case 8:
          var info = this.gamedatas.card_types[8].data[typeId];
          var title = this.getTooltipTitle(info["name"]);
          var desc = this.getTooltipMessage(info["description"]);
          dyn = title + desc;
          break;
      }

      var html = this.getTooptipHtml(dyn, clone.outerHTML);
      this.addTooltipHtml(attachToNode.id, html, delay);
    },

    addTooltipHtml: function (id, html, delay) {
      //console.log( 'addTooltipHtml :' + id );
      if (false) {
        // for debugging
      } else {
        return this.inherited(arguments);
      }
    },
    getTooltipTitle: function (name) {
      return "<div class='tooltiptitle'>" + this.getTr(name) + "</div>";
    },
    getTooltipMessage: function (infos) {
      if (!infos) return "";
      if (typeof infos == "string") {
        return "<p>" + this.getTr(infos) + "</p>";
      }
      var result = "";
      for (var did in infos) {
        var desc = infos[did];
        result += "<p>" + this.getTr(desc) + "</p>";
      }
      return result;
    },

    getTooptipHtml: function (message, imagehtml) {
      if (message == null || message == "-") return "";
      if (!message) message = "";

      if (!imagehtml) imagehtml = "";

      return (
        "<div class='tooltipcontainer'>" +
        "<div class='tooltipimage'>" +
        imagehtml +
        "</div>" +
        "<div class='tooltipmessage tooltiptext'>" +
        message +
        "</div></div>"
      );
    },
    getTr: function (name) {
      if (typeof name == "undefined") return null;
      if (typeof name.log != "undefined") {
        name = this.format_string_recursive(name.log, name.args);
      } else {
        name = this.clienttranslate_string(name);
      }
      return name;
    },
    getLandmarkFromBenefit: function (landmarkbe) {
      var landmark = 0;
      if (landmarkbe) {
        for (var i = 1; i <= 12; i++) {
          var lo = this.landmark_data[i];
          if (lo.benefit == landmarkbe[0]) {
            landmark = i;
            break;
          }
        }
      }
      return landmark;
    },
    getPrettyTrackSpotName: function (track, spot) {
      return this.divTrackSlot(track + "_" + spot);
    },

    getTechSpotTooltip: function (ttid, uid) {
      var info = this.tech_track_data[ttid][uid];
      var title = this.getTooltipTitle(this.getPrettyTrackSpotName(ttid, uid));
      var desc = this.getTooltipMessage(info.description);
      var bonus = info.description_bonus;
      var bonus_text = bonus ? _("BONUS:") + " " + this.getTr(bonus) : "";
      var landmarkbe = info.landmark;
      var landmark = this.getLandmarkFromBenefit(landmarkbe);
      var landmark_desc = "";
      if (landmark) {
        landmark_desc = this.getLandmarkTooltipForBenefit(landmark);
        var avail = document.querySelector(".landmark_mat .landmark" + landmark);
        var spot_id = "tech_spot_" + ttid + "_" + uid;

        var spot = $(spot_id);
        //console.log('spot',spot_id,spot);
        if (avail) {
          if (spot) dojo.addClass(spot, "landmark_available");
          landmark_desc += _("Landmark is Available");
        } else {
          if (spot) dojo.removeClass(spot, "landmark_available");
          landmark_desc += _("Landmark is not Available");
        }
      }
      return title + desc + bonus_text + landmark_desc;
    },

    getCivTooltip: function (cid) {
      var info = this.civilizations[cid];
      var title = this.getTooltipTitle(info["name"]);
      var desc = this.getTooltipMessage(info["description"]);
      var adj = "";
      var slots = "";
      if (info.slots) {
        if (info.slots_description !== "") {
          slots = this.getTooltipMessage("<b>" + _("Slots:") + "</b> ");
          slots += this.getTooltipMessage(info.slots_description);
          for (var sid in info.slots) {
            var slot = info.slots[sid];
            //if (slot.wrap)
            {
              slots += "<br/>";
            }
            slots += sid + ": ";
            if (slot.benefit) {
              var tt = this.getBenTooltipStr(slot.benefit, true);
              slots += tt + " ";
            } else if (slot.title) {
              slots += this.getTr(slot.title) + " ";
            }
          }
        }
      }
      if (info.achi) {
        slots += this.getTooltipMessage("<b>" + _("Achievements:") + "</b> ");
        for (var sid in info.achi) {
          var slot = info.achi[sid];
          if (slot.tooltip) {
            var tt = this.getTooltipMessage(sid + ": " + this.getTr(slot.tooltip));
            slots += tt + " ";
          }
        }
      }
      if (this.gamedatas.variants.variant_adjustments) {
        var adj = this.getTooltipMessage(info["adjustment"]);
        if (adj) adj = "<b>" + this.getTooltipMessage(_("Adjustment:")) + "</b>" + adj;
      }
      return title + desc + slots + adj;
    },

    getTapestryTooltip: function (cid) {
      var info = this.tapestry_data[cid];
      var title = this.getTooltipTitle(info["name"]);
      var desc = this.getTooltipMessage(info["description"]);
      if (info.rulings) {
        desc += this.getTooltipMessage("<b>" + _("Rulings:") + "</b> " + info.rulings);
      }
      return title + desc;
    },

    getTechTooltip: function (cid) {
      var info = this.tech_card_data[cid];
      var title = this.getTooltipTitle(info["name"]);
      var circle = this.getTooltipMessage(_("Circle:") + " " + this.getTr(info["circle"]["description"]));
      //circle+=this.getBenTooltipStr(info['circle']['benefit']);
      var square = this.getTooltipMessage(_("Square:") + " " + this.getTr(info["square"]["description"]));
      var req = this.getTrackRequirement(
        this.tech_card_data[cid]["requirement"]["track"],
        this.tech_card_data[cid]["requirement"]["level"]
      );
      var requires = "<p><i>" + _("Square Prerequisite: You or your neighbour must have discovered") + " " + req + "</i></p>";

      var landmark = info.landmark;
      var desc = "";
      if (landmark) {
        desc = this.getLandmarkTooltipForBenefit(landmark);
      }

      return title + circle + square + requires + desc;
    },

    /*
          #columns: i - income, t - topple, at - automa track, st - shadow track 
#st,at values: a - all, f - close to finish, l - landmark or finish
#tt - track tiebreaker exp 1, sci 2, mil 3, tech 4, fav 5, 
#mt map break, start node, first increment, second increment
           */
    getDecisionTooltip: function (typeId) {
      var info = this.gamedatas.card_types[this.CON.CARD_DECISION].data[typeId];
      var title = this.getTooltipTitle(_("Decision Card") + " " + typeId);
      let desc = "";
      desc += this.getTooltipMessage(_("Income:") + " " + this.getAutomaTrackDecisionText("i", info.i));
      desc += this.getTooltipMessage(_("Topple:") + " " + this.getAutomaTrackDecisionText("t", info.t));
      desc += this.getTooltipMessage(_("Atoma track:") + " " + this.getAutomaTrackDecisionText("at", info.at));
      desc += this.getTooltipMessage(_("Shadow track:") + " " + this.getAutomaTrackDecisionText("st", info.st));
      desc += this.getTooltipMessage(_("Tie-breaker:") + " " + this.getAutomaTrackDecisionText("tt", info.tt));
      return title + desc;
    },

    getAutomaTrackDecisionText: function (field, value) {
      switch (field) {
        case "i":
        case "t":
          return value ? _("Yes") : _("No");
        case "at":
        case "st":
          switch (value) {
            case "a":
              return _("All");
            case "f":
              return _("Closest to end of track");
            case "l":
              return _("Closest to unclaimed landmark or end of track");
          }
          return "";
        case "tt":
          if (value == 5) return _("Favourite track");
          if (value < 10) {
            const name = this.getTr(this.tech_track_types[value]["description"]);
            return name ?? "";
          } else {
            const chars = (value + "").split("");
            return chars.map((c) => this.getAutomaTrackDecisionText(field, c)).join(", ");
          }
      }
    },

    //define("FLAG_GAIN_BENFIT",0b0001); // gain befit
    //define("FLAG_PAY_BONUS",  0b0010); // may pay for bonus
    //define("FLAG_FREE_BONUS", 0b0100); // gain free bonus
    //define("FLAG_MAXOUT_BONUS", 0b1000); // gain 5VP if maxout
    getAdvanceTypeStr: function (flags) {
      var str = "";
      if (flags == 0) str = _("no benefits");
      else {
        if (flags & this.CON.FLAG_GAIN_BENFIT) {
          str = _("benefit");
        } else {
          str = _("no benefit");
        }
        if (flags & this.CON.FLAG_PAY_BONUS) {
          str += ", " + _("bonus");
        }
        if (flags & this.CON.FLAG_FREE_BONUS) {
          str += ", " + _("free bonus");
        }
        if (flags & this.CON.FLAG_MAXOUT_BONUS) {
          str += ", " + _("maxout VP");
        }
      }

      return "(" + str + ")";
    },
    /**
			return div icon of benefit
			 */
    getBenIcon: function (benefits_arr, level) {
      if (!benefits_arr) return "";
      if (!level) level = 0;
      if (typeof benefits_arr === "string") {
        benefits_arr = benefits_arr.split(",");
      }

      if (typeof benefits_arr != "object") {
        benefits_arr = [benefits_arr];
      }
      let total = 1;
      if (Array.isArray(benefits_arr)) {
        for (var i in benefits_arr) {
          var ben = benefits_arr[i];
          if (ben === 0) benefits_arr.splice(i, 1);
        }
        total = benefits_arr.length;
      }

      let pay = false;
      if (benefits_arr.p && benefits_arr.g) {
        // pay/gain
        pay = true;
        total = 2;
      }
      var v = 0;
      var count = 1;
      var names = [];

      for (var i in benefits_arr) {
        var ben = benefits_arr[i];
        if (ben === 0) continue;
        if (i === "m") {
          count = ben;
          continue;
        }
        if (i === "h") {
          count = 1; // hidden
          continue;
        }
        if (ben == 15) v++;
        else if (ben >= 500 && ben < 600) v = ben - 500;
        else if (typeof ben === "object") {
          names.push(this.getBenIcon(ben, level + 1));
        } else if (this.benefit_types[ben]) {
          const ben_type = this.benefit_types[ben].r;
          if (ben_type == "die") {
            const die = this.benefit_types[ben].die;
            let div = "";
            if (die == "research") {
              const num = this.gamedatas.dice.science;
              div = this.divTrackSlot(num);
            } else {
              div = this.getConqDieDiv(die);
            }
            names.push(div);
          } else {
            const icon = this.benefit_types[ben].icon;
            if (icon === "no" || icon === 0 || (icon === undefined && ben > 64)) {
              var benname = this.benefit_types[ben]["name"];
              names.push("<div class='named-benefit'>" + this.getTr(benname) + "</div>");
            } else {
              const icon_type = icon == "yes" || !icon ? "" : icon;
              names.push(`<div class="icon icon_ben icon_ben_${ben} ben_type_${ben_type} ben_set_${total} ${icon_type}"></div>`);
            }
          }
        } else {
          names.push(ben);
        }
      }
      if (v > 0) {
        names.push(`<div class="icon icon_ben icon_ben_${ben} ben_type_v ben_set_${total}">${v}</div>`);
      }
      if (pay) {
        return "<div class='pay-benefit'>" + names.join("<div class='pay-separator'></div>") + "</div>";
      }
      let ret = names.join("");

      if (count > 1) ret += " * " + count;
      if (level == 0) return "<div class='gain-benefit'>" + ret + "</div>" + "&ZeroWidthSpace;";
      return ret;
    },
    getBenTooltipStr: function (benefits_arr, inline) {
      // if (this.benefit_types[benefits_arr]) {
      // 	return this.getTr(this.benefit_types[benefits_arr].name);
      // }
      if (!benefits_arr) return "?";
      if (typeof benefits_arr === "string") {
        benefits_arr = benefits_arr.split(",");
      }

      if (typeof benefits_arr === "number") {
        benefits_arr = [benefits_arr];
      }

      var desc = "";
      var v = 0;
      var names = [];
      let count = 1;

      if (benefits_arr.p && benefits_arr.g) {
        const p = this.getBenTooltipStr(benefits_arr.p, inline);
        const g = this.getBenTooltipStr(benefits_arr.g, inline);
        names.push(_(`Pay [${p}] to gain [${g}]`));
      } else {
        for (var i in benefits_arr) {
          var ben = benefits_arr[i];
          if (ben === 0) continue;
          if (i === "m") {
            count = ben;
            continue;
          }
          if (typeof ben === "object") {
            names.push(this.getBenTooltipStr(ben, inline));
            continue;
          }
          if (ben == 15) v++;
          else if (ben >= 500 && ben < 600) v += ben - 500;
          else {
            const bene = this.benefit_types[ben];
            if (bene) {
              if (!bene.auto) {
                var benname = this.benefit_types[ben]["name"];
                names.push(this.getTr(benname));
              }
            } else {
              names.push(ben);
            }
          }
        }
        if (v > 0) {
          names.push(v + " " + _("VP"));
        }
      }
      for (var i in names) {
        var name = names[i];
        if (inline) {
          if (i > 0) desc += ", ";
          desc += name;
        } else desc += "<p>" + name + "</p>";
      }
      if (count > 1) return desc + " * " + count;
      return desc;
    },

    getLandmarkTooltipForBenefit: function (landmark) {
      var desc = "";
      if (landmark) {
        var info = this.landmark_data[landmark];
        var landmarkname = this.getTr(info["name"]);
        desc = "<p>";
        desc += this.getTooltipMessage(_("Landmark: ") + landmarkname + " " + info["width"] + " x " + info["height"]);
        var landmark_tmp = '<div class="landmark landmark${lid}" style="width: ${w}px; height: ${h}px"></div>';

        var div = this.format_string_recursive(landmark_tmp, { lid: landmark, w: info["width"] * 42, h: info["height"] * 42 });
        desc += div;
      }
      return desc;
    },

    getTrackRequirement: function (track, level) {
      if (track < 5) {
        return this.divTrackSlot(track + "_" + level);
      } else {
        var intrack = track - 4;
        return (
          this.getTr(this.income_track_data[intrack]["name"]) +
          " - " +
          this.getTr(this.income_track_data[intrack][level]["name"]) +
          " (" +
          level +
          ")"
        );
      }
    },

    cancelLocalStateEffects: function () {
      if ($("button_confirm")) dojo.destroy("button_confirm");

      this.restoreResources();

      this.restoreServerGameState();
    },

    restoreResources: function () {
      var line = $("resource_line");
      if (!line) return;
      line.querySelectorAll(".resource.chosen_resource").forEach((div) => {
        var gain = div.getAttribute("data-gain");
        var rtype = div.getAttribute("data-restype");
        var increase = gain == "true" ? 1 : -1;
        //var count = this.getResourceCounter(this.player_id, rtype);

        this.incResourceCounter(this.player_id, rtype, -increase); // return resource back
      });

      dojo.destroy("resource_line");
    },

    onScreenWidthChange: function () {
      // set  size for layouts
      var gamenodeId = "thething";
      if (!$(gamenodeId)) return;
      var game_screen = dojo.marginBox(gamenodeId);
      var game_w = game_screen.w;
      var padding = 10;
      var board_w = 1000;
      var player_w = 1230;
      var acc_w = 120;
      var layout;
      if (game_w >= board_w + player_w) layout = "layout-l"; // can possibly fir player board on the side also
      else if (game_w >= board_w + padding + acc_w) layout = "layout-m"; // fits board and extra cards
      else layout = "layout-s"; // smaller

      dojo.removeClass(gamenodeId, "layout-m");
      dojo.removeClass(gamenodeId, "layout-l");
      dojo.removeClass(gamenodeId, "layout-s");
      dojo.addClass(gamenodeId, layout);
    },

    updateBreadCrumbs: function (enabled) {
      try {
        var parent = $("breadcrumbs");

        var order = this.benefitQueueList;
        dojo.empty(parent);

        if (order && enabled) {
          for (var i = 0; i < order.length; i++) {
            var bene = order[i];
            this.showBenInBreadCrumbs(i, bene);
          }

          return;
        }
      } catch (e) {
        console.error("Exception thrown", e.stack);
      }
    },
    placeBreadCrum: function (argid, html) {
      dojo.create("div", { id: argid, class: "breadcrumbs_element", innerHTML: html }, "breadcrumbs");
    },

    showBenInBreadCrumbs: function (i, bene) {
      var player_id = bene.benefit_player_id;
      var argid = "benorder_" + i;
      var divImgOp = i + ". ";
      var color = this.gamedatas.players[player_id]["basic"].color;
      divImgOp += dojo.create("div", { class: "cube cube_" + color + " cube" + color }).outerHTML;
      const cat0 = bene.benefit_category;
      const options = cat0.split(",");
      const cat = options[0];
      options.shift();
      if (cat == "or") divImgOp += _("Choice");
      else if (cat == "o") {
        divImgOp += _("Choice:") + " " + this.getBenTooltipStr(options, true);
        if (bene.reason) divImgOp += "<br>(" + this.getTr(bene.reason) + ")";
      } else if (cat == "a") {
        divImgOp += _("Order Choice:") + " " + this.getBenTooltipStr(options, true);
        if (bene.reason) divImgOp += "<br>(" + this.getTr(bene.reason) + ")";
      } else if (cat == "choice") divImgOp += _("Order Choice");
      else if (cat == "bonus") {
        divImgOp += _("Bonus:");
        divImgOp +=
          bene.benefit_quantity +
          " x " +
          this.getBenTooltipStr(bene.benefit_type) +
          SYM_RIGHTARROW +
          this.getBenTooltipStr(bene.benefit_data);
      } else if (cat == "p") {
        for (const value of options) {
          if (value == "p") continue;
          if (value == "g") {
            divImgOp += SYM_RIGHTARROW;

            continue;
          }
          divImgOp += this.getBenTooltipStr(value);
        }
      } else {
        if (bene.name) divImgOp += this.getTr(bene.name);
        if (bene.reason) divImgOp += "<br>(" + this.getTr(bene.reason) + ")";
      }

      dojo.create(
        "div",
        {
          id: argid,
          class: "breadcrumbs_element",
          innerHTML: divImgOp
        },
        "breadcrumbs"
      );
    },

    ///////////////////////////////////////////////////
    //// Player's action

    onIncomeTurn: function (event) {
      dojo.stopEvent(event);
      if (!this.checkAction("takeIncome")) {
        return;
      }
      if (this.gamedatas.gamestate.args.advances.length > 0) {
        this.confirmationDialog(
          _("Are you sure you wish to take an income turn? You still have possible advance you can afford..."),
          dojo.hitch(this, function () {
            this.ajaxcallwrapper("takeIncome");
          })
        );
      } else {
        this.ajaxcallwrapper("takeIncome");
      }
    },

    onCubeHolderClick: function (event) {
      dojo.stopEvent(event);
      var holder_id = event.currentTarget.id;
      if (this.showHelp(holder_id)) return;
      if (event.currentTarget.parentNode.parentNode.id == "civilizations") {
        dojo.query("#civilizations .selected").removeClass("selected");
        dojo.addClass(event.currentTarget.parentNode, "selected");
        return;
      }

      var cid = getIntPart(holder_id, 1);

      //civ_11_12
      switch (this.gamestate) {
        case "civAbility":
          switch (cid) {
            case 7:
            case 13:
              dojo.toggleClass(holder_id, "clicked");
              return;
            case 15:
              return;

            default:
              // if (!this.checkActiveSlot(holder_id)) return;
              this.ajaxcallwrapper("civTokenAdvance", {
                cid: cid,
                spot: getPart(holder_id, 2)
              });
              this.selectedCiv = 0;
              break;
          }

          break;

        case "placeStructure":
          switch (cid) {
            case 3:
              this.ajaxcallwrapper("placeCraftsmen", {
                slot: getPart(holder_id, 2)
              });
              break;
            default:
              return;
          }

          break;

        default:
          if (cid == this.CON.CIV_MILITANTS) {
            dojo.query(".clicked").removeClass("clicked");
            dojo.toggleClass(holder_id, "clicked");
            dojo.query("#generalactions .bgabutton_blue").removeClass("bgabutton_blue");
            if ($("button_" + holder_id)) dojo.toggleClass("button_" + holder_id, "bgabutton_blue");
            return;
          }

          break;
      }
    },

    onOutpost: function (event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;
      console.log("on click ", id);
      if (!this.checkActiveSlot(id)) return;
      switch (this.gamestate) {
        case "client_standup":
        case "conquer":
          if (this.clientStateArgs.num <= 0 && !dojo.hasClass(id, "selected")) {
            this.showError(_("Maximum is selected"));
            return;
          }
          dojo.toggleClass(id, "selected");
          this.clientStateArgs.num = 3 - document.querySelectorAll(".selected").length;
          this.setDescriptionOnMyTurn(_("Select up to ${num} of your outposts to stand up"), {
            num: this.clientStateArgs.num
          });
          break;
        default:
          this.showMoveUnauthorized();
          return;
      }
    },

    onLandClick: function (event) {
      dojo.stopEvent(event);
      var land_id = event.currentTarget.id;
      if (this.showHelp(land_id)) return;

      switch (this.gamestate) {
        case "explore":
          if (this.gamedatas.gamestate.args.bid == 105) {
            var coords = land_id.split("_");
            this.ajaxcallwrapper("colonialism", {
              U: coords[1],
              V: coords[2]
            });
          } else {
            if (!dojo.hasClass(land_id, "active_slot") || !this.checkAction("explore")) {
              return;
            }
            this.selectedland = land_id;
            var sel = document.querySelector(".territory_tile.selected");
            dojo.query(".selected").removeClass("selected");
            dojo.addClass(land_id, "selected");

            if (this.selectedTile) {
              // move back to stock
              var tile = this.cardsman[this.CON.CARD_TERRITORY].findDivByType(this.selectedTile);
              sel = this.attachToStock(tile.id, this.territory[this.player_id], 0);
              this.selectedTile = null;
            }

            if (sel) {
              this.selectedTile = dojo.getAttr(sel, "data-type-arg");
              const card_id = dojo.getAttr(sel, "data-card-id");

              this.playTerritoryTile(this.player_id, this.selectedTile, card_id, this.selectedRot, land_id);

              if (this.territoryPopup != null) this.resetTerritoryPopup();
              this.showTileRotators();
              break;
            }

            this.showTerritoryPopup();
          }
          break;
        case "civAbility":
          if (!this.checkActiveSlot(land_id)) return;
          this.clientStateArgs.land_id = land_id;

          if (this.selectedCiv == this.CON.CIV_TRADERS) {
            if (this.getAdjustmentLevel() >= 4) {
              this.setClientState("client_trader");
            } else {
              this.sendTrader(0);
            }
            return;
          }
          this.clientStateArgs.extra = land_id;
          this.ajaxClientStateAction(); // who calls it?
          break;
        case "conquer":
          if (!this.checkAction("conquer")) {
            return;
          }
          this.conquerSlot = event.currentTarget.id + "_2";
          if (this.gamedatas.gamestate.args.bid == this.CON.BE_STANDUP_3_OUTPOSTS) {
            this.showMoveUnauthorized();
            return;
          }
          if (this.ownsCiv(this.CON.CIV_ISOLATIONISTS) && this.emptyTerritory() && this.hasIsolationTokens()) {
            this.setClientState("client_isolationist", {
              descriptionmyturn: _("Do ${you} wish to place an isolationist token?")
            });
          } else {
            this.processConquer(false);
          }

          break;
        case "placeStructure":
          if (!this.ownsCiv(this.CON.CIV_NOMADS)) {
            return;
          }
          if (!this.checkActiveSlot(land_id)) return;
          var coords = land_id.split("_");
          this.ajaxcallwrapper("conquer_structure", {
            u: coords[1],
            v: coords[2]
          });
          //  dojo.query(".possible").removeClass("possible");

          break;
        case "moveStructureOnto":
          if (!this.checkActiveSlot(land_id)) return;
          var outpost_id = 0;
          if (this.ownsCiv(this.CON.CIV_MILITANTS)) {
            outpost_id = this.getMilitantOutpost(true);
            if (outpost_id == 0) return;
          }
          this.ajaxcallwrapper("moveStructureOnto", {
            location: land_id,
            id: outpost_id
          });
          break;
        case "client_threasureHunterChoice":
          if (!this.checkActiveSlot(land_id)) return;
          this.clientStateArgs.extra = land_id;
          this.ajaxClientStateAction();
          break;
        default:
          break;
      }
    },

    onTechCardClick: function (event) {
      dojo.stopEvent(event);
      var card = event.currentTarget.id;
      if (this.showHelp(card)) return;

      var id = getPart(card, 2);

      switch (this.gamestate) {
        case "invent":
          dojo.query(".selected").removeClass("selected");
          dojo.addClass(card, "selected");
          this.ajaxcallwrapper("invent", { id: id ? id : 0 });
          break;

        case "upgradeTechnology":
          if (!id) {
            this.showError(_("Select invented technology"));
            return;
          }
          dojo.query(".selected").removeClass("selected");
          dojo.addClass(card, "selected");
          this.ajaxcallwrapper("upgrade", { id: id });
          break;

        case "techBenefit":
          if (!id) {
            this.showError(_("Select invented technology"));
            return;
          }
          dojo.query(".selected").removeClass("selected");
          dojo.addClass(card, "selected");
          if (!this.checkActiveSlot(card)) return;
          this.ajaxcallwrapper("techBenefit", { id: id });
          break;

        case "civAbility":
          if (!this.ownsCiv(8)) {
            return;
          }
          if (!id) {
            this.showError(_("Select invented technology"));
            return;
          }
          dojo.query(".selected").removeClass("selected");
          dojo.addClass(card, "selected");
          this.ajaxcallwrapper("sendInventor", { id: id });
          break;

        default:
          dojo.toggleClass(card, "selected");
          break;
      }
    },

    onCivilizationClick: function (event) {
      dojo.stopEvent(event);

      var id = event.currentTarget.id;
      if (this.showHelp(id)) return;
      if (this.getStateName() == "civAbility") {
        this.selectedCiv = event.currentTarget.getAttribute("data-type-arg");
        dojo.empty("generalactions");
        this.onUpdateActionButtons("civAbility", this.gamedatas.gamestate.args);
        return;
      }

      if (event.target?.id?.startsWith("building")) {
        if (event.target.classList.contains("landmark")) {
          const civ = getPart(id, 1);
          this.ajaxcallwrapper("activatedAbility", { ability: `civ_${civ}`, arg: getPart(event.target.id, 1) }, undefined, true);
          return;
        }
      }

      if (!this.checkActiveSlot(id)) return;

      const selmode = this.clientStateArgs.selmode ?? 2;
      dojo.query(".civilization.selected").removeClass("selected");
      dojo.addClass(id, "selected");
      if (selmode == 1 && $("button_confirm")) {
        $("button_confirm").click();
      }
    },

    onTapestryCardClick: function (event) {
      dojo.stopEvent(event);
      console.log("on tap hand", event);
      var cid = event.currentTarget.id;
      if (this.showHelp(cid)) return;
      switch (this.gamestate) {
        case "playTapestryCard":
          if (this.gamedatas.gamestate.args.bid == "112") {
            var id = getPart(cid, 1);
            this.ajaxcallwrapper("tapestryChoice", { card_id: id });
            break;
          }
          if (!this.checkActiveSlot(cid)) return;
          var id = this.getStockItemIdByDivId(cid);

          this.ajaxcallwrapper("playCard", { card_id: id });
          break;

        case "conquer_trap":
          var id = this.getStockItemIdByDivId(cid);

          this.ajaxcallwrapper("trap", { card_id: id });
          break;
        case "bonus":
          this.tapestry[this.player_id].onClickOnItem(event);
          break;
        case "keepCard":
          const cardsman = this.cardsman[this.CON.CARD_TAPESTRY];
          cardsman.onClickOnItem(event);
          break;

        default:
          if (!this.checkActiveSlot(cid)) return;
          const card = event.currentTarget;
          if (dojo.hasClass(card, "multi-select")) {
            dojo.toggleClass(card, "selected");
            break;
          }
          var id = getPart(cid, 1);
          this.ajaxcallwrapper("tapestryChoice", { card_id: id });
          break;
      }
    },

    onTerritoryTileClick: function (event) {
      dojo.stopEvent(event);
      const div = event.currentTarget;
      var id = event.currentTarget.id;
      console.log("on click " + id);
      if (this.showHelp(id)) return;
      const cardsman = this.cardsman[this.CON.CARD_TERRITORY];

      switch (this.gamestate) {
        case "explore":
          if (!this.checkAction("explore")) {
            return;
          }
          const item = cardsman.getItemFromDiv(div);

          if (this.gamedatas.gamestate.args.ageOfSail) {
            this.territory[this.player_id].unselectAll();
            this.territory[this.player_id].selectItem(item.id);
            return;
          }

          if (this.selectedland == null) return;

          if (this.selectedTile) {
            // move back to stock
            var tile = cardsman.findDivByType(this.selectedTile);
            this.territory[this.player_id].slideIn(tile);
          }

          this.selectedTile = item.type;
          cardsman.unselectAll();

          // Create new territory tile and Transfer tile to active slot... (then engage possible rotations).

          this.placeToken(div, this.selectedland);
          if (this.territoryPopup != null) this.resetTerritoryPopup();
          this.showTileRotators();
          break;

        default:
          cardsman.onClickOnItem(event);
          return;
      }
    },

    onSpaceTileClick: function (event) {
      dojo.stopEvent(event);
      console.log("on click ", event);
      var id = event.currentTarget.id;
      if (this.showHelp(id)) return;
      switch (this.clientStateArgs.action) {
        case "keepCard":
          const cardsman = this.cardsman[this.CON.CARD_SPACE];
          cardsman.onClickOnItem(event);
          return;
        default:
          const type_arg = dojo.getAttr($(id), "data-type-arg");

          this.ajaxcallwrapper("explore_space", { sid: type_arg });
          return;
      }
    },

    onDieClick: function (event) {
      dojo.stopEvent(event);
      var cid = event.currentTarget.id;
      if (this.showHelp(cid)) return;

      if (!this.checkPossibleActions("choose_die")) {
        // roll it for fun
        this.rolldie(getPart(cid, 0), -1);
        return;
      }

      var die = cid == "red_die" || cid == "button_red" ? 0 : 1;

      this.ajaxcallwrapper("choose_die", { die: die });
    },

    onCubeClick: function (event) {
      dojo.stopEvent(event);
      var cid = event.currentTarget.id;
      console.log("cube click " + cid, event);

      if (this.showHelp(cid)) return;
      if (dojo.hasClass(event.currentTarget.parentNode, "activatable")) {
        this.ajaxcallwrapper("activatedAbility", { ability: event.target.parentNode.id }, undefined, true);
        return;
      }

      var id = getPart(cid, 1);
      if (!this.checkActiveSlot(cid)) return;
      this.ajaxcallwrapper("select_cube", { cube: id });
    },

    onSetupConfirm: function (event) {
      dojo.stopEvent(event);
      if (!this.checkPossibleActions("chooseCivilization")) {
        return;
      }

      var civ = 0;
      var items = dojo.query("#civilizations .civilization.selected");
      if (items.length != 1) {
        this.showError(_("You must select 1 civilization"));
        return;
      } else {
        civ = getIntPart(items[0].id, 1);
      }

      var cap = 0;
      if (this.cap_choice.count() > 0) {
        items = this.cap_choice.getSelectedItems();
        if (items.length != 1) {
          this.showError(_("You must select 1 capital"));
          return;
        } else {
          cap = items[0].id;
        }
      }
      this.ajaxcallwrapper(
        "chooseCivilization",
        {
          civ: civ,
          cap: cap
        },
        undefined,
        true
      );
    },

    onResearchDecision: function (event) {
      dojo.stopEvent(event);
      var decision = event.currentTarget.id == "button_research_decline" ? 0 : 1;
      if (decision > 0) {
        decision = getPart(event.currentTarget.id, 3);
        spot = getPart(event.currentTarget.id, 4);
        this.ajaxcallwrapper("research_decision", { decision: decision, spot: spot });
      } else {
        this.ajaxcallwrapper("research_decision", { decision: 0, spot: 0 });
      }
    },

    onOptionBenefitClick: function (event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;
      var bid = getIntPart(id, 2);
      if (this.clientStateArgs.action == "advance") {
        var order = this.gamedatas.gamestate.args.options;
        var first = order[bid];
        var second = order[1 - bid];

        this.clientStateArgs.order = "" + first + "," + second;
        this.ajaxClientStateAction();
      } else {
        this.ajaxcallwrapper(this.clientStateArgs.action, { bid: bid, spot: getPart(id, 4) });
      }
    },

    onAdvance: function (event) {
      dojo.stopEvent(event);
      var id = event.currentTarget.id;

      var coords = id.split("_");
      var track = (this.clientStateArgs.track = coords[2]);
      var spot = (this.clientStateArgs.spot = coords[3]);
      var tech_track = "tech_upgrade_" + track + "_" + spot;
      if (this.showHelp(tech_track)) return;

      switch (this.gamestate) {
        case "playerTurn":
          if (!this.checkAction("advance")) {
            this.showHelp(tech_track, true);
            return;
          }
          this.clientStateArgs.action = "advance";
          if (spot == 0) return;
          if (dojo.hasClass(id, "illegal_slot")) {
            if (spot == 12) this.showError(_("You cannot advance anymore"));
            else this.showError(_("You do not have enough resources to pay"));
            return;
          }
          if (!id.startsWith("button") && !dojo.hasClass(id, "active_slot")) {
            if (this.rejectCount < 3) {
              this.showError(_("You may only advance by a single space"));
              this.rejectCount++;
              return;
            }
            this.confirmationDialog("This is testing feature allowing to advance out or order. Cheat now?", () => {
              this.setupResourceLineForAdvance();
            });
            return;
          } else {
            this.rejectCount = 0;
          }

          this.setupResourceLineForAdvance();
          break;

        case "trackSelect":
          if (!this.checkActiveSlot(id)) return;

          this.ajaxcallwrapper("selectTrackSpot", {
            track: this.clientStateArgs.track,
            spot: this.clientStateArgs.spot
          });

          break;
        case "research":
          this.ajaxcallwrapper("research_decision", { decision: this.clientStateArgs.track, spot: this.clientStateArgs.spot });
          break;
        case "benefitOption":
        case "benefitChoice":
          if (!dojo.hasClass(id, "active_slot")) return;
          var bid = 0;

          for (var bid in this.gamedatas.gamestate.args.tracks) {
            if (track == this.gamedatas.gamestate.args.tracks[bid].track) {
              break;
            }
          }
          this.ajaxcallwrapper(this.clientStateArgs.action, {
            bid: bid,
            track: this.clientStateArgs.track,
            spot: this.clientStateArgs.spot
          });

          break;
        default:
          return;
      }
    },
    onUndo: function (event) {
      this.ajaxcallwrapper(
        "actionUndo",
        undefined,
        (err) => {
          if (err) return;
          this.setMainTitle(_("Undo requested..."));
          dojo.empty("generalactions");
        },
        true
      );
    },

    showTileRotators: function () {
      // Show tile rotators around this.selectedTile
      var node = $("selected_tile");
      dojo.style(node, "display", "block");
      var land = $(this.selectedland);
      dojo.addClass(land.parentNode, "elevated");
      dojo.place("selected_tile", this.selectedland);
      this.applyRotationTile();
      if (!$("button_rotate")) this.addActionButton("button_rotate", _("Rotate"), "onRotateTileRight");
    },

    hideRotators: function () {
      dojo.style("selected_tile", "display", "none");
      dojo.place("selected_tile", "board");
      dojo.destroy("button_rotate");
    },

    showTerritoryPopup: function () {
      this.hideRotators();
      if (this.territoryPopup != null) this.resetTerritoryPopup();

      var html =
        '<div id="territoryPopup">' + this.format_block("jstpl_territory_panel", { title: _("Select a territory tile") }) + "</div>";

      this.territoryPopup = new dijit.TooltipDialog({
        id: "territoryPopup",
        content: html
      });

      dijit.popup.open({
        popup: this.territoryPopup,
        around: $(this.selectedland),
        //orient: {BL:'TL', TL:'BL',BR: 'TR'},
        closable: true
      });

      $("territory_select").appendChild($("territory_tiles_" + this.player_id));
      $("territory_cancel").innerHTML = _("Cancel");
      dojo.connect($("territory_cancel"), "onclick", this, "onTerritoryCancel");

      //this.territory[this.player_id].updateDisplay();
    },

    onTerritoryCancel: function (event) {
      dojo.stopEvent(event);
      dojo.query(".selected").removeClass("selected");
      this.resetTerritoryPopup();
    },

    resetTerritoryPopup: function () {
      $("tapestry_cards_" + this.player_id).after($("territory_tiles_" + this.player_id));
      dijit.popup.close(this.territoryPopup);
      dijit.byId("territoryPopup").destroy();
      this.territoryPopup = null;
      this.territory[this.player_id].updateCounter();
    },

    cancelConfirmTimer() {},

    onPaymentConfirm: function (event) {
      dojo.stopEvent(event);
      this.cancelConfirmTimer();
      var payment = this.checkPaymentComplete(true);
      if (payment === false) return;
      this.clientStateArgs.payment = payment;

      // this.clientStateArgs.options = [];
      // this.clientStateArgs.order = '';
      // var info = this.tech_track_data[this.clientStateArgs.track][this.clientStateArgs.spot];
      // if (info.benefit && info.benefit.choice) {
      // 	this.gamedatas.gamestate.args.options = info.benefit.choice;
      // 	this.setClientState('client_benefitChoice', {
      // 		options: info.benefit.choice,
      // 		descriptionmyturn: _('${you} must choose which benefit to take first')
      // 	});
      // 	return;
      // }

      this.ajaxClientStateAction();
    },

    ajaxClientStateHandler: function (event, argname) {
      if (event) {
        dojo.stopEvent(event);
        let id = event.currentTarget.id;
        if (id) id = id.replace("button_", "");
        if (argname) {
          this.clientStateArgs[argname] = id;
        } else {
          this.clientStateArgs.extra = parseInt(id);
        }
      }
      this.ajaxClientStateAction();
    },

    onCivSpotHandler: function (event) {
      if (event) {
        dojo.stopEvent(event);
        let id = event.currentTarget.id;
        if (id) id = id.replace("button_", "");
        this.clientStateArgs.spot = id;
        const bid = this.clientStateArgs.bid;
        switch (this.clientStateArgs.cid) {
          case this.CON.CIV_TREASURE_HUNTERS:
            const targets = this.gamedatas.gamestate.args.benefits[bid].slots_choice[id].targets;
            const terindex = this.civilizations[this.CON.CIV_TREASURE_HUNTERS].slots[id].ter;
            const args = {
              territory_name: this.gamedatas.terrain_types[terindex].name
            };
            this.setClientStateUpd("client_threasureHunterChoice", () => {
              this.clientStateArgs.cid = this.CON.CIV_TREASURE_HUNTERS;
              this.setDescriptionOnMyTurn(_("TREASURE_HUNTERS: ${you} must choose adjacent territory of type ${territory_name}"), args);
              for (var x in targets) {
                dojo.addClass("land_" + targets[x], "active_slot");
              }
              this.addCancelButton();
            });
            return;
        }
      }
      this.ajaxClientStateAction();
    },

    ajaxClientStateAction: function (action) {
      const args = dojo.clone(this.clientStateArgs);
      const sendAction = action ? action : args.action;
      if (!sendAction) {
        this.showError("Cannot determine the action to take, reload the browser");
        return;
      }
      if (args.extra &&  typeof args.extra == "object") {
        args.extra_js = JSON.stringify(args.extra);
        delete args.extra;
      }
      this.ajaxcallwrapper(sendAction, args);
    },

    selectPaymentResource: function (div, check) {
      var gain = div.getAttribute("data-gain");
      var rtype = div.getAttribute("data-restype");
      var increase = gain == "true" ? 1 : -1;
      var count = this.getResourceCounter(this.player_id, rtype);

      if (check == false) {
        var needres = increase == -1 || count;
        dojo.addClass(div, "unchosen_resource");
        if (dojo.hasClass(div, "chosen_resource") && needres) {
          dojo.removeClass(div, "chosen_resource");
          this.incResourceCounter(this.player_id, rtype, -increase);
          return true;
        }
      } else {
        var needres = increase == 1 || count;
        if (!dojo.hasClass(div, "chosen_resource") && needres) {
          dojo.addClass(div, "chosen_resource");
          dojo.removeClass(div, "unchosen_resource");
          this.incResourceCounter(this.player_id, rtype, increase);
          dojo.addClass(div.parentNode, "complete");
          return true;
        }
      }
      return false;
    },

    onSelectPayment: function (event) {
      dojo.stopEvent(event);
      var chosen = event.currentTarget;

      var box = event.currentTarget.parentNode;

      if (this.selectPaymentResource(chosen, true)) {
        for (var i = 0; i < box.children.length; i++) {
          var child = box.children[i];
          if (child.id != chosen.id) {
            this.selectPaymentResource(child, false);
          }
        }
      }

      if (this.checkPaymentComplete(false)) {
        this.addButtonTimer("button_confirm", undefined, 5);
      } else if ($("button_confirm")) {
        $("button_confirm").dataTimerCounter = 0;
      }
    },

    onToggleAllCards: function (event) {
      dojo.stopEvent(event);
      var node = event.currentTarget;
      var parent = node.parentNode.parentNode;

      var content = parent.querySelector(".expandablecontent");

      var toExpand = dojo.style(content, "display") == "none";

      var arrow = parent.querySelector(".expandablearrow " + "div");

      if (toExpand) {
        dojo.style(content, "display", "block");
        dojo.removeClass(arrow, "icon20_expand");
        dojo.addClass(arrow, "icon20_collapse");
      } else {
        dojo.style(content, "display", "none");
        dojo.removeClass(arrow, "icon20_collapse");
        dojo.addClass(arrow, "icon20_expand");
      }
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
				setupNotifications:
			    
				In this method, you associate each of your game notifications with your local method to handle it.
			    
				Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
					  your tapestry.game.php file.
		    
			*/
    setupNotifications: function () {
      console.log("notifications subscriptions setup");

      dojo.subscribe("resource", this, "notif_resource");
      dojo.subscribe("setupComplete", this, "notif_setupComplete");
      dojo.subscribe("structures", this, "notif_structures");
      dojo.subscribe("newCards", this, "notif_newCards");
      dojo.subscribe("newCardsMine", this, "notif_newCardsMine");
      dojo.subscribe("invent", this, "notif_invent");
      dojo.subscribe("newTechCards", this, "notif_newTechCards");
      this.notifqueue.setSynchronous("newTechCards", 550);

      dojo.subscribe("revealCards", this, "notif_revealCards");
      this.notifqueue.setSynchronous("revealCards", 550);

      dojo.subscribe("explore", this, "notif_explore");
      dojo.subscribe("exploreSpace", this, "notif_exploreSpace");
      dojo.subscribe("conquer_roll", this, "notif_conquer_roll");
      dojo.subscribe("conquer", this, "notif_conquer");
      dojo.subscribe("science_roll", this, "notif_science_roll");

      dojo.subscribe("techtransfer", this, "notif_techtransfer");
      dojo.subscribe("VP", this, "notif_VP");
      this.notifqueue.setSynchronous("notif_VP", 100);
      dojo.subscribe("tapestrycard", this, "notif_tapestrycard");
      dojo.subscribe("moveStructure", this, "notif_moveStructure");
      this.notifqueue.setSynchronous("moveStructure", 300);
      dojo.subscribe("alchemistRoll", this, "notif_alchemistRoll");
      dojo.subscribe("discardCard", this, "notif_discardCard");
      dojo.subscribe("moveCard", this, "notif_moveCard");
      this.notifqueue.setSynchronous("moveCard", 500);
      //dojo.subscribe('advance', this, "notif_advance");
      dojo.subscribe("trap", this, "notif_trap");

      dojo.subscribe("topple", this, "notif_topple");
      dojo.subscribe("benefitQueue", this, "notif_benefitQueue");
      //this.notifqueue.setSynchronous("benefitQueue", 300);

      dojo.subscribe("income", this, "notif_income");
      dojo.subscribe("undoMove", this, "notif_undoMove");
      dojo.subscribe("log", this, "notif_log");
      dojo.subscribe("message_error", this, "notif_message_error");
      dojo.subscribe("message_info", this, "notif_message_info");
      dojo.subscribe("deckCounters", this, "notif_deckCounters");
    },

    notif_resource: function (notif) {
      var player_id = notif.args.player_id;
      var increase = notif.args.increase;
      var type = notif.args.benefit_id;
      var count = notif.args.count;
      this.updateResourceCounter(player_id, type, count);
    },

    notif_setupComplete: function (notif) {
      console.log("setupComplete", notif);
      var capitals = notif.args.capitals;
      var civilizations = notif.args.civilizations;

      var outposts = notif.args.outposts;
      var tokens = notif.args.tokens;

      // CIV
      for (var pid in civilizations) {
        var civ = civilizations[pid];

        this.setupCivCard(civ);
      }

      // CAPITAL
      for (var pid in capitals) {
        var cap = capitals[pid];
        var cap_id = cap["card_type_arg"];
        this.setupCapitalMat(cap_id, pid);
      }

      this.setupStructures(tokens);
      this.setupStructures(outposts);

      this.onScreenWidthChange();
    },

    notif_structures: function (notif) {
      var tokens = notif.args.structures;
      if (!tokens) return;
      this.setupStructures(tokens);
    },

    notif_newCards: function (notif) {
      // General cards..
      console.log("newCards", notif);
      var player_id = notif.args.player_id;
      var card_type = parseInt(notif.args.card_type) || parseInt(notif.args.type);
      var cards = notif.args.cards;
      var count = notif.args.count;

      switch (card_type) {
        case this.CON.CARD_TERRITORY:
          this.moveCards(player_id, cards);
          return;

        case this.CON.CARD_SPACE:
          this.moveCards(player_id, cards);
          return;

        case 3: // tapestry
          if (this.player_id != player_id) {
            for (var a = 0; a < count; a++) {
              this.slideTemporaryObject(
                '<div class="tapestry_deck"></div>',
                "tapestry_deck",
                "tapestry_deck",
                "tapestry_cards_" + player_id,
                300,
                300 * a
              );
            }
          } else if (notif.args.mine) {
            this.moveCards(player_id, cards);
          }
          if (!notif.args.mine)
            //will be opponent card counts only.
            this.updateTapestryCount(player_id, count);
          break;

        case 4: // tech card from deck only.
          for (var cid in cards) {
            var card = cards[cid];
            var holder = "tech_holder_" + player_id + "_0";
            this.placeToken("tech_card_" + card.type_arg, holder);
          }
          break;

        case 5: // Civilization!
          for (var cid in cards) {
            var card = cards[cid];
            var div = this.setupCivCard(card, "civilization_deck");
            this.placeToken(div.id, "civilization_holder_" + player_id);
          }
          break;
        case 7: // Decision
          for (var id in cards) {
            var card = cards[id];
            var type_arg = card.type_arg;
            var divid = "decision_" + type_arg;
            var location = card.location;
            if (!$(divid)) {
              var div = dojo.place(this.format_block("jstpl_decision", { cid: type_arg }), "deck_decision");
              this.addTooltipForToken("decision", type_arg, divid);
            }
            this.placeToken(divid, location);
          }
          break;
        default:
          alert("new cards drawn (opp) that are not coded yet - please refresh! (" + card_type + ")");
      }
    },

    notif_newCardsMine: function (notif) {
      // General cards..
      console.log("newCardsMine", notif);
      notif.args.mine = true;
      this.notif_newCards(notif);
    },

    notif_invent: function (notif) {
      console.log(notif);
      var player_id = notif.args.player_id;
      var card_id = notif.args.card_id;
      var holder = "tech_holder_" + player_id + "_0";
      this.placeToken("tech_card_" + card_id, holder);
    },

    placeToken: function (token, newloc, duration) {
      this.stripPosition(token);
      this.phantomMove(token, newloc, duration);
    },

    placeCard: function (card, player_id) {
      var card_type = parseInt(card.card_type) || parseInt(card.type);
      var notif = {
        args: {
          player_id: player_id || this.player_id,
          cards: [card],
          count: 1,
          type: card_type
        }
      };
      this.notif_newCards(notif);
    },

    notif_newTechCards: function (notif) {
      var cards = notif.args.cards;

      var discards = notif.args.discards;
      for (var cid in discards) {
        var card = discards[cid];
        this.placeToken("tech_card_" + card.type_arg, "tech_discard");
      }

      for (var cid in cards) {
        var card = cards[cid];
        this.moveCard(card, "tech_deck");
      }
    },

    notif_revealCards: function (notif) {
      var cards = notif.args.cards;
      const dia = new ebg.popindialog();
      dia.create("reveal_hand");
      var dest = document.createElement("div");
      dest.className = "revealCards";
      $("popin_reveal_hand_contents").appendChild(dest);

      for (var cid in cards) {
        var card = cards[cid];
        card = this.normCard(card);

        const div = this.cardsman[this.CON.CARD_TAPESTRY].createDiv(card.card_type_arg, card.card_id, dest, card);
        this.addTooltipForToken("tapestry", card.card_type_arg, div.id);
      }
      dia.show();
    },

    playTerritoryTile: function (player_id, tid, card_id, rot, place) {
      const div = this.cardsman[this.CON.CARD_TERRITORY].findDivById(card_id);

      if (div) {
        div.setAttribute("data-orientation", rot);
        this.cardsman[this.CON.CARD_TERRITORY].slide(div, place);
        this.territory[player_id].updateCounter();
      } else {
        const div = this.cardsman[this.CON.CARD_TERRITORY].createDiv(tid, card_id, place);
        div.setAttribute("data-orientation", rot);
      }
    },

    notif_explore: function (notif) {
      var player_id = notif.args.player_id;
      var tile_id = notif.args.card_type_arg;
      var rot = notif.args.orient;
      // Exploration tile will either be in correct position (if active player) or in one of the player stocks.
      // Check player stock (this will confirm if already been positioned).
      var land_id = notif.args.location;
      this.playTerritoryTile(player_id, tile_id, notif.args.card_id, rot, land_id);
    },

    notif_exploreSpace: function (notif) {
      this.moveStockCard(notif.args);
    },

    getSlotByLandId: function (land_id) {
      if (!land_id) return land_id;
      if (!land_id.startsWith("land")) return land_id;
      var land = $(land_id + "_1");
      if (!land) {
        console.error("Cannot find land " + land_id);
        return land_id;
      }
      var slot = land.innerHTML == "" ? 1 : 2;
      var slot_id = land_id + "_" + slot;
      return slot_id;
    },

    notif_conquer: function (notif) {
      // TODO: remove - not sent anymore
      //var player_id = notif.args.player_id;
      var coord = notif.args.coord;
      var outpost_id = notif.args.outpost_id;
      var land_id = "land_" + coord;
      var slot_id = this.getSlotByLandId(land_id);
      this.placeToken("outpost_" + outpost_id, slot_id);
    },

    notif_conquer_roll: function (notif) {
      var die_red = notif.args.die_red;
      var die_black = notif.args.die_black;
      this.updateConquerDice(die_red, die_black);
      if (die_red) this.gamedatas.dice.red = parseInt(die_red);
      if (die_black) this.gamedatas.dice.black = parseInt(die_black);
    },

    notif_science_roll: function (notif) {
      var die = notif.args.die;
      this.updateScienceDie(die);
      this.gamedatas.dice.science = parseInt(die);
    },

    notif_techtransfer: function (notif) {
      var player_id = notif.args.player_id;
      var card_id = notif.args.card_id;
      var holder = "tech_holder_" + player_id + "_0";
      this.placeToken("tech_card_" + card_id, holder);
    },

    notif_VP: function (notif) {
      var player_id = notif.args.player_id;
      var increase = notif.args.increase;

      this.scoreCtrl[player_id].incValue(increase);
      if (!increase && notif.args.score) this.scoreCtrl[player_id].setValue(notif.args.score);

      if (increase) this.animScore(notif.args);
    },

    notif_income: function (notif) {
      var player_id = notif.args.player_id;
      var turn = notif.args.turn_number;
      var phase = notif.args.income_turn_phase;

      if (turn >= 6) {
        this.updateEliminatedPlayer(player_id);
      }

      // income
      var inhelp = $("income_help_" + player_id);

      dojo.setAttr(inhelp, "data-income", phase);
      dojo.setAttr("page-title", "data-income", phase);

      this.updateCurrentEra(player_id, turn);
    },
    updateEliminatedPlayer: function (player_id) {
      this.disablePlayerPanel(player_id);
      if (this.player_id == player_id && !$("button_elim")) {
        var player_data = this.gamedatas.players[player_id];
        if (!player_data || player_data.eliminated) return;
        var div = dojo.create(
          "a",
          { id: "button_elim", class: "bgabutton bgabutton_blue", innerHTML: _("Leave the table") },
          "current_player_board"
        );
        dojo.connect(div, "onclick", this, () => this.ajaxcallwrapper("actionEliminate", [], undefined, true));
      }
    },

    updateCurrentEra: function (player_id, era) {
      var slot = "tapestry_slot_" + player_id + "_" + era;

      dojo.query("#playerBoard_" + player_id + " .current_era").removeClass("current_era");
      if ($(slot)) dojo.addClass(slot, "current_era");
      var rome = "0";
      switch (parseInt(era)) {
        case 1:
          rome = "I";
          break;
        case 2:
          rome = "II";
          break;
        case 3:
          rome = "III";
          break;
        case 4:
          rome = "IV";
          break;
        case 5:
          rome = "V";
          break;
        case 6:
          rome = "X";
          break;
      }
      $("counter_era_" + player_id).innerHTML = rome;
    },

    animScore: function (args) {
      var inc = args.increase;

      var anim_node;
      if (args.place) anim_node = args.place;
      else anim_node = this.getPlaceForReason(args.reason_data, args.player_id);

      if (anim_node && $(anim_node) && !args.noa) {
        // local animation
        //console.log("animScore", args);
        this.animSpinCounter(anim_node, inc, args.player_id);
      }
    },

    getPlaceForReason: function (reason, player_id) {
      if (!reason) return null;
      var split = reason.split(":");
      var kind = split[1];
      var value = split[2];
      if (!kind) return null;
      var home = "playerArea_" + player_id;
      var res = null;
      switch (kind) {
        case "tapestry":
          var her = $("civilization_6");
          if (her) {
            var res = her.querySelector(".tapestry_" + value);
            if (res) return res.id;
          }
          var res = $(home).querySelectorAll(".tapestry_" + value);
          if (res.length == 0) res = $("playerArea_wrapper").querySelectorAll(".tapestry_" + value);
          if (res.length > 0) return res[res.length - 1].id;
          //	if (res) return res[res.length - 1].id;
          break;

        case "spot":
          var res = "tech_spot_" + value;
          break;
        case "civ":
          var res = "civilization_" + value;
          break;
        case "space":
          var res = "space_explored_" + player_id + "_item_" + value;
          break;

        case "tech":
          var res = "tech_card_" + value;
          break;
      }
      return res;
    },

    notif_benefitQueue: function (notif) {
      console.log(notif);
      this.benefitQueueList = notif.args;
      this.updateBreadCrumbs(true);
    },

    // qq: function () {
    //   const card = this.normCard(token);
    //   let [card_id, type, card_type_arg, location, player_id, card_location_arg2] = [
    //     card.card_id,
    //     card.card_type,
    //     card.card_type_arg,
    //     card.card_location,
    //     card.card_location_arg,
    //     card.card_location_arg2,
    //   ];

    //   var color = 0;
    //   if (player_id) color = this.gamedatas.players[player_id]["basic"].color;
    //   type = parseInt(type);
    //   location = this.getSlotByLandId(location);

    //   switch (type) {
    //     case 1: // Income structures (mostly on capital mat)
    //     case 2:
    //     case 3:
    //     case 4:
    //       if (location != "income") {
    //         if (location == "hand") {
    //           location = "player_extras_" + player_id;
    //         }
    //         if (!$(location)) {
    //           console.error("cannot find location for", token);
    //           break;
    //         }
    //         var div = dojo.place(this.format_block("jstpl_building", { type: type, bid: card_id }), location);

    //         this.toggleTopple(card_id, card_type_arg); // TOPPLE AS NESCESSARY

    //         dojo.addClass(div, "income_building");
    //       } else {
    //         // Income buildings
    //         const incomefield = "income" + type;
    //         const income = parseInt(this.gamedatas.players[player_id]["basic"][incomefield]);
    //         for (var b = income + 1; b <= 6; b++) {
    //           location = "income_track_" + player_id + "_" + type + "_" + b;
    //           if ($(location).children.length > 0) continue;
    //           var div = dojo.place(this.format_block("jstpl_building", { type: type, bid: card_id }), location);
    //           this.connect(div, "onmouseover", "onRaiseBuilding");
    //           dojo.addClass(div, "income_building");
    //           break;
    //         }
    //       }

    //       break;
    //     case 5: // outpost
    //       if (location == "hand") {
    //         location = "player_extras_" + player_id;
    //       }
    //       if (!$(location)) {
    //         console.error("cannot find location for", token);
    //         break;
    //       }
    //       var id = "outpost_" + card_id;
    //       var div = $(id);

    //       if (div) dojo.destroy(div);

    //       div = dojo.place(this.format_block("jstpl_outpost", { type: color, oid: card_id }), location);

    //       if (card_type_arg == 1) {
    //         this.toggleTopple(card_id); // TOPPLE AS NESCESSARY
    //       }

    //       if (location.startsWith("civ")) this.connect(div, "mouseover", "onRaiseBuilding");
    //       //console.log("outpost " + card_id + " " + player_id+ " " + color + " -> " + location,token);
    //       break;
    //     case 6: // landmark
    //       if (location == "hand") {
    //         location = "player_extras_" + player_id;
    //       }
    //       if (!$(location)) {
    //         console.error("cannot find location for", token);
    //         break;
    //       }
    //       var div = dojo.place(this.format_block("jstpl_building", { type: type, bid: card_id }), location);
    //       dojo.addClass(div, "landmark landmark" + card_location_arg2);
    //       dojo.addClass(div, "rot" + card_type_arg);
    //       this.addTooltipForToken("landmark", card_location_arg2, div.id);
    //       break;
    //     case 7: // cubes
    //       if (location == "hand") break;
    //       if (!$(location)) {
    //         console.error("cannot find location for", token);
    //         break;
    //       }
    //       var id = "cube_" + card_id;
    //       var div = $(id);
    //       if (div) dojo.destroy(div);

    //       div = dojo.place(this.format_block("jstpl_cube", { type: color, cid: card_id }), location);
    //       if (location.startsWith("civ")) this.connect(div, "mouseover", "onRaiseBuilding");
    //       if (location.startsWith("tech_spot")) {
    //         this.connect(div, "onclick", "onCubeClick");
    //       }
    //       if (dojo.hasClass(location, "activatable")) {
    //         this.connect(div, "onclick", "onCubeClick");
    //         dojo.addClass(div, "active_slot");
    //         dojo.addClass(div, "permanent_active_slot");
    //       }
    //       if (card_location_arg2 && card_location_arg2.startsWith("dic_")) {
    //         dojo.addClass(div, "dictator");
    //       }
    //       if (card_type_arg) {
    //         dojo.setAttr(div, "data-type-arg", card_type_arg);
    //       }
    //       if (location.startsWith("track_fav_") && $("counter_fav_" + player_id)) {
    //         let track = getIntPart(location, 2);
    //         dojo.setAttr("counter_fav_" + player_id, "data-track", track);
    //       }

    //       //console.log("cube " + card_id + " " + color + " -> " + location);
    //       break;
    //     case 8:
    //       this.moveMarker(card_id, location, card_type_arg, player_id);
    //       break;
    //   }
    // },

    setupStructures: function (tokens) {
      if (!tokens) return;
      for (var tid in tokens) {
        var token = tokens[tid];
        this.moveStructure(token);
      }
    },

    moveStructure: function (structure) {
      const card = this.normCard(structure);
      let [token_id, type, type_arg, location, card_location_arg, card_location_arg2] = [
        card.card_id,
        card.card_type,
        card.card_type_arg,
        card.card_location,
        card.card_location_arg,
        card.card_location_arg2
      ];

      var player_id = card_location_arg;
      var color = 0;
      if (player_id) color = this.gamedatas.players[player_id]["basic"].color;

      if (location == "hand") {
        location = "player_extras_" + player_id;
      } else if (location.startsWith("land")) {
        location = this.getSlotByLandId(location);
      } else if (location == "income") {
        // ok
      } else {
        // if (!$(location)) {
        //   console.error("cannot find location for", location);
        //   return;
        // }
      }

      switch (type) {
        case 1: // Income buildings.
        case 2:
        case 3:
        case 4:
          if (location == "income") {
            // Income buildings on income track
            const incomefield = "income" + type;
            const income = parseInt(this.gamedatas.players[player_id]["basic"][incomefield]);
            for (var b = income + 1; b <= 6; b++) {
              location = "income_track_" + player_id + "_" + type + "_" + b;
              if ($(location).children.length > 0) continue;
              var div = dojo.place(this.format_block("jstpl_building", { type: type, bid: token_id }), location);
              this.connect(div, "onmouseover", "onRaiseBuilding");
              dojo.addClass(div, "income_building");
              break;
            }
          } else {
            var div = $("building_" + token_id);
            if (div == null) {
              div = dojo.place(this.format_block("jstpl_building", { type: type, bid: token_id }), location);
              dojo.addClass(div, "income_building");
            } else {
              this.stripPosition(div);
            }

            this.placeToken(div, location);
            this.setTopple(div, type_arg);
          }
          break;
        case 5: // Outpost:
          var id = "outpost_" + token_id;
          var div = $(id);

          if (!div) {
            div = dojo.place(this.format_block("jstpl_outpost", { type: color, oid: token_id }), location);
            if (location.startsWith("civ")) this.connect(div, "mouseover", "onRaiseBuilding");
          }

          this.placeToken(div, location);
          this.toggleTopple(token_id, type_arg);
          break;
        case 6: // Landmark
          var rot = type_arg;
          var div = $("building_" + token_id);
          let newdiv = true;
          if (!div) {
            div = dojo.place(this.format_block("jstpl_building", { type: type, bid: token_id }), location);
            dojo.addClass(div, "landmark landmark" + card_location_arg2);
            this.addTooltipForToken("landmark", card_location_arg2, div.id);
          } else {
            this.stripPosition(div);
            newdiv = false;
          }

          if (location.startsWith("capital_cell_")) {
            dojo.addClass(div, "rot" + rot);
          } else if (location.startsWith("civ")) {
            // nothing special
          } else {
            this.toggleTopple(token_id, type_arg);
          }
          this.placeToken(div, location);

          if (!newdiv && card_location_arg2 <= 12) {
            // RE-GENERATE TECH TRACK TOOLTIPS
            for (var ttid in this.gamedatas.tech_track_types) {
              for (var upgrade = 1; upgrade <= 12; upgrade++) {
                var tech_track = "tech_upgrade_" + ttid + "_" + upgrade;
                var save = this.defaultTooltipPosition;
                this.defaultTooltipPosition = undefined;
                this.addTooltipHtml(tech_track, this.getTechSpotTooltip(ttid, upgrade), 800);
                this.defaultTooltipPosition = save;
              }
            }
          }

          break;
        case 7: // CUBE
          var div = dojo.byId("cube_" + token_id);
          if (location.startsWith("land") && this.ownsCiv(this.CON.CIV_INFILTRATORS, player_id)) {
            location = card.card_location;
          }
          if (location.startsWith("tapestry")) {
            location = "card_" + getPart(location, 1);
          }
          if (!$(location)) {
            console.error("cannot find location for " + token_id, location);
            return;
          }
          if (div != null) {
            this.placeToken(div, location);
          } else if (token_id) {
            if (card.card_location == "hand") break;
            div = dojo.place(this.format_block("jstpl_cube", { type: color, cid: token_id }), location);
            if (location.startsWith("civ")) this.connect(div, "mouseover", "onRaiseBuilding");
            if (location.startsWith("tech_spot")) {
              this.connect(div, "onclick", "onCubeClick");
            }
          }
          if (dojo.hasClass(location, "activatable") && this.player_id == player_id) {
            this.disconnect(div, "onclick", "onCubeClick");
            this.connect(div, "onclick", "onCubeClick");
            dojo.addClass(div, "active_slot");
            dojo.addClass(div, "permanent_active_slot");
          }

          if (card_location_arg2 && card_location_arg2.startsWith("dic_")) {
            dojo.addClass(div, "dictator");
          } else {
            dojo.removeClass(div, "dictator");
          }
          if (type_arg) {
            dojo.setAttr(div, "data-type-arg", type_arg);
          }
          if (location.startsWith("track_fav_") && $("counter_fav_" + player_id)) {
            let track = getIntPart(location, 2);
            dojo.setAttr("counter_fav_" + player_id, "data-track", track);
          }
          break;

        case 8: // MARKER
          this.moveMarker(token_id, card.card_location, type_arg, player_id);
          break;
      }
    },

    notif_moveStructure: function (notif) {
      console.log("moveStructure", notif);
      this.moveStructure(notif.args);
    },

    moveMarker: function (token_id, location, type_arg, player_id) {
      var cube = "marker_" + token_id;
      var color = this.getPlayerColor(player_id);
      if ($(cube)) {
        if (location == "hand") {
          dojo.destroy(cube);
          return;
        }
        dojo.place(cube, location);
      } else {
        if (location == "hand") {
          return;
        }
        dojo.create("div", { id: cube, class: "marker" }, location);
      }
      if (type_arg) {
        dojo.setAttr(cube, "data-type-arg", type_arg);
      } else {
        dojo.removeAttr(cube, "data-type-arg");
      }
      dojo.setStyle(cube, "color", "#" + color);
    },

    notif_alchemistRoll: function (notif) {
      var player_id = notif.args.player_id;
      var token_id = notif.args.token_id;
      var tokens = notif.args.tokens;
      var die = notif.args.die;

      if (token_id == -1) {
        for (var tid in tokens) {
          var token = tokens[tid];
          this.slideToObjectAndDestroy("cube_" + token.card_id, "overall_player_board_" + player_id);
        }
      } else {
        var color = this.gamedatas.players[player_id]["basic"].color;
        var location = "civ_1_" + die;
        dojo.place(this.format_block("jstpl_cube", { type: color, cid: token_id }), location);
      }

      if (this.gamedatas.dice.empiricism > 0) {
        this.gamedatas.dice.empiricism = 0;
        this.setClientState("civAbility", { descriptionmyturn: _("${you} may use your civilization ability") });
      }
    },
    notif_trap: function (notif) {
      var outposts = notif.args.outposts;

      for (var oid in outposts) {
        var outpost = outposts[oid];
        var bid = outpost["card_id"];
        this.toggleTopple(bid);
      }
    },

    notif_tapestrycard: function (notif) {
      var player_id = notif.args.player_id;

      var card_type_arg = notif.args.card_type_arg;
      var card_id = notif.args.card_id;
      var esp = notif.args.espionage || false;
      var tapDivId = this.cardsman[this.CON.CARD_TAPESTRY].getItemDivId(card_id);

      let dest = this.getCardDivLocatonId(notif.args);
      if (!$(dest)) dest = "limbo"; // just to recover
      let div = $(tapDivId);

      if (dest == "tapestry_slot_" + player_id + "_6") {
        // spcial case card is overplayed

        if (div) {
          this.placeToken(tapDivId, dest);
        }
      } else {
        // Create new card

        if (!div) div = this.cardsman[this.CON.CARD_TAPESTRY].createDiv(card_type_arg, card_id, "tapestry_cards_" + player_id, notif.args);
        this.placeToken(div, dest);
      }
      if (this.isCurrentPlayerActive()) this.tapestry[this.player_id].updateCounter();
      else this.updateTapestryCount(player_id, -1);
      this.extraMarkup();
    },

    notif_discardCard: function (notif) {
      console.log("notif_discardCard", notif.args);
      this.moveCard(notif.args);
    },

    moveStockCard: function (card, from) {
      card = this.normCard(card);
      const cardsman = this.cardsman[card.card_type];
      const location_to = this.getCardDivLocatonId(card);
      const div = cardsman.findOrCreateDiv(card.card_type_arg, card.card_id, from, card);
      cardsman.slide(div, location_to);
      if (location_to == cardsman.discard || location_to == 'deck_13') {
        cardsman.fadeOutAndDestroy(div);
      }
      return div;
    },

    moveCards: function (player_id, list, from) {
      if (!list) return;
      let num = 0;
      for (const key in list) {
        const card = list[key];
        this.moveCard(card, from, num * 100);
        if (this.instantaneousMode || this.inSetup) {
          continue; // do not increase num
        }
        num++;
      }
    },

    getCardDivLocatonId: function (card) {
      card = this.normCard(card);
      const card_location = card.card_location;
      switch (card.card_type) {
        case this.CON.CARD_TERRITORY:
          if (card_location == "hand") {
            return "territory_tiles_" + card.card_location_arg;
          }
          if (card_location == "discard") {
            return "territory_deck";
          }
          if (card_location == "deck_territory") {
            return "territory_deck";
          }
          if (card_location.startsWith("civ_21_")) {
            return card_location;
          }
          let location = (card_location == "map" ? "land" : card_location) + "_" + card.card_location_arg2;
          return location;
        case this.CON.CARD_SPACE:
          if (card_location == "hand") {
            return "space_tiles_" + card.card_location_arg;
          }
          if (card_location == "hand_space") {
            return "space_explored_" + card.card_location_arg;
          }
          if (card_location == "discard") {
            return "space_deck";
          }
          if (card_location == "deck_space") {
            return "space_deck";
          }
          return card_location;
        case this.CON.CARD_TAPESTRY:
          if (card_location == "hand") {
            return "tapestry_cards_" + card.card_location_arg;
          }
          if (card_location == "discard") {
            return "tapestry_deck";
          }
          if (card_location == "discard_13") {
            return "deck_13";
          }
          if (card_location == "deck_tapestry") {
            return "tapestry_deck";
          }

          if (card_location.startsWith("era")) {
            var era = card_location[card_location.length - 1];
            return "tapestry_slot_" + card.card_location_arg + "_" + era;
          }

          if (card_location.startsWith("tapestry_")) return card_location.replace("tapestry_", "card_");

          return card_location;
        case this.CON.CARD_TECHNOLOGY:
          if (card_location == "hand") {
            const slot = card.card_location_arg2;
            if (slot > 2) slot = 0;
            var holder = "tech_holder_" + card.card_location_arg + "_" + slot;
            return holder;
          }
          if (card_location == "discard") return "tech_discard";
          if (card_location == "deck_tech_vis") return "tech_deck_visible";
          break;
        case this.CON.CARD_CIVILIZATION: {
          let location = card_location;

          if (location == "hand") location = "civilization_holder_" + card_player_id;
          else if (location == "choice") location = "civilizations";
          else if (location == "discard") location = "civilization_deck";
          return location;
        }
      }
      return card_location;
    },
    moveCard: function (card, from, delay) {
      card = this.normCard(card);
      const [card_id, card_type, card_type_arg, card_location, card_player_id, card_location_arg2] = [
        card.card_id,
        card.card_type,
        card.card_type_arg,
        card.card_location,
        card.card_location_arg,
        card.card_location_arg2
      ];

      switch (parseInt(card_type)) {
        case this.CON.CARD_TAPESTRY:
          this.moveStockCard(card, from);
          return;
        case this.CON.CARD_TERRITORY:
          //  if (card.card_location_arg==this.CON.PLAYER_AUTOMA || card.card_location_arg==this.CON.PLAYER_SHADOW) return;
          const div = this.moveStockCard(card, from);

          if (this.territory[card.card_location_arg] !== undefined) {
            this.territory[card.card_location_arg].updateCounter();
          } else {
            if (div) div.setAttribute("data-orientation", card.card_location_arg);
          }
          return;
        case this.CON.CARD_SPACE:
          this.moveStockCard(card, from);
          if (this.space[card.card_location_arg] !== undefined) {
            this.space[card.card_location_arg].updateCounter();
          }
          return;
        case this.CON.CARD_CIVILIZATION:
          const div_id = `civilization_${card_type_arg}`;
          let location = card_location;

          if (location == "hand") location = "civilization_holder_" + card_player_id;
          else if (location == "choice") location = "civilizations";
          else if (location == "discard") location = "civilization_deck";

          if (!$(div_id)) {
            this.setupCivCard(card, location);
          } else {
            this.placeToken(div_id, location);
          }
          return;
        case this.CON.CARD_TECHNOLOGY: {
          const div_id = `tech_card_${card_type_arg}`;
          const card_div = $(div_id);
          if ($(from) && card_div) dojo.place(card_div, from);
          let location = this.getCardDivLocatonId(card);

          dojo.addClass(card_div, "card");
          dojo.setAttr(card_div, "data-card-id", card_id);
          dojo.setAttr(card_div, "data-type-arg", card_type_arg);
          dojo.setAttr(card_div, "data-type", card_type);

          this.placeToken(div_id, location);
          return;
        }
        default:
          this.showError("Unsupported card type to move " + card_type);
          return;
      }
    },

    findCardDiv(item_id) {
      return document.querySelector(`div[data-card-id='${item_id}']`);
    },

    detachFromStock: function (item_id, stock, new_id, new_loc) {
      if (!stock) {
        return null;
      }
      const stock_div_id = stock.getItemDivId(item_id);
      const stock_div = $(stock_div_id);
      if (!stock_div) {
        return null;
      }

      let div_tile;
      if (stock.mainclass) {
        div_tile = stock_div;
      } else {
        div_tile = dojo.clone(stock_div);
        if (new_id) div_tile.id = new_id;
        dojo.removeClass(div_tile, "stockitem");
        stock.removeFromStockById(item_id);
      }
      if (new_loc) {
        this.stripPosition(div_tile);
        dojo.place(div_tile, new_loc);
      }

      return div_tile;
    },

    attachToStock(div, stock, duration) {
      div = $(div);
      if (!div) return;

      const item_id = dojo.getAttr(div, "data-card-id");
      const item_type = dojo.getAttr(div, "data-type-arg");
      if (stock.mainclass && item_id && item_type) {
        // this is antistock
        this.placeToken(div, stock.container_div, duration);
      } else {
        // XXX?
        dojo.destroy(div);
      }
      const stock_div_id = stock.getItemDivId(item_id);
      return $(stock_div_id);
    },

    notif_moveCard: function (notif) {
      var player_id = notif.args.player_id;
      var from = notif.args.from;
      console.log("notif_moveCard", notif.args);
      if (notif.args.cards) this.moveCards(player_id, notif.args.cards, from);
      else this.moveCard(notif.args, from);
    },

    notif_topple: function (notif) {
      var bid = notif.args.bid;
      this.toggleTopple(bid);
    },

    notif_bonusPayment: function (notif) {
      //nothing, there is another call discardCard after
    },
    notif_undoMove: function (notif) {
      //['undoMove' => $move, 'partial_undo' => 1]
      this.setUndoMove(notif.args.undo_move, notif.args.partial_undo, notif.args.player_id);
    },
    notif_log: function (notif) {
      if (notif.log) console.log(notif.log, notif.args);
      else if (notif.args && notif.args.log) console.log(notif.args.log, notif.args);
      else console.log(notif.args);
    },
    notif_deckCounters: function (notif) {
      console.log(notif);
      this.updateDeckCounters(notif.args);
    },

    isReadOnly: function () {
      return this.isSpectator || typeof g_replayFrom != "undefined" || g_archive_mode;
    },

    notif_message_error: function (notif) {
      if (!this.isReadOnly()) {
        var message = this.format_string_recursive(notif.log, notif.args);
        this.showMessage(_("Warning:") + " " + message, "warning");
      }
    },
    notif_message_info: function (notif) {
      if (!this.isReadOnly()) {
        var message = this.format_string_recursive(notif.log, notif.args);
        this.showMessage(_("Announcement:") + " " + message, "info");
      }
    },
    setUndoMove: function (undoMove, partial_undo, player_id) {
      this.gamedatas.undo_move = undoMove;
      this.gamedatas.partial_undo = partial_undo;
      this.gamedatas.undo_player_id = player_id;
      undoMove = parseInt(undoMove);
      var moveNode = document.querySelector('.movestamp[data-move-id="' + undoMove + '"]');
      var place = "after";
      var placeNode = null;
      if (!moveNode) {
        undoMove++;
        moveNode = document.querySelector('.movestamp[data-move-id="' + undoMove + '"]');
      }
      if (!moveNode) {
        if (this.lastMoveId < this.gamedatas.undo_move) {
          placeNode = document.querySelector("#logs > *");
          place = "before";
        }
      } else {
        placeNode = moveNode.parentNode;
        place = "after";
      }

      var undoButId = "button_undo_y";
      dojo.destroy(undoButId);
      if (placeNode) {
        var name = this.divPlayerName(player_id);
        var messsage = this.format_string_recursive(_("${player} may undo up to this point"), {
          player: name
        });
        var div = dojo.create("div", {
          id: undoButId,
          innerHTML: messsage,
          class: "undomarker",
          title: _("Click to undo your move up to this point"),
          onclick: "return false"
        });
        dojo.place(div, placeNode, place);
        dojo.connect(div, "onclick", this, "onUndo");
        console.log("undo move connected #" + this.gamedatas.undo_move + " " + placeNode.id);
      } else {
        console.log("undo move not connected #" + this.gamedatas.undo_move);
      }
    },
    /** @Override */
    addMoveToLog: function (log_id, move_id) {
      this.inherited(arguments);
      //console.log("log",log_id, move_id);
      if (!move_id) return;
      this.lastMoveId = move_id;

      //lognode.setAttribute('data-move-id', move_id);

      var prevmove = document.querySelector('[data-move-id="' + move_id + '"]');
      if (!prevmove) {
        var lognode = $("log_" + log_id);
        tsnode = document.createElement("div");
        tsnode.classList.add("movestamp");
        tsnode.innerHTML = _("Move #") + move_id;
        lognode.appendChild(tsnode);

        tsnode.setAttribute("data-move-id", move_id);
      }
    },
    /*
     * [Undocumented] Override BGA framework functions to call onLoadingLogsComplete when loading is done
     */
    setLoader: function (image_progress, logs_progress) {
      this.inherited(arguments);
      //console.log("loader", image_progress, logs_progress)
      if (!this.isLoadingLogsComplete && logs_progress >= 100) {
        this.isLoadingLogsComplete = true;
        this.onLoadingLogsComplete();
      }
    },

    onLoadingLogsComplete: function () {
      console.log("Loading logs complete");
      this.setUndoMove(this.gamedatas.undo_move, this.gamedatas.partial_undo, this.gamedatas.undo_player_id);
    }
  });
});

function reloadCss() {
  var links = document.getElementsByTagName("link");
  for (var cl in links) {
    var link = links[cl];
    if (link.rel === "stylesheet" && link.href.includes("99999")) {
      var index = link.href.indexOf("?timestamp=");
      var href = link.href;
      if (index >= 0) {
        href = href.substring(0, index);
      }

      link.href = href + "?timestamp=" + Date.now();

      console.log("reloading " + link.href);
    }
  }
}

function getIntPart(word, i) {
  return parseInt(getPart(word, i));
}

function getPart(word, i) {
  var arr = word.split("_");
  if (i < 0) i = arr.length + i;
  if (i >= arr.length) return "";
  return arr[i];
}
