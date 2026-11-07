# Plan: replace the AMD/Dojo module loading of the main JS file

Status: code done (2026-11-06), studio load and setup choice sanity checked, manual test plan below
still to run. Follows the `modernize-bga-game-project` workflow `bga-es6`, scoped
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

This plan landed first; PLAN_VIEW_REMOVAL step 2 now plans `modules/js/TapLayout.js` as an
`export class` imported by Game.js.

## As implemented (differences from the steps above)

- Framework check in studio (live gameui): only `setup`, the state callbacks and `bgaFormatText` are
  called on `gameui.GameModule`; `addMoveToLog`, `showMessage`, `updatePlayerOrdering`,
  `onScreenWidthChange` are called on gameui only, so they are hooked. Each hook runs the gameui
  original first, then the Game method (`showMessage` used to add the bug link before the message;
  `onScreenWidthChange` used to replace the original, which is an empty function on gamegui).
  Hooks are installed once: the framework re-runs `setup()` on the same Game after an undo restore.
- String handlers are not rewritten at call sites: they reach gameui through ~10 game helpers
  (`addImageActionButton`, `addPlayerActionButton`, `addCancelButton`, ...), so `Game0Basics`
  resolves a string against the Game in its `addActionButton` / `connect` / `connectClass` proxies.
- `getPart` / `getIntPart` / `reloadCss` are exported from Game0Basics.js, not TapAntiStock.js.
  `reloadCss` is no longer a console global.
- Antistock `slide`: prototype assignment (passing the game would break `fork()`, which
  `dojo.clone`s the instance).
- `ebg/stock` via top-level `await importDojoLibs`; `cap_choice.create` gets `this.bga.gameui` as page
  (ebg controls call `registerEbgControl` on it). `ebg/counter` dropped (unused).
- `defaultTooltipPosition` is read by gameui `addTooltipHtml`, so it is saved/restored on gameui.
- Help mode: `onClickForHelp` / `closeCurrentTooltip` are arrow fields and use `this`, not the
  `gameui` global.
- Strict mode (modules): 10 implicit globals declared with `var`, found with
  `npx tsc --allowJs --checkJs --noEmit` (error TS2304, ignoring framework globals).

## Review notes (2026-11-06)

Commits:
- c7e6d4e `bgaFormatText` instead of the `format_string_recursive` override (legacy file, studio checked)
- e45353b renames only; does not load on its own (matters for bisect)
- 3884f98 the conversion and `Game0Basics.js`
- ed909ef docs

Checked:
- `npm run predeploy` passes (PHP only, no JS tests)
- `node --check` on each module (as .mjs copies)
- studio table 994817 (2 players, still open): load with no game console errors, Game on Game0Basics,
  4 hooks installed, move stamps in the log, `player_board_config` panel first, layout class set,
  450 tooltips registered; setup choice played (civ click, capital stock select, Confirm, formatted
  log line with "Move #2")
- not checked: everything in the manual test plan below

Decisions to review:
- String handlers resolved in the `Game0Basics` proxies instead of arrows at the call sites (see
  above). Arrows at the call sites would be more explicit, but every one missed is a button that
  silently does nothing.
- `showMessage` order: the bug report link now goes to the log after the error message, not before.
- `reloadCss` no longer reachable from the browser console.
- Antistock `slide` patched on the prototype, shared by all instances, as `dojo.extend` did.
- Against the `bga-es6` skill rules, as this plan intended: Game extends a game-owned base class (the
  rule forbids `extends`/`super`, aimed at gameui), and the 4 hooks replace methods on gameui (the
  rule says not to monkey-patch gameui). No documented Game hook exists for those 4.
- Proxies in `Game0Basics` keep call sites untouched; the skill prefers explicit
  `this.bga.gameui.x(...)` at each call site.

Found, not fixed (in TODO): `getCardDivLocatonId` reads undeclared `card_player_id` and reassigns a
`const slot`; both throw today as well.

## Risks / verify

- `npm run predeploy` only covers PHP; there are no JS tests. Syntax check with
  `node --check modules/js/Game.js` (ES module: `node --input-type=module --check` or a .mjs copy).
- Studio: run the manual test plan below.
- Grep after conversion: no `this.inherited`, no `declare(`, no `define(`, no `bgagame.`, no string
  handler left in `connect` / `addActionButton` / `connectClass`.
- Delete nothing else; the old `tapestry.js` path must not be recreated by any build step.

## Manual test plan (studio)

Driven through the browser with the `game-play-in-studio` skill, 2 player table plus one Automa
table. Run it once on the legacy code first as a baseline (note anything already broken), then
after each commit that changes JS. Watch the browser console the whole time: any new error fails
the run.

### Load
- Fresh table: setup finishes, board, player areas, capital mats, income mats render.
- F5 mid game, as active player, as inactive player, as spectator.
- Replay from the log and the archive view: no animations, no action buttons (`isReadOnly`).
- Player panels: current player first, `player_board_config` panel first (`updatePlayerOrdering`).
- Automa / Shadow Empire table: bot panels and bot areas present.

### Client states
- Advance with an "o," choice benefit: `client_benefitChoice` buttons, pick one, server accepts.
- Same, then Cancel: back to the server state with its buttons, no stale selection highlight.
- Place a territory tile on explore: tile selection, rotation, land click, confirm.
- Conquer with a tapestry that asks (militarism / exploitation) when one is in hand (`debug_awardCard`).
- One civ client state if cheap to set up (Traders `client_trader`, Architects `client_ArchitectsSwap`).
- Income turn: "I confirm" dialog (`confirmationDialog`), `resourceChoice` payment boxes.
- String-named handlers: click every action button reached above and the board/card clicks; nothing
  silently does nothing.

### Undo
- Take an action, Undo: board, resources, tracks and counters revert, log shows the undo.
- Undo from inside a client state and from a later benefit of the same turn (`button_undo_x`).
- F5 after undo: state matches what was shown.

### Logs
- Log entries with player names, resource icons, card / tech / civ names, tooltips on log items
  (`bgaFormatText`, formerly `format_string_recursive`).
- `log_others` variant: the opponent sees the other text for a private action.
- Move numbers next to log entries, also after F5 (`addMoveToLog` hook and replay of
  `log_to_move_id`).
- Illegal action (click something the server rejects): error message shows, with the bug link
  (`showMessage`, `metasiteurl` + `table_id`).
- Die roll "for fun" message goes to log only.

### Zoom / scale
- Zoom in / out buttons: board scales, stays aligned, value survives F5 (localStorage).
- Resize the window narrow / wide: layout reflows (`onScreenWidthChange`).
- Card antistocks slide to the right place at non-1 zoom; capital choice stock still works.
