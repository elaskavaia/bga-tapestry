# Plan: remove tapestry.view.php and tapestry_tapestry.tpl

Status: not started (2026-11-03). Source: the Studio dry run warning, TODO "STUDIO BUILD WARNINGS".

The view and template are obsolete (BGA Studio Migration Guide, "Removal of view and template"). Their
markup moves into a new JS class so tapestry.js does not grow.

## Current state

- `tapestry_tapestry.tpl` (415 lines): a `<script>` block with 25 global `jstpl_*` snippets, the static
  page markup, and one `playerArea` block per player.
- `tapestry.view.php` inserts `playerArea` for every player from `loadPlayersBasicInfosWithBots()`
  (bots included): the current player first, then the players after them in turn order, then the ones
  before. A spectator gets turn order. Variables: `{X}` player id, `{C}` color, `{PLAYER_NAME}`.
- 32 `this.format_block("jstpl_x", args)` calls in tapestry.js find the snippets by global name.
  `format_block` is not in bga-framework.d.ts, it is legacy too.
- `setup()` in tapestry.js moves template elements around for the bots (`shadow_in_play`,
  `automa_in_play`) and `setZoom` needs `zoom-wrapper`, so the markup must exist first.

## Changes

- New `modules/taplayout.js`: a Dojo `declare("bgagame.taplayout", null, ...)` like `tapantistock.js`,
  added to the `define` list of tapestry.js. It holds:
  - the static page markup, placed into `this.bga.gameArea.getElement()`;
  - a per player `playerArea` builder. Order from `gamedatas.players[id].no` (bots have it too),
    rotated to start at the current player, spectators in plain `no` order;
  - the 25 `jstpl_*` snippets as properties (decision: option a, no globals);
  - loops instead of repeated markup: 33 limbo tech cards, 4x6 income track spaces, tapestry slots,
    landmark slots (class list), die faces.
- tapestry.js: build the layout as the first thing in `setup()`, before `setZoom`.
- The 32 `format_block("jstpl_x", args)` calls become `this.format_string(<layout snippet>, args)`,
  possibly through a short helper. Check `format_string` substitutes the same way as `format_block`
  (`${var}` syntax, missing keys).
- Delete `tapestry.view.php`, `tapestry_tapestry.tpl` and the TODO line.

## Risks / verify

- CSS that depends on the exact nesting of the old template: diff the DOM of `game_play_area` before
  and after on the same table (2 players, solo Automa, Shadow Empire).
- Player names were inserted raw by the template; keep the same, or escape if cheap.
- Play a few turns in Studio (`game-play-in-studio`), then run the Studio dry run: the view/tpl
  warning is gone.
