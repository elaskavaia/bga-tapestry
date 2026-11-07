# Plan: remove tapestry.view.php and tapestry_tapestry.tpl

Status: not started (2026-11-06). Source: the Studio dry run warning, TODO "STUDIO BUILD WARNINGS".
Follows the `modernize-bga-game-project` skill workflows `bga-jstpl` then `bga-remove-view-tpl`.

The view and template are obsolete (BGA Studio Migration Guide, "Removal of view and template").
Two steps, one commit each.

## Current state

- `tapestry_tapestry.tpl` (415 lines): `{OVERALL_GAME_HEADER}`, a `<script>` block that holds only
  26 global `jstpl_*` declarations, the static page markup, one `playerArea` block per player,
  `{OVERALL_GAME_FOOTER}`.
- `tapestry.view.php` inserts `playerArea` for every player from `loadPlayersBasicInfosWithBots()`
  (bots included): the current player first, then the players after them in turn order, then the ones
  before. A spectator gets turn order. Variables: `{X}` player id, `{C}` color, `{PLAYER_NAME}`.
- 32 `this.format_block("jstpl_x", args)` calls in tapestry.js and modules/*.js, no dynamically
  built template names. `format_block` is not in bga-framework.d.ts, it is legacy too.
- `setup()` in tapestry.js moves template elements around for the bots (`shadow_in_play`,
  `automa_in_play`) and `setZoom` needs `zoom-wrapper`, so the markup must exist first.

## Step 1: jstpl to template literals (workflow `bga-jstpl`)

The `.tpl` and view stay in place, only the script block goes. No `format_block` / `format_string`
fallback and no snippet strings kept as properties.

- Unused, delete (8): some_game_item, landmark, tapestry_card, territory, space, payment_panel,
  income_wrapper, cancel.
- One call site, inline a template literal at the call (11): tech_track, tech_age, tech_upgrade,
  civilization, land, land_other, territory_panel, capital_cell, resource_zone, decision, automa_civ.
- Several call sites, one method returning a template literal on `bgagame.tapestry`, all calls go
  through it (7): building (8), cube (3), tech_spot, resource_token, cube_holder, outpost,
  resource_line (2 each).
- Per call: evaluate each argument once (an arg used twice in the template goes into a local first),
  keep ids, classes, whitespace and escaping as they are.
- Delete the `<script>` block from the tpl.
- Verify: no `jstpl_` and no `format_block` left in maintained JS.

## Step 2: layout into JS (workflow `bga-remove-view-tpl`)

- New `modules/taplayout.js`: a Dojo `declare("bgagame.taplayout", null, ...)` like `tapantistock.js`,
  added to the `define` list of tapestry.js, so tapestry.js does not grow. It holds:
  - the static page markup, placed with
    `this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", ...)`;
  - a per player `playerArea` builder. Order from `gamedatas.players[id].no` (bots have it too, same
    `player_no` the view sorted by in `loadPlayersBasicInfosWithBots`), rotated to start at the current
    player, spectators in plain `no` order. Sort explicitly: JS iterates numeric keys ascending;
  - `map(...).join("")` over arrays instead of repeated markup: 33 limbo tech cards, 4x6 income track
    spaces, tapestry slots, landmark slots (class list), die faces. Keep `_()` literals explicit in the
    arrays so translation extraction finds them.
- tapestry.js: build the layout as the first thing in `setup()`, before `setZoom` and any DOM lookup.
- Do not copy `{OVERALL_GAME_HEADER}` / `{OVERALL_GAME_FOOTER}`, they are framework wrappers.
- Replace `{X}`, `{C}`, `{PLAYER_NAME}` with gamedatas values. Player names were inserted raw by the
  template; keep the same.
- No duplicate root containers if setup runs again (refresh/reconnect).
- Delete `tapestry.view.php`, `tapestry_tapestry.tpl` and the TODO line.

## Risks / verify

- CSS that depends on the exact nesting of the old template: diff the DOM of `game_play_area` before
  and after on the same table (2 players, solo Automa, Shadow Empire, spectator).
- Search for leftover `{VAR}` placeholders, `BEGIN`/`END` markers and references to the deleted files.
- After each step play a few turns in Studio (`game-play-in-studio`). After step 2 run the Studio dry
  run: the view/tpl warning is gone.
