# Bug Triage - project config and state

Game-specific configuration and running state for the `game-bug-triage` skill. The skill itself is
game-independent; everything project-specific lives here. Read this file at the start of every
triage run.

## Game reference

- **BGA game id:** 1446 (Tapestry)
- **Bug list:** https://boardgamearena.com/bugs?game=1446
- **Single report:** https://boardgamearena.com/bug?id=<REPORT_ID>
- **Studio error log:** https://studio.boardgamearena.com/studioissues?game=tapestry - studio short
  name is `tapestry`, not the numeric id above. Add `&tableId=<TABLE_ID>` for one table,
  `&id=<ERROR_ID>` for one error, `&projects=frontend|backend` to filter. (`taptest` is the renamed
  test copy produced by `misc/rename.sh`, not the production project - never triage against it.)
- **Designer:** Jamey Stegmaier designed Tapestry, and his playtest feedback is transcribed in
  [TODO.md](TODO.md). He does not really file bug reports, so unlike some sibling projects there is
  no standing "hands off the designer's reports" rule here. If one ever does appear under his name,
  treat it as a design ruling rather than a player claim and check with Victoria first.

## Triage priority: Adjustment Civilization Pack

The Pack is where the game is going, so reports from Pack tables are worked **first**. This is a
priority ordering, not a filter - a report on a deprecated adjustment value is still a real report
and still gets triaged; it just waits behind the Pack ones.

The "Civilization Adjustments" game option (id 100 in
[gameoptions.inc.php](../gameoptions.inc.php)):

- `8` - Adjustment Civilization Pack (the official expansion) - **priority**
- `9` - Pack + reworked Alchemists (testing) - also the Pack, **priority**
- `1`, `2` - official / no adjustments, both deprecated EOL 2027 - lower priority
- `4` - experimental Nov 2022, removed from table creation - lower priority
- `3` - turns up on old tables; not a value the current option defines

The Pack has no converted rules doc (see the note under Rules sources), so a Pack civ's _intended_
behaviour is still an open question rather than a fact, even for a priority report.

**Every tracked entry records the table's options and player count.** Both change what "correct"
means, and both are one fetch away (see below), so there is no excuse for an entry without them:

- Civilization Adjustments (option 100) - which adjustment set the civ data came from
- Civilization Set (option 140) - `1` Original Only, `3` Original + Plans & Ploys, `7` All (+ Arts &
  Architecture); decides whether the reported civ or card was even in the game
- Marriage of State (101) and Renaissance (102) - `0` Keep, `1` Remove; house-rule removals that
  have their own bug reports
- Shadow Empire (152) on 2-player tables, Automa Level (151) on solo tables - both only meaningful
  at those player counts, and both send you to [RULES_AUTOMA.md](RULES_AUTOMA.md)
- Player count - 1-3 use the small map, 4-5 the big one, and several civs behave differently by
  count

## Reading a report page

- **Do not click "Load on the Studio".** That loads the table dump into the studio for debugging;
  it is not a triage step and it disturbs the studio state.
- Click **"See table no NNN"** instead. The table page lists the game options that table was
  created with - the row is literally called "Civilization Adjustments" - plus the player count and
  the players.
- Faster for a batch: the table page is ~1.9 MB and fills the option in from JS, so scraping it is
  painful. `POST /table/table/tableinfos.html?id=<TABLE_ID>` returns JSON; the value is
  `data.options["100"].value`. Same-origin `fetch` from any boardgamearena.com page works, with
  `x-request-token: window.bgaConfig.requestToken` as the only header needed. `data.options["140"]`
  is the separate "Civilization Set" option (1 = Original, 3 = +Plans & Ploys, 7 = All).
- Two other JSON endpoints on the same host save a lot of snapshot parsing:
  `POST /bugs/bugs/getBugs.html` (`page`, `game`, `per_page`) for the list, and
  `POST /bug/bug/getPageData.html` (`id`) for one report's title/status/votes/table/details.
  Comments are not in `getPageData` - read those from the report page snapshot.

## Rules sources (for `rules`-type reports)

Check a reporter's claim against these, in order, before assuming the code is wrong:

1. [RULES.md](RULES.md) - the base game rulebook (`TapestryRules_r13.pdf`) plus the per-space
   reference guide tables (`Tap_ReferenceGuide_r11.pdf`). You must read this every run.
2. [RULES_AUTOMA.md](RULES_AUTOMA.md) - the solo rules (`Automa_TapRules_r8.pdf`): Automa and
   Shadow Empire behaviour, the decision deck, hex tiebreaker, difficulty levels. Read this for any
   report on a solo table (the report page states the player count beside the dump info).
3. [FORMAL_RULES.txt](FORMAL_RULES.txt) - Victoria's rules interpretation: effect ordering,
   void/mandatory/optional semantics, advance/regress definitions. This is the tie-breaker for
   anything the rulebook leaves ambiguous, and it also records open questions to the designer. It
   is an interpretation, not a printed rule - cite it as such.
4. [TODO.md](TODO.md) - carries an unanswered "Questions for Jamey (orig dev)" block plus Jamey
   Stegmaier's own playtest feedback. Some entries are marked FIXED inline; do not assume the
   unmarked ones are still open without checking.

The converted rules cover the base game and solo play only. Expansion content (Plans & Ploys, Arts &
Architecture, Fantasies & Futures) and the Civilization Adjustments have no converted rules doc yet -
for a report about those, say so and record it as an open question rather than guessing.

Source PDFs live outside the repo in `~/Develop/bga/bga-assets/Tapestry/`. Convert with a tool
(`pdftotext`), never by reading the PDF directly.

## Deploy model

Process: **creating a git tag = deployed.** Tag name is the BGA version string, e.g.
`v260731-2000`. To decide if a fix is live:

- Find the fix commit: `git log --oneline -i --grep='#<reportid>'`. Commit convention here is
  `Bug #<id>: "<title>"` (7 such commits predate this file); sibling projects use `BGA #<id>`. The
  case-insensitive `#<id>` grep catches both.
- Is it in the latest tag: `git merge-base --is-ancestor <commit> $(git describe --tags --abbrev=0)
&& echo LIVE`.
- List tags containing it: `git tag --contains <commit> --sort=creatordate | head`.

In the latest deployed tag -> **Fixed**. Committed/tagged but that tag not deployed -> **Waiting for
deploy**. When unsure whether a tag is deployed, ask.

**Tagging only started 2026-08-26**, back-filled from Victoria's recollection: `v260731-2000` on
5ac2007 is the last deployed release. Anything after it on `main` is undeployed. There is no tag
history before that, so a fix commit older than 5ac2007 cannot be dated by tag - such a commit
predates the last deploy and is therefore live.

The local `prod` branch is stale (tip 2025-09-05, far behind `main`) and is NOT a deploy marker.
Ignore it.

## Investigation and tests

- Tests live under `tests/`, PHPUnit, configured by `phpunit.xml` (bootstrap `tests/_autoload.php`).
- Single method:
  `APP_GAMEMODULE_PATH=~/git/bga-sharedcode/misc/ phpunit --filter <method> tests/<File>.php`
- The investigation agent runs **only its own test**, never `npm run tests` or `npm run predeploy`.
- A reproducing test must be **green**, asserting the current buggy behavior, with a comment pinning
  it for `Bug #<id>` (flip the assertion when the fix lands) - see the skill's CONFIRMED note.
- Tests that touch civilization data must call `doAdjustMaterial($players, $variant)` first, since
  material is rewritten in place per player count and per Civilization Adjustments option.
- Framework behaviour comes from stubs in `~/git/bga-sharedcode/misc/php/stubs/`, which are more
  forgiving than production - a green test at a framework boundary is not proof.

## Bug triage last checked

**2026-08-26 18:48 EDT** - only triage BGA reports created/updated after this time; bump this line
to `date` at run start after each run.

(The 2026-08-26 run was scoped to the top defects by vote, not a full sweep - the Open and Waiting
for deploy status sweeps still have not been run. There are 2,624 reports on the tracker in total.)

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug
comment.

Pre-existing bug notes that are NOT BGA reports live in [TODO.md](TODO.md) (Jamey's playtest list,
the Coal Baron / Utilitarians notes). Leave them there; only reports with a BGA id belong here.

Entries marked **CONFIRMED** have a reproducing test named in them; everything else is a **lead**, a
hypothesis from reading code with no test behind it. Each entry records the cited table's options and
player count, per the priority section above. No BGA report status has been changed yet - the
confirmed two are still OPEN on the tracker.

### Priority: Adjustment Civilization Pack (adj=8), plus anything proven to hit it

- [x] **BGA #202072** - "civ: Utilitarians first action errors". OPEN on BGA, **27 votes** (the
      tracker's top defect), `action`, created 2026-01-19, latest player comment 2026-08-10. Listed
      here rather than under the deprecated options because it was **proven
      adjustment-independent** - it hits Pack tables too. After choosing Utilitarians the UI offers
      "Move token into <landmark> from [empty slot] [empty slot]", and any click returns the server
      error `[missing benefit]`. ~15 "same here" comments; one reporter (sslib) hit it gaining
      Utilitarians as a _third_ civ midgame instead of at setup.
      **CONFIRMED 2026-08-26**, twice over. Victoria reproduced it live in the studio (studio log
      27/08 00:59 UTC, table T950560, move 11) - that production stack trace is the real proof. Test
      `tests/UtilitariansTest.php`, methods
      `testUtilitariansTriggeredCrashesOnStateArgsReload`,
      `testUtilitariansMidgameCrashesOnStateArgsReload`,
      `testUtilitariansLandmarkSlotsAreAdjustmentIndependent` - green, pinning the buggy behaviour.
      Verified here: 3 tests, 11 assertions, OK.
      Root cause, three things composing: (1) `action_civTokenAdvance`
      ([PGameXBody.php:4698](../modules/PGameXBody.php#L4698)) calls `benefitCashed()` but only
      transitions at [:4702](../modules/PGameXBody.php#L4702), leaving a window inside the action
      where the game sits in state 14 (`civAbility`, `args => argCivAbility`) with a drained stack;
      (2) the triggered branch at [:4923](../modules/PGameXBody.php#L4923) and the midgame branch at
      [:4911](../modules/PGameXBody.php#L4911) both call `dbSetStructureLocation($id, $spot, 0)` with
      three arguments, so `$player_id` defaults to null all the way to
      [tapcommon.php:277](../modules/tapcommon.php#L277), which sends it into
      `getMostlyActivePlayerId()` -> `$this->gamestate->state()` -> the framework re-evaluating the
      current state's args; (3) [PGameXBody.php:9624](../modules/PGameXBody.php#L9624) throws instead
      of returning `[]`.
      It is intermittent because the crash needs the civ benefit to be the **last** row on the stack;
      with anything else pending, `argCivAbility` returns `[]` at
      [:9626](../modules/PGameXBody.php#L9626) and nobody notices.
      sslib's midgame report is the **same defect**, not a second one - identical three-argument call
      on the `$is_midgame` branch. TODO.md line 6 ("Utilitarients - no city when they place
      landmark") is **separate**: that is the `isAdjustments8()` branch at
      [:4926](../modules/PGameXBody.php#L4926), which only calls `queueBenefitInterrupt`.
      Adjustment-independence was verified by construction: the `lm` slot map for CIV_UTILITARIENS is
      byte-identical across variants 1, 2, 4, 8 and 9, so the 13 adj=1 player tables and the studio
      repro are one bug.
      Proposed fix, the investigation's pick: make [tapcommon.php:237](../modules/tapcommon.php#L237)
      call `$this->gamestate->state(true)`. The framework signature already carries the escape hatch
      (`state(bool $bSkipStateArgs = false, ...)`), `getMostlyActivePlayerId()` only reads
      `$state["type"]`, and skipping the arg load kills the **whole class** of failure - no
      null-player_id notification from any action in any state can re-enter an `arg*` method again.
      It is also a free performance win. Hardening `argCivAbility` to return `[]` instead would only
      cover this one state (`argBenefitChoice` at
      [:9605](../modules/PGameXBody.php#L9605) has the same shape); passing an explicit `$player_id`
      at :4911 and :4923 fixes only those two lines but is worth doing anyway, as a second commit,
      since a `moveStructure` notification whose player is guessed from the active player is wrong on
      its own terms.
      **Verify in the studio before shipping:** the vendored stub implements `state()` as a plain
      lookup and ignores `$bSkipStateArgs` entirely
      (`BgaFrameworkStubs.php:1196` - checked), so nothing local can prove production's `state(true)`
      actually skips `loadStateArgs()`. The doc comment says it does; `getCurrentMainState()` is the
      other candidate. This needs a live check, not a test.
      Same reason the test is partly synthetic: it overrides `getMostlyActivePlayerId()` in the UT
      subclass to do what the studio trace documents. Every other link in the chain is unmodified
      production code, and the test independently proves both facts that make the crash inevitable -
      `getMostlyActivePlayerId()` is reached, and `getCurrentBenefit()` is null at that moment.
      The reporter's table 792096537 is 5 players, adj=1 (with Adjustments), set=3 (Original + Plans
      & Ploys), Marriage of State kept, Renaissance kept, dump state 34 move 8. All 13 tables named
      in the report are adj=1: 792096537, 805148071, 813143236, 815820844, 819892187, 822175391,
      823545866, 825509956, 826092475, 827581079, 828703921, 862485195, 876708471.
      Note the whole-game backend log showed no `missing benefit` event in its retained window
      (2026-07-27 onward) even while the bug was live - absence there proves nothing for this assert.
      **FIXED 2026-08-26** (committed, not deployed). `getMostlyActivePlayerId()`
      ([tapcommon.php:236](../modules/tapcommon.php#L236)) now calls
      `gamestate->isMultiactiveState()` instead of the deprecated `state()` - the predicate reads
      the state row without the arg reload. Victoria chose it over the `state(true)` variant
      proposed above. Tests: `tests/UtilitariansTest.php` (renamed from
      `UtilitariansBenefitTest.php`, tests are now named by feature), 5 tests / 14 assertions,
      driving the real crash path end to end - the stubs now model `loadStateArgs()` (opt-in via
      `gamestate->game`), and reverting the fix reproduces the production error. Blind-reviewed;
      the review confirmed fix semantics, stub back-compat and test non-tautology.
      **NOTE for Victoria - studio check before marking fixed on BGA:** the real framework's
      `isMultiactiveState()` body must not itself reload args (the stub assumes it does not;
      nothing local can prove it). Replaying T950560's move 11 would settle it.
      Review follow-ups, all out of scope here: `action_unblock`
      ([PGameXBody.php:8620](../modules/PGameXBody.php#L8620)) has the same mid-action
      `state()["transitions"]` reload after `clearCurrentBenefit()` and is reachable in state 14
      (would still crash the same way); lower risk `state()` sites at PGameXBody:8549 (error
      paths), :12042 (zombieTurn) and tapcommon:62 (doUndoSavePoint) - `state(true)` them in a
      follow-up. The explicit `$player_id` at :4911/:4923 remains a good second commit. The
      predeploy FakeTestCase gate only runs `GameTest`, so the new tests run under real phpunit
      only - Victoria declined extending the legacy shim (since resolved: the shim is gone and
      predeploy runs all of `tests/`).
- [ ] **BGA #183142** - "Islanders gained exploration tiles after the opportunity to use civ
      ability". OPEN on BGA, 11 votes, `rules`. Table 726016713, 5 players, dump state 15 move 9,
      created 2025-09-06. Options: adj=8 (Pack), set=7 (All: Original + PP + AA), Marriage of State
      removed, Renaissance removed.
      Reporter: prompted to explore on the Islanders mat at the start of income turn 1 _before_ being
      given the 4 starting territory tiles; expects the 4 tiles first.
      **CONFIRMED 2026-08-26 (code ordering only - see the caveat).** Test
      `tests/IslandersTest.php`, method
      `testIslandersIncomeTurn1TriggerJumpsAheadOfStartTiles` - green, pinning the buggy behaviour.
      Verified here: 2 tests, 7 assertions, OK.
      Root cause, and it is **not Islanders-specific** - Islanders just exposes it. `setupCiv` calls
      `interruptBenefit()` at [PGameXBody.php:10503](../modules/PGameXBody.php#L10503) before queuing
      the civ's `start_benefit`, and `interruptBenefit` is global:
      `UPDATE benefit SET benefit_prerequisite = benefit_prerequisite + 1`
      ([:6241](../modules/PGameXBody.php#L6241)). `stFinishSetup` runs `setupCiv` once per player, so
      every later player's setup pushes every earlier player's start rows one prerequisite deeper.
      `BE_RESUME` is then queued after the loop at prerequisite 0
      ([:10475](../modules/PGameXBody.php#L10475)). `getCurrentBenefit()` pops
      `ORDER BY benefit_prerequisite, benefit_id` ([:9189](../modules/PGameXBody.php#L9189)), and
      `awardBenefits` case 201 does `clearCurrentBenefit(); jumpToState(13); return false`
      ([:1897](../modules/PGameXBody.php#L1897)), which ends the drain. **So setup finishes with the
      start benefits of every player except the last one processed still on the stack.** If such a
      player is the randomly chosen starting player, their income turn 1 calls
      `queueEraCivAbilities()` first ([:3891](../modules/PGameXBody.php#L3891)), inserting the civ
      trigger at prerequisite 0 - ahead of their own stranded tiles. That is literally the report
      title.
      Repro window is narrow: the Islanders player must be the starting player AND not last in the
      `setupCiv` loop. If anyone else acts first, that turn's end-of-turn drain flushes the stranded
      rows and the bug disappears. (Every player's first turn is an income turn per
      [RULES.md](RULES.md) Player setup - "Each player's first turn is an income turn" - so that
      condition is automatic, not a third constraint.)
      **This is a class of bug.** Verified in material: **ADVISORS** has
      `start_benefit => ["m" => 3, "g" => BE_TAPESTRY]` with `income_trigger@a8` from=1, and
      **HISTORIANS** has `start_benefit@a4a8 => [BE_TERRITORY]` with `income_trigger@a4a8p4p5` from=1
      (4-5 players). Both have the same collision under the Pack. Fix the class, not the civ.
      Proposed fix: after the `setupCiv` loop in `stFinishSetup`, flatten the stack
      (`UPDATE benefit SET benefit_prerequisite = 0`) before queuing `BE_RESUME`, so setup rows drain
      in insertion order and `BE_RESUME` - highest `benefit_id` - pops last. Narrower alternative:
      skip `interruptBenefit()` in `setupCiv` when `$start` is true, since that interrupt exists for
      the midgame path (a civ gained mid-turn must cut ahead of the current stack) and is actively
      harmful during setup. **Confirm that reading of `interruptBenefit` before committing to it.**
      There is no `Islanders.php` in [modules/civs/](../modules/civs/) - it falls back to
      `BasicCivilization`, so nothing sequences setup against the income trigger. `slots` and
      `midgame_setup` are irrelevant here (they feed the midgame path).
      **Caveat on the evidence - weaker than the other two confirmations.** The vendored stubs run no
      SQL (`DbQuery` is a no-op), so the test cannot exercise the benefit table end to end. It
      overrides `interruptBenefit()` and `benefitSingleEntry()` with in-memory equivalents and then
      calls the _real_ `setupCiv` and `queueEraCivAbility`, so the sequencing decisions come from
      production code - but the final step ("`BE_RESUME` pops and ends the drain, stranding the
      rest") is established by reading `awardBenefits` case 201 and `stBenefitManager`, not by
      executing them. Those lines were re-read and confirmed by hand. Treat this as strong evidence,
      not the same grade of proof as a studio repro.
      Rules half: **still OPEN.** The only in-repo source is card text transcribed into
      implementation data - `CIV_ISLANDERS` `description@a8` says "Start with 4 territory tiles in
      your supply." and "<b>At the beginning of your income turns (1-5)</b>, you may explore 1 of the
      hexes on this mat". "Start with" reads naturally as setup-time and therefore before income turn
      1, but that is a reading of card text, not a ruling. Nothing in
      [FORMAL_RULES.txt](FORMAL_RULES.txt) or [TODO.md](TODO.md) settles it. Maintainer to decide.
      Aside: the reported dump state 15 (`playTapestryCard`) cannot be income turn 1 - tapestry play
      is only queued for income turns 2-4 ([:3895](../modules/PGameXBody.php#L3895)) - so the dump
      was taken after the incident. The reporter's own pointer is "before move #10".
      No fix commit.
- [x] **BGA #203108** - "Gaining Historian Midgame." **FIXED 2026-08-26.** Reporter gained
      HISTORIANS midgame in era 4 with no advancement-track landmarks left and got none of the
      exposed benefits. `Historians::noLandmarksLeft()` counted every `landmark_mat_slot%` row, but
      landmarks 13-19 are the extra pool (Bakery, Barn, Com Tower, Library, Stock Market, Treasury,
      Urban Center) and never sit on a track, so the count was never zero and the clause never
      fired. Renamed to `noTrackLandmarksLeft()` and filtered to `card_location_arg2 <= 12`, the
      same cut already used at [PGameXBody.php:9431](../modules/PGameXBody.php#L9431) and
      [Historians.php:93](../modules/civs/Historians.php#L93). Tests: `tests/HistoriansTest.php`,
      `testTrackLandmarksExhaustedButMatStillHoldsExtras`,
      `testTrackLandmarkRemainingBlocksTheClause`,
      `testEmptyMatAwardsNothingWithoutTheAdjustmentPack`.
      Also gated the clause on `isAdjustments4or8()`: it is printed only on `description@a4a8`
      ("<i>Then, if there are no landmarks remaining on advancement tracks, gain the exposed
      benefits.</i>"), the base card stops at "leaving the squares exposed on this mat". Without the
      gate the filter fix would have started awarding variants 1/2 four benefits the printed card
      never promises - the clause was previously dead code there, so this was latent, not a
      regression.
      **NOTE (not studio-verified)** - the stubs run no SQL, so the tests model the structure table
      rather than executing it. The filter itself is now plain PHP and is exercised, but a live
      table 795801647 check would be the real confirmation.
      **Also fixed (same family, no separate report).** Benefit 111's guard accepted any
      `landmark_mat_slot%` row while its arg builder and
      [selectLandmark](../modules/PGameXBody.php#L8280) both cut at 12. Setup seeds all 19 landmarks
      at `landmark_mat_slot1..19` ([:610](../modules/PGameXBody.php#L610)) even though the client
      draws 13-19 in a separate `landmark_extra` container, so the prefix does match the extras.
      With only extras left the guard passed, state 34 opened with empty `choices` and has no
      decline action - the active player soft-locked instead of getting the "No more landmarks left"
      skip. Deferred once as rare (needs all 12 track landmarks claimed and Dystopia firing
      afterwards), then taken once `getUnclaimedTrackLandmarks()` made it a one-liner. Test
      `tests/LandmarksTest.php::testDystopiaSkipsWhenOnlyTheExtraPoolIsLeft`.
      **NOTE (unverified, pre-existing)** - `activateBenefits`
      ([Historians.php:116](../modules/civs/Historians.php#L116)) queries `civ_7_%` with no owner
      filter; harmless with one owner, latent if the a4/a8 "discard and draw another in era 1-2"
      path can leave a prior holder's cubes behind. Not traced.
      **NOTE (cosmetic)** - the literal 12 now appears in five places (Historians.php 93 and 109,
      PGameXBody.php 3009, 8268, 9431). A `LANDMARK_TRACK_MAX` constant would be the tidy-up; not
      done, to keep the fix diff small.
      Related but a separate ticket: the `Invalid historian token` auto-error is thrown at
      [Historians.php:41](../modules/civs/Historians.php#L41) when the chosen token is not at
      `civ_7_$token_id`; after a midgame acquisition all 4 tokens have moved to `pb_X`, so if the
      income-turn "send a historian" action is still offered the server throws. Same civ, same
      midgame state, but a different code path and the table ids were not cross-checked - a guess.
      Note it is a bare `feException`, not `userAssertTrue`, so it surfaces as a crash rather than a
      friendly message.

### Lower priority (deprecated adjustment options)

The highest-voted defects on the tracker, but every table cited on them ran a deprecated
Civilization Adjustments value, so they queue behind the Pack ones above. Still real reports.

- [ ] **BGA #102790** - "Spies and Revolution tapestry". OPEN, 21 votes, `action`. Table 432621471,
      2 players, Oct 2023. Options: adj=1 (with Adjustments), set=2 (a value the current option does not
      define), Marriage of State kept, Renaissance kept, Shadow Empire off.
      Using the Spies ability to copy the Revolution tapestry to stand up 3 outposts returned
      `Internal Error ... [expecting tapestry 35 but it was none]`. Tapestry 35 is
      REVOLUTION (`material.inc.php`, `"or" => [BE_ANYRES, BE_STANDUP_3_OUTPOSTS]`), so the error names
      the right card and the copy path lost it. Unverified. No fix commit.
- [ ] **BGA #58486** - "Age of Discovery does not work properly." OPEN, 15 votes, `rules`. Table
      237670282, 3 players, Jan 2022. Options: adj=1 (with Adjustments), set=1 (Original Only), Marriage
      of State kept, Renaissance kept.
      With two tokens on the science track (after finishing Technology and restarting on Science), an
      Age of Discovery science roll advanced the lower token with no choice offered.
      Rules: [RULES.md](RULES.md), "Other important notes", AI SINGULARITY - "This technology track
      benefit may result in you having multiple player tokens on the same track. Either is eligible for
      advance turns. When considering the relative position on a track, only look at your most advanced
      token." That backs the reporter: either token is eligible, so the player picks. Note #62514 ("Not
      allowed to pick which cube on an Advancement Track to move", 9 votes, adj=1) looks like the same
      defect - check for duplicate before working either.
- [ ] **BGA #118067** - "Forced to play dystopia with no buildings remaining". OPEN, 12 votes,
      `block`. Table 487565028, 2 players, Mar 2024. Options: **adj=4** (experimental Nov 2022, removed
      from table creation), set=1 (Original Only), Marriage of State removed, Renaissance removed,
      Shadow Empire off.
      Forced to play the Dystopia tapestry with no buildings left, with no decline and no undo - game
      stuck. Unverified. No fix commit.
- [ ] **BGA #70600** - "I drew Mystics mid game from Radio and it kept it". OPEN, 12 votes,
      `rules`. Table 294249199, 2 players, Aug 2022. Options: **adj=3** (a legacy value the current
      option does not define), set=1 (Original Only), Marriage of State removed, Renaissance removed,
      Shadow Empire off.
      Reporter says Mystics should have been discarded with another civ drawn instead.
      Rules: the base rulebook has no midgame-civ discard rule at all ([RULES.md](RULES.md), "Other
      important notes", CIVILIZATIONS, says only "It is possible to gain additional civilizations ...
      add them to the left of your current civilization mat"); the discard-and-redraw wording lives in
      the _civ mats'_ own midgame text. Whether Mystics carries it is an open question, not a fact.

### Studio auto-errors (no reporter, 2026-08-26 sweep)

From `studioissues?game=tapestry&projects=backend`, timespan "Three months" - the log only actually
retains back to **2026-07-27**. Nobody filed reports for these; they are for the maintainer to
prioritise, and per the skill no BGA report should be opened for them. Event/user counts are from
that window.

- [ ] `feException: Invalid territory tile` - **106 events / 75 users**, 2026-08-05 to today, still
      firing. Biggest blast radius on the tracker by a wide margin. Two ids for the same text
      (`...146A8F` and `...145Y47`, the older one 42 events to 2026-08-05), so the real count is ~148.
- [ ] `feException: This transition (benefit) is impossible at this state (20)` - **89 events / 11
      users** (plus `...3814642R`, 39 events / 7 users). Few users, many events - that is people stuck
      retrying, i.e. a block, and it deserves priority above its raw count. State 20 is a benefit state.
- [ ] `feException: invalid map location` - 46 events / 36 users, last seen today.
- [ ] `DatabaseInvalidResultException` on `getUniqueValue` for
      `SELECT card_id FROM structure WHERE card_location='land_R_C' AND card_location_arg='1'` -
      fragmented across ~14 issue ids (35, 30, 20, 2, 2, then nine singletons) for `land_2_3`,
      `land_3_1`, `land_3_2`; summed it is ~95 events / ~50 users. Two structures share one map
      location where the code expects one.
- [ ] `feException: items do not belong to player` - 25 events / 12 users (plus 15 / 8 on an older
      id), last seen today.
- [ ] `feException: Invalid historian token` - 7 events / 4 users. Possibly related to **#203108**
      above (Historians), but that is a guess - the table lists were not cross-checked.
- [ ] `TypeError: Unsupported operand types: string + int` - 3 events / 3 users, twice.
- [ ] `feException: Error: generated notifications are larger than 128k (142359)` - 4 events / 2
      users. A single turn producing >128k of notifications will hard-fail.

Not yet checked this run: the `projects=frontend` (browser/JS) half of the log.

**Housekeeping:** resolved - `npm run predeploy` now runs the whole `tests/` directory through real
PHPUnit, so every new test file gates commits with no extra wiring.

## Triage run log

Short log of each run: date, reports touched, outcome.

- **2026-08-26** - Setup only, no triage run. Created this file and [RULES.md](RULES.md) (converted
  and [RULES_AUTOMA.md](RULES_AUTOMA.md) (converted from the rulebook, reference guide and Automa
  PDFs with `pdftotext`). Bootstrapped the deploy model by
  back-tagging `v260731-2000` on 5ac2007, the last commit before the 2026-08-25 work burst; 20
  commits on `main` are undeployed as of today. Game id 1446 and studio short name `tapestry` both
  confirmed by Victoria.
- **2026-08-26** - First real triage run, scoped by Victoria to the **top defects by vote** rather
  than a full sweep; the Open / Waiting for deploy status sweeps were **not** run. Looked at
  #202072, #102790, #58486, #118067, #70600 (the five highest-voted bug-type reports) plus #183142
  and #203108 (surfaced by the adj=8 filter). **No report status was changed and no public comment
  was posted** - nothing reached a reproducing test, and the two Pack reports turn on Pack rules that
  have no converted doc. Checked all 30 top-voted reports for their table's Civilization
  Adjustments value and player count: 17 on adj=1, 9 on adj=4, 1 on adj=2, 1 on adj=3, and only
  **2 on adj=8** (#183142, #203108) - so the Pack-priority queue is currently two reports deep.
  Recorded the backend studio auto-errors above. Confirmed the option-check technique
  (`tableinfos.html`) and wrote it into "Reading a report page".
  **Finding worth acting on:** making the Pack the default is commit `f76dddf`, which is **not in
  `v260731-2000`** and therefore not deployed - live BGA still defaults option 100 to `1`. Until the
  pending commits ship, almost no new report will come from a Pack table, so the Pack-priority queue
  will stay near-empty and the lower-priority list is where the volume is.
- **2026-08-26 (later, same session)** - Victoria reproduced **#202072** live in the studio and
  supplied the full stack trace, then had three investigation agents launched. Two came back
  **CONFIRMED** with green tests that pin the current buggy behaviour: #202072
  (`tests/UtilitariansTest.php`, 3 tests / 11 assertions) and #203108
  (`tests/HistoriansTest.php`, 3 tests / 9 assertions). Both re-run and verified here. The
  #183142 Islanders agent then also returned **CONFIRMED** (`tests/IslandersTest.php`,
  2 tests / 7 assertions, re-run and verified here), but on weaker evidence - the stubs run no SQL,
  so its test models the benefit table rather than executing it; the mechanism was re-read and
  confirmed by hand instead.
  #202072 turned out **not** to be a Utilitarians bug at all - it is a null-`player_id` notification
  re-entering `arg*` state-argument evaluation mid-action, and it was proven
  adjustment-independent, so it moved from the lower-priority list up into the priority section.
  Its proposed one-word fix (`state(true)`) **cannot be validated locally** - the vendored stub
  ignores `$bSkipStateArgs` - so it needs a studio check before shipping.
  #183142 likewise turned out to be a class defect rather than a civ bug: `setupCiv`'s global
  `interruptBenefit()` strands every player's start benefits except the last one's, which also
  exposes ADVISORS and HISTORIANS under the Pack. So two of the three confirmed reports are engine
  ordering/lifecycle defects wearing a civ's name, and the fixes are structural.
  Still no BGA status changes and no public comments: both confirmed reports remain OPEN on the
  tracker pending Victoria's go-ahead. The Open / Waiting for deploy sweeps and the frontend error
  log remain unswept.
- **2026-08-26 (later still)** - Implemented the **#202072** fix. Victoria overruled the
  `state(true)` variant since `state()` is deprecated; `getMostlyActivePlayerId()` now calls
  `gamestate->isMultiactiveState()`. The shared stubs
  (`bga-sharedcode/.../BgaFrameworkStubs.php`, uncommitted there) were extended so the local
  validation gap closed: `state()` honors `$bSkipStateArgs` and models `loadStateArgs()` when the
  test wires `gamestate->game`, internal stub helpers switched to a private `stateRow()` so only
  the deprecated public path reloads args. The Utilitarians tests were flipped from pinning the
  crash to asserting the fix through the real code path (5 tests / 14 assertions; reverting the fix
  reproduces the production error - checked both directions). Full suite and predeploy green.
  Remaining before shipping: studio check that the real `isMultiactiveState()` does not reload
  args, and the explicit `$player_id` at :4911/:4923 as a second commit.
- **2026-08-26 (wrap-up)** - Ran the game-bug-fix loop's missing steps for **#202072**: blind
  review by a fresh agent (it independently re-ran the suite and a mutation check - reverting the
  fix crashes the new tests with the production error), findings triaged into the entry above. New
  finding worth its own line: **`action_unblock` still carries the same defect class** - args
  reload on a drained stack, reachable from the "Unblock stuck game" button in state 14. The
  reviewer's suggestion to name the test `Bug202072Test.php` was rejected (feature-named tests are
  now the convention, in CLAUDE.md); its predeploy-gate finding (FakeTestCase runs only `GameTest`)
  was declined by Victoria as legacy. Fix committed; entry flipped to fixed, studio check still
  pending before the tracker is touched.
