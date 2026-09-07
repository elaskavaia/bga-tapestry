# Artificers - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).

Status: planned, not started.

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
baked into income resolution, into the one place a claimed building's revealed benefit is read, into
two building-count derivations, and into the client's building layout. Stage 1 removes the
assumption with no civ at the table; stage 2 is then genuinely small.

## Shape

- Material: `income_trigger` `["from" => 2, "to" => 5]` with no `decline` key, so the default
  `decline => true` applies and `civDecline` works (the card says "you may" on both abilities).
  `automa => true`: unlike Genies, Illuminati and Weefolk the ability needs no opponent, and the
  Automa never holds a real civilization card (its civ comes from `automa_civ_cards`, and
  `effect_automaIncomeVP` never goes through `effect_IncomeBenefits`), so nothing bot-side has to
  learn about the mat. No `slots`: the choice is made on the player's own income mat, not on the
  civ mat. No `midgame_setup`.
- Probably zero new `benefit_types.csv` rows. The default `AbsCivilization::queueEraCivAbility`
  already queues a `civ` category entry when the income turn is in range, which lands in state 14
  with `argCivAbility`, and the answer arrives in `moveCivCube`. The mutation itself grants nothing,
  so there is no follow-up row of the Faefolk `BE_FAEFOLK_FLICKER` kind; the income turn 5 VP option
  can queue plain `BE_VP` with a computed count and `reason_civ(CIV_ARTIFICERS)`. Add a named row
  only if the benefit queue display looks wrong without one - decide during stage 2, do not
  speculate a row now.
- New global `income_mat_layout`, next free id after Celestials (39 is `illuminati_dice`; 40 if
  Celestials has not taken it). One int holding four 6-bit masks, bit `spot - 1` set meaning "that
  spot is uncovered". 0 means "no mat has been mutated", which is every game without an Artificers
  and every Artificers game before the first use. The Illuminati precedent applies exactly: only one
  player can own the civ, so the owner is implicit and is `getCivOwner(CIV_ARTIFICERS)`; globals sit
  inside the undo savepoint, so undo restores the layout for free.
- Deliberately not new columns on `playerextra`. Four masks per player would be the general answer
  and would need an `upgradeTableDb` for in-flight tables. The single-owner global costs nothing and
  is reversible; if a second mat-mutating civ ever appears, the read seam below is the only thing
  that has to change.
- Set-aside buildings move to `card_location = "income_aside_<player_id>"`. Every existing query
  that looks for a claimable building filters on `card_location='income'` exactly
  ([PGameXBody.php](../modules/PGameXBody.php) lines 1783, 2767, 2889 and 9693), so a set-aside
  building drops out of all of them with no edit. Nothing counts it as being in the capital either,
  since capital counts match `capital_cell%`.
- Client-visible state: `income_mat` in `getAllDatas`, the per-player uncovered masks, so a reload
  can lay the buildings out again. Stage 1 ships it as the prefix mask for everyone.
- `player_income_<field>` keeps its exact current meaning: the number of uncovered spaces on the
  track, equal to `6 - buildings still on the track`. Set aside increments it (a space is uncovered),
  slide leaves it alone (nothing is uncovered on balance). This is the load-bearing decision of the
  whole plan: every existing consumer of the scalar that is really asking "how far along the track"
  keeps working untouched. Only the two consumers that use it as "how many buildings did I claim"
  break, and those are listed below.

## Stage 1 - engine seams

Nothing here mentions Artificers. It lands, with tests, while the mask is always the prefix, and the
regression suite proves it changed nothing.

### What is hardcoded today, and what each costs

PHP, all in [PGameXBody.php](../modules/PGameXBody.php) unless noted:

- `effect_IncomeBenefits` (around line 11795) is the only reader of income spot benefits. It runs
  `for ($slot = 1; $slot <= $limit; $slot++)` over `$limit = $player_income_data[$field]`. This is
  the whole income read path: `effect_gainVPIncome`, `effect_gainResourcesIncome` and
  `effect_gainCardsIncome` all funnel through it with different `$allowed_benefits`. Cost: one loop
  over a spot list instead of a range. The `reason("inspot", "{$track}_{$slot}")` it already builds
  then carries the real spot, so `getTokenName` case `inspot` (line 10279) names the right
  technology in the log with no further edit.
- `claimIncomeStructure` (line 2907) is the only writer of the level: `SET $field = $field + 1`. It
  does not know or say which space it just uncovered, because today that is always the new value of
  the scalar. Cost: a new single writer `dbRevealIncomeSpot(int $player_id, int $track): int` that
  bumps the scalar, sets the bit and returns the spot uncovered.
- `dbGetIncomeBuildingOfType` (line 2883) picks the building with `LIMIT 1` and no `ORDER BY`, so
  "the leftmost building" is not expressed anywhere today - it is accidental primary key order, and
  it is only harmless because all buildings on a track are interchangeable while the layout is a
  prefix. The card's "Continue to use buildings from left to right during gameplay" makes it matter.
  Cost: order the query by the building's spot, and replace the `$income_level >= 6` exhaustion test
  with "no building left on this track". Flag while there: setup ([PGameXBody.php:581](../modules/PGameXBody.php#L581))
  creates `nbr => 6` buildings of each type but the mat only ever has five track positions
  (2..6 at level 1), and the client's placement loop silently drops the sixth. See open questions -
  do not build the "leftmost" ordering on the assumption that five is the count until this is
  resolved.
- [Traders.php:74](../modules/civs/Traders.php#L74) claims a building and then reads
  `dbGetIncomeTrackLevel` back as the index of the space it just uncovered, to pay the opponent the
  revealed benefit. That is exactly the prefix assumption, in the one civ that already depends on it.
  Cost: take the spot from what the claim returns.
- `checkPrivateAchievement` case 5 (line 8224) and `finalStats` (line 11983) use `level - 1` as
  "income buildings claimed". True today, false the first time a building is set aside. Cost: one
  helper `getIncomeBuildingsPlacedDb(int $player_id, ?int $track = null): int` counting structures of
  the type that are neither on the track nor set aside, and two call sites.
- `isUpgradePrereqMet` (around line 6753) compares `MAX(player_income_<field>)` across neighbours
  against a tech card requirement for tracks 5-8. No change: it is a count comparison and the
  scalar's meaning is preserved. It is listed because it is the second place the scalar's honesty is
  load-bearing, and it is the place a wrong ruling on set-aside would show up.
- `getResourceCountAll`, `Nomads::setupCiv` and `getPlayerIncomeData` read the scalar as a count.
  No change.

JS, in [tapestry.js](../tapestry.js):

- `moveStructure` lines 6310-6321 is the only income mat layout code. For a structure at location
  `income` it reads `gamedatas.players[pid].basic["income" + type]` and scans
  `income + 1 .. 6` for the first empty slot div. Cost: compute the covered spots from the mask and
  fill those; plus a new `relayoutIncomeTrack(player_id, track)` that re-seats the divs already on a
  track when the mask changes.
- Nothing else. The six `.income_track_space` cells are identical float cells, and the per spot
  tooltips (lines 552-559) come from `income_track_data[track][level].name` keyed by the fixed spot
  position, which stays correct under any mutation.

CSS, in [tapestry.css](../tapestry.css):

- `.income_track_space` is `width: 16.6666%; float: left` inside `.income_track`, with no per spot
  rules at all. The benefit icons of all 24 spaces are painted into `img/income_mat.png`.
  Cost: nothing. This is the single biggest de-risking fact in this plan: the physical mutation moves
  buildings, never icons, so no art is dynamic and no sprite has to be re-cut. The only new CSS is
  for the set-aside area in stage 2.

### Deliverables

- `getIncomeUncoveredSpots(int $player_id, int $track): array` - ascending spot numbers. Stage 1
  body: `range(1, $level)`. This is the one read seam every income resolution and every client
  payload goes through.
- `getIncomeCoveredSpots(int $player_id, int $track): array` - the complement, what the client fills
  with buildings.
- `dbRevealIncomeSpot(int $player_id, int $track): int` - the single writer, called from
  `claimIncomeStructure`, returning the spot uncovered.
- `getIncomeBuildingsPlacedDb` and its two call sites.
- `dbGetIncomeBuildingOfType` ordered leftmost-first and its exhaustion test rewritten.
- `Traders` taking the revealed spot from the claim.
- `getAllDatas` gains `income_mat` (player id to four masks), prefix masks in stage 1.
- Client: layout by mask, plus `relayoutIncomeTrack`.
- `IncomeMatTest.php`: the regression suite described under Test cases. It has to be written against
  today's behaviour and pass before and after the refactor, unchanged.

## Stage 2 - the civilization

- `CIV_ARTIFICERS` (40, the first of the FF block by alphabetical order) material entry: name,
  the three rules sentences as separate `clienttranslate` strings, `exp => "FF"`,
  `income_trigger => ["from" => 2, "to" => 5]`, `automa => true`.
- [modules/civs/Artificers.php](../modules/civs/Artificers.php), `ERR:Artificers:NN` codes:
  - `argCivAbilitySingle` builds `slots_choice` from the legal moves, branching on
    `getCurrentEra($player_id)`. Turns 2-4: for each track that still has a building, one entry for
    "set aside the leftmost <track name> building" and, when there is an uncovered space immediately
    to its left, one entry for "slide it left". Up to 8 entries, each with a `title` and a `tooltip`,
    the shape Faefolk already uses for its non-benefit option. Turn 5: one entry for the VP option
    and one per track for "slide all left", omitting tracks where nothing would move.
  - `moveCivCube(int $player_id, int $spot, $extra, array $civ_args)` decodes the entry key and
    applies it. Encode the key as `track * 10 + action` so it stays readable in the log and needs no
    second round trip; `extra` is free if a second value is ever needed.
  - Three private mutators, all going through the mask and `dbSetStructureLocation`:
    `setAsideBuilding`, `slideBuildingLeft`, `slideTrackLeft`.
  - The turn 5 VP option counts income buildings in `capital_cell%` only (see rulings, it is a
    narrower count than `VPincomeStructure`) and queues `BE_VP` with `reason_civ`.
  - No `awardBenefits` override if no new row is added; no `onDieRolled`, `zombieBenefit` or
    `interceptOpponentBenefit` - every effect is the owner's own.
- Mask writes go through one `setIncomeMatLayout` helper that notifies, mirroring
  `Illuminati::setDiceOnMat`, so there is exactly one place the client and the DB can diverge.
- `getIncomeUncoveredSpots` learns the mask: when the global is non-zero and the player is
  `getCivOwner(CIV_ARTIFICERS)`, return the set bits; otherwise the prefix. Assert that the
  popcount equals the scalar (`systemAssertTrue("ERR:Artificers:2N")`) - that invariant is what keeps
  the untouched consumers listed in stage 1 correct.
- `[Artificers.php]`, `[ArtificersTest.php]`, one CSS block, one client notification handler.

## Test infrastructure

- `GameUT` does not model `playerextra` at all (see its own header comment), so `getPlayerIncomeData`
  and `dbGetIncomeTrackLevel` are unusable in tests as they stand. The test subclass overrides them
  to read a scripted array, the same seam style the capital grid got for Weefolk and
  `getTileBenefit` got for Illuminati. That override is stage 1 work and belongs in a shared
  `IncomeUT` (or on `GameUT` itself) so both `IncomeMatTest` and `ArtificersTest` use it.
- The income tuple recorder: a test subclass override of `awardBenefits` that appends
  `[player_id, benefit_id, count, reason]` instead of resolving. `effect_IncomeBenefits` calls
  `awardBenefits` once per benefit per spot, so the recorded list is a complete and readable
  fingerprint of an income phase. Every regression case below is an assertion on that list.
- No new randomness, so nothing to seed.
- `npm run predeploy` is the gate for both stages.

## Test cases

Stage 1, written first and passing unchanged afterwards:

- The income fingerprint for each track at each level 1 through 6, for VP income, resource income
  and card income separately, since each passes a different `$allowed_benefits`.
- MERCANTILISM (food total) and CAPITALISM (coin total) still fire off the totals
  `effect_IncomeBenefits` accumulates.
- A claim returns the spot it uncovered and bumps the scalar by one, for each track, at each level.
- `dbGetIncomeBuildingOfType` returns the leftmost building, and reports exhaustion when the track is
  empty rather than when the level hits 6.
- Traders placing an income building pays the opponent the benefit of the space just uncovered, at
  several levels.
- `getIncomeBuildingsPlacedDb` agrees with `level - 1` for every prefix layout - the assertion that
  the new helper is a drop-in today.
- `getAllDatas` carries the prefix mask for every player.

Stage 2:

- Material entry: `income_trigger` 2-5, decline allowed, `automa` true, `exp` FF.
- Set aside at income turns 2, 3 and 4: the building leaves the mat to `income_aside_*`, the scalar
  goes up by one, the mask gains the bit, and the same turn's income pays the newly uncovered space.
- Slide left: the scalar does not move, the mask loses the covered bit and gains the vacated one, and
  the same turn's income pays the new set and not the old one.
- Slide left is not offered when the leftmost building is already at spot 2 with spot 1 uncovered
  only, or generally when there is no uncovered space immediately left of it.
- Nothing legal to do: a track with no buildings offers neither entry; a player whose four tracks are
  all exhausted gets the "not applicable" path rather than an empty prompt.
- Income turn 5, VP option: 1 VP per income building in `capital_cell%`, and a set-aside building
  scores nothing.
- Income turn 5, slide all: buildings pack against the left edge, the scalar is unchanged, the mask
  becomes the top N spaces, and the VP income of that same turn uses the new mask.
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
- Row 110 and row 144 (gain / discard an income building) with a mutated mat, including the
  `argBuildingSelect` choice list.
- The OLYMPIC HOST "at least one opponent" building gain, since it has its own `card_location='income'`
  query.
- MERCANTILISM and CAPITALISM totals over a mutated mat.
- Tech card upgrade prerequisite against a neighbour who has set a building aside.
- Achievement category 5 (buildings of the same type) and `finalStats` after a set aside: the
  building count is the placed count, not `level - 1`.
- Final scoring with a set-aside building on the table: it scores nothing anywhere.
- The whole matrix repeated with no Artificers at the table, asserting byte-identical fingerprints
  to stage 1 - the regression guard for the seam.

## Client

- Rendering is entirely "which slot does each building div sit in". Icons never move, because the
  benefit art is in `img/income_mat.png` and the mutation moves buildings. `moveStructure` fills the
  covered spots from `gamedatas.income_mat` instead of scanning `income + 1 .. 6`.
- `relayoutIncomeTrack(player_id, track)` collects the `#building_*` divs currently inside that
  track's six slot divs, left to right, and re-seats them into the new covered spots with
  `slideToObjectRelative` (the existing helper for tokens that use no inline positioning and need to
  be re-parented mid-animation). A slide is then a real one-cell slide on screen, and "slide all
  left" is four of them at once.
- One new notification, `artificersMat`, carrying the owner, the track and the four masks as
  `withPreserveArg`, the `Illuminati::setDiceOnMat` shape. The set-aside building additionally rides
  the existing `moveStructure` notification to `income_aside_<player_id>`, so it animates off the mat
  with no new client code beyond the location existing.
- New markup: one `<div id="income_aside_{X}" class="income_aside"></div>` inside `income_mat_{X}` in
  [tapestry_tapestry.tpl](../tapestry_tapestry.tpl), so the location string equals the element id and
  `moveStructure` needs no special case for it. Uncertain: whether `placeToken` behaves for that div
  without a slot percentage; if not, the aside area gets the plain `dojo.place` path the way
  `player_extras_*` does. Verify in the studio rather than guessing.
- CSS: `.income_aside` (a strip below or beside the tracks) and `.building.set_aside` (dimmed), plus
  nothing else. No per spot rules are added.
- Tooltips: the per spot tooltips are already keyed to the fixed spot and stay correct. Add a line to
  the set-aside building's tooltip saying it is out of play, and extend the income help text with the
  Artificers sentence only if the owner is at the table.
- The prompt itself needs no new client state handler: the civ ability state (14) already renders
  `slots_choice` entries with `title` and `tooltip` and no benefit, which is what Faefolk's "Flicker
  only" option uses.
- Reload: `getAllDatas` must carry `income_mat` for every player, because the layout is otherwise
  unrecoverable from the structure rows (a building on the track has no spot of its own). The set
  aside buildings restore from their `card_location`.
- Considered and rejected: storing the spot on the building's `card_location_arg2`. It would make the
  client trivially correct with no payload, but `card_location_arg2` is reused as landmark type and
  as the capital placement argument on the same table, so it would have to be cleared on every claim,
  and it needs a migration for in-flight games. The mask costs one number.

## Rulings

Proposed FORMAL_RULES clauses, continuing from 5.29:

- The mutation happens in the civ ability phase (`INCOME_CIV`, phase 10), which is queued by
  `queueEraCivAbilities` before the tapestry, upgrade, VP and resource rows. So the new layout is the
  one that turn's income is paid from, on every income turn including turn 5's VP income. This is
  what makes the turn 5 "slide all left" option worth taking.
- "Slide that building left to cover the leftward space" moves it exactly one space, onto the
  uncovered space immediately to its left. It is not a "slide as far as you like". The turn 5 ability
  is the only one that moves more than one space. Needs Victoria: the card's singular "the leftward
  space" reads this way, but "if there is at least one empty space to the left" hints at a longer
  move.
- A set-aside building is out of the game. It is not in the capital, so it scores nothing for capital
  row and column scoring, nothing for `BE_VP_FARM` and its siblings, nothing for the Artificers turn
  5 VP option, and it is not a building the district or achievement counts see. It is also not on the
  track, so it can never be claimed again.
- Setting a building aside does uncover a space, so it advances the income track for every purpose
  that reads the track position, including the tech card upgrade prerequisite. It does not count as a
  building claimed for achievement category 5 or for the `game_building_income` statistic. These two
  used to be the same number and now are not.
- "1 VP per income building in your capital city" at income turn 5 counts buildings on capital cells
  only. It is deliberately narrower than the existing `VPincomeStructure`, which also counts buildings
  on the map (Traders) and on the Collectors mat.
- Undo: the layout is a global and the buildings are structure rows, both inside the undo savepoint,
  so undoing an income turn restores the mat exactly, the Illuminati precedent.
- Interaction with civs that touch income order: none of Traders, Collectors, Militants, Nomads or
  Urban Planners choose which space is uncovered, they only claim the leftmost building, so the only
  thing they need from Artificers is "leftmost means leftmost", which stage 1 makes true for
  everyone. The mutation is the owner's own mat only; no opponent's income is ever affected.
- Two income civs on one player at the same income turn resolve in benefit id order, as they do
  today; an Artificers mutation resolved before another civ's income row means that row sees the new
  layout. Consistent with the Illuminati clause 5.29 rule that the mat is read when the row pops.
- Solo: `automa => true`. The ability needs no opponent, nothing on the Automa's side reads a human's
  income mat, and `effect_automaIncomeVP` does not go through `effect_IncomeBenefits`. Artificers is
  the first FF civ that is genuinely solo-safe among the engine band.
- A finished or zombie owner mutates nothing: the civ row is dropped by `checkAliveForBenefit` like
  every other income row.

## Open questions

For Victoria:

- Does the income turn 2-4 slide move exactly one space, or as many as the player likes? The plan
  assumes one. If it is many, the turn 5 ability loses most of its point, which is the argument for
  one.
- Can the turn 2-4 slide move a building that is not the leftmost one? The card says "your leftmost
  building on that track" for the set aside and then "slide that building", which reads as the same
  building. Confirm.
- Turn 5 "slide all buildings as far leftward as possible": does that mean pack them against spot 1
  (so the uncovered spaces become the highest numbered ones), or pack them against the leftmost
  uncovered space? The plan assumes against spot 1, which is the only reading that makes the option
  compete with the VP option.
- If the owner declines the turn 5 ability entirely, is that legal? The plan assumes yes ("you may
  either ... or ...", and the default `decline` is true).
- Setup creates six income buildings of each type
  ([PGameXBody.php:581](../modules/PGameXBody.php#L581)) but the mat has five track positions and the
  client's layout loop silently drops the sixth. Is the sixth a deliberate spare (some effect grants
  a building from outside the track), or a long-standing off-by-one? The answer changes what "the
  leftmost building" is allowed to assume and whether "set aside" can ever run the track dry in a way
  no existing code expects. This is the one thing that could make stage 1 bigger than described.

Unresolved technically, to settle in stage 1 rather than by guessing:

- Whether `placeToken` will position a building inside a new `income_aside_*` div, or whether that
  area needs the plain `dojo.place` path.
- Whether `notifqueue.setSynchronous("moveStructure", 300)` gives the slide animation enough room
  when four buildings move at once on the turn 5 ability, or whether the relayout should be one
  notification with its own duration.
