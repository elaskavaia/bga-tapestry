# Fantasies & Futures - implementation plan

Scope: the 10 civilizations transcribed in [FF_CIVILIZATIONS.md](FF_CIVILIZATIONS.md) - Artificers,
Celestials, Elder Ones, Faefolk, Genies, Illuminati, Merfolk, Psionics, Weefolk, Werefolk.

This is a high level plan: what has to be built, in what order, and what has to be true before each
piece is considered done. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).

## Classification

The work splits into three bands, and the band decides how much of the plan below applies.

Fits the existing `AbsCivilization` hooks:

- Faefolk - token walking a 7 spot ring, structurally Alchemists with different topology.
- Werefolk - random bit (the coin flip), then regress or advance.

Needs new cross-player interactive states, precedent in Advisors and Historians:

- Genies - random opponent picks a circled benefit, you mirror it plus an adjacent squared one.
- Weefolk - your token occupies a plot in an opponent's capital and counts as filled, then row and
  column scoring at income 5.

Genies, Illuminati and Weefolk all need a live opponent to point at, so all three are kept out of
solo games with `"automa" => false` in material - the civ pool in `setupNewGameTables` already drops
any civ carrying that flag. Ruled by Victoria.

Needs engine changes beyond the civ hooks:

- Elder Ones and Merfolk - both keep taking turns after income turn 5 while everyone else is
  finished. This is the end of game state machine, not a civilization. Built once, shared by both.
  Merfolk additionally needs a hidden "submerged" card zone.
- Psionics - hooks every random draw in the game (die, tech, tapestry, landmark, territory, space
  tile, civilization) with draw two keep one, and must add rather than multiply with Empiricism.
- Illuminati - hooks every die roll globally with "whose mat did this die come from", plus the rule
  that an opponent's roll leaves the die off the mat until your next income turn.
- Artificers - mutates the income track building layout mid game (set aside, slide left). Touches
  income resolution and the income mat UI.
- Celestials - a player token replaces an outpost, moves between territories, blocks conquest
  without taking control, plus "landmark hangs off the side of the capital" scoring.

## One time infrastructure

Done once, before the first civ lands.

- `CIV_*` constants for the ten new civs in [material.inc.php](../material.inc.php), in a fresh
  numeric block that does not collide with the AA (21-25) and PP (30-39) ranges. Assign them 40-49
  in alphabetical order, so the constant minus 40 is the civ's index in the sprite and no lookup
  table is needed.
- New `exp` tag (`FF`) on every FF civ entry, so `tapestry.js` puts `exp_FF` on the mat div and CSS
  can point the whole pack at its own sprite.
- `EXP_FF_FLAG` (`0b1000`) next to the existing expansion flags, plus the `FF` case in
  `isExpansionIncluded()`. No new filtering code is needed: the civ pool in `setupNewGame` already
  drops any civ whose `exp` flag is not in the selected set.
- New values on the existing "Civilization Set" option (140) in
  [gameoptions.inc.php](../gameoptions.inc.php) rather than a separate option, since it is already
  the bitmask that picks which packs are in play:
  - `0b1001` Original + Fantasies & Futures.
  - `0b1000` Fantasies & Futures only. Testing only, marked `beta`, and removed before the pack
    ships - it exists so the ten new civs can be exercised without base civs diluting the draw.
    It is the first value ever to exclude the base civs, so expect to find code that assumes one is
    always available (Automa civ exclusions, the adjustments-9 Alchemists insert, the "not enough
    civs for this player count" path). Fix what it finds, since those assumptions are worth
    removing anyway, but do not let the shipped pack depend on this value.
  - `0b1111` All, extending the existing All value.
- Default stays `0b001`, and every existing value keeps its current meaning, so tables created
  without the pack are unaffected.
- `doAdjustMaterial($players, $variant)` gets FF entries only if a civ needs a player-count or
  adjustment twin. The pack itself is gated by the flag above, not by the adjustment level.
- Art is in place: `img/exp_ff/civ_ff.webp`, 2510x1552, a 5 wide by 2 tall sprite in alphabetical
  order (Artificers, Celestials, Elder Ones, Faefolk, Genies on the top row; Illuminati, Merfolk,
  Psionics, Weefolk, Werefolk on the bottom). Tile is 502x776, close enough to the 216x330 mat box
  that no aspect fix is needed. webp is used for size; keep the rest of the pack in webp too.
- CSS block for `.civilization.exp_FF` and the ten `.civilization_4N` positions: done, in
  [tapestry.css](../tapestry.css). `background-size: 500% auto` as the `exp_pp` block does - that
  sprite is also 5x2 with the same tile aspect, so `auto` lands the two rows correctly.
- A shared test helper that sets up a game with the FF pack enabled, so per-civ tests do not each
  re-derive the option plumbing.

## Per civilization pipeline

Every civ goes through these six steps regardless of band. The engine band adds work under "server",
not new steps.

### 1. Graphics and CSS

- Nothing to do for the mat itself: `civ_ff.webp` and all ten `.civilization_4N` position rules are
  already in place.
- Any civ-local overlay art (Faefolk ring, Genies ring, Weefolk plot token, Celestials map token)
  gets its own class next to the existing per-civ CSS.
- Tokens placed on a mat are positioned by the `slots` percentages, not by CSS offsets - CSS only
  has to size and skin them.

### 2. Material

- `civilizations` entry: `name`, `description` (each rules sentence its own `clienttranslate`),
  `exp`, `income_trigger`, `adjustment` if the pack changes its setup.
- `slots` with `top`/`left`/`w`/`h` percentages tuned against the art, and `benefit` on each slot
  pointing at a `benefit_types.csv` row.
- New benefit rows go in [benefit_types.csv](benefit_types.csv) and come back through
  `npm run genmat` plus `npx prettier --write material.inc.php`. Never hand-edit the generated
  block.
- Anything the client shows as a name or icon needs its notification argument helper
  (`notifArgsAdd*`) available, so the log stays translatable.

### 3. Server (PHP)

- One class per civ in [modules/civs/](../modules/civs/) extending `AbsCivilization`, named after
  the civ's material `name` so `getCivilizationInstance()` resolves it with no registry change.
  Trivial civs may stay on `BasicCivilization`.
- Prefer the existing override points: `awardBenefits`, `moveCivCube`, `argCivAbilitySingle`,
  `setupCiv`, `finalScoring`, `hasActivatedAbilities`, `triggerPreGainBenefit`, `queueEraCivAbility`.
- Everything a civ grants or costs goes on the benefit stack via `queueBenefitStandardOne`,
  `queueBenefitNormal`, `queueBenefitInterrupt` or `effect_onQueueBenefit`, with a `reason()` string
  so the client can explain the effect. No civ resolves resources directly.
- Interactive choices become a state that `stBenefitManager` transitions into and returns from.
  Cross-player choices (Genies, Weefolk) follow the Advisors and Historians pattern: an active
  player that is not the turn player, and a guaranteed return path.
- Rule violations use `userAssertTrue`, internal invariants `systemAssertTrue("ERR:Class:NN", ...)`
  with a fresh code block per new class.

### 4. Server tests

- One test file per civ in [tests/](../tests/), named after the civ (e.g. `FaefolkTest.php`), never
  after a bug number. Bug numbers belong in docblocks.
- Every test calls `doAdjustMaterial` with the FF variant before asserting on civ data.
- Minimum coverage per civ: setup, the ability firing at each income turn in its `income_trigger`
  range, every branch of every choice, the "nothing legal to do" path, and end of game scoring.
- Randomness (Werefolk coin, Psionics draws, Illuminati dice) must be injectable or seeded so the
  branches are testable without flakiness. If the current stubs cannot do that, making them able to
  is part of the Psionics/Illuminati engine item, not a per-civ afterthought.
- `npm run predeploy` (php lint plus the full suite) is the gate for every civ.

### 5. Client hooks

- Most civs need nothing: the mat, its slots and the benefit stack render generically.
- A civ that adds a state needs `onUpdateActionButtons_<stateName>` in
  [tapestry.js](../tapestry.js), and a `notif_*` handler for any new notification type.
- Civ-local UI beyond a token on a slot: Weefolk (token inside an opponent's capital grid),
  Celestials (token on the map where an outpost would be), Artificers (income mat re-layout mid
  game), Merfolk (a hidden zone whose contents only the owner sees).
- `notif_benefitQueue` already renders pending benefits; new civ effects should show up there for
  free if they are queued properly, which is a useful check that the server side was done right.

### 6. Per civ integration cases

Before a civ is called done, it must be played through in the studio at least once at 2 players and
once at 4, confirming: the mat renders and its tooltip is correct, the ability triggers on the right
income turns, the log text is translated and readable, undo across the ability works, and a zombie
or quit player does not leave the ability half-resolved.

## Shared engine work items

These are built and verified as standalone changes, against existing civilizations, before any new
civ depends on them.

- Post income 5 alternate turn loop. One player continues taking turns after everyone else is
  finished. Affects the end of game state machine, scoring trigger and turn order display. Used by
  Elder Ones and Merfolk.
- Random draw interception. A single seam every random draw goes through, so Psionics can turn it
  into draw two keep one, and so it composes additively with Empiricism.
- Die roll provenance. Every roll carries which player's mat it came from, and a die rolled by an
  opponent stays off the mat until the owner's next income turn.
- Income mat mutation. Buildings can be set aside and the remaining ones slide left, mid game, with
  income resolution and the client mat both respecting the new layout.
- Hidden card zone. A per-player face-down zone that only its owner can see, for Merfolk.
- Non-outpost map token. A player token that occupies a territory, blocks conquest, and does not
  grant control, for Celestials.

Each of these gets its own tests independent of the civ that motivated it.

## Overall integration test cases

Run once the pack is assembled, not per civ.

- Pack off: a full game with the FF flag clear draws no FF civ and behaves identically to today.
  This is the regression guard for the option 140 change.
- FF only (`0b1000`, the testing value): a full game where every player has an FF civ. This is how
  the pack gets exercised without waiting on the draw. Retire the value once the pack ships; the
  cases below are the ones that must still pass afterwards.
- Pack on, mixed table: FF civs against base, AA and PP civs, confirming no civ assumes it is the
  only one modifying a shared subsystem.
- Two players with random-modifying civs at the same table (Psionics against Illuminati, Psionics
  against a base civ that rolls dice), confirming the hooks compose instead of overwriting.
- Elder Ones and Merfolk at the same table, so two players are both in the post income 5 loop.
- Weefolk against Celestials and against Infiltrators, since all three place something in or on
  another player's space.
- Artificers plus any civ that gains income track benefits, confirming the mutated layout scores
  correctly at income and at final scoring.
- Full game to final scoring with every FF civ in play across several games, checking the score
  breakdown adds up and the end of game trigger still fires exactly once.
- Undo across each new interactive state, including cross-player ones.
- Zombie, quit and eliminated player while an FF ability is mid-resolution.
- Reload mid-state: refreshing during each new state restores the same UI.

## Sequencing

Simplest first, so each civ pays for the next one's plumbing. Two deliberate deviations from strict
complexity order are called out below.

1. Faefolk. Done. Cheapest civ, fully specified, and it exercised every step of the per-civ pipeline
   at once, along with the one time pack infrastructure. What it cost is the measured unit for
   everything after it.
2. Werefolk. Done. Confirmed the unit and finished the cheap band. Its coin flip needed seeded
   randomness in the test harness, which landed first as an overridable bgaRand and is also the
   first piece of what Psionics and Illuminati will need later.
3. Genies. Done. First cross-player ability and cheaper than expected: a benefit row owned by
   another player already makes that player active, so the drawn opponent answers a plain
   choose-one row and no new state was needed. What it did need was two engine hooks, an
   opponent-benefit intercept in effect_onQueueBenefit and a zombie hook so a quitter cannot
   swallow someone else's ability. Two benefit rows, one civ class, the opponents' tokens as cubes
   on the mat, one CSS rule.
4. Weefolk. Done. Same cross-player machinery as Genies plus a token living in an opponent's capital
   grid and row and column scoring at income 5. The engine work was the bigger half: the capital grid
   got a writer seam so tests can drive placement at all, the mask walk became one helper, and the
   placement paths learned that a cube belonging to someone else is not a building of the capital's
   owner.
5. Elder Ones. Done. Special handling of era 5, fall back on era 4 tapestry slot, and special
   handling of "income" during that (end the game, no extra income). The shared end of game work
   turned out to be three small pieces rather than a turn loop: the extended play predicate over
   the two existing globals, a finishPlayer helper both ends share, and getTapestryEra.
6. Merfolk. Done. The hidden submerged card zone, and the first civ that takes its own turns in
   extended play. The engine half was four small pieces rather than a turn loop: the extended play
   predicate now hands back the civ instance, `startExtendedTurn` lets that civ run the turn,
   `queueEndOfIncome` puts the cull inside the income turn's undo window, and `moveCardsHidden`
   plus one `getAllDatas` mask is the whole hidden zone.
7. Illuminati. We need to track if die "on mat" or not, but it does not need to be physically there
   in UX, can just have some overlays on dice itself (similar to what we do when we mark marriage of
   state cube). We need die roll interceptor but after first roll on mat flag is cleared, so all
   subsequent re-rolls are normal until civ owner gets it back. Plan in the Illuminati section below.
8. Psionics. Draw two keep one on every random source in the game, composing additively with
   Empiricism. Out of complexity order deliberately: it is the harder of the two random hooks, but
   doing it straight after Illuminati means one seam gets designed once instead of twice.
9. Celestials. A player token on the map that blocks conquest without granting control, plus
   landmark scoring off the side of the capital.
10. Artificers. Last despite not being the most complex civ logic, because it mutates the income
    mat, which is shared UI that every other civilization renders.

## Risks

- The post income 5 turn loop is the single largest unknown. It affects the end of game state
  machine, which every other civilization also depends on. Regression risk is high.
- Psionics and Illuminati are cross cutting. Every future random source and every future die roll
  must remember they exist. This is an ongoing tax on all later work, not a one time cost.
- Artificers changes the income mat, which is shared UI, not civ local.
- Cross civ interactions are combinatorial. Ten new civs against the existing set is where the
  integration testing goes.
- The real BGA framework is not available locally, only the stubs in `bga-sharedcode`, so a share of
  the verification only happens in the studio.
- Existing civilizations still receive bug fixes one to two years after shipping (Infiltrators was
  last touched in February 2026). Expect the same tail here.

## Resolved blockers

- The Faefolk ellipse and the Genies ring are transcribed. Every icon maps to an existing benefit
  type in `benefit_types.csv`. The only new row appears to be Faefolk's "score any building", which
  is a choose one over the existing farm, armory, house and market VP benefits.
- Art is available, so `slots` coordinate tuning is unblocked.

## Illuminati

Four effects on one civ: a draw 3 keep 1 tapestry at setup, a gain whenever an opponent takes one
of the three dice from the mat (both conquer die benefits, or an optional no-benefit no-bonus
advance on the science track rolled), the taken die staying off the mat until the owner's next
income turn, and 6 VP per die still on the mat at the start of income turns 2-5, after which all
three come back. The mat has no token spots, so no `slots`. The dice are never physically on the
mat in the UI: each die carries an overlay while it is "on the mat", the MARRIAGE OF STATE marker
style.

### Shape

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

### Engine work

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

#### Stage 1 status

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

#### Stage 2 status

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

Rulings are recorded as FORMAL_RULES clauses 5.24-5.29. `getTileBenefit` warning on a missing hex
when black face 1 comes up outside a conquer is logged in [TODO.md](TODO.md).

#### Studio run, 2 players, FF only

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

### Test infrastructure

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

### Client

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

### Inspect

- Once implemented, a blind review of the diff with `game-review-diff` in offline mode; each
  finding fixed, or logged in TODO.md when not.

### Rulings

Proposed, to be recorded in FORMAL_RULES

- The card's conquer dice sentence is read as this amended text, ruled by Victoria from the
  publisher's answer (Joe of Stonemaier Games on their Discord, relayed by Alex S in BGG thread
  3245586 "Treasure Hunters vs Illuminati"): "When an opponent takes any of the three dice from
  your mat, you gain the benefit rolled on that die (ignoring any rerolls). If multiple dice are
  taken at the same time, you get the benefit of each die." So dice are taken one at a time, an
  opponent rolling only the black die takes only the black die, a conquer takes both, and a die
  leaves the mat on its first roll: every later roll of it, including Empiricism's second roll,
  is an ordinary roll with no gain until the owner's next income turn returns the die.
- The science die is taken by any science roll from the mat, including one a tapestry card
  triggers (Mike Young, designer, BGG thread 3058558 "Illuminati - Chimera"): research, Age of
  Discovery, the tech card research benefit, row 302.
- The conquer die gain is what the roller would gain from that face: black face 1 is the benefit
  of the territory the roller is conquering, a zero-effect face gives nothing.
- The science die gain is an optional single-step advance on the track rolled with no spot
  benefit and no bonus; a track landmark reached this way is still gained, as on every other
  no-benefit advance in the game.
- The owner resolves their gain from a taken die before the roller does anything with the roll,
  on every roll: before the die pick on a conquer, before the research decision, before the
  roller's advance on Age of Discovery and the tech card research, before a keep-or-reroll
  choice. Ruled by Victoria from the card's "immediately after they take the die". A race to the
  same track landmark between owner and roller therefore goes to the owner.
- The owner rolling a die from the mat, on any turn and for any effect, leaves it on the mat.
- A finished or zombie owner gains nothing from a taken die, and their dice never return.
- The income ability scores the dice on the mat at the start of the income turn. A civ gained in
  the civ phase of an income turn 2-5 fires in that same turn with all three dice on the mat,
  the engine's rule for every income civ gained there.
- Illuminati against Psionics (a Psionics opponent rolls twice and keeps one): decided in the
  Psionics section, the hook above fires once per physical roll and Psionics decides what a
  physical roll is.
