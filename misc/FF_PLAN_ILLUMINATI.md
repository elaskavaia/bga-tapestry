# Illuminati - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).

Status: done, stages 1 and 2 landed and verified in the studio.

Four effects on one civ: a draw 3 keep 1 tapestry at setup, a gain whenever an opponent takes one
of the three dice from the mat (both conquer die benefits, or an optional no-benefit no-bonus
advance on the science track rolled), the taken die staying off the mat until the owner's next
income turn, and 6 VP per die still on the mat at the start of income turns 2-5, after which all
three come back. The mat has no token spots, so no `slots`. The dice are never physically on the
mat in the UI: each die carries an overlay while it is "on the mat", the MARRIAGE OF STATE marker
style.

## Shape

- Material: `income_trigger` 2-5 with `decline => false`, `automa => false` (the bot never rolls,
  so there is nobody to take a die), no `slots`, no mid game setup entry: `setupCiv` runs the same
  code for a start and a mid game gain.
- Two new CSV rows. `BE_ILLUMINATI_DRAW` is the Draw 3, Keep 1 Technology row (175) with
  `ct => CARD_TAPESTRY`: the same handler draws into `draw`, the keepCard state offers the three,
  and the generic tail of `effect_keepCard` moves the kept one to hand and discards the rest, so no
  new keep logic. `BE_ILLUMINATI_INCOME` is `civ => CIV_ILLUMINATI`, resolved in `awardBenefits`.
- Dice on the mat are one new global, `illuminati_dice` (id 39), a bitmask: black 1, red 2,
  science 4. Only one Illuminati exists at a table, so "whose mat did this die come from" is "the
  bit is set" plus `getCivOwner(CIV_ILLUMINATI)`. Globals sit inside the undo savepoint, so a
  roller's undo puts the die back on the mat along with dropping the owner's queued gain.
- The income ability is a deterministic row, the Merfolk shape, not a civ state: nothing is
  chosen, and 6 VP has no ordering interaction with another income civ. It counts the set bits,
  queues `BE_VP` for 6 per die with the civ reason, sets all three bits and notifies.
- The gain from a taken die is queued at roll time, from the hook below, on the owner, with
  `reason_civ(CIV_ILLUMINATI)`:
  - a conquer die queues what `getConquerDieBenefit($die)` answers for the face rolled, the
    Traders shape, including the territory benefit on black face 1 and a "no benefit" message on
    a zero face;
  - the science die queues a choose-one of row 76 plus track minus 1 (rows 76-79, Advance no
    benefits, `flags => 0`) and 401 (decline): an optional single advance with no benefit and no
    bonus. Not rows 84-87: those write `science_die` and `science_die_empiricism`, and the owner's
    row now resolves before the roller's research decision reads them.
- A die stays where it is when the owner rolls it. There is no "put it back" step: the bit is
  simply not cleared.

## Engine work

The "die roll provenance" item from the shared work list. Built so Psionics hooks the same place
later.

Stage 1 (the seam and the timing moves, no civ yet) is done, see "Stage 1 status" at the end of this
section for what landed and what is still open.

- `rollDieFace(string $die): int` pulls the `bgaRand` call and the face remap (5 becomes 1 on
  black, 2 on red, 1-4 on science) out of `rollConquerDice`, `rollRedConquerDie`,
  `rollBlackConquerDie` and `rollScienceDie`. One function produces every die value in the game;
  Psionics' roll twice keep one wraps it.
- `dieRolled(string $die, int $face, int $roller_id)` runs at the end of those four functions,
  after the roll notification so the log reads roll first, effect second. `rollConquerDice` calls
  it twice. It calls a new `AbsCivilization::onDieRolled($die, $face, $roller_id)` on every civ in
  play, the `getAllCivs` walk over all players. `rollScienceDie` resolves its `-1` default to the
  active player before the hook.
- Illuminati's `onDieRolled`: return when the roller is the owner or the bit is clear; clear the
  bit and notify (this is the "die leaves the mat" moment); return with a message when the owner
  is finished or zombie; otherwise queue the gain above. Because the first roll clears the bit,
  every later roll of that die (Empiricism's second roll, the second roll of rows 301 and 304,
  an Alchemists reroll) sees a clear bit and does nothing: the card's "only from the first roll"
  falls out with no reroll tracking.
- Ordering, ruled owner-first everywhere: the owner's rows are queued Normal at roll time, and
  the rule for every flow is that whatever the roller does with the roll is queued Normal after
  the roll too, so the owner's rows sit ahead by id. Where the roller's continuation must jump
  the queue, `interruptBenefit()` is called before the roll, never after it. Per flow:
  - `conquer()`: nothing to change, the die pick row (141) is already queued after the roll.
  - Rows 301, 303, 304: nothing to change, the roller's gains are queued after each roll.
  - `research()` (the science track research spots): today it rolls and jumps straight into the
    research state. It becomes interrupt, roll, queue a new `BE_RESEARCH_DECISION` row for the
    roller that enters the research state without touching the dice globals, and return to the
    manager. Without an Illuminati at the table the timing is exactly today's.
  - The tech card research benefit (`r` in the card benefit switch): today it rolls and advances
    inline. It becomes interrupt, roll, queue the advance row of the matching family for the
    track and the row's flags. This is the costliest of the four.
  - Age of Discovery: the roller's advance is queued with interrupt after the roll today. Move
    the interrupt before the roll and queue the advance Normal.
  - Rows 324 and 325: `interruptBenefit()` moves from after the roll to before it.
  - Alchemists rolling from the mat: verify the keep-or-reroll civ row resolves after the owner's
    rows; if the civ category jumps ahead, apply the same recipe in `rollAllDice`.
- `getAllDatas` adds `dice.on_mat` (the mask, 0 without an Illuminati) and `dice.mat_owner`.
- `setupCiv` on the civ class: queue `BE_ILLUMINATI_DRAW`, set the mask to 7, notify. Same for
  start and mid game. A civ gained in phase 1 of an income turn 2-5 fires its income ability in
  that turn, as every income civ does today, so it scores all three dice at once (see rulings).
- Nothing to do for the finished-player guards: a finished owner's rows are dropped by
  `checkAliveForBenefit`, and the hook stops before queueing anyway. No `zombieBenefit` is
  needed: unlike Genies, every row this civ queues belongs to the owner.
- Not covered: the black die's territory benefit outside a conquer reads whatever hex
  `getSelectedMapHex` still holds, for the owner exactly as for the roller today.

## Stage 1 status

Done, `npm run predeploy` green at 300 tests (284 before):

- `rollDieFace` and `dieRolled` in [PGameXBody.php](../modules/PGameXBody.php), wired into all four
  roll functions. `rollConquerDice` reports red then black, the order it rolls them in.
  `AbsCivilization::onDieRolled` is the no-op default.
- `research()` takes the research row and the player, interrupts before the roll, and queues the
  optional advance row of the rolled track instead of transitioning: 88-91 for `BE_RESEARCH`, 84-87
  for `BE_RESEARCH_NB`, 97-100 for `BE_RESEARCH_MAXOUT`. Rows 72 and 97 had no constant, so they
  got one (`BE_RESEARCH_MAXOUT`, `BE_ADVANCE_EXPLORATION_NOBENEFIT_MAXOUT_OPT`) - the `-con` column
  of the CSV only emits the comment, the `define()` itself is hand-maintained in
  [material.inc.php](../material.inc.php). No new `BE_RESEARCH_DECISION` row was needed - those
  three families already carry exactly the flags `action_research_decision` reads off the stack,
  and already enter the research state without re-rolling. Empiricism's second track survives
  because the advance row only rewrites `science_die` with the value already there.
- Age of Discovery interrupts before the roll and queues the roller's advance Normal.
- Rows 324 and 325 interrupt before the roll.
- Verified as needing nothing, now with tests: `conquer()` (row 141 already after the roll), rows
  301, 303 and 304, and Alchemists - `getCurrentBenefit()` orders by prerequisite then id with no
  category priority, so `benefitCivEntry` after `rollAllDice` is already behind the roll's rows.

Open:

- The plan's fourth timing item, "the tech card research benefit (`r` in the card benefit switch)",
  is `queueBenefitAutomaSingle` case `"r"` - the only roll-then-advance-inline site left in the
  codebase. It is Automa only (`$player_id = PLAYER_AUTOMA` is hardcoded) and the Automa exists
  only in solo, which Illuminati is out of by `automa => false`, so the two can never meet. Left
  alone rather than churning bot code. Confirm that is what the item meant.
- The conquer ordering test drives `effect_conquer` with the map, outpost pool and
  `effect_placeOnMap` stubbed in the test subclass, since none of those are modelled by `GameUT`.

## Stage 2 status

Done, `npm run predeploy` green at 320 tests. The civ itself:

- Two CSV rows, `BE_ILLUMINATI_DRAW` (354) and `BE_ILLUMINATI_INCOME` (355), plus a name for row 76,
  `BE_ADVANCE_EXPLORATION_NOBENEFIT`, which is the family the owner's science gain queues. The draw
  row is row 175's shape with `ct => CARD_TAPESTRY` and joins its `case` group, so `arg_keepCard`
  and `effect_keepCard` need nothing.
- `CIV_ILLUMINATI` material entry, `illuminati_dice` global (39), `dice.on_mat` and `dice.mat_owner`
  in `getAllDatas`, and [Illuminati.php](../modules/civs/Illuminati.php).
- Client: `notif_illuminatiDice`, the setup path from `gamedatas.dice`, `.on_civ_mat` (a FontAwesome
  eye in the owner's colour on the die wrapper) and "On the ILLUMINATI mat" in the die tooltip. The
  tooltip build came out of `rolldie` into `updateDieTooltip` so a mask change can refresh a tooltip
  without re-running the roll animation.
- [IlluminatiTest.php](../tests/IlluminatiTest.php), 21 cases over the list above.

Found by the blind review and fixed here rather than in stage 1, where they belonged:

- ALCHEMISTS `alchemistRoll` (adjustment variants 1, 2 and 4) queued its bust benefit with
  `queueBenefitInterrupt` after the science roll, so the roller's consolation jumped the owner's
  gain. Stage 1 only checked `rollAllDice` (variants 8 and 9). Same recipe as rows 324/325:
  interrupt before the roll, queue Normal after. Pinned by
  `testAnAlchemistsBustResolvesAfterTheOwnersGain`, which was confirmed to fail without the fix.
- `notif_conquer_roll` guarded its `gamedatas.dice` writes with `if (die_red)`, dropping face 0,
  which is a real face. Harmless until `updateDieTooltip` started reading that value back.

Rulings are recorded as FORMAL_RULES clauses CIV.ILLUMINATI.1-6. `getTileBenefit` warning on a missing hex
when black face 1 comes up outside a conquer is logged in [TODO.md](TODO.md).

## Studio run, 2 players, FF only

Played through, and it found one bug the tests could not: the eye badge was painting *behind* the
conquer dice. `.die_wrapper` is `transform-style: preserve-3d`, so the `::after` sat at z 0 while
the cube's front face is translated forward; `#science_die` is flat, which is why only that one
looked right. Fixed with `transform: translateZ(40px)` on the badge.

Confirmed in the studio: the mat renders and the card text reads; setup logs the dice and hands the
owner a keepCard of 3; an opponent's research logs roll then take, makes the *owner* active with a
choose-one of "advance on the rolled track (no benefits)" and Decline, and only hands the roller
their research decision afterwards; an opponent's conquer takes both dice and pays both benefits
before the die pick; the owner's own conquer leaves both bits set and queues nothing; income turn 2
with an empty mat scores nothing and refills, income turn 3 with three dice scores 18 VP and
refills; the badges clear and restore per die, the inline colour is cleared with them, and a page
reload restores mask, badges and the "On the ILLUMINATI mat" tooltip line from `getAllDatas`.

## Test infrastructure

- `seedRand` already drives the rolls; `rollConquerDice` consumes red then black.
- `getSelectedMapHex` and `getTileBenefit` are raw SQL over `map`: the test subclass scripts the
  tile benefit, the same seam style as the capital grid.
- Cases: the material entry; setup draws 3 and the keepCard keeps 1 and discards 2, at start and
  mid game, mask 7 afterwards; an opponent's conquer clears black and red and queues both
  benefits ahead of row 141, territory benefit on black 1, a zero face gives a message and no row;
  row 301 rolled by an opponent queues the first face only; an opponent's research clears science
  and queues the optional no-benefit advance for the track rolled ahead of the roller's research
  decision row, Empiricism's second roll ignored and both tracks still offered to the roller;
  Age of Discovery orders owner, roller, then the other players; the tech card research and row
  324 put the owner's row before the roller's; a table without Illuminati keeps today's order on
  every one of those flows; the owner rolling any die leaves the bits alone and queues nothing;
  income turns 2-5
  score 18, 12, 6 and 0 VP for 3, 2, 1 and 0 dice and reset the mask, income turn 1 nothing;
  a finished or zombie owner gains nothing; a table without Illuminati rolls exactly as before
  (the regression guard for the seam); `getAllDatas` carries the mask and owner.

## Client

- `notif_illuminatiDice` (mask, owner) and the same on setup from `gamedatas.dice`: toggle an
  `on_civ_mat` class on the two `.die_wrapper` divs and on `#science_die`, coloured with the
  owner's player colour.
- CSS: `.on_civ_mat::after` is a FontAwesome eye badge in the corner of the die, the
  `.marriage::after` recipe. It sits on the wrapper, not the rotating cube, so the roll animation
  and the board rotation leave it in place.
- `rolldie` adds "On the ILLUMINATI mat" to the die tooltip while the bit is set.
- The keepCard state already renders three drawn tapestry cards for Gamblers; verify it does for
  this row too.
- The owner's pending gains show up in `notif_benefitQueue` for free.

## Inspect

- Once implemented, a blind review of the diff with `game-review-diff` in offline mode; each
  finding fixed, or logged in TODO.md when not.

## Rulings

Recorded in FORMAL_RULES as CIV.ILLUMINATI.1-7.

Implementation notes those clauses deliberately leave out:

- CIV.ILLUMINATI.2 covers row 302 as the tech card research benefit.
- Illuminati against Psionics (a Psionics opponent rolls twice and keeps one): the hook above
  fires once per physical roll, and Psionics decides what a physical roll is. Still open, to be
  recorded with the rest of the Psionics rulings.
