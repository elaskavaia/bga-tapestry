# Plan: replace the AMD/Dojo module loading of the main JS file

Status: not started (2026-11-06). Follows the `modernize-bga-game-project` workflow `bga-es6`, scoped
down: only the module loading changes. `dojo.*` calls, `$()`, ajaxcall, jstpl/tpl stay as they are
(separate plans). No TypeScript. Reference implementation: bga-patchwork commits d511d94 and 267323e
(patchwork also extracted PatchManager and moved to `this.bga.*` services; Tapestry skips that).

## Current state

- `tapestry.js`: `define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter",
  "ebg/stock", g_gamethemeurl + "modules/tapantistock.js"], ...)` returning
  `declare("bgagame.tapestry", ebg.core.gamegui, {...})`. Globals outside `define`: `SYM_RIGHTARROW`,
  `reloadCss`, `getIntPart`, `getPart`.
- `modules/tapantistock.js`: `declare("bgagame.tapantistock", null, {...})`, created with
  `new bgagame.tapantistock(...)` (3 places). `setup()` patches it with
  `dojo.extend(bgagame.tapantistock, { slide: dojo.hitch(tapgame, "placeToken") })`.
- `ebg.stock()` is used once (`cap_choice`), `ebg.counter` is in the list but unused,
  `ebg.popindialog` is used 4 times.
- Framework overrides that the framework calls on gameui, so they stop working without `extends`:
  `format_string_recursive` (uses `this.inherited`), `showMessage` (`this.inherited`),
  `updatePlayerOrdering` (`this.inherited`), `addMoveToLog` (`this.inherited`),
  `onScreenWidthChange`. `isInstantaneousMode` and `setZoom` are game-owned helpers (not in
  gameui), they become plain class methods.
- Inherited gameui members used on `this` (counts): gamedatas 101, player_id 75, addActionButton 70,
  format_block 32, connect 29, addTooltip 17, format_string 12, addTooltipHtml 9, notifqueue 8,
  confirmationDialog 7, connectClass 5, tooltips 4, table_id 2, metasiteurl 2, disconnect 2,
  rotate 2, and 1 each: updatePageTitle, slideToObjectAndDestroy, slideTemporaryObject, setupTour,
  setupHelper, setClientState, removeTooltip, placeOnObject, fadeOutAndDestroy, disablePlayerPanel,
  clienttranslate_string.
- String-named handlers that gameui would resolve on itself: `this.connect(..., "name")` 21,
  `this.connectClass(..., "name")` up to 5, `addActionButton(..., "name")` 37.
  `dojo.connect(node, ev, this, "name")`, `dojo.hitch(this, "name")` and
  `dojo.subscribe(topic, this, "notif_x")` pass `this` explicitly, so they stay working.

## Prerequisite: format_string_recursive to bgaFormatText

The `bga-es6` workflow blocks while a game-owned `format_string_recursive` override exists. Rename
the override to `bgaFormatText(log, args)` returning `{ log, args }` (as patchwork did), and keep a
`format_string_recursive` proxy to `this.bga.gameui.format_string_recursive` for the internal
callers. Can be its own commit before step 1, done on the legacy file and verified in studio.

## Step 1: renames only (one commit)

So git keeps the history; this commit alone does not load.

- `git mv tapestry.js modules/js/Game.js`
- `git mv modules/tapantistock.js modules/js/TapAntiStock.js`

## Step 2: convert content (one commit)

- TapAntiStock.js: `export class TapAntiStock { constructor(typeclass, mainclass) {...} ... }`.
  Move `getIntPart` / `getPart` (and other shared helpers) out of the global scope as `export function`,
  imported where used. Module scope is no longer global: grep the tpl and inline HTML `onclick` strings
  for any of these names first.
- Game.js:
  - `import { TapAntiStock, getPart, getIntPart } from "./TapAntiStock.js";`
  - `import { Game0Basics } from "./Game0Basics.js";`
  - `export class Game extends Game0Basics { constructor(bga) { super(bga); ...existing constructor
    body } }`. Object literal methods to class methods (`name: function (` to `name(`, drop the
    commas between members).
  - `SYM_RIGHTARROW`, `reloadCss` become module-level `const` / `function`.
  - Replace the `dojo.extend` patch with a plain prototype assignment:
    `TapAntiStock.prototype.slide = (...args) => this.placeToken(...args)`, or better pass the game
    to the antistock (it already has `bind(game, ...)`). Ask before choosing.
- New `modules/js/Game0Basics.js`: non game specific base class, plain JS adapted from bga-euro
  `src/ts/Game0Basics.ts` (not a copy). Holds `bga` and:
  - `get gamedatas()` / `set gamedatas(v)` forwarding to `this.bga.gameui.gamedatas` (the framework
    mutates and replaces that object, a private copy goes stale; patchwork learned this).
  - getters for `table_id`, `metasiteurl`, `notifqueue`, `tooltips`. No `player_id` getter: call
    sites already use `getPlayerId()`.
  - one-line forwarding methods for the inherited methods listed above (minimal diff, call sites
    untouched).
  - generic helpers moved from Game: `getPlayerId`, `isReadOnly`, `isInstantaneousMode`, and other
    non game specific utilities found while converting.
- Framework overrides, hooked on gameui in `Game0Basics` setup (bga-euro `hookMoveToLog` pattern):
  `addMoveToLog` (also replay existing `gameui.log_to_move_id` entries), `onScreenWidthChange`,
  `showMessage`, `updatePlayerOrdering`. Each wraps the saved original instead of `this.inherited`.
- String-named handlers: wrap in arrows, e.g. `this.connect(node, "onclick", "onFoo")` to
  `this.connect(node, "onclick", (e) => this.onFoo(e))`; same for `addActionButton` and
  `connectClass`. Callbacks passed as bare `this.method` references to framework helpers need the
  same treatment.
- ebg libs: `ebg.popindialog` is core. For `ebg.stock`, check in studio whether `ebg.stock` is still
  a global; if not, load it with `importDojoLibs(["ebg/stock"])` before `setup` uses it. Drop
  `ebg/counter` if it is still unused. Not sure how the framework handles this, verify in studio.
- `dojo` stays a global; no Dojo DOM call is changed.

## Interaction with PLAN_VIEW_REMOVAL

Its step 2 plans `modules/taplayout.js` as a Dojo `declare` added to the `define` list. If this plan
lands first, make it `modules/js/TapLayout.js` with `export class` and an `import` instead. Decide the
order before starting either.

## Risks / verify

- `npm run predeploy` only covers PHP; there are no JS tests. Syntax check with
  `node --check modules/js/Game.js` (ES module: `node --input-type=module --check` or a .mjs copy).
- Studio: load, F5, replay, spectator, Automa / Shadow Empire tables, every action button, tooltips
  and help mode (`gameui._helpMode` paths), log formatting (`bgaFormatText`), move numbers in the log,
  error message bug link (`showMessage`), player panel ordering (`player_board_config` first),
  zoom / screen width change, capital choice stock, card antistocks sliding.
- Grep after conversion: no `this.inherited`, no `declare(`, no `define(`, no `bgagame.`, no string
  handler left in `connect` / `addActionButton` / `connectClass`.
- Delete nothing else; the old `tapestry.js` path must not be recreated by any build step.
