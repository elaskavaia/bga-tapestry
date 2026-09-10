# Artificers - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).

Status: done. Stage 1 shipped the engine seams, the migration, the client layout and
IncomeMatTest; stage 2 shipped the material entry, `modules/civs/Artificers.php` and
ArtificersTest. The migration guard in `upgradeTableDb` is a placeholder date and must become the
real deploy version. The studio checks are listed in [TODO.md](TODO.md).

Two effects on one civ. At the start of income turns 2-4 the owner picks one of the four income
tracks and either sets its leftmost building aside (off the mat, never into the capital), or slides
that building one space left onto an already uncovered space, which covers that space and uncovers
the one the building came from. At the start of income turn 5 the owner either scores 1 VP per
income building in their capital city, or picks one track and slides every building on it as far
left as it will go, which packs the buildings against the left edge and uncovers the highest
numbered spaces just in time for that turn's VP income.

The civ logic is small. The cost is entirely in one sentence on the card: "This changes the benefits
that the income track provides to you." The set of uncovered spaces on a track stops being the
prefix 1..N that the whole codebase assumes and becomes an arbitrary subset. That assumption is
baked into income resolution, into the one place a claimed building's revealed benefit is read, and
into the client's building layout. Stage 1 removes the assumption with no civ at the table; stage 2
is then genuinely small.

## Shape

- Material: `CIV_ARTIFICERS` is already 40. `income_trigger` `["from" => 2, "to" => 5]` with no
  `decline` key, so the default `decline => true` applies and `civDecline` works (the card says "you
  may" on both abilities). `automa => true`: unlike Genies, Illuminati and Weefolk the ability needs
  no opponent, and the Automa never holds a real civilization card (its civ comes from
  `automa_civ_cards`, and `effect_automaIncomeVP` never goes through `effect_IncomeBenefits`), so
  nothing bot-side has to learn about the mat. No `slots`: the choice is made on the player's own
  income mat, not on the civ mat. No `midgame_setup`.
- Probably zero new `benefit_types.csv` rows. The default `AbsCivilization::queueEraCivAbility`
  already queues a `civ` category entry when the income turn is in range, which lands in state 14
  with `argCivAbility`, and the answer arrives in `moveCivCube`. The mutation itself grants nothing,
  so there is no follow-up row of the Faefolk `BE_FAEFOLK_FLICKER` kind; the income turn 5 VP option
  can queue plain `BE_VP` with a computed count and `reason_civ(CIV_ARTIFICERS)`. Add a named row
  only if the benefit queue display looks wrong without one - decide during stage 2, do not
  speculate a row now.
- **The building row carries its spot.** A building on the track keeps `card_location = 'income'`
  and `card_location_arg = player_id` exactly as today, and `card_location_arg2` becomes the spot
  1..6 it covers. Today arg2 is `'0'` on every income building and nothing reads it for types 1..4,
  so the column is free. The uncovered set of a track is then `1..6` minus the arg2 of the rows on
  it: one source of truth, no parallel state, no owner lookup, and it works for any player and any
  future mat-mutating civ. A claim resets arg2 to `'0'` when it moves the row to
  `capital_structure`, because placement ([dbSetStructureLocationRot](../modules/PGameXBody.php#L10594))
  writes only location and rotation and Weefolk passes arg2 of anything in a capital cell into
  `getStructureCells`; clearing it is cheaper than proving that ignores it.
- No new global, no new column, no `income_mat` payload in `getAllDatas`: the structure rows are
  already sent per player ([PGameXBody.php:841](../modules/PGameXBody.php#L841)) with arg2, so a
  reload lays the mat out from the rows alone. A slide is an ordinary `moveStructure` notification.
- Set-aside buildings go to `card_location = 'hand'`, the location an out-of-bounds placement
  already uses ([PGameXBody.php:8716](../modules/PGameXBody.php#L8716)) and that benefit row 144
  ("gain an income building and place outside of the city bounds",
  [selectIncomeBuilding](../modules/PGameXBody.php#L8509)) already produces. The client renders
  `hand` into `player_extras_<id>` ([tapestry.js:6333](../tapestry.js#L6333)). No new location
  string, no new markup, no new CSS. Every query that looks for a claimable building filters on
  `card_location='income'` exactly, so a set-aside building drops out of all of them with no edit.
- `player_income_<field>` keeps its exact current meaning: the number of uncovered spaces on the
  track, which after stage 1 is always `6 - rows on the track`. Set aside increments it (a space is
  uncovered), slide leaves it alone (nothing is uncovered on balance). Every consumer of the scalar
  keeps working untouched, including the two that read it as "buildings that left the track"
  (achievement category 5 and the `game_building_income` stat): a set-aside building did leave the
  track, and it still counts for its type (BUILDING.4, CIV.ARTIFICERS.3), so `level - 1` stays the
  right number. Stage 1 adds the invariant assertion `level == 6 - rows` at the read seam.
- "Per building of this type" scoring becomes `5 - rows on the track`, no location checked.
  Nothing ever moves a building back onto the track (the only writer of `income` is setup), so a
  building that left it is on the table somewhere and BUILDING.1 says every such place counts:
  the city grid, beside the mat, a civ mat, the map, set aside. Today
  [VPincomeStructure](../modules/PGameXBody.php#L2392) enumerates locations (`capital_cell%`, the
  CRAFTSMEN slot, `land_%`) and so misses `hand` (out-of-bounds placements, row 144) and the
  Collectors mat. Stage 1 replaces the enumeration with the count of rows off the track, which is
  a scoring change for those two cases in every table and is called out in the regression suite.
- In-flight tables need `upgradeTableDb` ([tapestry.game.php:93](../tapestry.game.php#L93)): their
  rows carry no spot. Per player and building type, the `income` rows in `card_id` order get arg2
  `level+1 .. 6`, and whatever is left over is deleted (see the sixth building below). The only way
  to avoid the migration is a permanent "arg2 = 0 means prefix layout" fallback in both PHP and JS.
  Not doing that.
- The sixth building. Setup ([PGameXBody.php:583](../modules/PGameXBody.php#L583)) creates
  `nbr => 6` of each type but a track has five building spots (2..6 at level 1), so today one row
  per type is unreachable: `dbGetIncomeBuildingOfType` refuses at level 6 and the client layout loop
  has no slot for it. Once rows carry spots the orphan has nowhere to sit and it breaks
  `level == 6 - rows`, so setup drops to `nbr => 5` with arg2 `2..6` assigned at creation, and the
  migration deletes the orphan in existing tables.

## Stage 1 - engine seams

Nothing here mentions Artificers. It lands, with tests and the migration, while every layout is
still a prefix, and the regression suite proves it changed nothing.

### What is hardcoded today, and what each costs

PHP, all in [PGameXBody.php](../modules/PGameXBody.php) unless noted:

- [effect_IncomeBenefits](../modules/PGameXBody.php#L11888) is the only reader of income spot
  benefits. It runs `for ($slot = 1; $slot <= $limit; $slot++)` over
  `$limit = $player_income_data[$field]`. This is the whole income read path: `effect_gainVPIncome`,
  `effect_gainResourcesIncome` and `effect_gainCardsIncome` all funnel through it with different
  `$allowed_benefits`. Cost: one loop over `getIncomeUncoveredSpots` instead of a range. The
  `reason("inspot", "{$track}_{$slot}")` it already builds then carries the real spot, so
  `getTokenName` case `inspot` names the right technology in the log with no further edit.
- [claimIncomeStructure](../modules/PGameXBody.php#L2918) is the only writer of the level:
  `SET $field = $field + 1`, plus a raw `UPDATE` to `capital_structure` and a notify. Cost: the
  move becomes `dbSetStructureLocation($sid, "capital_structure", 0, $message, $player_id)`, which
  writes arg2 through its `$state` argument and notifies, so the raw update and the separate notify
  go away; the level bump moves into `dbIncIncomeTrackLevel($player_id, $track)`, the single
  scalar writer that the test harness can override. The return contract (true when nothing to
  claim, false after a transition) is unchanged.
- [dbGetIncomeBuildingOfType](../modules/PGameXBody.php#L2894) picks the building with `LIMIT 1`
  and no `ORDER BY`, so "the leftmost building" is not expressed anywhere today. It is accidental
  primary key order, harmless only while every building on a track is interchangeable. The card's
  "Continue to use buildings from left to right during gameplay" makes it matter. Cost: build it on
  `getStructuresSearch(type, null, "income", player_id)` and take the row with the smallest arg2,
  and replace the `$income_level >= 6` exhaustion test with "no row on this track". Going through
  `getStructuresSearch` is what lets `GameUT` serve it (see Test infrastructure).
- [Traders.php:74](../modules/civs/Traders.php#L74) claims a building and then reads
  `dbGetIncomeTrackLevel` back as the index of the space it just uncovered, to pay the opponent the
  revealed benefit. That is exactly the prefix assumption, in the one civ that already depends on
  it. Cost: it already fetches the row id one line earlier, so read that row's arg2 before the
  claim and index `income_tracks` with it.
- `isUpgradePrereqMet` compares `MAX(player_income_<field>)` across neighbours against a tech card
  requirement. No change: it is a count comparison and the scalar's meaning is preserved. It is
  listed because it is the place a wrong ruling on set-aside would show up.
- `checkPrivateAchievement` case 5 ([PGameXBody.php:8269](../modules/PGameXBody.php#L8269)),
  [finalStats](../modules/PGameXBody.php#L12062), `getResourceCountAll`, `Nomads::setupCiv` and
  `getPlayerIncomeData` read the scalar as a count. No change.
- [VPincomeStructure](../modules/PGameXBody.php#L2392) counts by location. Cost: the count becomes
  `5 - count(getStructuresSearch($type, null, "income", $player_id))`, one line, and the five
  callers are untouched. This is the one deliberate behaviour change in stage 1.
- Setup ([PGameXBody.php:583](../modules/PGameXBody.php#L583)): `nbr => 5`, then one `UPDATE` per
  player that hands out arg2 `2..6` per type in `card_id` order. Cost: a few lines, and it is the
  same loop the migration runs, so write it once as `dbAssignIncomeSpots($player_id)` and call it
  from both.
- [upgradeTableDb](../tapestry.game.php#L93): for every `playerextra` row and every type 1..4,
  `dbAssignIncomeSpots` from the current level, then delete the leftover row. Rows mid-claim at
  `capital_structure` are untouched. Guard it with the stage 1 deploy version like every BGA
  migration.

JS, in [tapestry.js](../tapestry.js):

- The `income` branch of `moveStructure` ([tapestry.js:6350](../tapestry.js#L6350)) is the only
  income mat layout code. It reads `gamedatas.players[pid].basic["income" + type]` and scans
  `income + 1 .. 6` for the first empty slot div. Cost: the target is
  `income_track_<pid>_<type>_<arg2>`, no scan. When the building div already exists (a slide, or a
  reload after a claim), `placeToken(div, target)` re-parents it with the usual animation; otherwise
  create it as today. `.income_track_space > *` is already `position: absolute`
  ([tapestry.css:903](../tapestry.css#L903)), which is what `placeToken` expects. Verify in the
  studio that the initial `setupStructures` pass still lands every building in its slot.
- Nothing else. The six `.income_track_space` cells are identical float cells, and the per spot
  tooltips ([tapestry.js:552](../tapestry.js#L552)) come from
  `income_track_data[track][level].name` keyed by the fixed spot position, which stays correct under
  any mutation.

CSS, in [tapestry.css](../tapestry.css): nothing. The benefit icons of all 24 spaces are painted
into `img/income_mat.png`, and the mutation moves buildings, never icons, so no art is dynamic and
no sprite has to be re-cut. This is the single biggest de-risking fact in this plan.

Docs: the income buildings section of [DESIGN.md](DESIGN.md#L81) gains "arg2 is the spot 1..6
while the location is `income`, `'0'` otherwise" and records `hand` as "beside the capital mat
(out of bounds placement, row 144, set aside)".

### Deliverables

- `getIncomeUncoveredSpots(int $player_id, int $track): array` - ascending spot numbers, `1..6`
  minus the arg2 of the `income` rows of that type, built on `getStructuresSearch`. Asserts
  `count == level` (`systemAssertTrue`), the invariant every untouched consumer of the scalar relies
  on. This is the one read seam every income resolution goes through.
- `dbGetIncomeBuildingOfType` leftmost-first on the same search, exhaustion by "no row left".
- `dbIncIncomeTrackLevel(int $player_id, int $track)` - the single scalar writer, called from
  `claimIncomeStructure`.
- `claimIncomeStructure` moving the row through `dbSetStructureLocation` with arg2 `0`.
- `dbAssignIncomeSpots(int $player_id)` shared by setup and the migration; setup at `nbr => 5`.
- `Traders` taking the revealed spot from the row.
- `VPincomeStructure` counting rows off the track.
- `upgradeTableDb` guarded by the deploy version; the orphan row is deleted, not parked.
- Client: layout by arg2, `placeToken` for an existing div.
- `IncomeMatTest.php`: the regression suite described under Test cases. It has to be written against
  today's behaviour and pass before and after the refactor, unchanged.

## Stage 2 - the civilization

- Material entry: name, the three rules sentences as separate `clienttranslate` strings,
  `exp => "FF"`, `income_trigger => ["from" => 2, "to" => 5]`, `automa => true`.
- [modules/civs/Artificers.php](../modules/civs/Artificers.php), `ERR:Artificers:NN` codes:
  - `argCivAbilitySingle` builds `slots_choice` from the legal moves, branching on
    `getCurrentEra($player_id)`. Turns 2-4: for each track that still has a building, one entry for
    "set aside the leftmost <track name> building" and, when the spot immediately left of it is
    uncovered (exists and is not in the row set), one entry for "slide it left". Up to 8 entries,
    each with a `title` and a `tooltip`, the shape Faefolk already uses for its non-benefit option.
    Turn 5: one entry for the VP option and one per track for "slide all left", omitting tracks
    where nothing would move (already packed, or empty).
  - `moveCivCube(int $player_id, int $spot, $extra, array $civ_args)` decodes the entry key and
    applies it. Encode the key as `track * 10 + action` so it stays readable in the log and needs no
    second round trip; `extra` is free if a second value is ever needed.
  - Three private mutators, all through `dbSetStructureLocation` so the DB write and the
    `moveStructure` notification cannot diverge:
    - `setAsideBuilding`: leftmost row to `hand` with arg2 `0`, then `dbIncIncomeTrackLevel`. Not
      `claimIncomeStructure`: that fires the gain-a-building hooks (CAPITALISM coin, Relentless), and
      CIV.ARTIFICERS.6 says setting aside is not gaining.
    - `slideBuildingLeft`: leftmost row stays at `income`, arg2 becomes `spot - 1`. Scalar untouched.
    - `slideTrackLeft`: rows sorted by arg2 get `1..k`. Scalar untouched.
  - The turn 5 VP option is `VPincomeStructure` summed over the four types with value 1 and
    `reason_civ`: buildings off the tracks, wherever they sit, set-aside ones included.
  - No `awardBenefits` override if no new row is added; no `onDieRolled`, `zombieBenefit` or
    `interceptOpponentBenefit` - every effect is the owner's own.
- `[Artificers.php]`, `[ArtificersTest.php]`, nothing in the client beyond what stage 1 shipped.

## Test infrastructure

- `GameUT` already models the `structure` table in memory (`DeckInMem`) but only through the named
  accessors it overrides (`getStructuresSearch`, `dbSetStructureLocation`, `dbAddStructure`, ...);
  raw SQL in the game class is invisible to it ([CODE_STYLE.md](CODE_STYLE.md#L96)). That is why
  stage 1 rebuilds `dbGetIncomeBuildingOfType` and `getIncomeUncoveredSpots` on
  `getStructuresSearch` and routes the claim through `dbSetStructureLocation`: the tests then drive
  the production code, not a copy of it. `argBuildingSelect` (row 110/144) and `olympicHostEnd` keep
  their raw `card_location='income'` queries, which stay correct, so they are studio checks, not
  unit tests.
- `GameUT` does not model `playerextra`, so the level is a scripted array on the test subclass:
  override `getPlayerIncomeData`, `dbGetIncomeTrackLevel` and `dbIncIncomeTrackLevel` to read and
  write it, the same seam style the capital grid got for Weefolk. That override is stage 1 work and
  belongs on `GameUT` itself so both `IncomeMatTest` and `ArtificersTest` use it.
- The income tuple recorder: a test subclass override of `awardBenefits` that appends
  `[player_id, benefit_id, count, reason]` instead of resolving. `effect_IncomeBenefits` calls
  `awardBenefits` once per benefit per spot, so the recorded list is a complete and readable
  fingerprint of an income phase. Every regression case below is an assertion on that list.
- A layout helper on the test subclass, `layoutTrack(player_id, type, spots)`, that adds the rows
  with the given arg2 values and sets the level to `6 - count(spots)`, so a test states a mutated
  mat in one line.
- No new randomness, so nothing to seed.
- `npm run predeploy` is the gate for both stages.

## Test cases

Stage 1, written first and passing unchanged afterwards:

- The income fingerprint for each track at each level 1 through 6, for VP income, resource income
  and card income separately, since each passes a different `$allowed_benefits`.
- MERCANTILISM (food total) and CAPITALISM (coin total) still fire off the totals
  `effect_IncomeBenefits` accumulates.
- A claim takes the row with the smallest arg2, moves it to `capital_structure` with arg2 `0` and
  bumps the scalar by one, for each track, at each level.
- `dbGetIncomeBuildingOfType` returns the leftmost building, and reports exhaustion when the track is
  empty rather than when the level hits 6.
- Traders placing an income building pays the opponent the benefit of the space just uncovered, at
  several levels.
- `getIncomeUncoveredSpots` equals `range(1, level)` for every prefix layout, and its invariant
  assertion trips on a layout whose row count disagrees with the level.
- `dbAssignIncomeSpots` hands out `level+1 .. 6` in id order and leaves the leftover row alone for
  the migration to delete, at each level.
- `VPincomeStructure` before and after: identical for a player whose claimed buildings are all in
  the city grid, on the map or on the CRAFTSMEN slot; one higher per building in `hand` (out of
  bounds, row 144) or on the Collectors mat. Those two cases are the only fingerprints stage 1 is
  allowed to change, and the test names them.

Stage 2:

- Material entry: `income_trigger` 2-5, decline allowed, `automa` true, `exp` FF.
- Set aside at income turns 2, 3 and 4: the building goes to `hand` with arg2 `0`, the scalar goes up
  by one, the uncovered set gains the spot, the same turn's income pays the newly uncovered space,
  and neither CAPITALISM nor Relentless fires.
- Slide left: the scalar does not move, the uncovered set loses the target spot and gains the vacated
  one, and the same turn's income pays the new set and not the old one.
- Slide left is not offered when the leftmost building is already at spot 1, or generally when there
  is no uncovered space immediately left of it.
- Nothing legal to do: a track with no buildings offers neither entry; a player whose four tracks are
  all exhausted gets the "not applicable" path rather than an empty prompt.
- Income turn 5, VP option: 1 VP per building off the tracks over all four types, with a set-aside
  building counted and a track that was slid but never claimed from contributing nothing.
- Income turn 5, slide all: rows get `1..k`, the scalar is unchanged, the uncovered set becomes the
  top `6-k` spaces, and the VP income of that same turn uses it.
- Decline at any turn changes nothing.
- Income turn 1 and turn 6 offer nothing.
- A civ gained mid game at income turns 2-5 fires in that turn, the engine's rule for every income
  civ.

Cross civ regression matrix, all with a mutated mat (say farms uncovered at 1, 2 and 4):

- Traders placing an income building on the mutated track pays the opponent the benefit of the space
  actually uncovered, not `level`.
- Collectors (`claimIncomeStructure(BUILDING_HOUSE)`) and the `BE_MARKET`/`BE_HOUSE`/`BE_FARM`/
  `BE_ARMORY` rows 8-11 take the leftmost building of the mutated track and uncover the right space.
- Nomads' adjustment-8 "lowest track" choice reads the scalar and is unaffected.
- Militants' `militantBenefits` exposed-benefit gain and Urban Planners, against the mutated mat.
- Row 110 and row 144 (gain / discard an income building) with a mutated mat, in the studio, since
  `argBuildingSelect` keeps its raw query.
- The OLYMPIC HOST "at least one opponent" building gain, since it has its own `card_location='income'`
  query.
- MERCANTILISM and CAPITALISM totals over a mutated mat.
- Tech card upgrade prerequisite against a neighbour who has set a building aside.
- Achievement category 5 (buildings of the same type) and `finalStats` after a set aside: the
  set-aside building counts, `level - 1` is still the number.
- Final scoring with a set-aside building on the table: every "per building of this type" benefit
  counts it.
- The whole matrix repeated with no Artificers at the table, asserting byte-identical fingerprints
  to stage 1 - the regression guard for the seam.

## Client

- Rendering is entirely "which slot does each building div sit in". Icons never move, because the
  benefit art is in `img/income_mat.png` and the mutation moves buildings. The slot is
  `income_track_<pid>_<type>_<arg2>`, read straight off the structure row.
- A slide is one `moveStructure` notification per building, and the existing
  `notifqueue.setSynchronous("moveStructure", 300)` paces them; "slide all left" is up to five in a
  row, which is the same thing Traders or a multi-building claim already produces. No new
  notification, no relayout helper.
- The set-aside building rides `moveStructure` to `hand`, which the client already maps to
  `player_extras_<id>`, the same place an out-of-bounds building lands. It animates off the mat with
  no new client code.
- Tooltips: the per spot tooltips are already keyed to the fixed spot and stay correct. Extend the
  income help text with the Artificers sentence only if the owner is at the table.
- The prompt itself needs no new client state handler: the civ ability state (14) already renders
  `slots_choice` entries with `title` and `tooltip` and no benefit, which is what Faefolk's "Flicker
  only" option uses.
- Reload: nothing to add. The rows in `getAllDatas` carry arg2.
- Considered and rejected: a bitmask global of the uncovered spaces per track. It avoided the
  migration and the query edits, but it was a second copy of state that had to be kept equal to the
  rows, it only worked for a single owner, it needed its own payload, notification and relayout code
  in the client, and it still left "leftmost" unexpressed on the rows. Also rejected: the spot in the
  location string (`income_track_<pid>_<type>_<spot>`, equal to the DOM id). Same data, but it edits
  four working queries, the client branch and DESIGN.md for no gain over arg2.

## Rulings

Recorded in FORMAL_RULES as CIV.ARTIFICERS.1-7.

Implementation notes those clauses deliberately leave out:

- CIV.ARTIFICERS.5 is `INCOME_CIV` (phase 10), queued by `queueEraCivAbilities` before the tapestry,
  upgrade, VP and resource rows. Two income civs on one player resolve in benefit id order, so a
  mutation resolved first means the other civ's row sees the new layout.
- CIV.ARTIFICERS.3 with BUILDING.4: a set-aside building still counts for its type, so the two
  `level - 1` consumers (achievement category 5, `game_building_income`) are already right and stay
  untouched, and `VPincomeStructure` counting rows off the track is the same number. That is also
  why set aside can share `hand` with out-of-bounds placements: the two are indistinguishable and
  are meant to be.
- CIV.ARTIFICERS.6: set aside bumps the level without going through `claimIncomeStructure`, so the
  gain-a-building hooks stay silent while every consumer of the track position, the tech card
  upgrade prerequisite included, sees the uncovered space.
- CIV.ARTIFICERS.7 falls out of `checkAliveForBenefit` dropping the civ row like every other income
  row.
- Undo: the buildings are structure rows and the level is a `playerextra` column, both inside the
  undo savepoint, so undoing an income turn restores the mat exactly.
- None of Traders, Collectors, Militants, Nomads or Urban Planners choose which space is uncovered,
  they only claim the leftmost building, so all they need from Artificers is "leftmost means
  leftmost", which stage 1 makes true for everyone.
- Solo: `automa => true`. The ability needs no opponent, nothing on the Automa's side reads a human's
  income mat, and `effect_automaIncomeVP` does not go through `effect_IncomeBenefits`. Artificers is
  the first FF civ that is genuinely solo-safe among the engine band.

## Open questions

None open.

Answered:

- `VPincomeStructure` counts `capital_cell%`, the CRAFTSMEN slot and the map, but not `hand`, so an
  out-of-bounds building scores nothing for "per building of this type" today, against BUILDING.3
  and BUILDING.4. A: with the new rules it should count as 5 minus the number of occupied slots, no
  special location checked. Consequence worth knowing: houses on the Collectors mat start counting
  too, since that mat is not in today's list either.
- The migration deletes the orphan sixth row per type, or parks it somewhere harmless like `box`?
  A: delete.
- If the owner declines the turn 5 ability entirely, is that legal? The plan assumes yes ("you may
  either ... or ...", and the default `decline` is true). A: its always beneficial, so no point declining it
- Whether the set-aside area needs its own div and `placeToken` path. A: set aside zone is same as
  out of city bounds, it places pieces on tableau/player home, nothing special is needed. Hence
  `hand`.
- Whether `notifqueue.setSynchronous("moveStructure", 300)` gives the slide animation enough room
  when up to five buildings move on the turn 5 ability. A: moveStructure moves one at a time, and the
  queue paces them.
