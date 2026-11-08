# Plan: remove tapestry.view.php and tapestry_tapestry.tpl

Status: steps 1 and 2 done, committed together (2026-11-07). DOM identical to the baseline on
every captured seat. Server-side undo checked: layout rebuilt once, no duplicates (miniboards
included), settings and zoom buttons work. Not verified yet: Shadow Empire only table, mobile
412px, Studio dry run warning gone (TODO "STUDIO BUILD WARNINGS").

Follows the `modernize-bga-game-project` skill workflows `bga-jstpl` then `bga-remove-view-tpl`.
The view and template are obsolete (BGA Studio Migration Guide, "Removal of view and template").

## Current state

- `tapestry_tapestry.tpl` (415 lines): `{OVERALL_GAME_HEADER}`, a `<script>` block that holds only
  26 global `jstpl_*` declarations, the static page markup, one `playerArea` block per player,
  `{OVERALL_GAME_FOOTER}`. No `{LB_*}` or other translated text: the markup has no strings at all.
- `tapestry.view.php` inserts `playerArea` for every player from `loadPlayersBasicInfosWithBots()`
  (bots included, sorted by `player_no`): the current player first, then the players after them in
  turn order, then the ones before. A spectator gets plain `player_no` order. Variables: `{X}` player
  id, `{C}` color, `{PLAYER_NAME}`.
- `gamedatas` already carries everything the view used:
  - `playerorder_withbots`: ids from the same `loadPlayersBasicInfosWithBots()` call, already in
    `player_no` order, bots included. No sort needed.
  - `players[id].color` / `.name`: from `playerextra`, which `setupNewGameTables` fills from
    `loadPlayersBasicInfos` after `reattributeColorsBasedOnPreferences`, so same values as the view.
- 27 live `this.format_block("jstpl_x", args)` calls in Game.js, plus 5 inside commented-out code
  (the commented-out `qq` block before `Game.js setupStructures`: building x3, outpost, cube). No dynamically built template names.
  `format_block` is not in bga-framework.d.ts; Game.js reaches it through the `Game0Basics` proxy list.
- `format_block` is `dojo.string.substitute`: it throws on a missing/undefined arg, a template literal
  prints "undefined". All live calls pass every key, so the only difference is on already broken data.
- `setup()` in Game.js: `setZoom` (needs `thething`, `zoom-wrapper`) is the first DOM access, then
  bot code moves template elements around (`shadow_in_play`, `automa_in_play`). Nothing runs on the
  layout before `setup()`: the constructor does not touch it and `onScreenWidthChange` is hooked
  inside `setup()` and is guarded by `if (!$("thething"))`.

## Step 1: jstpl to template literals (workflow `bga-jstpl`)

The `.tpl` and view stay in place, only the script block goes. No `format_block` fallback and no
snippet strings kept as properties. `format_string` / `format_string_recursive` are unrelated, keep.

- Every template is an exported `get...Html()` function in [modules/js/TapLayout.js](../modules/js/TapLayout.js),
  called as `TapLayout.getXHtml(...)` (namespace import), even the single use ones: tpl markup stays
  out of Game.js. Plain functions, not a class: no state.
- Unused, delete (8): some_game_item, landmark, tapestry_card, territory, space, payment_panel,
  income_wrapper, cancel.
- Function arguments are evaluated once, so no locals are needed for repeated placeholders.
- `jstpl_resource_zone` has no closing `</div>` (the parser auto-closes it): kept as is until the
  step 2 snapshot comparison is done, then add the `</div>` as a separate change.
  `territory_panel` with its `territoryPopup` wrapper is one function, `getTerritoryPopupHtml()`.
- The `jstpl_tech_spot` call for spot 0 passes `width`, `height`, `top` the template never used: drop.
- Commented-out code keeps its `format_block` calls untouched (it already refers to dead code paths).
- Delete the `<script>` block (and the stock "Please REMOVE this comment" header) from the tpl.
- Remove `format_block` from the `Game0Basics` proxy list once no live caller is left.
- Verify: `grep -n 'jstpl_\|format_block' modules/js/*.js` shows only the commented-out lines and
  the unrelated own-property templates (`TapAntiStock.jstpl_item`, `cap_choice.jstpl_stock_item`,
  local `jstpl_score`). `npm run predeploy` passes.
- Done: grep clean, predeploy passes, Studio DOM identical to the baseline on the 2p (997767) and
  solo (996867) tables except the removed `<script>` block.

## Step 2: layout into JS (workflow `bga-remove-view-tpl`)

- `modules/js/TapLayout.js` (from step 1) also gets the page layout, so Game.js does not grow:
  - the static page markup, placed with
    `this.bga.gameArea.getElement().insertAdjacentHTML("beforeend", ...)`;
  - a per player `playerArea` builder, placed into `#playerArea_wrapper` in the same string;
  - `map(...).join("")` over arrays instead of repeated markup: 33 limbo tech cards, 4x6 income track
    spaces, tapestry slots (1,2,3,4,6, used twice: player mat and `income_mat_automa`), the 19
    landmark slots (class list per slot, slot 19 also `hidden`), die faces (6 for each of 2 dice),
    die12 faces (2 boxes x 6), achievement holders (3,3,2), tech holders (2,1,0), the 5 `allcards_*`
    expandables (identical shape, ids tech/tap/civ/terr/space).
- Player order: add `getOrderedPlayerIds()` to `Game0Basics` like bga-euro `Game0Basics.ts`, but over
  `gamedatas.playerorder_withbots`, rotated to start at the current player, unchanged for a
  spectator (`gamedatas.players[this.getPlayerId()]` undefined, same test the view and `setup()` use).
- Game.js: build the layout as the very first thing in `setup()` inside the `try`, before `setZoom`.
- Do not copy `{OVERALL_GAME_HEADER}` / `{OVERALL_GAME_FOOTER}`, they are framework wrappers.
- Replace `{X}`, `{C}`, `{PLAYER_NAME}` with the id and `gamedatas.players[id].color` / `.name`.
  The view inserted the name raw, keep raw (bot names are fixed English strings from `playerextra`,
  shown untranslated today too).
- Re-run: `setup()` runs again after a server-side undo (BGA docs, setup: "when a player performs a
  server-side Undo"). Not documented what the game area holds then. Evidence the framework puts the
  tpl markup back: `setupSettings` removes a second `#player_board_config`, which only appears if the
  tpl copy is restored while the first copy, moved into `#player_boards`, survives. To match that
  whatever the framework does: before inserting, remove the 3 tpl root elements if present
  (`setup_choices`, `breadcrumbs`, `zoom-wrapper`), so every `setup()` gets a fresh layout, never a
  duplicate and never stale pieces. Do not clear the whole game area (framework banners live there).
  The `#player_board_config` duplicate check in `setupSettings` stays as is.
- Verify in Studio after step 2: do a server-side undo, then `#zoom-wrapper`, `#playerArea_<id>`
  and `#player_board_config` each exist once, and the settings/zoom buttons still work.
- Delete `tapestry.view.php`, `tapestry_tapestry.tpl` and the TODO line.

## Risks / verify

- DOM baseline (taken 2026-11-07, session scratchpad `dom/`, compare script `domcmp.sh`):
  `#game_play_area` + `#player_boards` `outerHTML` on table 997767 (2p mid game: laskava0, laskava1,
  spectator 2300664) and 996867 (solo: laskava0, spectator 2300663). Do not play these tables, a
  reload must give the same state. Save slots are shared between tables, so they cannot restore
  them. Noise filtered: HTML comments, `<script>`, the premium emblem UUID ids, the random science
  die face, online/offline status. Shadow Empire only (2p + Shadow) has no baseline: check by eye.
  After step 2: reload the same seats, capture, diff.
- Commit a small mocha spec for `getOrderedPlayerIds` (rotation, spectator, bots) in tests/js.
- Keep the ids `player_board_config` and `player_boards` targets intact: the mobile grid and
  overflow rules in tapestry.css (PANEL section) select `#player_board_config`, and `setupSettings`
  moves it into `#player_boards`. Check the mobile layout (412px) and a spectator after step 2.
- Search for leftover `{VAR}` placeholders, `BEGIN`/`END` markers and references to the deleted files.
- After each step play a few turns in Studio (`game-play-in-studio`), including a turn that opens the
  payment box (`resource_line`), the territory popup and a solo Automa table (`automa_civ`,
  `decision`). After step 2 run the Studio dry run: the view/tpl warning is gone.
