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

**2026-11-07 16:33 EST** - only triage BGA reports created/updated after this time; bump this line
to `date` at run start after each run.

Reports untouched since before 2026-11-03 are grandfathered: skip the full Open sweep and do not ask
about them. Still run the Waiting for deploy sweep.

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug
comment.

Pre-existing bug notes that are NOT BGA reports live in [TODO.md](TODO.md) (Jamey's playtest list,
the Coal Baron / Utilitarians notes). Leave them there; only reports with a BGA id belong here.

Entries marked **CONFIRMED** have a reproducing test named in them; everything else is a **lead**, a
hypothesis from reading code with no test behind it. Each entry records the cited table's options and
player count, per the priority section above.

### Priority: Adjustment Civilization Pack (adj=8), plus anything proven to hit it

- [ ] **Leads left over from #255108 / #254612** (both shipped in v261107-2229), unverified: the
      Advisors fix was never checked in the studio, and the advisor gets no warning when picking a
      THIS ERA Espionage target (it gives nothing); `notif_tapestrycard` lowers non-active viewers'
      hand counter on every clone or overplay notif, Heralds included; a tech card invented this
      session has no `data-card-id` until F5.

- [ ] **Historians leads left over from the 52459b2 token fix** (shipped in v261026-0118):
      `onMystic` (~2427) collects global `.clicked`; an owner of both Historians and Mystics could
      send a leftover historian holder as a Mystics id. Should be scoped to `#civilization_13` -
      untested. `Historians::sendHistorian` still throws untranslated `VisibleSystemException` for a bad token
      or tile and never checks `$pid`.

- [ ] **BGA #245466** - "Traders income action". OPEN, 1 vote, `action`. Table 915804573. Options:
      adj=8, set=7. Traders could not use the income ability although "there was a tile with 1
      outpost on it"; a commenter says the log shows no civ income action at all. Not triaged; not
      known whether the ability was skipped or just had no valid target (e.g. no tile in hand).

- [ ] **BGA #252596** - "Unable to select landmark to place". OPEN, 1 vote, `action`. Table
      934388349, 4 players. Options: adj=8, set=7, Marriage of State removed, Renaissance removed.
      Urban Planners: a landmark gained from a tech upgrade shows beside the outposts instead of on the
      civ mat and cannot be selected on later turns; reporter does not know how it got there. Not
      triaged. 2026-10-31 reporter: end of game said "you have 1 landmark in area 2", "Can close
      this out". No server errors for the table. Left Open: the landmark being unplaceable is still
      unexplained.

- [ ] **BGA #253474** - "convert 2 tiles to 5 points". INFO NEEDED (2026-11-01), 1 vote, `action`.
      Table 940371646, solo. Options: adj=8, set=1, Marriage of State removed, Renaissance removed,
      Automa level 2. Confirm on the exploration track 8 bonus (discard 2 territory tiles for 5 VP)
      "proceeds to error on server". No studio error for the table (token checked against the
      whole-game list), so likely a user-facing exception, which is not logged. Asked for the exact
      message, move, tile count, and whether F5 helped.

- [ ] **BGA #168202** - "Utilitarians are able to use the Lighthouse effect during their Income
      turn". OPEN on BGA, 1 vote, `rules`. Not triaged. The card says "Once on each of your
      advancement turns"; `ownsLighthouseAndCanPlayIt` checks once per turn (v261025-1948, #252057)
      but not the turn kind. Open rules question, no ruling in RULES.md or FORMAL_RULES.txt.

- [ ] **Historians sending to Automa / Shadow Empire** - BGA #208143 (OPEN; #209034 by the same
      reporter marked Duplicate of it) and #88718 (OPEN, 6 votes, solo, Historians gained midgame do
      not give the tokens away; also a "negative score" complaint). Not triaged. Probably also
      #223277 ("Unable to place historian token", era 2, cannot select a player; table 860658433,
      adj=1, apparently solo; older than the error log retention) - unverified.

- [ ] **Utilitarians midgame owner lead** (from the #202072 Unblock follow-up, shipped in
      v261107-2229), untested: on adj=8 `queueEraCivAbility` fires at income 2 for any Utilitarians
      owner; a midgame owner has no landmark on `civilization_39`, so it would read a null
      structure. Not checked whether the civ can be gained before income 2.

- [ ] **BGA #216213, #223846** - Utilitarians start landmark `missing benefit`. Not triaged.

- [ ] **BGA #195898** - Coal Baron, state 20 error, no Historians mention. Not triaged.

- [ ] **BGA #210991** - the reporter asked to close it themselves. Untouched.

### Lower priority (deprecated adjustment options)

The highest-voted defects on the tracker, but every table cited on them ran a deprecated
Civilization Adjustments value, so they queue behind the Pack ones above. Still real reports.

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

- [ ] `feException: This transition (benefit) is impossible at this state (20)` - **89 events / 11
      users** (plus `...3814642R`, 39 events / 7 users). Few users, many events - that is people stuck
      retrying, i.e. a block, and it deserves priority above its raw count. State 20 is a benefit state.
- [ ] `feException: invalid map location` - 46 events / 36 users, last seen today. Logged as
      `VisibleSystemException` after the exception migration, a new issue id, not a fix.
- [ ] `DatabaseInvalidResultException` on `getUniqueValue` for
      `SELECT card_id FROM structure WHERE card_location='land_R_C' AND card_location_arg='1'` -
      fragmented across ~14 issue ids (35, 30, 20, 2, 2, then nine singletons) for `land_2_3`,
      `land_3_1`, `land_3_2`; summed it is ~95 events / ~50 users. Two structures share one map
      location where the code expects one.
- [ ] `feException: items do not belong to player` - 25 events / 12 users (plus 15 / 8 on an older
      id), last seen today. Logged as `VisibleSystemException` after the exception migration.
- [ ] `TypeError: Unsupported operand types: string + int` - 3 events / 3 users, twice. Live again
      as 761167 (v261103-1426, 3 / 3, tables 942004049, 942425068, 942695106): Treasure Hunters
      (cid 38) mat-slot click sent `civTokenAdvance` with no hex, the server moved the token to
      `land__` and `setSelectedMapHex` added "" + 50; any hex of any terrain was also accepted.
      **FIXED 2026-11-04, shipped in v261107-2229**: the target must be one of the slot's offered territories
      (systemAssertTrue `ERR:TreasureHunters:01`), and a mat-slot click starts the
      normal territory choice. Tests `tests/TreasureHuntersTest.php`. Client not browser-verified:
      check in the studio at a Treasure Hunters income, also with a second civ benefit pending.
      After the deploy, check 761167 has no events on the new release.
- [ ] `feException: Error: generated notifications are larger than 128k (142359)` - 4 events / 2
      users. A single turn producing >128k of notifications will hard-fail. Still live: 766267
      (161943 bytes, 3 events / 1 user, 2026-11-04), 756345 (2 / 2, last 2026-11-04) and 769305
      (147612 bytes, 1 / 1, 2026-11-05).
- [ ] JS 767134 `Cannot read properties of null (reading 'className')` in `dojo.addClass` from
      `onUpdateActionButtons_civAbility`, game v261103-1426, 1 event / 1 user, table 942986299,
      2026-11-05. Lead, unproven: the Advisors adj=8 `targets` loop or the Traders `land_` loop
      adds a class to an id that is not on the page.
- [ ] `Track resource 4 not found in payment` - `action_advance` tier II/III payment check, table
      924389742, move 365, 2026-11-01, v261030-1658, twice in 2 s (from Victoria's server game log;
      UserException, not in the studio log). Client sent a payment without the track's own resource.
      No report matched yet.

## Triage run log

Short log of each run: date, reports touched, outcome.

Older detail is in git history of this file.

- **2026-08-26** - Setup: created this file, RULES.md and RULES_AUTOMA.md; back-tagged
  `v260731-2000` on 5ac2007.
- **2026-08-26** - Top-voted defects run; only 2 of the 30 top reports were on adj=8. #202072,
  #203108, #183142 confirmed by tests; #202072 fixed. Backend auto-errors recorded.
- **2026-10-24** - Incident from `v261024-2009` (income spot migration guard), hotfix
  `v261025-0248`; 34 reports Fixed.
- **2026-10-25** - Checks of `v261025-1540` / `1948`: Lighthouse and Historians state 36 clusters
  plus #202072 Fixed; Historians consolidation (about 25 Fixed, #209034 Duplicate, #210890 Not a
  bug); no-tile cluster confirmed as #214486.
- **2026-10-25** - `v261026-0118` close-out: #214486, #183142 and the Islanders reports Fixed.
- **2026-10-26** - Last-24h check: #252643 and #252596 recorded.
- **2026-10-28** - `v261028-1348`: Treasure Hunters conquer crash cluster Fixed (#252643,
  #252781, #252679, #252696, #252672, #252685, #252810); #252909 Works for me; #252974 recorded.
- **2026-10-30** - Scoped to the `Invalid territory tile` cluster after deploying v261030-1658 (live
  version confirmed on the gamepanel): #184923, #185215, #214800, #173400, #221934, #189712 Open ->
  Fixed with the BGA template. All six are solo tables; the match is by symptom since the tables are
  past log retention. Then #249482 (Open), #89731 and #95633 (both auto-closed `notupdated`) set to
  Fixed with "should be fixed, open a new report if it happens again": all three describe the tile
  moving to the Automa, the old card id 1 UPDATE. #252974 (solo setup crash, ed03125) set to Fixed
  with the BGA template. Marker not bumped (not a full sweep).
- 2026-10-31: #102790 (Spies copying Revolution, "expecting tapestry 35") is Fixed by e74f344 (made
  for #191475, Advisors with Revolution: drops the `isTapestryActive(35)` check in `action_standup`),
  first in v260731-2000. Tracked entry removed. Scanned the title, details and comments of every
  open/notupdated report for Spies AND Revolution: #135926, #115565, #110919, #133156 (Espionage
  copying Revolution, same check) (all auto-closed `notupdated`) set to Duplicate of #102790.
  #87819 (Militants cubes) is a different bug even though one comment there reports the Spies
  error. Marker not bumped (not a full sweep).
- 2026-11-01: Scoped to reports updated in the last 2 days. #253474 Open -> Info needed (no server
  error for the table). #252596 reporter comment recorded, left Open. #153921, #153927 were only
  auto-closed by BGA. Invalid territory tile recheck: no events after v261030-1658. New in the 7-day
  backend list: 756235 getUniqueValue `land_0_-1` card_type 7 (6 events / 1 user, 2026-10-30, a
  block), 756345 notifications >128k (1 event). Marker not bumped (not a full sweep).
- 2026-11-01: `v261101-2149` deployed the BGA #58486 fixes (two tokens without a choice, stuck
  move with no token on the track). #58486 Open -> Fixed, tracked entry removed. Duplicates of it set
  to Duplicate: #115834, #155617, #165479 (French, translation posted) and #132130 (Renaissance,
  same `trackMovementInteractive` guard) for the stuck move, #60561 for the silent spot-0 token.
  Left alone, not proven to be the same: #236773 ("already at end of track", stuck), #85804 (real
  token on the track yet the assert fired), #97814 / #111185 / #65573 (no token choice, but not the
  start-space token), #64013 (adjacent-token bug, still open in TODO.md).
- 2026-11-03: `v261103-1426` deployed the BGA #188139 Traders fix; #188139 Open -> Fixed, tracked
  entry removed. Last 48h: no new reports (#154057 only auto-closed by BGA). Errors last seen in
  48h: 756796 "BGA main website do not respond" (4 / 4) and 760357 mysql timeout (1 / 1), both
  BGA infrastructure. JS 760051 / 754591 `animSpinCounter` crash (empty NodeList from
  `getPlaceForReason`) fixed in 5797337, not deployed: check its Release breakdown after the next
  deploy. Invalid territory tile (Automa decision draw moved card id 1, plus client tile picks)
  fixed in v261030-1658: no events after 2026-10-29, entry closed.
- 2026-11-03: Traders sweep (titles matching trader in any language). Same bug as #188139, all
  adj=8, set to Fixed with the BGA template: #196947 (tile kept, benefit twice), #207610 and
  #244412 (income building paid only the opponent). Left Open: #245466 (adj=8, ability not offered
  on income), #200844, #194201, #175503, #186079, #77549 (adj=4) and #156499 (adj=1), all different
  claims. Marker bumped; older reports grandfathered.
- 2026-11-04: No new tag since v261103-1426, nothing Waiting for deploy. One new report: #254612
  (Spies, keepCard `invalid card selected`, cause from the server game log), left Open with a lead.
  Not in the studio log: `systemAssertTrue` threw UserException, so its text only showed in the
  server game log - ask Victoria for it instead of the reporter. Switched to VisibleSystemException
  (not deployed yet): from the next tag these asserts should appear in the studio log - verify then.
  7-day backend list: 761167 `string + int` (3 / 3, since 2026-11-03), 766267 notifications >128k.
  Marker bumped.
- 2026-11-06: No new tag (live 261103-1426 on the gamepanel), nothing Waiting for deploy. One new
  report: #255108 (Advisors + Espionage, adj=8) Open -> Confirmed with the BGA template, test
  `AdvisorsEspionageTest`. 7-day log: 761167 one more event (fix not deployed), new 769305
  (>128k notifications) and JS 767134 (civAbility addClass on null) recorded. Marker bumped.
- 2026-11-07: `v261107-2229` deployed (ES module client, framework API moves). #255108 Confirmed ->
  Fixed and #254612 Open -> Fixed, BGA template; tracked entries removed, side leads kept. Also
  shipped: Unblock civAbility fix (entry closed), Treasure Hunters 761167, `animSpinCounter`
  760051 / 754591, `VisibleSystemException` asserts. No new reports, nothing Waiting for deploy.
  Next run: check those error ids have no events on 261107-2229 and scan the frontend list for
  new JS errors from the module conversion. Marker bumped.
