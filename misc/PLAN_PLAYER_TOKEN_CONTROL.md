# Plan: player token control and the floating capital

Status: done (2026-10-26). Part A is commit 0e96db9. In part B, INFILTRATORS has one `addCube`
call site, not two. The tooltip is skipped: map cubes have `pointer-events: none`, so a gold glow is
the only marker. Remove the legacy fallback (TODO in `isFloatingCapital`, server and client) after
2026-12.

Rules: FORMAL_RULES MAP.2-MAP.5, CIV.CELESTIALS.1 and .3 (rewritten 2026-10-26).

- A territory is controlled by the owner of its upright outpost or, with no upright outpost on it,
  by the owner of a player token on it.
- A player token has no stored upright/toppled state: it is toppled while an upright outpost shares
  its territory. Toppling a player token fires no topple-outpost effect.
- The CELESTIALS floating capital is the only inert item: it never controls, it looks different and
  it is the only item the CELESTIALS move may float.
- A territory has at most one owner. The adjustment variant 4 TRADERS "share control" is dropped
  (variant 4 is deprecated, to be deleted).

Two commits: part A fixes BGA #252643 and stands alone, part B makes the floating capital unique.
Existing tables must keep working with no DB migration (only the variant 4 TRADERS may change).

## Current state

- `getMap` adds the owner of every structure for which `isControllingStructure` is true
  (`card_type_arg != 1`), so `map_owners` can hold 2 players.
- Civ mat player tokens are created by `addCivToken` with `card_type_arg = CUBE_CIV` and
  `card_location_arg2 = reason_civ(civ)` (TREASURE HUNTERS: `:civ:38`). `effect_placeOnMap` keeps
  both, so a TREASURE HUNTERS player token on the map controls (arg 2).
- `effect_placeOnMap(..., false)` sets `card_type_arg = 1` (inert): ISOLATIONISTS, INFILTRATORS,
  floating capital.
- INFILTRATORS and the floating capital are both `addCube('hand')`: arg2 is `0` for both, so on a
  running table they cannot be told apart.
- `effect_conquer` topples `getStructureInfoSearch(null, 0, $location, $owner_id)`, which misses a
  player token with arg 2. The hex ends with two owners and `effect_endOfConquer` asserts
  (BGA #252643).
- `effect_trap` runs `card_type_arg = 1 - card_type_arg` on every structure on the hex: a player
  token with arg 2 becomes -1.
- `Celestials::getMoveTargets` offers every player token of the owner with `card_type_arg = 1`.

## Part A: one owner per territory (BGA #252643)

1. `getMap`: compute the owner per MAP.2 after collecting structures. Upright outpost owner first,
   else the owner of a player token that is not the floating capital. `map_owners` keeps its array
   shape with at most 1 entry, so callers (`isHexOwner`, `getControlHexes`, conquer targets,
   adjacency, scoring) need no change.
2. Remove `isControllingStructure`; nothing reads a player token's `card_type_arg` for control.
3. `effect_conquer`: topple only an upright outpost of the previous owner. When the previous owner
   came from a player token, write nothing (the new upright outpost wins). Keep `toppled_player`
   set so the trap card is still offered to that owner (ORDER.4e).
4. `checkToppleAward` already counts toppled outposts only: no change, cover it with a test.
5. `effect_trap`: toggle outposts only. With the derived rule a TREASURE HUNTERS player token
   controls again once the conqueror's outpost is toppled.
6. Floating capital, until part B: a player token of a CELESTIALS owner with `card_type_arg = 1`
   and arg2 `0` is the floating capital.
7. `effect_endOfConquer`: keep the `count == 1` assert, now an invariant of `getMap`.
8. Existing tables: the derived owner equals today's owner on every reachable hex. ISOLATIONISTS
   and INFILTRATORS player tokens always share their hex with an upright outpost (a conquer always
   leaves one and the trap only swaps which outpost is upright), so making them controlling changes
   nothing. Tables stuck on BGA #252643 resume: the retried `effect_endOfConquer` finds one owner.
   One change on running tables, intended by MAP.2: a base TRADERS token placed on an empty
   territory used to own nothing and now controls it (counts as controlled, blocks its owner from
   conquering it, offers its owner the trap when an opponent conquers it).
9. Tests (MapTest, keep in memory):
   - a lone TREASURE HUNTERS player token controls its territory
   - conquering it: conqueror is the only owner, no assert, trap offered to the TREASURE HUNTERS
     owner, no topple award count
   - trap played on it: the TREASURE HUNTERS owner controls again, no -1 arg
   - ISOLATIONISTS, INFILTRATORS and the floating capital keep their current control result
10. Docs: BUG_TRIAGE #252643 FIXED, TODO entry for part B.

## Part B: unique floating capital

1. Marker: `card_location_arg2 = reason_civ(CIV_CELESTIALS)`, the same origin convention as civ mat
   player tokens. No schema change, no new `card_type_arg` value.
2. `Celestials::setupCiv`: create the player token with the marker (`addCube` takes arg2).
   `Infiltrators` (`addCube('hand')`, two call sites) also passes `reason_civ(CIV_INFILTRATORS)`,
   so on a new table no player token on the map has arg2 `0`.
3. One helper `isFloatingCapital($structure)`: arg2 is the marker, or (legacy table) arg2 is `0`,
   `card_type_arg = 1` and the owner has CELESTIALS. `getMap` (step A1), `getMoveTargets` and the
   client marker read it. Replaces the step A6 rule. Add comment to remove legacy rules in few weeks.
4. `getMoveTargets`: only the floating capital, never another player token (CIV.CELESTIALS.3).
   On a legacy table where the owner also has INFILTRATORS, every legacy candidate stays movable,
   which is today's behaviour. Control is unaffected either way (step A8).
5. Client, `moveStructure` case 7: add a `floating_capital` class to a player token on the map
   when arg2 is the marker, or arg2 is `0` and its owner has CELESTIALS (same rule as step B3; on a
   legacy table an INFILTRATORS player token of that owner looks like one too, accepted). CSS for a
   distinct look, tooltip "Floating capital".
6. Material: CELESTIALS ruling text for CIV.CELESTIALS.3 (CivRulingsTest mirrors FORMAL_RULES and
   fails until this is done).
7. No migration: legacy tables are covered by the step B3 fallback.
8. Tests: setup marks the floating capital, the move offers only it, an INFILTRATORS player token of
   the same owner is not offered; legacy table (arg2 `0`) still moves and still never controls.

## Notes

- INFILTRATORS third player token counts the floating capital (CIV.INFILTRATORS.1, "whatever placed
  it"). Not documented further.
