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
  `&id=<ERROR_ID>` for one error, `&projects=frontend|backend` to filter.
- **Designer:** Jamey Stegmaier designed Tapestry, and his playtest feedback is transcribed in
  [TODO.md](TODO.md). He does not really file bug reports, so unlike some sibling projects there is
  no standing "hands off the designer's reports" rule here. If one ever does appear under his name,
  treat it as a design ruling rather than a player claim and check with Victoria first.

## Triage priority: Adjustment Civilization Pack

The Pack is where the game is going, so reports from Pack tables are worked **first**. This is a
priority ordering, not a filter - a report on a deprecated adjustment value is still a real report
and still gets triaged; it just waits behind the Pack ones.

The "Civilization Adjustments" game option (id 100 in
[gameoptions.jsonc](../gameoptions.jsonc)):

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

`main` is the production branch; its latest tag is what runs on prod. The `prod`, `prod1` and
`prod2` branches are inactive and NOT deploy markers. Ignore them.

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

- [ ] **Historians leads left over from the 52459b2 token fix** (shipped in v261026-0118):
      `onMystic` (~2427) collects global `.clicked`; an owner of both Historians and Mystics could
      send a leftover historian holder as a Mystics id. Should be scoped to `#civilization_13` -
      untested. `Historians::sendHistorian` still throws untranslated `feException` for a bad token
      or tile and never checks `$pid`.

- [ ] **BGA #252974** - "Solo game error when starting due to something with SQL request". OPEN,
      1 vote, `access`. Table 939097438, 1 player. Options: adj=8, set=7, Marriage of State removed,
      Renaissance removed, Automa level 6. Setup fails with `UPDATE card SET card_location='limbo' ...
      WHERE card_id IN ()` (quoted in the report). Cause: solo leaves TAP_MARRIAGE_OF_STATE out of
      the tapestry deck (`setupNewGameTables`, `$atoma_tap_exclusions`), so with option 101 = Remove
      `removeSomeComponents` passes `[]` to `dbMoveCards`. Regression from 9afe285 (live since
      v260926-0320); the old raw UPDATE matched zero rows harmlessly. Every solo table with Marriage
      of State removed cannot start. **FIXED 2026-10-28 (uncommitted)**: `dbMoveCards` returns early
      on an empty list; guarded on real MySQL by `tests/GameOptionsDbTest.php`
      `testRemovingCardAbsentFromDeckDoesNotCrashSetup` (fails without the guard). The "removed from
      the game" message still posts in solo, as it did before 9afe285. Not from today's deploy; not
      in the studio error log (setup failures do not land there).

- [ ] **BGA #252596** - "Unable to select landmark to place". OPEN, 1 vote, `action`. Table
      934388349, 4 players. Options: adj=8, set=7, Marriage of State removed, Renaissance removed.
      Urban Planners: a landmark gained from a tech upgrade shows beside the outposts instead of on the
      civ mat and cannot be selected on later turns; reporter does not know how it got there. Not
      triaged.

- [ ] **BGA #168202** - "Utilitarians are able to use the Lighthouse effect during their Income
      turn". OPEN on BGA, 1 vote, `rules`. Not triaged. The card says "Once on each of your
      advancement turns"; `ownsLighthouseAndCanPlayIt` checks once per turn (v261025-1948, #252057)
      but not the turn kind. Open rules question, no ruling in RULES.md or FORMAL_RULES.txt.

- [ ] **Historians sending to Automa / Shadow Empire** - BGA #208143 (OPEN; #209034 by the same
      reporter marked Duplicate of it) and #88718 (OPEN, 6 votes, solo, Historians gained midgame do
      not give the tokens away; also a "negative score" complaint). Not triaged. Probably also
      #223277 ("Unable to place historian token", era 2, cannot select a player; table 860658433,
      adj=1, apparently solo; older than the error log retention) - unverified.

- [ ] **Unblock in civAbility, follow-up of BGA #202072** - `action_unblock` (PGameXBody.php ~9420)
      reads `$this->gamestate->state()["transitions"]` after `clearCurrentBenefit`; in state 14 that
      reloads `argCivAbility`, which asserts "missing benefit" when the civ row was the last on the
      stack (midgame civ gain, landmark trigger), so Unblock rolls back. Setup is safe (BE_RESUME
      underneath). **CONFIRMED 2026-10-25**, pinned green in `tests/UtilitariansTest.php`
      `testUnblockLastCivBenefitStillHitsMissingBenefit` (flips when fixed); safe case
      `testUnblockSetupCivBenefitWithResumeRowBelow`. Proposed fix: a transitions accessor that does
      not reload args, or always `jumpToState(18)` after clearing (every unblockable state returns
      to 18). Low severity: only Unblock is affected; the normal civ action works since 283c361.
      NOTE: unverified that the real framework's `isMultiactiveState()` does not reload args (the
      stubs model it that way).
      NOTE: unrelated lead, untested - on adj=8 `queueEraCivAbility` fires at income 2 for any
      Utilitarians owner; a midgame owner has no landmark on `civilization_39`, so ~5488 would read
      a null structure. Not checked whether the civ can be gained before income 2.

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
      firing. Biggest blast radius on the tracker by a wide margin.
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
- [ ] `feException: Invalid historian token` - 7 events / 4 users. Client cause fixed in 52459b2,
      shipped in v261026-0118 - check the Release breakdown shows nothing on 0118 before closing.
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

- **2026-10-24** - Incident run, scoped by Victoria to the last ~8 hours (marker NOT bumped: the
  backlog since 2026-08-26 is still unswept). Deploy `v261024-2009` broke every in-flight prod
  table: the income spot migration guard (`<= 2609091200`) sat below the previous prod version
  2609160116, so old tables kept `card_location_arg2 = 0` on income buildings; the client aborted
  setup (`ownerDocument` / `null.id` JS errors, no cubes or buildings drawn) and income reads hit
  `ERR:game:04 ... uncovers 6 spots`. Hotfix bd2f96d (guard `<= 2610242100`, only players with
  unassigned rows, so an Artificers slide survives) shipped as `v261025-0248`; verified live by
  spectating tables 936813355 and 936879986. 36 reports in the window: 34 are this incident.
  All 34 set to Fixed with the BGA default template (verified); English translations posted first
  on #252154, #252116, #252113, #252105. Left Open:
  #252057 (tapestry card + civ infinite loop, unrelated) and #252054 (thin, game abandoned).
  Unrelated burst seen: `NotificationTransportException` on the backend from ~16:23, BGA side.
- **2026-10-25 13:42 EDT** - Post-deploy check of `v261025-1540`, scoped by Victoria to the last ~4
  hours (marker NOT bumped). The deploy did not break anything: no studio error first seen today,
  and none of yesterday's income spot JS errors have fired since 21:04 yesterday. Only 3 errors
  fired in the window, all pre-existing: `Invalid territory tile` (2 of 80 events on 1540),
  Historians state 36 (1 of 78 events on 1540; #252235's table alone has ~15 retries)
  and `Invalid historian token` (2 of 7 on 1540, unrelated to that tag's diff). Reports touched:
  #252235 and #138733 (Historians, tracked above). No status changes, no comments.
- **2026-10-25 16:10 EDT** - Post-deploy check of `v261025-1948` (marker NOT bumped): no new error
  on 1948 except 2 single-event `DatabaseTimeOutException` in untouched code (BGA side), state 36
  silent since before the deploy, no report activity. Closed as Fixed (BGA default template):
  #252057 with Lighthouse duplicates #151628 and #198766; #252235 with state 36 duplicates
  #250595, #245071, #241249, #231672, #197005, #193285, #191870 (each quotes the error).
  #138733 also Fixed (votes are the crash; its original no-tile claim is unverified). #210991 is a
  reporter's own "you can close this", untouched. #202072 (27 votes) Fixed: 283c361 shipped in
  v260826-2103 and every comment predates it; a regression-check agent is investigating. #252054
  Fixed on a bet: no server error for its table, filed minutes before the v261024-2009 client
  errors that v261025-0248 fixed - timing only, unproven.
- **2026-10-25 16:40 EDT** - Historians consolidation, all open reports read (31 open, 33 already
  auto-closed "not updated" left alone). Fixed (BGA default template): the double-transition send
  crash in v261025-1948 landing on state 36, 20 or 29 - #199906, #244247, #235432, #213638, #190850,
  #189334, #185663, #183478 (quote the error), and #245405, #220533, #183196, #203747, #197023,
  #246816, #204760, #193742, #193415, #186281, #203142, #204833 (same send-time error, no exact
  text); the midgame-benefits bug fixed in v260826-2103 - #203108, #220995, #200225, #191982.
  English translations posted first on #244247, #235432, #213638. #209034 Duplicate of #208143.
  #210890 Not a bug (late midgame Historians cannot be discarded; card text quoted). Left open:
  the no-tile cluster and Automa/Shadow (tracked above), #223277 (no error, cannot pick a player -
  maybe the undeployed client slot fix), #195898 (Coal Baron, state 20, no Historians mention).
- **2026-10-25 16:55 EDT** - No-tile cluster proven by the investigation agent (tests cherry-picked
  as aec06b3). #214486 set Confirmed (BGA default template) as canonical; #246956, #196736, #195672,
  #193150, #177610, #171977, #160624 marked Duplicate of it.
- **2026-10-25 20:21 EDT** - Close-out of `v261026-0118` (014fd29 start benefits order, 52459b2
  historian token client), scoped by Victoria to Historians / Islanders (marker NOT bumped). Fixed
  (BGA default template, verified): #214486, #183142 and the Islanders no-tiles-before-income-1
  reports #251631, #227602, #223048, #215457, #208744, #198419, #193083; English translation
  posted first on #208744. Duplicates of #214486 left as Duplicate (#246956 may be adj=1, not this
  bug). #239039 (4p, adj=8, setup, no server error) also Fixed on Victoria's call - unproven.
  Left open: #223277 (moved to Automa/Shadow).
  Unrelated, untouched: #216213 and #223846, Utilitarians start landmark `missing benefit`.
- **2026-10-26 18:20 EDT** - Last-24h check (marker NOT bumped). Only two new reports, both adj=8,
  both tracked above: #252643 (`block`, Treasure Hunters conquer crash, regression from 47be951 by
  code reading) and #252596 (Urban Planners landmark). The other 24h rows were the previous run's
  own close-outs. Studio error log was down for maintenance, so no release breakdown. No status
  changes, no comments.
- **2026-10-28** - Deploy v261028-1348 (fix 0e96db9). #252643 and #252781 (same Treasure Hunters
  conquer crash, tables 938488147 and 938040949, matched by symptom only: the studio error log shows
  no errors for either table, nor for 933811349) set to Fixed with a "should be fixed, press F5 and
  pass again" comment. Resuming a stuck table not verified on Studio. Marker NOT bumped.
- **2026-10-28 08:53 EDT** - Last-48h check (marker NOT bumped: the window does not cover the gap
  since the marker). Cluster of the same Treasure Hunters conquer crash, all set to Fixed with the
  same "press F5 and pass again" comment: #252679 and #252696 (table 930891813, 3p, adj=1, quote
  the `one owner` assertion on an outpost plus a `:civ:38` token; English translation posted on
  #252679), #252672 (938155847, 2p, adj=8, same assertion), #252685 (933811349, the #252643 table),
  #252810 (935378358, 2p, adj=8, no error quoted, matched by symptom only). #252596 untouched.
- **2026-10-28 17:35 EDT** - Post-deploy check of `v261028-1348`, last 48h (marker NOT bumped). No
  Waiting for deploy reports exist. The Treasure Hunters cluster closed this morning has no player
  replies. Two new reports since the deploy: #252974 (solo setup crash when Marriage of State is
  removed, regression from 9afe285, NOT from this deploy, tracked above) and #252909 (BGA site will
  not load, no server errors for table 938929744, not a game bug). Studio log: three error ids first
  seen today (`Invalid territory tile` J4Y, `invalid map location` J4B, `items do not belong to
  player` HS7) are the same throw sites as before, shifted +4/+5 lines by the deploy; the old ids
  stopped before it. No new error class on 1348. No status changes, no comments.
