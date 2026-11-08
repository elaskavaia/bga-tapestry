/** Game markup that used to live in tapestry_tapestry.tpl */

const range = (from, to) => Array.from({ length: to - from + 1 }, (_value, index) => from + index);
const join = (items, render) => items.map(render).join("\n");

function getTapestrySlotsHtml(owner) {
  return join([1, 2, 3, 4, 6], (slot) => `<div id="tapestry_slot_${owner}_${slot}" class="tapestry_slot tapestry_slot${slot}"></div>`);
}

function getDieHtml(color) {
  const faces = ["front", "back", "left", "right", "top", "bottom"];
  return `<div id="${color}_die_home" class="die_wrapper">
    <div id="${color}_die" class="die shape ${color}">
      ${join(faces, (face) => `<div class="die-face  die-face-${face}"></div>`)}
    </div>
  </div>`;
}

function getScienceDieHtml() {
  const facebox = (box) => `<div class="die12-facebox${box} die12-facebox">
    ${join(range(1, 6), (face) => `<div class="face${face} die12-face"></div>`)}
  </div>`;
  return `<div id="science_die" class="science_die">
    <div id="science_die_3d" class="die12-wrapper camera">
      <div class="die12 die">
        ${facebox(1)}
        ${facebox(2)}
      </div>
    </div>
  </div>`;
}

function getAchievementsHtml() {
  const slots = [3, 3, 2];
  return join(
    slots,
    (count, index) => `<div id="achievement_${index + 1}" class="achievement">
      ${join(range(1, count), (slot) => `<div id="achievement_${index + 1}_${slot}" class="cube_holder achievement${slot}"></div>`)}
    </div>`
  );
}

function getLandmarkSlotsHtml(from, plots) {
  return join(plots, (plot, index) => {
    const slot = from + index;
    return `<div id="landmark_mat_slot${slot}" class="plot${plot} landmark_slot${slot == 19 ? " hidden" : ""}"></div>`;
  });
}

function getAllCardsHtml() {
  return join(
    ["tech", "tap", "civ", "terr", "space"],
    (kind) => `<div id="allcards_${kind}" class="allcards_${kind} expandable">
      <div class="expandabletitle">
        <a href="#" id="allcards_${kind}_toggle" class="expandabletoggle expandablearrow">
          <div class="icon20 icon20_expand"></div>
          <span id="allcards_${kind}_title"></span>
        </a>
      </div>
      <div class="expandablecontent " id="allcards_${kind}_content">
      </div>
    </div>`
  );
}

function getPlayerAreaHtml(player) {
  const id = player.id;
  const resources = ["coins", "worker", "food", "culture"];
  const incomeTracks = join(
    range(1, 4),
    (track) => `<div id="income_track_${id}_${track}" class="income_track income_track${track}">
      ${join(range(1, 6), (space) => `<div id="income_track_${id}_${track}_${space}" class="income_track_space income_track_${track}_${space}"></div>`)}
    </div>`
  );
  const extras = ["tapestry_cards", "submerged_cards", "territory_tiles", "space_tiles", "space_explored"];
  return `<div id="playerArea_${id}" class="playerArea">
    <div id="playerBoard_${id}" class="playerBoard">
      <div id="miniboard_${id}" class="miniboard">
        <div class="icon_era counter counter_card" id="counter_era_${id}"></div>
        ${join(resources, (name, index) => `<div class="icon_${name} counter counter_resource" id="counter_resource_${index + 1}_${id}"></div>`)}
        <div class="icon_tapestry counter counter_card" id="counter_tapestry_${id}"></div>
        <div class="icon_terra counter counter_card" id="counter_terra_${id}"></div>
        <div class="icon_space counter counter_card" id="counter_space_${id}"></div>
      </div>
      <div id="civilization_holder_${id}" class="civilization_holder"></div>
      <div id="income_mat_${id}" class="income_mat">
        <div id="income_help_${id}" class="income_help"></div>
        ${incomeTracks}
        <div id="pb_${id}" class="player_board_income"></div>
        ${getTapestrySlotsHtml(id)}
        <div id="resource_track_${id}" class="resource_track"></div>
      </div>
      <div class="capital_wrapper">
        <div id="capital_mat_${id}" class="capital">
          <span id="player_mat_title_${id}" class="player-name player-title player-${player.color}" style="color: #${player.color}">${player.name}</span>
          <div id="capital_grid_${id}" class="capital_grid"></div>
        </div>
        <div id="tech_holder_${id}" class="tech_holders">
          ${join([2, 1, 0], (slot) => `<div id="tech_holder_${id}_${slot}" class="tech_slot tech_slot_${slot}"></div>`)}
        </div>
      </div>
    </div>
    <div id="player_extras_${id}" class="player_extra hand">
      ${join(extras, (name) => `<div id="${name}_${id}" class="${name}"></div>`)}
    </div>
  </div>`;
}

/** The whole game area, one player area per entry of the sorted players array */
export function getGameAreaHtml(players) {
  return `<div id="setup_choices">
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
            <div id="tech_deck" class="card tech_card tech_card_0 tech_deck"></div>
            <div id="tech_discard" class="card tech_card_0 tech_discard"></div>
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
          ${getDieHtml("black")}
          ${getDieHtml("red")}
        </div>
        <div id="dice_holder2">
          ${getScienceDieHtml()}
        </div>
        <div id="achievement_holder">
          ${getAchievementsHtml()}
        </div>
      </div>
      <button id="rotator_board2" class="fa fa-repeat fa-2x config-control rotator-control"></button>
    </div>
    <div id="playerArea_wrapper" class="playerArea_wrapper">
      ${join(players, getPlayerAreaHtml)}
    </div>
    <div id="game_wrapper_bottom">
      <div id="landmark_mat" class="landmark_mat">
        ${getLandmarkSlotsHtml(1, ["3_2", "2_2", "4_2", "3_3", "3_3", "2_2", "3_2", "4_4", "2_2", "2_2", "2_3", "3_3"])}
      </div>
      <div id="landmark_extra" class="landmark_extra">
        ${getLandmarkSlotsHtml(13, ["2_2", "3_3", "3_2", "2_4", "2_2", "2_2", "2_2"])}
      </div>
      <div id="income_mat_automa" class="income_mat_automa" data-level="2">
        <div id="slot_automa_civ"></div>
        <div id="slot_automa_level" class="automalevel"></div>
        <div id="slot_automa_landmark"></div>
        ${getTapestrySlotsHtml(1)}
      </div>
      <div id="decision_pair" class="decision_pair"></div>
      <div id="deck_decision" class="deck_decision deck">
        <div id="discard_decision" class="discard discard_decision"></div>
      </div>
      <div id="deck_progress" class="deck_progress deck"></div>
    </div>
    <div id="allcards" class="allcards">
      ${getAllCardsHtml()}
    </div>
    <div id="limbo" class="limbo">
      ${join(range(1, 33), (num) => `<div id="tech_card_${num}" class="card tech_card tech_card_${num}"></div>`)}
      <div id="player_board_config">
        <div id="player_config">
          <div id="player_config_row">
            <button id="rotator_board" class="fa fa-repeat fa-2x config-control rotator-control"></button>
            <button id="zoom-out" class=" fa fa-search-minus fa-2x config-control"></button>
            <button id="zoom-in" class=" fa fa-search-plus fa-2x config-control"></button>
            <button id="help-mode-switch" class="fa fa-question-circle fa-2x config-control help-mode-switch"></button>
            <button id="show-settings" class="config-control fa fa-cog fa-2x"> </button>
          </div>
          <div class="settingsControlsHidden" id="settings-controls-container">
          </div>
        </div>
      </div>
    </div>
    <div id="oversurface"></div>
  </div>
</div>
`;
}

export function getBuildingHtml(type, id) {
  return `<div class="building building${type}" id="building_${id}"></div>`;
}

export function getCubeHtml(color, id) {
  return `<div class="cube cube${color} cube_${color}" id="cube_${id}"></div>`;
}

export function getOutpostHtml(color, id) {
  return `<div class="outpost outpost${color}" id="outpost_${id}"></div>`;
}

export function getCubeHolderHtml(id) {
  return `<div class="cube_holder ${id}" id="${id}"></div>`;
}

export function getResourceTokenHtml(type, id) {
  return `<div class="resource resource${type} restype_${type}" id="resource_${id}" data-restype="${type}"></div>`;
}

export function getResourceZoneHtml(id) {
  const holders = range(1, 4).map(
    (holder) => `<div id="resource_holder_${id}_${holder}" class="resource_holder resource_holder${holder}"></div>`
  );
  return `<div id="resource_zone_${id}" class="resource_zone">${holders.join("")}</div>`;
}

export function getResourceLineHtml() {
  return `<div class="payment_line resource_line" id="resource_line"></div>`;
}

export function getTechTrackHtml(trackId, track) {
  return `<div class="tech_track" id="tech_track_${trackId}" style="top: ${track.top}%; left: ${track.left}%; transform: rotate(${track.rot}deg);"></div>`;
}

export function getTechAgeHtml(trackId, age, width) {
  return `<div class="tech_age" id="tech_age_${trackId}_${age}" style="width: ${width}%;"></div>`;
}

export function getTechUpgradeHtml(trackId, upgrade) {
  return `<div class="tech_upgrade" id="tech_upgrade_${trackId}_${upgrade}"></div>`;
}

export function getTechSpotHtml(trackId, spotId) {
  return `<div class="tech_spot" id="tech_spot_${trackId}_${spotId}"></div>`;
}

export function getCivilizationHtml(civ) {
  return `<div class="civilization civilization_${civ}" id="civilization_${civ}"></div>`;
}

export function getAutomaCivHtml(civ) {
  return `<div id="automaciv_${civ}" class="automaciv automaciv_${civ}"></div>`;
}

export function getDecisionHtml(type) {
  return `<div id="decision_${type}" class="decision decision_${type}"></div>`;
}

export function getLandHtml(landId, top, left) {
  return `<div class="land_wrapper" id="land_wrapper_${landId}" style="top: ${top}%; left: ${left}%;"><div class="land" id="land_${landId}"><div id="land_${landId}_1" class="land_slot1 land_slot"></div><div id="land_${landId}_2" class="land_slot2 land_slot"></div></div></div>`;
}

export function getLandOtherHtml(type, landId, top, left) {
  return `<div class="land_wrapper ${type}_wrapper" id="${type}_wrapper_${landId}" style="top: ${top}%; left: ${left}%;"><div class="land ${type}" id="${type}_${landId}"></div></div>`;
}

export function getCapitalCellHtml(id, left, top) {
  return `<div id="capital_cell_${id}" class="capital_cell" style="left:${left}px; top: ${top}px;"></div>`;
}

export function getTerritoryPopupHtml() {
  return `<div id="territoryPopup"><div id="territory_panel"><h3>${_("Select a territory tile")}</h3><div id="territory_select"></div><div id="territory_buttons"><div id="territory_cancel" class="bgabutton bgabutton_blue"></div></div></div></div>`;
}
