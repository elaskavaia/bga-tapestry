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
   the two existing globals, a finishPlayer helper both ends share, and getTapestryEra. Plan in the
   Elder Ones section below.
6. Merfolk. Done. The hidden submerged card zone, and the first civ that takes its own turns in
   extended play. The engine half was four small pieces rather than a turn loop: the extended play
   predicate now hands back the civ instance, `startExtendedTurn` lets that civ run the turn,
   `queueEndOfIncome` puts the cull inside the income turn's undo window, and `moveCardsHidden`
   plus one `getAllDatas` mask is the whole hidden zone. Plan in the Merfolk section below.
7. Illuminati. Global die roll provenance, and an opponent's roll leaving the die off the mat.
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

## Elder Ones

Three effects on one civ: a choose-one gain on income turns 2-4, a tapestry-for-resources trade on
income turn 5, and extended play after income turn 5 (advance turns only, the era 4 tapestry stays in
effect, 10 VP per landmark, the game ends when no advance is affordable). The mat has no token spots,
so nothing on it is clicked and there are no `slots`.

### Shape

- Material: `income_trigger` 2-5, `automa => false` (the bot has no advance turns to extend), no
  `slots`, no mid game setup.
- Turns 2-4 go through the civ ability state the Faefolk way: two `slots_choice` buttons, one queueing
  `BE_ANYRES`, the other `[BE_TAPESTRY, BE_TAPESTRY]`, `decline => false`. A choose-one row cannot
  hold a two-benefit option, and the civ state keeps the ability in the pool a player orders other
  income civs against.
- Turn 5 is not a civ state at all. `queueEraCivAbility` queues a bonus row, the DEMOCRACY shape:
  pay `BE_TAPESTRY`, gain `5,5` per card. The bonus state already offers the hand for selection and
  a Decline button, and `action_acceptBonus` already discards the paid cards and queues the gain once
  per card. Only the cap is missing (see engine work).
- No new benefit rows in the CSV: every gain is an existing type, and the trade is a bonus row.
- The landmark VP is `awardVP(10)` from a new `onGainLandmark` civ hook, guarded by the extended play
  predicate.

### Engine work

The "post income 5 alternate turn loop" from the shared work items turns out not to be a loop: a
player is finished when `player_income_turns` is 6 and `stTransition` already skips finished
players, so a player left at 5 keeps getting turns with no state machine change. What has to be
built is the predicate, the deferred finish and the tapestry fallback.

- `AbsCivilization::hasExtendedPlay()`, false by default, true on ElderOnes. Merfolk sets it too
  later; its turn content is its own problem, this item only keeps the player in the game.
- `isExtendedPlay($player_id)`: era 5, a civ with extended play, and income turn 5 over. The last
  part is the existing `income_turn` global together with `current_player_turn`: an income turn
  only ever happens inside the player's own turn, so era 5 while it is not their turn, or their
  turn without that global, is extended play. No schema change.
- `effect_endOfIncome` at turn 5 for such a player skips final scoring and the era 6 write and
  announces that they play on. The two lines it skips move into a `finishPlayer($player_id)`
  helper (final scoring, era 6, the income notification the client keys on) so the deferred end
  and the normal end are the same code.
- `stPlayerTurn`: today "no affordable advance" auto-takes income. In extended play it calls
  `finishPlayer` and moves to the next player instead. `takeIncome` refuses with `userAssertTrue`
  in extended play and `argPlayerTurn` gains an `extended_play` flag so the client can swap the
  button. An explicit `endGame` action on the same state lets the player stop while an advance is
  still affordable (see rulings).
- `getTapestryEra($player_id)`: 4 in extended play, `getCurrentEra` otherwise. Readers to switch:
  `isTapestryActive`, `playTapestryCard` (both the previous-card lookup and the `era$era`
  destination, so an overplay lands on era 4 and covers the old card into `era_6` as usual),
  `stTapestryCard` for benefit 64 (its "no tapestry in round 5" refusal). The income tapestry (2-4
  only) and "first to era" (below 5) never see era 5 and stay as they are.
- Bonus cap: `benefit_quantity` below -1 on a bonus row is an upper bound (-6 = up to six), -1
  stays unlimited. `action_acceptBonus` asserts the count, `stBonus` skips the row when the player
  holds no card at all (today only a positive count can trigger that skip), the client bonus
  handler reports the cap the way it reports a wrong count.
- Trap: the row queued for the toppled owner is dropped with a message when that owner is in
  extended play. No other response card exists in the code today; a future one gates on the same
  predicate.
- `gainLandmarkTriggers` calls `onGainLandmark($player_id, $landmark_type)` on each civ of the
  player. Utilitarians stays inline for now; moving it there is a cleanup for another day.
  Assumption to verify while implementing: every landmark gain funnels through that function.
- Nothing to do for finished-player guards: `checkAliveForBenefit`, `queueBonus`, `getPlayersInGame`,
  `getGameProgression`, `actionEliminate` and the Genies and Weefolk eligibility checks all read
  era 5 as "still playing", which is right.
- Zombie in extended play: `isPlayerFinished` already treats a quitter as finished, so they are
  skipped like any quitter and their final scoring runs where a quitter's does today.

### Test infrastructure

- Per-player eras the WeefolkUT way, plus the two globals above driven through the stub
  `setGameStateValue`.
- `getLatestTapestry` and `getTapestryOn` are raw SQL. Give `GameUT` in-memory versions over the
  card model so `isTapestryActive` and the overplay path run for real instead of being stubbed as
  WeefolkUT does.
- `getPossibleAdvances` and `finalGameScoring` read `playerextra` with SQL: the test subclass
  scripts the first and records the second, the same seam style as the capital grid.
- Cases: both turn 2-4 buttons; turn 5 paying 0, 3 and 6 cards, 7 refused, two resource rows per
  card, empty hand skipped; after income 5 the era stays 5 and final scoring has not run; a turn
  with an affordable advance continues, one without finishes the player exactly once; income
  refused; trap dropped; landmark 10 VP only in extended play, not during income turn 5, not
  before; the era 4 THIS ERA card inactive during income turn 5 and active in extended play;
  overplay in extended play lands on era 4 and covers the old card; the game ends once the last
  extended player finishes; Elder Ones and a finished opponent share a table.

### Client

- `playerTurn` buttons: with `extended_play` set, the Income button becomes "End my game" with a
  confirmation dialog, red.
- Turn 5 uses the bonus UI unchanged apart from the cap message.
- `updateCurrentEra` finds no slot 5 and simply drops the highlight, which is what happens for
  everyone at income 5 today. Keeping era 4 lit during extended play is polish, not required.
- The finished panel state keys on the income notification with turn 6, which `finishPlayer` sends,
  so the player greys out when they end and not before.

### Inspect

- after done impl launch blind agent using `game-review-diff` in offline mode to code inspect, review and fix or log finding (not implemented finding log into TODO.md)

### Rulings

Recorded in FORMAL_RULES 5.13 to 5.17:

- The era 4 tapestry is inactive during income turn 5 itself (1.3 applies as for everyone) and
  active again from the end of income turn 5 until the player's game ends. The predicate gives
  exactly this reading for free.
- "No longer able to take an advance turn" is judged at the start of the player's turn by the same
  affordability test that auto-triggers income today. Effects that block a track (THEOCRACY,
  DICTATORSHIP, BROKER OF PEACE) are not consulted, as they are not today.
- Ending is also voluntary: "may take advance turns" reads as optional, and a player who would
  rather stop than spend down their tie-break resources gets the button. Ending is final, the way
  income turn 5 is final after its confirm row.
- Every landmark gain after income turn 5 scores the 10 VP, whatever gave it and whoever's turn it
  is (a SOCIALISM push counts), track landmarks, district landmarks and landmark cards alike. A
  landmark gained during income turn 5 does not.
- An extended play player is still in the game for opponent-facing prompts: they can answer a
  Genies wish and receive a Weefolk token.
- Income turn 5 itself is unchanged: VP income, achievement VP and the confirm row all happen; only
  final scoring and the era 6 write are deferred to the end, so a second civ with final scoring
  (Islanders, Riverfolk) scores when the Elder Ones player actually stops.

## Merfolk

Three effects on one civ: on income turns 2-4 gain a tapestry card and submerge all but 2 of the
hand under the mat (hidden, unusable), on income turn 5 gain a card, surface everything and at the
end of that turn cull the hand to 5, and extended play afterwards where every turn is either a
discard for 5 VP each or a play onto the era 4 stack, until the hand is empty. Traps stay playable.
The mat has no token spots, so no `slots`.

### Shape

- Material: `income_trigger` 2-5 with `decline => false`, `automa => false`, no `slots`, no mid
  game setup: a civ gained mid game starts submerging at its next income turn in range.
- Three new CSV rows, all `civ => CIV_MERFOLK`, resolved in `awardBenefits` with no interaction
  of their own, the Werefolk pattern: the row does the deterministic part and queues a prompt only
  when there is something to choose. Dive, Surface and Cull are code names only. Row names,
  buttons and log lines use the card's own words: "submerged" is the card's term, the rest is
  "place all but 2 under this mat", "return the submerged tapestry cards to your hand" and "keep
  up to 5 tapestry cards".
  - `BE_MERFOLK_DIVE` (turns 2-4): `awardCard` one tapestry, a real gain so ACADEMIA style
    triggers fire, then if the hand holds more than 2, interrupt with the civ row in phase
    `submerge`.
  - `BE_MERFOLK_SURFACE` (turn 5): gain one tapestry, then move every `submerged` card back to
    `hand` with `effect_moveCard`, a return rather than a gain, so no trigger fires.
  - `BE_MERFOLK_CULL` (end of turn 5): if the hand holds more than 5, interrupt with the civ row
    in phase `keep`.
- One civ row, three phases in benefit_data the Weefolk way (`submerge`, `keep`, `turn`), all
  answered in the civ ability state Historians style: the owner selects cards in their hand and
  the ids travel comma separated in the `extra` argument of `moveCivCube`.
  - `submerge`: select the 2 cards to keep, the rest go to `submerged`.
  - `keep`: select the 5 cards to keep, the rest are discarded.
  - `turn`: two buttons. "Discard selected cards" awards 5 VP per card, at least one. "Play a
    tapestry" needs no selection: it queues benefit 64, the existing overplay, which lands on era
    4 through `getTapestryEra` and covers the old card as usual, and the player picks the card in
    the play tapestry state. The button is offered only when an era 4 card exists to cover.
- Submerged cards are plain tapestry rows in location `submerged` with the owner in
  `card_location_arg`. Nothing that reads the hand sees them, which is the whole rule: hand
  counts, bonus payments, reveal-hand, the Faefolk visible count.

### Engine work

Two pieces: a hook that lets a civ take over the turn in extended play, and the hidden zone from
the shared work items. Extended play is the period after income turn 5 for a player whose civ
answers `hasExtendedPlay()`: `player_income_turns` stays 5, `isExtendedPlay` reads that together
with the `current_player_turn` and `income_turn` globals, `stTransition` keeps handing the player
turns, and `finishPlayer` (final scoring, then the era 6 write) is what ends their game.

- `AbsCivilization::startExtendedTurn($player_id): bool`, default false. `stPlayerTurn` asks it
  right after the first turn check, before the lighthouse and activated ability checks, when the
  player is in extended play; true means the civ took the turn over and the state moves on.
  Merfolk: an empty hand ends the game through `endExtendedPlay`, otherwise it queues the civ row
  in phase `turn` and transitions to the benefit manager the way `takeIncomeAuto` does. A civ on
  the default keeps the ordinary advance turn.
- `queueTrapResponse` today drops the response row for any player in extended play. It gates on
  the civ instead: `AbsCivilization::playsResponseCards()`, true by default and on Merfolk, false
  on ElderOnes. `isExtendedPlay` gets a sibling that returns the civ instance so the trap gate and
  the turn hook do not each walk the civ list.
- An end of income hook: `queueIncomeTurn` calls `queueEndOfIncome($player_id, $incomeTurn)` on
  each civ between the VP income row and the confirm row, so the cull stays inside the undo window
  of the income turn. Merfolk queues `BE_MERFOLK_CULL` there at turn 5.
- Hidden zone. `awardCard` already shows the shape: the owner gets the cards on a private
  notification, everyone else a public one with the cards stripped. Submerge and surface go
  through one `moveCardsHidden` helper doing the same, the public half carrying ids and count
  only. `getAllDatas` masks `card_type_arg` to 0 on another player's `submerged` rows, which the
  client already renders as the FACE DOWN CARD. A `submerged` entry joins the `tapestry` hand
  counter in the per-player counters.
- Nothing to do for the finish: `effect_endOfIncome` already defers `finishPlayer` for a civ with
  extended play, `getTapestryEra` already answers era 4 in extended play so the play lands on the
  right stack, and the finished-player guards (`checkAliveForBenefit`, `getPlayersInGame`, the
  Genies and Weefolk eligibility checks) read era 5 as still playing. The empty hand is the only
  new end condition and it lives in the civ.
- Not supported: two extended play civs on one player. The first civ found decides, and a
  systemAssert says so.

### Test infrastructure

- `effect_moveCard` is raw SQL and gets an in-memory version in `GameUT` over the card model, as
  `getLatestTapestry`, `awardCard` and `effect_discardCard` already have.
- Per-player eras (`getCurrentEra` by player id) and the `current_player_turn` and `income_turn`
  globals are driven from the test. ElderOnesUT carries those overrides today; they move into
  GameUT now that a second test wants them.
- Cases: turn 2-4 with 5 cards keeps 2 and submerges 3, with 2 or fewer no prompt; submerged cards
  invisible to the hand count, a bonus payment and reveal-hand; turn 5 gains, surfaces, then the
  cull with 7 cards keeps 5 and with 5 has no prompt; after income 5 the era stays 5 and final
  scoring has not run; an extended turn discarding 3 scores 15 VP, zero selected refused; the play
  button queues 64 and is absent without an era 4 card; an empty hand at turn start finishes the
  player exactly once; a trap is still offered to a Merfolk defender and its discard can empty the
  hand; Merfolk and Elder Ones at one table both in extended play, the game ending once the last
  finishes.

### Client

- A `submerged_cards_{X}` div next to `tapestry_cards_{X}` in the template, hidden while empty.
  The owner's is a stock like the hand; an opponent's gets the `tapestry_deck` back and a counter,
  exactly how their hand is drawn today. Built as the mask only: the face down cards are the
  count, so no per-player submerged counter was added.
- `getCardDivLocatonId` maps `submerged` to that div.
- `case CIV_MERFOLK` in `onUpdateActionButtons_civAbility`: the hand becomes multi selectable in
  every phase with a description per phase, and the button handler puts the selected ids into
  `clientStateArgs.extra`, the Weefolk build shape with a list instead of one tile.
- One handler for the hidden moves, keyed on whether the cards carry a type: faces for the owner,
  backs and a counter for everyone else.

### Inspect

- Once implemented, a blind review of the diff with `game-review-diff` in offline mode; each
  finding fixed, or logged in TODO.md when not.

### Rulings

Proposed, to be recorded in FORMAL_RULES

- Submerge means the player picks the 2 cards to keep; a hand of 2 or fewer has nothing to
  submerge and gets no prompt. Submerged cards are not in hand for anything: counts, payments,
  reveal-hand effects, opponents' effects that read a hand, and a Faefolk count on the same player.
- Returning the submerged cards at income 5 is not gaining them, so no "whenever you gain a
  tapestry" trigger fires. The single card drawn on each of turns 2-5 is a gain.
- "Keep up to 5" is answered as exactly 5: discarding more gains nothing in extended play, where
  every card is worth at least 5 VP, so the prompt asks for the 5 to keep and is skipped at 5 or
  fewer.
- An extended turn is mandatory: at least one card is discarded when discarding, and there is no
  pass. A free pass would let a player stall the table indefinitely.
- Any card can be played in extended play. A WHEN PLAYED effect applies, and a THIS ERA card played
  there is in effect for the rest of the player's game, since era 4 stays the active tapestry slot
  in extended play. "ERA 5 effects" and "left-hand charm bonuses" belong to Fantasies & Futures
  tapestry cards that are not in the code; nothing to do until those cards land.
- The game ends at the start of a Merfolk turn with an empty hand, not the moment the last card
  leaves. A trap played in defence counts: it comes out of the hand.
- Merfolk keeps trap and other response cards in extended play; the card says so explicitly.
- Income turn 5 is otherwise unchanged: VP income, achievement VP and the confirm row all happen.
  Only final scoring and the era 6 write are deferred to the end, so a second civ with final
  scoring (Islanders, Riverfolk) scores when the Merfolk player actually stops.
