# Alchemists Rework Plan (adjustment level 9)

Goal: add game option value 9 "testing" variant of the Adjustment Civilization Pack that
replaces the Alchemists with the new version below. Games in progress (option 8 and older)
must keep the old rules, tooltips and card graphics. Option 9 is beta, not default.

Source image: /home/elaskavaia/Develop/bga/bga-assets/Tapestry/newAlchemist.webp

## New Alchemists rules (transcribed from image)

The Alchemists, perhaps foolishly, attempt to make unstable-but-potent elixirs.

At the beginning of your income turns (2-5), research elixirs:

1. Roll all three dice; select one die to place on this mat.
2. Roll the remaining two dice; select one die to place on this mat.
3. Roll the final remaining die and place it on this mat.
4. Gain the benefit of the **red conquer die** and either the:
   - **Science die**: Advance on the track and gain the resulting benefit
     (you may pay to gain the bonus, if any).
   - or **Black conquer die**: Gain the benefits as indicated below.

Bottom of the mat maps each black conquer die face to a benefit (5 pairs). The last pair
is marked "Benefit on any territory you control". left to right:

Faces for black die:

1. [FOOD]+[EXPLORE]
2. [CULTURE]+[CONQUER]
3. [COIN]+[INVENT]
4. [WORKER]+[RESEARCH X]
5. [TERRITORY] plus [ANY RESOURCE], on any territory you control

Confirmed: benefit pairs are the same as current a8, so `slots@a8` falls through
to level 9 unchanged and no `slots@a9` twin is needed. The delta is only the dice
procedure (no rerolls, all three dice placed, red die benefit always gained at
face value, then choose science or black die).

## Mechanics: how level 9 works

- `getAdjustmentVariant()` returns 9 for new tables; old tables keep stored 8.
- All rules checks funnel through helpers in PGameXBody.php lines 126-137.
- Client stamps body class `variant_adjustments_9` automatically (tapestry.js line 134),
  CSS forks card art on it; tooltips come from adjusted material in gamedatas for free.

## Change list

### Server

1. gameoptions.inc.php: add value 9, beta, keep default 8. No startcondition.
   After deploy: "Reload game options" in studio control panel.
2. PGameXBody.php 126-137: `isAdjustments8()` becomes `>= 8`,
   `isAdjustments4or8()` becomes `== 4 || >= 8`, add `isAdjustments9()` with `>= 9`.
   Covers all ~85 civ call sites without touching them.
3. PGameXBody.php 203-207 (doAdjustMaterial), the risky one:
   - adj 9 accepts suffix `a9` and falls back to `a8` (otherwise all pack fields
     silently revert to base rules);
   - an `a8` key is skipped when the same field has an `a9` twin. Do not rely on
     merge order: array_replace_recursive cannot delete keys, so a shorter
     `slots@a9` merged over `slots@a8` leaves a stale slot.
4. PGameXBody.php 10416: setup message switch has `case 8` only; add case 9
   (or fall through) so level 9 announces its rules.
5. material.inc.php: Alchemists entry at line 3679; add `@a9` twins only for fields
   that change - mainly `description@a9` (new dice procedure text). Benefit pairs are
   unchanged, so `slots@a8` is inherited via fallback, no `slots@a9` needed.
   Hand-edited region, no genmat; prettier formats on save.
6. modules/civs/Alchemists.php: implement the new elixir flow gated on
   `isAdjustments9()`. Existing forks to review:
   - lines 21 and 42 use `getAdjustmentVariant() >= 8`; these gate the current pack
     dice flow, which level 9 replaces, so they must become 8-only or fork 8 vs 9;
   - lines 190 and 227 are `isAdjustments4()` legacy branches, leave alone;
   - `alchemistRoll8` (line 78) is the pack-8 roll; new flow is a 3-step draft
     (roll 3, keep 1; roll 2, keep 1; roll last) and needs its own multi-step state
     handling via the benefit stack.

### Client

7. tapestry.js 966: the only `== 8` (Traders prompt); make it `>= 8`.
   Every other check is already `>= 8`, `< 8` or `>= 4`.
8. tapestry.css 552-562: comma-join `.variant_adjustments_9` onto the three
   `variant_adjustments_8` art selectors so level 9 reuses the a8 card graphics.
   No proper new card art yet; when the asset arrives, add a single
   `.variant_adjustments_9 .civilization_1` override with the new Alchemists image.

### Tests

9. GameTest.php: sibling of the existing `doAdjustMaterial(2, 8)` tests with variant 9,
   asserting the three failure modes of change 3:
   - Alchemists shows a9 data;
   - an untouched civ still inherits its a8 data (fallback);
   - a compound `@a4a8` field still resolves.
10. Unit tests for the new elixir flow (roll/keep sequencing, red die always gained,
    science vs black choice, territory-control benefit).
11. `npm run predeploy` gate.

## Time Spent

- 2026-08-25: analysis and plan writing, 20:12 to 20:56 EDT, about 45 min (in progress)

## Notes

- Values 1/2/4/8 are never tested bitwise, 9 is safe as an ordered level.
- The material key regex `a[0-9]` is single digit; 9 is the last free value.
- Option 140 displaycondition (otheroptionisnot 4) is unaffected.
- Old tables never see level 9: option value is stored per table at creation.
