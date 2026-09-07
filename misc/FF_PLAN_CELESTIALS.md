# Celestials - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).

Status: planned, not started.

Three effects on one civ. Setup replaces one of the two starting outposts with a player token, the
floating capital, so the start hex holds one outpost and one token. At the start of income turns
2-4 the owner may move that token to an adjacent territory, even a full one, and if they do they
roll both conquer dice and gain both benefits; the move is not a conquer and the token never takes
control of anything, but it is an item on the hex, so a hex holding it plus anything else cannot be
conquered. At the start of income turn 5 the owner loses 2 VP per landmark that hangs off the side
of their capital city and gains 5 VP per landmark that does not. Gained mid game, the civ may be
discarded to draw another, the existing 173/174 pair.

The engine half is a hex occupant that is not an owner, and a reader for "does this landmark
footprint leave the mat". The second one turns out to be nearly free: overhang is already a legal,
reachable and tested state (see stage 1 below). The first one is where the work is.

## Shape

- Material entry `CIV_CELESTIALS` (41, already defined at [material.inc.php](../material.inc.php)
  line 235): `exp => "FF"`, `income_trigger => ["from" => 2, "to" => 5, "decline" => true]` (the
  move is optional; turn 5 is scoring only and does not prompt), `midgame_ben => ["or" => [174,
  173]]` for the discard-and-redraw sentence, no `slots` (the token lives on the map, not on the
  mat, so there is nothing for `populateSlotChoiceForArgs` to offer), no `tokens_count` (the token
  is created on the map by `setupCiv`, not on a civ slot).
- `automa => true` proposed, unlike Genies, Illuminati and Weefolk. Nothing here needs a live
  opponent. See rulings for the three Automa paths that have to be checked first.
- Two new [benefit_types.csv](benefit_types.csv) rows:
  - `BE_CELESTIALS_MOVE` with `'state' => 'celestialMove'` and `'icon' => 'no'`. It uses the row
    170 shape: the default branch of `awardBenefits` (PGameXBody.php:2133) transitions on `state`
    and leaves the row on the stack, and the action handler clears it. No `civ` key, so the generic
    dispatch handles it and the civ class only has to queue it.
  - `BE_CELESTIALS_SCORE` with `'civ' => CIV_CELESTIALS`, resolved in the civ's `awardBenefits`.
    The Illuminati and Weefolk income shape: deterministic, nothing chosen.
- No new globals and no new DB columns. The token is one `structure` row: `card_type`
  `BUILDING_CUBE`, `card_location` `land_${x}_${y}`, `card_location_arg` the owner, and
  `card_location_arg2` set to `reason_civ(CIV_CELESTIALS)` as its marker. `addCivToken`
  (PGameXBody.php:1404) already puts a reason string in `card_location_arg2`, and
  `getStructureInfoSearch(BUILDING_CUBE, CUBE_CIV, $card, null, reason_civ(CIV_INVENTORS))` at
  PGameXBody.php:6735 already searches by it, so this is an existing convention, not a new one.
  `card_type_arg` stays `CUBE_NORMAL`, deliberately not `1`: reusing the toppled flag to hide the
  token from `isHexOwner` would make it a stand-up target and a topple-award occupant.
- Client visible state: none beyond the structure row itself. `getAllDatas` already ships every
  structure, `moveStructure` in [tapestry.js](../tapestry.js) already renders a cube on a land slot
  (the Infiltrators path), and the marker in `card_location_arg2` is what the CSS keys on.
- No new state global for the move: `setSelectedMapHex` (PGameXBody.php:9375) already carries the
  hex the dice are rolled against and is what black face 1 reads.

## Stage 1 - engine seams

Everything here changes existing engine code, lands with tests, and provably changes nothing
without a Celestials at the table. The Weefolk work opened the analogous seam on the capital table
(`isForeignToken`, "a cube belonging to someone else is not a building of the capital owner",
PGameXBody.php:8560). Same shape, different table: the naming and the "one predicate, one writer,
one reader" discipline are reusable, the code is not.

### S1. Get the raw SQL out of getMap, so the map is testable at all

`getMap()` (PGameXBody.php:5796) is two calls: `getMapDataFromDb("map", $xcoords)`, which reads
only `card` rows through `getCardsSearch` and is therefore already modelled by `GameUT`, and one
raw `getCollectionFromDB("SELECT * FROM structure WHERE card_location LIKE 'land\\_%'")`. That
single query is the only reason no test in the suite can drive the map. Replace it with a
`getStructuresOnMapDb($xcoords = null)` helper over `getStructuresSearch` (PGameXBody.php:1330),
which `GameUT` already overrides against the in-memory structure model.

The payoff is out of proportion to the change: `getConquerTargets`, `isHexOwner`,
`getControlHexes`, `isHexBlockedForConquer`, `effect_endOfConquer` and the Isolationists final
scoring all become testable without a stub that fakes their answers. `DiceUT` currently fakes
`getMapHexData` to `["map_owners" => []]` and `effect_placeOnMap` to a location write, both of
which can then be deleted or narrowed.

Two things also have to move while in there:

- The `array_unique` / `array_values` pair at PGameXBody.php:5823-5824 sits *outside* the foreach,
  so it dedupes only whichever hex the last structure row happened to be on. It works today only
  because every caller that reads `map_owners` for a decision goes through `getMapHexData` with a
  single coord. That is a latent bug and Celestials adds a second occupant kind to the same lists;
  move both lines inside the loop and pin the "two of my own outposts on one hex" case.
- `getNeighbourHexes` (PGameXBody.php:7146) defaults `$valid_coords` to `SELECT map_coords FROM
  map`. Give it a `getMapCoordsDb()` helper backed by `getInitMapData`, which is pure material, so
  adjacency is testable too. The Celestials move needs adjacency; nothing else in the civ does.

### S2. isControllingStructure - an occupant that is not an owner

`getMap` decides ownership inline: `$toppled = $struc["card_type_arg"]; if ($toppled != 1) {
$map[$coords]["map_owners"][] = ... }` (PGameXBody.php:5816-5819). Extract that into

`isControllingStructure(array $structure): bool`

with today's rule (not toppled) plus one clause: a civilization token marked in
`card_location_arg2` controls nothing. `occupancy` and `map_occupants` are untouched, so the token
counts as an item for `isHexBlockedForConquer` (occupancy >= 2, PGameXBody.php:7142) for free,
which is exactly the card's "a territory with 2 items may not be conquered".

Deliberately narrow: the predicate excludes only the marked token, not cubes on land generally. An
Isolationists token and an Infiltrators cube on land keep controlling exactly as today, and 5.12
depends on that.

What this makes true, all of it verified against the call sites rather than assumed:

- `isHexOwner` / `getControlHexes` / `getNumberOfControlledTerritories` / the Isolationists landmass
  walk see nothing. A hex holding only the token belongs to nobody.
- `getConquerTargets`: a hex with the token alone is occupancy 1 and unowned, so it stays
  conquerable by anyone; a hex with the token plus one outpost is occupancy 2 and blocked for
  everyone, the card's rule.
- `effect_conquer` (PGameXBody.php:7654) reads `map_owners` to decide who gets toppled and who may
  play a trap. On a token-only hex that list is empty, so nobody is toppled and no trap is queued,
  which is right.
- `effect_endOfConquer` (PGameXBody.php:12223) asserts exactly one owner after a conquer. Still
  true: the conqueror's outpost is the only controlling structure.

### S3. The topple proxy in effect_endOfConquer

`$toppled = count($map_data["map_occupants"]) == 2;` (PGameXBody.php:12237) is a proxy for
"somebody was toppled", and it drives the topple achievement and the both-dice `conquer_bonus`
paths. With a third player's token on the hex the occupant count says 2 when nobody was toppled,
and says 3 when somebody was. Replace the proxy with the `toppled_player` global that
`effect_conquer` already writes on both branches a few lines above the roll. This is a correctness
fix that stands on its own; it is listed here because Celestials is what makes the proxy wrong.

### S4. Landmarks off the side of the capital - a reader only

This is the good news of the whole plan. Overhang is already representable, already reachable and
already tested:

- `importCapitalGrids` (PGameXBody.php:3810) seeds a 15 by 15 `capital` grid per player. The mat is
  x and y 3..11; every border cell outside it exists with `capital_occupied` 0.
- `onMat($x, $y)` (PGameXBody.php:10576) is `3..11` in both axes, and `argPlaceStructure`
  (PGameXBody.php:10517) accepts any anchor whose footprint covers only free cells and touches the
  mat at least once, so hanging over any of the four sides is legal today.
- `getCapitalScoreVP` (PGameXBody.php:2335) counts only 3..11, so the off-mat cells already
  contribute nothing to rows and columns.
- `tests/CapitalMatTest.php::testLandmarkOptionsSkipBuildingsAndMayOverhangTheEdge` asserts the
  anchor one column off the mat is offered.
- The client draws all 15 by 15 cells (`addCapitalGrid`, tapestry.js:3536), so the overhang renders
  without any client change.

So the only new code is a reader:

`isLandmarkOverhanging(array $structure): bool` - walk `getStructureCells(BUILDING_LANDMARK,
$structure["card_location_arg2"], $x, $y, (int) $structure["card_type_arg"])` and answer true when
any cell fails `onMat`. Both inputs are already how Weefolk's `countInLine` reads a landmark
footprint, including the `card_type_arg` is the rotation convention on the capital.

No new column, no placement-time bookkeeping, no migration for games in flight: the answer is
recomputed from the anchor and the mask whenever it is asked for.

### What does not need a seam

The movable token needs no new engine primitive. `dbSetStructureLocation` (PGameXBody.php:4759)
moves a structure row and notifies, and the client's `moveStructure` relocates an existing cube div
by id. `effect_placeOnMap` is deliberately not reused for the move: it writes `map.map_owner`,
which is the opposite of what this civ does. (Aside: `map_owner` is written by seven call sites and
read by none - ownership is derived from structures. Not this civ's problem, but the plan should
not add an eighth writer.)

### Regression tests that land with stage 1

A new `tests/Stubs/MapUT.php` (GameUT plus helpers to seed tiles and structures on hexes), and
cases that pass before and after the seams:

- `getConquerTargets` for an empty map, adjacency only, `anywhere`, `nomads` and `only_empty`.
- A hex with two outposts of one player is blocked; a hex with one is a target; an allied hex is not.
- `effect_conquer` on an owned hex topples, sets `toppled_player`, queues the trap and row 141; on
  an empty hex it does none of that.
- `effect_endOfConquer` awards the topple achievement exactly when `toppled_player` is set, at 1,
  2 and 3 occupants.
- `getMap` dedupes owners on every hex, not just the last one (fails before S1).
- `getCapitalScoreVP` and `argPlaceStructure` unchanged (the existing CapitalMatTest cases already
  cover this; S4 adds no write path, so they are the guard).
- The whole set run with no Celestials in play is the "the seams change nothing" proof; the same
  file gains the with-token cases in stage 2.

## Stage 2 - the civilization

- Two CSV rows above, `npm run genmat`, `npx prettier --write material.inc.php`.
- `CIV_CELESTIALS` material entry with the description split one `clienttranslate` per rules
  sentence, as Illuminati's is.
- `modules/civs/Celestials.php`:
  - `setupCiv($player_id, $start)`. Start: `stFinishSetup` (PGameXBody.php:10670) has already put
    two outposts on the start hex before it calls `setupCiv`, so this removes one of them back to
    `hand` (where `getOutpostsInHand` finds it again, its `card_location NOT LIKE 'land%'` filter)
    and creates the token on the same hex. Mid game: the same code, against the hex the outpost is
    actually on rather than the nominal start hex; see rulings for the cases where there is no
    outpost of theirs left to replace.
  - `queueEraCivAbility` overridden the Weefolk way: turns 2-4 queue `BE_CELESTIALS_MOVE`, turn 5
    queues `BE_CELESTIALS_SCORE`, anything else falls through to the parent's not-applicable
    message.
  - `awardBenefits` handles `BE_CELESTIALS_SCORE` only: count the owner's landmarks in
    `capital_cell_${player_id}_%`, partition on `isLandmarkOverhanging`, award `5 * kept - 2 *
    hanging` as one `awardVP` with `reason_civ(CIV_CELESTIALS)`. Opens with the
    `systemAssertTrue("ERR:Celestials:NN", isRealPlayer)` / `hasCiv` pair and closes with the
    unowned-benefit assert, per CODE_STYLE.
  - `getToken($player_id)` / `getMoveTargets($player_id)`: the token row, and
    `getNeighbourHexes(coords)` filtered to hexes with a tile on them (`map_tile_id != 0`) - no
    occupancy filter, since the card allows moving onto a full territory.
  - No `finalScoring`. The scoring is an income turn 5 row, not end of game.
- New state 40 `celestialMove` in [states.inc.php](../states.inc.php), `activeplayer`, args
  `argCelestialMove`, actions `celestialMove` and `decline`, transitions both to 18; plus
  `"celestialMove" => 40` in state 18's transition list. Adding a state at the end is safe per
  CODE_STYLE; renumbering is not.
- `argCelestialMove` on `PGameXBody`: `["targets" => [...], "decline" => true]` plus
  `notifArgsAddBen`, the `argConquer` shape (PGameXBody.php:9581), so the client can reuse the
  land-slot highlighting.
- `action_celestialMove($u, $v)` on `PGameXBody`, declared in `tapestry.action.php` with two
  `AT_posint`-style arguments the way `conquer` is: validate against the arg targets with
  `userAssertTrue`, clear the row, move the structure, `setSelectedMapHex($coord)`,
  `rollConquerDice($player_id)`, then `conquerDieBenefit("red", ...)` and `conquerDieBenefit
  ("black", ...)` for both benefits, then `nextState("next")`. Never `effect_conquer`, never row
  141, never `map_owner`: this is not a conquer, so no "whenever you conquer" trigger, no die pick,
  no Traders leftover die, no Utilitariens barracks, no trap.
- `action_decline` already exists for the generic decline path; confirm it clears the row for a
  `state` row and returns to 18 rather than needing its own handler.
- `zombieTurn` (PGameXBody.php:12427) walks the state's transitions for an active-player state, so
  state 40 must declare a transition it can take; verify a zombie in `celestialMove` declines
  rather than throwing.

## Test infrastructure

- `tests/Stubs/MapUT.php`, new, built on the S1 seam: `setTile($coord, $tile_id, $rot)` writing a
  `CARD_TERRITORY` row at `map` with `card_location_arg2` the coord (the shape `getMapDataFromDb`
  reads), `addOutpostAt`, `addCelestialTokenAt`, and a `hexOccupants($coord)` reader. With S1 in
  place these need no method overrides at all, which is the point of doing S1 first.
- What `GameUT` still does not model and needs a stub in the test subclass, the same way the
  Illuminati plan stubbed `getSelectedMapHex` and `getTileBenefit`:
  - `getSelectedMapHex` / `setSelectedMapHex` are `map_id` and `map_coords_selected` globals plus
    `getMapHexData`; the globals are modelled, so after S1 this may work unmodified. Verify rather
    than assume, and stub if not.
  - `getTileBenefit` reads `map_tile_id` off the selected hex and then material; script it from
    `DiceUT` as the Illuminati tests do, so the black face 1 case is deterministic.
  - `playerextra` is not modelled, so income turns come from `startIncomeTurn` / `eras`.
  - `prepareUndoSavepoint` is recorded, not executed, so undo across the move is a studio check,
    not a unit test.
- `doAdjustMaterial($players, $variant)` with the FF variant before any assertion on civ data.
- `seedRand` drives the move's dice; `rollConquerDice` consumes red then black.

## Test cases

Setup and the token:

- Material entry: `exp`, `income_trigger`, `midgame_ben`, `automa`, and no `slots`.
- Start setup leaves one outpost and one marked token on the start hex, and the second outpost is
  back in the supply where `getOutpostsInHand` finds it.
- Mid game setup replaces one outpost on the hex the owner still holds; the redraw option (173) is
  offered and taking it runs no setup.
- The token is not a controlling structure: `isHexOwner` false, the hex is absent from
  `getControlHexes`, `getNumberOfControlledTerritories` unchanged, and BE_VP per controlled
  territory scores nothing for it.

Conquest against the token:

- Token plus one outpost is not in any player's `getConquerTargets`, including the owner's, and
  including with `anywhere`.
- Token alone on a hex is conquerable; after the conquer the conqueror is the single owner, nobody
  was toppled, no trap row was queued, and the topple achievement was not awarded (fails before S3).
- Token on a hex the owner also has an outpost on: an opponent cannot conquer it; after the token
  moves away the same hex becomes conquerable again.
- Nomads, Militants and the `anywhere` conquer rows all see the same blocking.

The move:

- Income turns 2, 3 and 4 queue `BE_CELESTIALS_MOVE`; turn 1 and turn 5 do not.
- `argCelestialMove` offers exactly the adjacent tiled hexes, including one with two outposts on
  it, and excludes untiled hexes and the token's own hex.
- Moving rolls both dice and queues both benefits, in red then black order, with the civ reason,
  and queues no row 141.
- Black face 1 pays the benefit of the destination territory, not the origin (the
  `setSelectedMapHex` case).
- A zero-effect face pays nothing and says so.
- Declining rolls nothing.
- No legal target (a token boxed in by untiled hexes, or a 2 player small map edge) offers decline
  only and pays nothing.
- The move does not write `map_owner` and does not change `getControlHexes` for anyone.
- An Illuminati opponent takes both dice from the move roll and is paid before the mover gains
  anything (the 5.27 ordering, free from `dieRolled`, but pinned here because this is a new roll
  site).

Income turn 5 scoring:

- No landmarks scores 0.
- Three landmarks fully on the mat scores 15.
- One landmark anchored so a cell of its footprint leaves the mat scores -2, at each of the four
  sides, and for a rotated footprint where only the rotation makes it hang.
- A mixed capital: two on, one off, scores 8.
- A landmark set aside in `hand` (the Weefolk full-city case, 5.9) and a landmark on a Craftsmen
  civ slot score neither 5 nor -2.
- The score row is queued at income turn 5 whether or not the civ was gained mid game.
- A negative total is applied as a negative `awardVP`.

Guards:

- A table with no Celestials produces byte-identical conquest targets, topple awards and capital
  scoring to before the seams (the S1-S3 regression file, re-run).

## Client

- The token renders through the existing cube path in `moveStructure` ([tapestry.js](../tapestry.js)
  line 6382): `location.startsWith("land")` maps to the hex slot, and the cube div is created or
  relocated. The one addition is a class from `card_location_arg2` carrying the Celestials reason,
  the way the `dic_` prefix already adds `dictator` a few lines below.
- CSS: a `.celestial` skin on the cube (the floating capital art), sized like the Isolationists
  token on a land slot, plus a tooltip "Floating capital - this territory cannot be conquered while
  a second item is on it". Reuse the existing land-slot token positioning; do not add offsets.
- `onUpdateActionButtons_celestialMove(args)`: add `active_slot` to `land_${coord}` for every entry
  of `args.targets`, and a decline button. The conquer state (tapestry.js:1388) and the
  `placeStructure` state's `conquer_targets` (tapestry.js:1594) are the two precedents; both also
  show the click-handler guard that CODE_STYLE requires alongside `active_slot`, in `onLandClick`.
- `onLandClick` gets a `celestialMove` case calling `axcallwrapper("celestialMove", {u, v})`, with
  a `checkActiveSlot` guard so a dimmed hex does not fire.
- The move's dice show through the existing `conquer_roll` notification and `updateConquerDice`, so
  no new notification is needed for the roll. The move itself is a `moveStructure` notification with
  a log line of its own.
- The income turn 5 score is an `awardVP` with a civ reason, so the score breakdown renders for
  free. Confirm the negative half reads sensibly in the log.
- `notif_benefitQueue` shows the pending move row; if it does not, the row was queued wrong.

## Rulings

Proposed, to be recorded in FORMAL_RULES as 5.30 and following.

- The player token is an item on the territory but never controls it. It counts toward the two
  items that make a territory unconquerable, it does not make the owner the controller, and it is
  invisible to everything that reads control: conquer targeting and adjacency, the territory count
  benefits, the Isolationists landmass, the central island achievement, and the Mystics controlled
  territory prediction. It is not an outpost, so it is never toppled, never stood up, and never
  counts toward the topple achievement.
- Against each conquest path: an opponent may conquer a territory holding only the token, and the
  token stays on the territory afterwards, sharing it with the conqueror's outpost, which then
  makes the territory unconquerable at two items. An opponent may not conquer a territory holding
  the token plus anything else. Nomads moving a structure and any "conquer anywhere" effect obey
  the same two-item rule, since they read the same occupancy.
- Conquering a territory that holds only the token topples nobody and grants no trap, because the
  token has no upright state and its owner controls nothing there.
- The token stays where it is when the territory around it changes hands, and when its owner
  finishes their game, quits or is eliminated. It is not returned, and it keeps blocking. A zombie
  owner simply never moves it again. This is the physical-game answer, and the alternative, sweeping
  it off, would hand a conquest to whoever outlasts them.
- The move is not a conquer for any purpose: no "whenever you conquer" trigger, no die choice, no
  trap, no Traders leftover die, no Utilitariens barracks, no exploration. It is a roll of both
  conquer dice with both benefits gained, and the black die's face 1 pays the benefit of the
  territory moved into.
- Rolling those dice is rolling them from the table, so an Illuminati opponent's dice are taken and
  paid exactly as on any other roll (5.24).
- The move is optional and one hex per income turn 2-4; declining costs nothing and there is no
  catching up on a skipped turn. If the token has no adjacent territory with a tile, the ability is
  void for that turn.
- A landmark hangs off the side when any cell of its placed footprint is outside the 9 by 9 mat.
  Partly off is off; there is no proportion. This is judged at the start of income turn 5 from the
  footprint as placed, and landmarks placed after that turn score nothing either way.
- A landmark that is not in the capital city at all is neither: one set aside outside the mat by a
  Weefolk token (5.9) and one on a Craftsmen civ slot score no 5 and no -2. Only landmarks in
  `capital_cell` cells count.
- Weefolk interaction: a planted Weefolk token can never be replaced by a landmark and never moves
  one, so it cannot change whether a landmark hangs off. The Weefolk row and column count (5.7)
  keeps counting a hanging landmark once per line it touches, and the lines outside the mat are
  never a token's row or column.
- The overhanging cells contribute nothing to capital row and column scoring, districts, or
  building counts, exactly as today. Celestials adds a reason to hang a landmark off, it does not
  change what hanging off means anywhere else.
- Setup: the outpost not placed goes back to the owner's supply and may be used to conquer later.
  Gained mid game, the token replaces one of the owner's outposts on their starting territory and
  that outpost returns to the supply; a toppled outpost may be the one replaced. If the owner has
  no outpost left on their starting territory, the token is placed on that territory anyway, since
  the floating capital is theirs regardless of who holds the ground.
- Solo and Automa: `automa => true` is proposed, since nothing in the civ needs an opponent to
  point at. Before it ships, three Automa paths need confirming with the token on the board:
  `effect_automaConquer` target selection and its central-island pathing through
  `isHexBlockedForConquer`, `effect_automaToppleShadow`'s "controlled territories with only 1
  token" scan (PGameXBody.php:6320), which correctly skips a hex the token shares, and the Shadow
  Empire's toppled outpost placement. If any of them turns out to need bot changes, ship
  `automa => false` rather than churning bot code, the Illuminati precedent.

## Open questions

For Victoria:

- Mid game gain when the owner's starting territory holds no outpost of theirs. Place the token
  there anyway (proposed), place it on any territory they control, or skip the token?
- May the token move onto a territory with no tile explored yet? The card says "adjacent
  territory", and an unexplored hex is arguably not a territory; the plan assumes tiled hexes only.
- Does the token block *exploration* of the hex it sits on? It cannot happen with the tiled-hex
  reading above, but confirm the intent.
- Setup outpost: back to the supply (proposed, so it can be used for a later conquest), or out of
  the game? This is a real power difference.
- Income turn 5: is the landmark scoring affected by anything gained during the same income turn's
  earlier phases, i.e. is it start-of-turn as the card says, or after the income phase? The plan
  reads it as the start, alongside the other income civ abilities.
- `automa => true` or `false`, pending the three Automa checks above.
- Should the token be a distinct structure type rather than a marked cube? A new
  `BUILDING_SKYCAPITAL` would be inert to every existing `card_type` query and self-documenting,
  at the cost of a new case in the client's `moveStructure` and a new `structure_types` entry. The
  plan proposes the marked cube because the client path already exists, but the type is the cleaner
  long-term answer if more FF civs put things on the map.
