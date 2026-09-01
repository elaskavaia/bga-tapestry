# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Board Game Arena implementation of **Tapestry** (Jamey Stegmaier). Classic BGA project layout: no
namespaces, no bundler. PHP server logic + Dojo-style vanilla JS client, both edited directly (there
is no TypeScript/SCSS compile step here despite the `sass`/`typescript` devDependencies).

Read [misc/DESIGN.md](misc/DESIGN.md) first for the DB schema semantics, card/structure location
naming and the benefit resolution model. [misc/FORMAL_RULES.txt](misc/FORMAL_RULES.txt) is the
author's rules interpretation (effect ordering, void/mandatory/optional semantics) and is the
tie-breaker when a rules question comes up. [misc/TODO.md](misc/TODO.md) tracks open bugs and open
rules questions.

## External dependency

Everything assumes a sibling checkout at `~/git/bga-sharedcode`:

- `~/git/bga-sharedcode/misc/php/stubs/BgaFrameworkStubs.php` - in-memory stubs of the BGA
  framework (the real framework is not available locally).
- `~/git/bga-sharedcode/misc/genmat.php` - the CSV to PHP material generator.

`APP_GAMEMODULE_PATH` must point at `~/git/bga-sharedcode/misc/`; all npm scripts set it already.
`misc/module/table/table.game.php` is now only a shim over
`~/git/bga-sharedcode/misc/php/stubs/BgaFrameworkStubs.php`, which is the file to read when a
framework signature or `final` conflict breaks the build. The stubs expose test-only helpers
(`_setCurrentPlayerId`, `_setStates` on `GamestateMachine`) that `GameUT` uses to get around
`final`/`readonly` members - use those rather than overriding framework methods.

## Commands

- `npm run build` / `npm run genmat` - regenerate the generated blocks in `material.inc.php` from
  `misc/benefit_types.csv`. VS Code also runs this on save for any `misc/*.csv`.
- `npm run tests` - PHPUnit over [tests/](tests/). Config is [phpunit.xml](phpunit.xml), bootstrap is
  [tests/\_autoload.php](tests/_autoload.php), so a bare `phpunit` works too.
- `npm run lint:php` - `php -l` sweep over `modules/` and `tests/`.
- `npm run predeploy` - `lint:php` then `tests`. This is the pre-commit gate.
- `npm run lint:phpstan` - PHPStan level 1 ([misc/phpstan.neon](misc/phpstan.neon)). Not part of the
  gate: it still reports findings on the pre-namespace code.
- Single test method: `npm run tests -- --filter testCollectors`

There are no JS tests.

PHP is invoked as `php8.4` everywhere. Prettier (with the PHP plugin, width 140, 1tbs) formats on save.

## Time tracking

Work sessions are logged in [misc/time.timeclock](misc/time.timeclock), hledger timeclock format
(`i` clock in with an account like `alchemists:impl`, `o` clock out). Shell helpers `ti` / `to` /
`tb` live in `~/.bashrc` and resolve the file from the git root, so they work from anywhere in the
repo. Report with `hledger -f misc/time.timeclock balance --tree` (add `--daily` for per-day).

Entries must stay in chronological order and every `i` needs its `o` before the next `i`; a
dangling `i` is read as "still running until now".

## Architecture

### Server class chain

`Tapestry` ([tapestry.game.php](tapestry.game.php), a thin BGA entry point)
-> `PGameXBody` ([modules/PGameXBody.php](modules/PGameXBody.php), ~12k lines, all the game logic)
-> `tapcommon` ([modules/tapcommon.php](modules/tapcommon.php), framework overrides: undo savepoints,
notification batching, zombie/eliminated helpers, argument validation, studio chat debug)
-> `Table` (BGA framework).

`PGameXBody` is sectioned by banner comments: utilities, debug methods, player actions, state
arguments (`arg*`), state actions (`st*`), zombie.

### Material

[material.inc.php](material.inc.php) is `include`d into the game object's scope, so it assigns
directly to `$this->benefit_types`, `$this->civilizations`, `$this->tech_card_data`, etc. It also
defines all the `BE_*`, `CIV_*`, `CARD_*`, `BUILDING_*`, `FLAG_*`, `TRACK_*` constants (guarded by
`if (!defined("TAPESTRY"))` since it is included more than once).

Two regions are generated and must not be hand-edited:
`/* --- gen php begin benefit_types --- */` (from `misc/benefit_types.csv`) and
`/* --- gen php begin decision_deck --- */` (from `misc/decision_deck.csv`). Edit the pipe-delimited
CSV and re-run `npm run genmat` - the script ends with a prettier pass over `material.inc.php`,
which is required because the raw generator emits unformatted PHP and the committed file is
prettier-formatted. `awk` is safer than manual editing for those columns.

`doAdjustMaterial($players, $variant)` rewrites material in place for the player count and the
"Civilization Adjustments" option (2 = original, 1 = official, 4 = deprecated experimental,
8 = Adjustment Civilization Pack). Tests must call it before asserting on civ data.

### The benefit stack

The core engine. Anything that grants or costs something is a "benefit" row in the `benefit` DB
table, acting as a stack/queue. `stBenefitManager` (state 18) pops the current benefit and either
resolves it inline or transitions to the interactive state that collects the player's choice, then
returns to 18. `benefit_category` selects the shape: `standard`, `o,...` (choose one), `a,...`
(all, player picks order), `civ`, `bonus`. Producers are `queueBenefitStandardOne`,
`queueBenefitNormal`, `queueBenefitInterrupt`, `effect_onQueueBenefit`; `benefitCashed()` pops.

`benefit_data` carries the "reason" string built by `reason()` / `reason_tapestry()` /
`reason_civ()` in [modules/taputils.php](modules/taputils.php) - a `kind_number_arg` triple that the
client decodes to show what caused an effect.

### Civilizations

Each civ with non-trivial behaviour is its own class in [modules/civs/](modules/civs/) extending
`AbsCivilization`; everything else falls back to `BasicCivilization`. `getCivilizationInstance()`
resolves the class name from the civ's `name` in material (title-cased, spaces stripped) or an
explicit `class` key, then `include`s `civs/$classname.php` relative to
[modules/PGameXBody.php](modules/PGameXBody.php). Override points: `awardBenefits`, `moveCivCube`,
`argCivAbilitySingle`, `setupCiv`, `finalScoring`, `hasActivatedAbilities`,
`triggerPreGainBenefit`, `queueEraCivAbility`.

Add a civ by adding its material entry plus a class file named after it - no registry to update.

### Database

See [dbmodel.sql](dbmodel.sql) and the location-string conventions in [misc/DESIGN.md](misc/DESIGN.md).
`card` and `structure` are Deck-style tables with an extra `card_location_arg2` column; `benefit` is
the effect stack; `map` and `capital` hold board state; `playerextra` mirrors `player` plus resource
and track columns so bots (Automa, Shadow Empire) can live outside the real `player` table.

### Client

[tapestry.js](tapestry.js) is a single Dojo `declare` of `bgagame.tapestry`, plus
[modules/tapantistock.js](modules/tapantistock.js). Per-state UI is dispatched by convention:
`onUpdateActionButtons_<stateName>` is called from `onUpdateActionButtons` when present. Server
notifications map to `notif_*` methods; `notif_benefitQueue` renders the pending benefit stack that
the server engine described above maintains.

### Debug

`debug_*` methods on `PGameXBody` are callable from the studio chat box (see
[misc/DEBUG.txt](misc/DEBUG.txt) for the incantations): `debug_maxRes()`, `debug_awardCard(type, num)`,
`debug_giveCard`, `debug_res`, `debug_q`, `debug_next`. `isStudio()` / `isTestEnv()` gate them.

## Conventions

- Assertions: `userAssertTrue($message, $cond)` for player-facing rule violations,
  `systemAssertTrue("ERR:Class:NN", $cond)` for internal invariants. The `ERR:` codes are stable
  identifiers used in bug reports - reuse the existing numbering scheme per class.
- Prefer `notifyWithName()` over raw `notifyAllPlayers` / `notifyPlayer`: it injects `player_name`,
  collects `i18n` and `preserve` keys, routes private notifications via `_private`, and appends
  `Async` to the type for `noa`/`nop`/`nod` args. Use the `notifArgsAdd*` helpers to attach
  token/card/track names so the log stays translatable.
- Wrap player-visible strings in `clienttranslate()` (states/notifications) or `totranslate()`
  (gameinfos/gameoptions).
- Name tests in [tests/](tests/) after the feature under test (e.g. `UtilitariansTest.php`), never
  after a bug report number - bug numbers belong in the test docblocks. Shared test harness classes
  live in [tests/Stubs/](tests/Stubs/) (`GameUT`); a test file never requires another test file.
- `_ide_helper.php` and `bga-framework.d.ts` exist only for IDE autocomplete; do not edit them.
- `misc/` and `tests/` are not deployed to BGA - `misc/` holds docs, CSV sources and tools only.
- `misc/rename.sh` produces the renamed `taptest` copy of the project used for studio testing.
