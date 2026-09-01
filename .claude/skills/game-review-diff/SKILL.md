---
name: game-review-diff
description: Review uncommitted changes in the Tapestry BGA project for bugs, one at a time. Runs the generic review-diff checks plus Tapestry-specific conventions (benefit CSV and generated material, civ classes, game state globals, states and Dojo client, tests). Use when the user wants to review their current changes in this repo before committing.
---

# Tapestry BGA Diff Review

Run the generic review process from `~/.claude/skills/review-diff/SKILL.md` first - read and apply
it. If that file is missing, continue with the Tapestry checks below anyway. Then additionally
check for the Tapestry-specific issues below.

The architecture is `PGameXBody` plus the benefit stack plus one class per civilization.

## How to run this review

- **Ask in plain text, one issue at a time.** Write out the Yes/No/Defer choice as words and wait for the answer.
- **Defer** for this repo means: append the finding to [misc/TODO.md](../../../misc/TODO.md) as an
  unchecked `- [ ]` item under the relevant heading, with enough detail to act on later (the
  trigger, the file, and the suggested fix), and change no code. Wrap continuation lines indented
  six spaces so they stay inside the item. Tick it `- [x]` only once the work is actually done.
- Verify with the narrow command, never the whole suite:
  `npm run tests -- --filter <TestName>`.
- Cheap whole-diff gates worth running once: `npm run lint:php`, `npx prettier --check <changed files>`.

## Tapestry-specific checks

Each bullet is a **preferred pattern or a check to run** - flag any changed code that does not follow it.

### Generated material (`misc/*.csv` -> `material.inc.php`)

- Never hand-edit inside `/* --- gen php begin ... --- */` regions (`benefit_types`,
  `decision_deck`). Flag any change there that is not reproducible from the CSV.
- A new benefit row gets its `BE_*` constant from the CSV `-con` column. Flag a hand-added
  `define()` for a benefit that the generator should own.
- A new benefit that reuses existing machinery must be wired into the dispatch that machinery lives
  in - most often the `case` list in `awardBenefits` around
  [PGameXBody.php:1688](../../../modules/PGameXBody.php#L1688) for track advance/regress rows. A new
  row with `"r" => "t"` and no case there is silently unreachable.
- Benefit rows that drive a state (`"state" => "..."`) must have the state exist in
  `states.inc.php`, and the state's `arg*` method must exist if it declares `"args"`.

### Civilizations (`modules/civs/`)

- One class per civ with non-trivial behaviour, extending `AbsCivilization`; the class file name
  must match the material `name` title-cased with spaces stripped, or an explicit `class` key. A
  mismatch surfaces only at runtime.
- `awardBenefits` starts with `systemAssertTrue("ERR:<Civ>:NN", $game->isRealPlayer($player_id))`
  and `hasCiv`, ends with a `systemAssertTrue("ERR:<Civ>:NN", false)` for an unowned benefit.
- `ERR:` codes are stable bug-report identifiers: unique per class, reuse the class's existing
  numbering, never renumber existing ones.
- `userAssertTrue($message, $cond)` for player-facing rule violations, `systemAssertTrue("ERR:...")`
  for internal invariants. Flag a `userAssertTrue` guarding something a player cannot cause, and a
  `systemAssertTrue` guarding a legal-but-wrong player click.
- `awardBenefits` returns true to have the row cashed. Flag a path that resolves work and returns
  nothing.
- Check the civ against [misc/FORMAL_RULES.txt](../../../misc/FORMAL_RULES.txt); it is the
  tie-breaker on effect ordering and void/mandatory/optional semantics. A new rules interpretation
  belongs there as a numbered clause.

### Game state globals (the label array in `PGameXBody::__construct`)

- New globals are registered only in that array, with a unique id in the framework's 10-89 range.
  The framework default of 0 is the sentinel, and card ids start at 1, so 0 means "unset".
- **Lifetime is the bug magnet.** A global that marks a pending choice must be set as close as
  possible to the code that consumes it, and cleared on every path that does not consume it.
  Setting it early "so it is ready" leaks it into unrelated benefits later in the same turn.
  Trace every branch between the write and the read before accepting one.
- A clear placed in a handler that has nothing to do with the global (clearing a space-tile marker
  inside the territory `action_explore`, say) is a symptom, not a fix - flag it and ask whether the
  global's lifetime is wrong instead.

### States and client (`states.inc.php`, `tapestry.js`)

- A state declaring `"args" => "argFoo"` needs `argFoo()` on `PGameXBody`; per-state UI is
  `onUpdateActionButtons_<stateName>` by convention.
- `onUpdateActionButtons(stateName, args)` receives the server args **flat**; `onEnteringState`
  receives them wrapped as `args.args`. Flag the wrong shape.
- Adding `active_slot` to filter what is clickable is only half the change: the matching click
  handler needs an `isActiveSlot(id)` / `checkAction()` guard, or the dimmed element still fires
  the action and earns a server rejection toast.
- Prefer `notifyWithName()` over raw `notifyAllPlayers` / `notifyPlayer`, and attach names with the
  `notifArgsAdd*` helpers so the log stays translatable.
- Player-visible strings use `clienttranslate()` (states, notifications) or `totranslate()`
  (gameinfos, gameoptions, and assert messages).
- `states.inc.php` header warns against changing it while a game is running - flag renumbered or
  removed states, not added ones.

### Tests (`tests/`)

- Named after the feature (`WerefolkTest.php`), never after a bug number; bug numbers go in the
  docblock. A test file never requires another test file - shared harness lives in
  [tests/Stubs/GameUT.php](../../../tests/Stubs/GameUT.php).
- Tests asserting on civ data must call `doAdjustMaterial($players, $variant)` first.
- **A test written to guard a production-only path must be proven to fail without the fix.** The
  in-memory `GameUT` model bypasses the real SQL builders, so a test can exercise a different
  string than production does and pass either way. Stash the fix, run the test, confirm it fails,
  restore. Flag any new guard test that survives its own fix being reverted.
- A `GameUT` override that replaces production behaviour needs a docblock saying why the real one
  cannot run in tests.
- Randomness is seeded through `seedRand()`; an unseeded `bgaRand` only warns to stderr, so a test
  that flips a coin without seeding is passing by luck. Flag it.

### Conventions (all files)

- No non-ascii characters in code or .md files: no m-dash, no fancy quotes, no arrows. The
  copyright symbol is allowed.
- LF line endings everywhere. Flag a diff where a file was mass-converted CRLF to LF alongside a
  real change - it should be its own commit.
- Prettier (PHP plugin, width 140, 1tbs) formats on save; `npx prettier --check` must be clean.
- Never edit `_ide_helper.php` or `bga-framework.d.ts`.
- `misc/` and `tests/` are not deployed - nothing runtime may depend on them.
- Getters start with `get`; the only other sanctioned getter prefixes are `is` (boolean
  predicates) and `count` (methods doubling as expression-engine barewords, and even those stay
  descriptive: `countCardsInHand`, not `countHand`). Flag bare-noun getters like `cardCost()`.

### New code only (legacy is grandfathered)

This is an old game; do not flag existing code for these, but hold new and rewritten code to them:

- No deprecated framework methods: if the method's stub in
  `~/git/bga-sharedcode/misc/php/stubs/BgaFrameworkStubs.php` carries `@deprecated`, use the
  replacement its docblock names.
- Strict PHP types: new files start with `declare(strict_types=1);` (every `modules/civs/` file
  already does), and new or rewritten functions declare parameter and return types.
- No direct SQL in game logic: go through the Deck objects (`$this->cards`, `$this->structures`)
  or the `db*` helpers in [tapcommon.php](../../../modules/tapcommon.php). A genuinely new query
  becomes a new `db*` helper, not an inline `DbQuery` string in a state or action handler.

### Docs

- Open bugs and open rules questions go in [misc/TODO.md](../../../misc/TODO.md) as `- [ ]` items;
  finished ones become `- [x]`.
- A new rules interpretation goes in [misc/FORMAL_RULES.txt](../../../misc/FORMAL_RULES.txt) as a
  numbered clause.
- Check whether [misc/DESIGN.md](../../../misc/DESIGN.md) needs updating for new schema semantics
  or location-string conventions.

## Not a bug - do NOT flag

These have been raised before as review findings but are intentional or correct:

- `FLAG_GAIN_BENFIT` and other misspelled established constants - they are the API, leave them.
- Near-identical entries in the generated `material.inc.php` regions - that is the CSV; never
  suggest deduplication there.
- `<li>` in civ `description` arrays without a wrapping `<ul>` or a closing `</li>` - existing
  convention, there are dozens.
- `PGameXBody.php` being one 12k-line class, or `material.inc.php` assigning into `$this->` - both
  are the deliberate BGA-studio layout.
- `ERR:` numbers that repeat across different civ classes - the class prefix disambiguates them.
- Commented-out code - the user keeps it deliberately, never remove it.
- Civs with no `slots` key - several ship that way and every generic path guards for it.
- Benefit ids allocated out of numeric order in the CSV - the id is an identity, not a sequence.
- `getGameStateValue("x")` without an explicit default - the framework already defaults to 0.

## Reporting

Same as the generic skill: one issue at a time, Yes/No/Defer, with `file:line`, the problematic
code, and the concrete failing scenario. Auto-fix without asking: spelling, unused includes,
comment mismatches and WHAT-comments (never commented-out code), missing test assertions and other
test nits. List what was auto-fixed at the end so the user can review the diff.
