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
- Any civ-local overlay art (Faefolk ring, Genies ring, Weefolk plot token) gets its own class
  next to the existing per-civ CSS. The Celestials map token is a plain player token, no skin.
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

## Per civilization plans

- [Illuminati](FF_PLAN_ILLUMINATI.md) - done.
- [Psionics](FF_PLAN_PSIONICS.md) - planned.
- [Celestials](FF_PLAN_CELESTIALS.md) - stage 1 (engine seams) done, stage 2 planned.
- [Artificers](FF_PLAN_ARTIFICERS.md) - planned.
