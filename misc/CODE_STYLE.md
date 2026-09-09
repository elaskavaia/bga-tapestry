# Code Style

How code is written in this project, and what a review of changed code checks beyond the generic
bug hunt. The shared `game-review-diff` skill reads this file and flags changed code that breaks
it; the "Review: do not flag" section at the end lists patterns a review must accept.

The architecture is `PGameXBody` plus the benefit stack plus one class per civilization; see
[DESIGN.md](DESIGN.md). Each bullet below is a preferred pattern or a check to run.

## Naming (all languages)

- Getters start with `get`; the only other sanctioned prefixes are `is`, `has` and `can` (boolean
  predicates) and `count` (methods doubling as expression-engine barewords, and even those stay
  descriptive: `countCardsInHand`, not `countHand`). This covers any side-effect-free method that
  returns a value, not just field accessors: a `calcScore()` or `findBestHex()` is a getter and is
  named `get...`. No bare-noun getters like `cardCost()`.
- Never shorten identifiers (`button`, not `btn`). Concise, not cryptic.

## Assertions and error codes (php)

- `userAssertTrue($message, $cond)` for player-facing rule violations, `systemAssertTrue("ERR:...")`
  for internal invariants. Flag a `userAssertTrue` guarding something a player cannot cause, and a
  `systemAssertTrue` guarding a legal-but-wrong player click.
- `ERR:` codes are stable bug-report identifiers: unique per class, reuse the class's existing
  numbering, never renumber existing ones.

## Notifications and strings (php)

- Prefer `notifyWithName()` over raw `notifyAllPlayers` / `notifyPlayer`: it injects `player_name`,
  collects `i18n` and `preserve` keys, routes private notifications via `_private`, and appends
  `Async` to the type for `noa`/`nop`/`nod` args. Use the `notifArgsAdd*` helpers to attach
  token/card/track names so the log stays translatable.
- Player-visible strings use `clienttranslate()`: states, notifications and the message of a
  `userAssertTrue`. `totranslate()` is deprecated by the framework: flag any new call to it, and
  flag a rewritten block that carries an existing one along only if the line was actually changed,
  not just re-indented. [gameinfos.inc.php](../gameinfos.inc.php) and
  [gameoptions.inc.php](../gameoptions.inc.php) still use it: the framework's replacement there is
  JSON options/stats, not `clienttranslate()`, so leave those alone.

## Generated material (`misc/*.csv` -> `material.inc.php`)

- Never hand-edit inside `/* --- gen php begin ... --- */` regions (`benefit_types`,
  `decision_deck`). Flag any change there that is not reproducible from the CSV.
- A new benefit row gets its `BE_*` constant from the CSV `-con` column. Flag a hand-added
  `define()` for a benefit that the generator should own.
- A new benefit that reuses existing machinery must be wired into the dispatch that machinery lives
  in - most often the `case` list in `awardBenefits` around
  [PGameXBody.php:1688](../modules/PGameXBody.php#L1688) for track advance/regress rows. A new row
  with `"r" => "t"` and no case there is silently unreachable.
- Benefit rows that drive a state (`"state" => "..."`) must have the state exist in
  `states.inc.php`, and the state's `arg*` method must exist if it declares `"args"`.

## Civilizations (`modules/civs/`)

- One class per civ with non-trivial behaviour, extending `AbsCivilization`; the class file name
  must match the material `name` title-cased with spaces stripped, or an explicit `class` key. A
  mismatch surfaces only at runtime.
- `awardBenefits` starts with `systemAssertTrue("ERR:<Civ>:NN", $game->isRealPlayer($player_id))`
  and `hasCiv`, ends with a `systemAssertTrue("ERR:<Civ>:NN", false)` for an unowned benefit.
- `awardBenefits` returns true to have the row cashed. Flag a path that resolves work and returns
  nothing.
- Check the civ against [FORMAL_RULES.txt](FORMAL_RULES.txt); it is the tie-breaker on effect
  ordering and void/mandatory/optional semantics. A new rules interpretation belongs there as a
  coded clause (MOVE.2, CIV.WEEFOLK.3).

## Game state globals (the label array in `PGameXBody::__construct`)

- New globals are registered only in that array, with a unique id in the framework's 10-89 range.
  The framework default of 0 is the sentinel, and card ids start at 1, so 0 means "unset".
- **Lifetime is the bug magnet.** A global that marks a pending choice must be set as close as
  possible to the code that consumes it, and cleared on every path that does not consume it.
  Setting it early "so it is ready" leaks it into unrelated benefits later in the same turn. Trace
  every branch between the write and the read before accepting one.
- A clear placed in a handler that has nothing to do with the global (clearing a space-tile marker
  inside the territory `action_explore`, say) is a symptom, not a fix - flag it and ask whether the
  global's lifetime is wrong instead.

## States and client (`states.inc.php`, `tapestry.js`)

- A state declaring `"args" => "argFoo"` needs `argFoo()` on `PGameXBody`; per-state UI is
  `onUpdateActionButtons_<stateName>` by convention.
- `onUpdateActionButtons(stateName, args)` receives the server args **flat**; `onEnteringState`
  receives them wrapped as `args.args`. Flag the wrong shape.
- Adding `active_slot` to filter what is clickable is only half the change: the matching click
  handler needs an `isActiveSlot(id)` / `checkAction()` guard, or the dimmed element still fires
  the action and earns a server rejection toast.
- `states.inc.php` header warns against changing it while a game is running - flag renumbered or
  removed states, not added ones.

## Tests (`tests/`)

- Name tests after the feature under test (e.g. `UtilitariansTest.php`), never after a bug report
  number - bug numbers belong in the test docblocks. Shared test harness classes live in
  [tests/Stubs/](../tests/Stubs/) (`GameUT`); a test file never requires another test file.
- Tests asserting on civ data must call `doAdjustMaterial($players, $variant)` first.
- **A test written to guard a production-only path must be proven to fail without the fix.** The
  in-memory `GameUT` model bypasses the real SQL builders, so a test can exercise a different
  string than production does and pass either way. Stash the fix, run the test, confirm it fails,
  restore. Flag any new guard test that survives its own fix being reverted.
- A `GameUT` override that replaces production behaviour needs a docblock saying why the real one
  cannot run in tests.
- Randomness is seeded through `seedRand()`; an unseeded `bgaRand` prints a warning, which now
  fails the test (see below), so a test that flips a coin without seeding cannot pass by luck.
- **The suite is silent.** `phpunit.xml` sets `beStrictAboutOutputDuringTests` with `failOnRisky`,
  so anything a test prints - `$this->error()` / `$this->warn()` from the game, a leftover
  `var_dump` - fails it. A test whose subject is an error path declares what it expects with
  `expectOutputRegex()`, which doubles as the assertion that the error was raised; never write to
  `STDERR` from a test helper, since that escapes the capture.

## Formatting (all files)

- No non-ascii characters in code or .md files: no m-dash, no fancy quotes, no arrows. The
  copyright symbol is allowed.
- LF line endings everywhere. Flag a diff where a file was mass-converted CRLF to LF alongside a
  real change - it should be its own commit.
- Prettier (PHP plugin, width 140, 1tbs) formats on save; `npx prettier --check` must be clean.
- Never edit `_ide_helper.php` or `bga-framework.d.ts`; they exist only for IDE autocomplete.
- `misc/` and `tests/` are not deployed - nothing runtime may depend on them; `misc/` holds docs,
  CSV sources and tools only.

## Comments

- Few and short, explaining why, not what.
- Remove WHAT-comments on BGA boilerplate; keep WHY-comments.
- Never remove commented-out code; the author keeps it deliberately.

## New code only (legacy is grandfathered)

This is an old game; do not flag existing code for these, but hold new and rewritten code to them:

- No deprecated framework methods: if the method's stub in
  `~/git/bga-sharedcode/misc/php/stubs/BgaFrameworkStubs.php` carries `@deprecated`, use the
  replacement its docblock names.
- Strict PHP types: new files start with `declare(strict_types=1);` (every `modules/civs/` file
  already does), and new or rewritten functions declare parameter and return types.
- No direct SQL in game logic: go through the Deck objects (`$this->cards`, `$this->structures`)
  or the `db*` helpers in [tapcommon.php](../modules/tapcommon.php). A genuinely new query becomes
  a new `db*` helper, not an inline `DbQuery` string in a state or action handler.

## Docs

- Open bugs and open rules questions go in [TODO.md](TODO.md) as `- [ ]` items; finished ones
  become `- [x]`.
- A new rules interpretation goes in [FORMAL_RULES.txt](FORMAL_RULES.txt) as a coded clause
  (MOVE.2, CIV.WEEFOLK.3).
- Check whether [DESIGN.md](DESIGN.md) needs updating for new schema semantics or location-string
  conventions.

## Review: do not flag

Raised as review findings before, intentional or correct here:

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
