# Psionics - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).
The die half shares the engine seam that [Illuminati](FF_PLAN_ILLUMINATI.md) built, so this plan
reuses its vocabulary: `rollDieFace`, `dieRolled`, "interrupt before the roll, queue Normal after".

Status: planned, not started.

Psionics samples nearby realities. Every random thing the owner gains gets one extra option and the
owner keeps one: a die is rolled twice and one value is chosen, a card gained at random from a deck
is drawn two and one is kept while the other goes back. The effect ADDS rather than multiplies with
anything else that already hands out extra options, so EMPIRICISM plus Psionics is three science
rolls, not four, and a draw-3-keep-1 row becomes draw 4 keep 1. It does not apply to a face-up
option, nor to merely revealing a card. On top of that the mat pays a fixed income table: income
turn 2 a territory tile, 3 a tapestry card, 4 a technology, 5 a research. Those four gains are
themselves random gains, so the civ doubles its own income.

The important consequence for the codebase: unlike Illuminati, which reacts to a roll after the
fact, Psionics changes what the roll or the draw PRODUCES. Every consumer of a die face and every
random draw site has to learn that there may be more than one candidate. That is why the whole of
stage 1 is engine work with no civ in sight.

## Shape

- Material entry for `CIV_PSIONICS` (47, already defined in [material.inc.php](../material.inc.php),
  and `.civilization_47` is already in [tapestry.css](../tapestry.css)):
  `exp => "FF"`, `income_trigger => ["from" => 2, "to" => 5, "decline" => false]`, no `slots` (the
  income table on the art is a printed table, not token spots), no `midgame_setup` (the civ has no
  setup step at all: gaining it mid game simply turns the hooks on).
- `automa => false` proposed. The Automa never chooses, and every Psionics effect is a choice; a bot
  holding it would have to be given a keep policy for six different decks. It is a one word material
  flag, so it can be revisited once the pack ships. See open questions.
- New `benefit_types.csv` rows, all copies of the existing draw/keep shape that
  `case 172 / 175 / BE_ILLUMINATI_DRAW` in `awardBenefits` already resolves
  (`'icon' => 'no','tt'=>'card','ct'=>CARD_X,'draw'=>2,'keep'=>1`):
  - `BE_PSIONICS_TERRITORY` (`ct => CARD_TERRITORY`)
  - `BE_PSIONICS_TAPESTRY` (`ct => CARD_TAPESTRY`)
  - `BE_PSIONICS_TECH` (`ct => CARD_TECHNOLOGY`)
  - `BE_PSIONICS_SPACE` (`ct => CARD_SPACE`)
  - `BE_PSIONICS_CIV` (`ct => CARD_CIVILIZATION`) - this one must join the `$ben != 172` branch of
    `effect_keepCard`, because a kept civilization card stays in `draw` rather than moving to hand.
  No income row is needed: `queueEraCivAbility` maps the turn straight onto `BE_TERRITORY` (6),
  `BE_TAPESTRY` (7), the technology row and `BE_RESEARCH` (18), and those rows then get intercepted
  by the civ's own hook like any other random gain. Which technology row income turn 4 means is an
  open question below.
- New globals, in the same 10-89 block, after `illuminati_dice` (39):
  - `science_die_psionics` (40) - the third science option, exactly parallel to the existing
    `science_die` (12) and `science_die_empiricism` (27). Chosen over redesigning the science
    options into a list because those two globals are read in only three places and a third named
    global is the smallest change that keeps EMPIRICISM working; a fourth source would force the
    redesign, and that is called out as a risk.
  - `conquer_die_red_2` (41) and `conquer_die_black_2` (42) - the second face of each conquer die,
    parallel to `conquer_die_red` (10) and `conquer_die_black` (11). Zero means "no second face",
    which is the framework default, so a table with no Psionics never writes them.
  Lifetime is the bug magnet here (CODE_STYLE): all three are written by the roll that produced them
  and must be cleared by every roll that does not, in the same function, never "prepared early".
- Client visible state: `getAllDatas` gains `dice.psionics`, `dice.red2` and `dice.black2` next to
  the existing `dice.empiricism`; the roll notifications carry the second face; the research state
  args carry the third track. No new state: every choice Psionics creates is either an existing
  `keepCard` state or a plain choose-one benefit row rendered by the benefit machinery.

## Stage 1 - engine seam and timing

Everything here lands with tests and no civ class, and must provably change nothing at a table with
no Psionics. Two independent seams, because dice and cards go through different machinery.

### Seam A, the die

`rollDieFace(string $die): int` at [PGameXBody.php:12294](../modules/PGameXBody.php) already is the
single place every die value in the game comes from. It gains a sibling:

- `rollDieFaces(string $die, int $player_id): array` - returns `[$face]` today, and
  `[$face1, $face2]` when `hasCiv($player_id, CIV_PSIONICS)`. One `bgaRand` per element, so seeded
  tests stay readable. Every current caller of `rollDieFace` moves to it.

The four roll functions keep their current return value (the first face) and additionally record the
second face where the flow can reach it:

- `rollConquerDice($player_id)` (12316) - writes `conquer_die_red_2` and `conquer_die_black_2`, or
  zeroes them. Its single two-die notification gains two optional face args.
- `rollRedConquerDie($player_id, $undosave)` (12337) and `rollBlackConquerDie` (12353) - same, for
  their die only. The black one already builds a territory-benefit name for face 1; with two faces
  it has to name both.
- `rollScienceDie($data, $dievar, $player_id, $undosave)` (3459) - writes only `$dievar`, as today,
  and returns the array. The callers decide where a second face goes, because the science die has
  four different consumers.

`dieRolled($die, $face, $roller_id)` (12310) keeps firing exactly once per call site, with the FIRST
face. That is the Illuminati composition, and it is a ruling, not an accident: see rulings.

Then, per consuming flow. These are the flows I verified exist; each needs its own work because the
seam only produces faces, it does not know what they mean:

- `research()` (3434) and `rollScienceDie2($reason)` (3447). `rollScienceDie2` rolls once, then once
  more for EMPIRICISM when `isTapestryActive($player_id, TAP_EMPIRICISM)`. It gains a third arm:
  once more for Psionics, into `science_die_psionics`. This is where additivity is implemented and
  where it is most visible - one extra roll per decision, not one per physical roll, which is
  exactly the card's "a total of 3 times (not 4)" example. `argResearch()` (9718) and
  `action_research_decision($track, $spot)` (7773) each read the two globals today and both learn
  the third; the error message they build for an illegal track has to list up to three tracks.
- Row 302, "roll the research die twice and gain one benefit of your choice" (1982). Rolls twice and
  queues `["or" => [21 + $b1, 21 + $b2]]`. Additively that becomes three rolls and an `or` of three.
  Local change in the case.
- Rows 325 and 332 (2059, 2105). 325 interrupts, rolls, and queues 332, which reads `science_die`
  back. With a second face, 325 queues `["or" => [21 + $f1, 21 + $f2]]` directly instead of 332.
- Rows 324 and 330 (2046, 2096). Same recipe on the black die: 324 queues an `or` of the benefits of
  both faces instead of 330.
- Row 301, "roll the black conquer die twice and gain one benefit of your choice" (1958). Already
  an extra-option effect, so additively three rolls and an `or` of up to three; the existing
  "this die roll results in no benefit" message per empty face stays.
- Rows 303 and 304 (1992, 2002), "gain both benefits". These are two independent rolls, each of
  which is gained, so each becomes roll-twice-keep-one and two `or` rows are queued. This is the
  case where Psionics genuinely doubles the number of rolls, because there is no shared option set
  to add to.
- `conquer()` and `effect_endOfConquer($player_id)` (12223), reached by row 141. The roller picks
  one die's benefit unless `conquer_bonus` or PILLAGE AND PLUNDER makes it both. `getConquerDieBenefit`
  (7761) reads one global; it gains `getConquerDieBenefitOptions(string $die): array` returning one
  or two benefits. Proposed, and this is the cheapest option I found: for a Psionics roller in the
  single-die case, skip the `conquer_die` pick state entirely and queue one `or` over all four
  candidate benefits, because "choose a face per die, then choose a die" and "choose one of the four
  (die, face) pairs" have identical outcomes. The both-dice path cannot collapse and queues one `or`
  per die. Flagged as the costliest and least certain item in seam A.
- `Alchemists::rollAllDice` and `Alchemists::alchemistRoll` ([Alchemists.php](../modules/civs/Alchemists.php),
  adjustment variants 1, 2, 4, 8 and 9). Roll all remaining dice, keep one, reroll the rest. Not
  resolved here: see rulings and open questions.
- `queueBenefitAutomaSingle` case `"r"` - the last roll-then-advance-inline site, Automa only, and
  Illuminati already left it alone for the same reason (`automa => false` keeps the two apart).
  Nothing to do unless Psionics ships with `automa => true`.

### Seam B, the card

`effect_onQueueBenefit($ben, $player_id, $reason, $count)` (5233) is the seam. It already carries two
precedents for exactly this shape: the Advisors arm on `BE_TAPESTRY` (5245) and, better still,
`case 65 BE_GAIN_CIV` in `awardBenefits` (1654), which for an Infiltrators owner queues row 172
(draw 3 keep 1) with `queueBenefitInterrupt` instead of calling `awardCard`. Psionics is that same
substitution, generalised.

- A Psionics arm in `effect_onQueueBenefit` returns false for a random-card row owned by a Psionics
  player and queues the matching `BE_PSIONICS_*` row instead. The predicate is the row's own
  material, not a hardcoded list: `getRulesBenefit($ben, "ct")` naming a deck card type plus
  `tt == "card"`.
- Rows that already carry `draw` and `keep` (172, 175, `BE_GAMBLES_PICK` 311, `BE_GAMBLES_PICK_2`
  319, `BE_ILLUMINATI_DRAW` 354) are NOT re-queued. Psionics adds one to the `draw` count where the
  row is resolved, so draw 3 keep 1 becomes draw 4 keep 1. That is the additive rule again, and it
  is why the count must be read through a helper rather than straight off `getRulesBenefit`, e.g.
  `getDrawCount(int $ben, int $player_id): int`.
- `awardCard($player_id, $count, $card_type, ...)` (2465) is deliberately NOT the seam, even though
  it is the funnel every deck draw goes through. It returns the drawn cards and a dozen callers use
  the return value immediately; a keep-one choice is interactive and cannot return a card. Anything
  that must become a choice has to be a queued row, not a wrapped `awardCard`.

### Every random source the card names

For each: what produces it today, and whether a seam covers it.

- Die, black and red. Produced by `rollDieFace` via `rollConquerDice`, `rollRedConquerDie`,
  `rollBlackConquerDie`. Seam A produces the extra face; each of the eight consuming flows listed
  above needs its own work.
- Die, science. Produced by `rollDieFace` via `rollScienceDie`. Same: seam A plus per-flow work in
  `rollScienceDie2`, rows 302, 325 and the Alchemists loop.
- Technology, from the deck. `BE_TECH_CARD` (26) routes to the `invent` state, and row 126
  ("invent from top of the deck", `FLAG_FACE_DOWN`) calls `awardCard(CARD_TECHNOLOGY)` at 1851.
  Seam B covers row 126 and the face-DOWN half of invent. The face-up half is explicitly excluded by
  the card, so `invent` must apply Psionics only on the face-down branch, which is per-branch work
  inside the invent handler, not something seam B gets for free.
  `drawTechCards($count, $refresh)` (6688) fills `deck_tech_vis`, the face-up market: excluded by
  the card text, nothing to do, and worth a regression test that says so.
- Tapestry, from the deck. `BE_TAPESTRY` (7) at 1491: seam B covers it. Not covered, each needing
  its own decision: `effect_drawCardsUntil` (11013), DEMOCRACY drawing 3 (2698), the tapestry draws
  at 6097, 6140 (face down), 6174 and 11724, the "another player gains" path at 1936,
  [Merfolk.php](../modules/civs/Merfolk.php) lines 77 and 87,
  [Mystics.php](../modules/civs/Mystics.php) 228 and 252 (which draw from the Mystics private deck).
- Landmark card. No random landmark source exists in this codebase. Landmarks are `structure` rows
  seeded into `landmark_mat_slot*` at setup and claimed deterministically per track spot; row 111
  and row 305 are player choices from the remaining pool, not draws. So the card's landmark bullet
  has no implementation site today. Flagged rather than invented.
- Territory. `BE_TERRITORY` (6) at 1488: seam B covers it. Not covered: AGE OF SAIL drawing 3
  (2705), the pair at 2685 and 2692, the opponent gain at 2179, and 6021 and 6388, plus
  [Isolationists.php](../modules/civs/Isolationists.php) line 19.
- Space tile. `case 51` at 1609: seam B covers it. Not covered:
  [Werefolk.php](../modules/civs/Werefolk.php) line 70, which draws the tile it then flips.
- Civilization. `case 65 BE_GAIN_CIV` at 1654: seam B covers it, and the Infiltrators branch two
  lines above is the precedent for how (and the additive case: 3 + 1 = 4). Not covered: the initial
  civ deal in `setupNewGameTables`, which is also the draw that hands out Psionics itself, so it
  cannot apply to its own arrival. Needs a ruling.
- Not random gains, listed so a later reader does not have to re-derive it: the decision deck
  (bot only), `Genies::grantWish`'s random opponent, `Weefolk`'s random plot, and the Werefolk coin
  flip - a coin is not a die.

### Regression tests that pin today's behaviour

All of these are written and green BEFORE the civ exists, and every one must pass unchanged
afterwards at a table with no Psionics:

- `rollDieFaces` with no Psionics returns exactly one face and consumes exactly one `bgaRand`, for
  all three dice, including the face-5 remap.
- Each of the eight die flows above, driven through `DiceUT`, produces the same benefit rows in the
  same order as today. `tests/DieRollSeamTest.php` already covers most of these for Illuminati and
  is the file to extend rather than duplicate.
- `science_die_psionics`, `conquer_die_red_2` and `conquer_die_black_2` are zero after every roll at
  a table with no Psionics, including a roll that follows a Psionics roll from an earlier turn (the
  lifetime test).
- `effect_onQueueBenefit` returns true and queues the ordinary row for every card gain when nobody
  has Psionics, and the Advisors and Infiltrators arms still fire.
- An Illuminati table with no Psionics still sees `dieRolled` exactly once per roll with the same
  face, which is the guard that seam A did not disturb clause CIV.ILLUMINATI.1.

## Stage 2 - the civilization

- [modules/civs/Psionics.php](../modules/civs/Psionics.php), `class Psionics extends AbsCivilization`,
  `declare(strict_types=1)`. It is unusually thin, because stage 1 put the behaviour in the engine
  and the civ only answers "is it on":
  - `queueEraCivAbility($player_id, $incomeTurn = 0)` - the Illuminati shape: in range, queue the
    turn's row with `reason_civ(CIV_PSIONICS)`; out of range, fall through to the parent so the
    "not applicable in era" message still prints.
  - `getExtraDrawCount(int $ben): int` and `getExtraRollCount(): int` or equivalent, the two
    predicates the engine calls. Whether these live on the civ class or as `hasCiv` checks inline is
    a judgement call; the civ class is preferred so a later civ with the same trick has somewhere to
    go, but the engine must not pay a `getCivilizationInstance` per die roll in a loop.
  - No `setupCiv`, no `awardBenefits` (there is no `civ =>` row), no `finalScoring`.
- Material entry as described in Shape, with each rules sentence its own `clienttranslate`, matching
  the Illuminati entry's structure.
- The five `BE_PSIONICS_*` CSV rows through `npm run genmat` plus the prettier pass. Never hand
  edit the generated block.
- `case BE_PSIONICS_*` joins the existing `case 172 / 175 / BE_ILLUMINATI_DRAW` group in
  `awardBenefits`, and `BE_PSIONICS_CIV` additionally joins the `$ben != 172` branch of
  `effect_keepCard`.
- `getAllDatas` gains the three new dice fields.

## Test infrastructure

- `tests/Stubs/DiceUT.php` is the harness for everything in seam A: it already scripts
  `getTileBenefit`, `isTapestryActive`, the map and the outpost pool, and its `runManager()` pops the
  benefit stack the way `stBenefitManager` does. `PsionicsUT` extends it, `giveCiv(ROLLER, CIV_PSIONICS)`,
  and every case states the faces it seeds. `IlluminatiUT` is the model for the wrapper class.
- Randomness is `seedRand(...)` on `GameUT`; an unseeded `bgaRand` prints a warning and
  `beStrictAboutOutputDuringTests` turns that into a failure, so no case can pass by luck. Note that
  the roll ORDER matters more here than anywhere else in the suite: `rollConquerDice` consumes red
  then black, and with Psionics each consumes two values, so a conquer seeds four numbers.
- Card cases need cards in the deck: `addCard(CARD_X, "deck_x", 0, $type)` per `IlluminatiUT::fillDeck`.
- A case that must prove the additive rule is a production-only path in the sense CODE_STYLE means:
  write it, stash the change, confirm it fails, restore.

## Test cases

Material and income:
- The material entry: `exp` FF, `automa` false, `income_trigger` 2-5 with `decline` false, no `slots`.
- Income turns 2, 3, 4 and 5 each queue their one row with the civ reason; income turn 1 queues
  nothing and prints the not-applicable message.
- Each income row is then itself intercepted, so income turn 2 ends in a keepCard over two territory
  tiles rather than a territory in hand.

Dice, all seeded:
- A plain conquer by a Psionics roller consumes four rand values and offers one `or` over the
  benefits of all four faces; the same conquer by a non-Psionics roller consumes two and is
  byte-identical to today.
- A both-dice conquer (`conquer_bonus` 2, and again via PILLAGE AND PLUNDER) queues two `or` rows,
  one per die, and never collapses them.
- A black face 0 among the four candidates offers no benefit for that face but does not suppress the
  others; all four faces at 0 gives the existing no-benefit message and no row.
- Black face 1 among the candidates offers the territory benefit from `getTileBenefit`.
- Research with Psionics and no EMPIRICISM offers two tracks; with EMPIRICISM, three, and never
  four; the roller may still decline; picking a track clears all three globals.
- Row 301 with Psionics rolls three times and offers three; row 302 the same on science.
- Rows 303 and 304 with Psionics roll four times and queue two independent `or` rows.
- Rows 324 and 325 with Psionics offer both faces instead of the fixed 330 / 332 row.
- The full set again with no Psionics at the table: identical rows, identical order, identical rand
  consumption. This is the seam's regression guard.

Cards:
- Each of `BE_TERRITORY`, `BE_TAPESTRY`, the technology row, the space row and `BE_GAIN_CIV` for a
  Psionics owner enters keepCard with two cards, keeps one and returns the other; for a non-owner it
  goes straight to hand.
- A kept civilization card stays in `draw`; a kept tapestry, territory, space and technology card
  moves to hand and fires `effect_cardComesInPlay` exactly once.
- An empty deck reshuffles the discard and still offers two; a deck with one card left offers one
  and does not assert.
- Additivity: Gamblers 311 for a Psionics owner draws 4, Infiltrators' row 172 draws 4,
  Illuminati's `BE_ILLUMINATI_DRAW` draws 4. None of them multiplies.
- Face-up exclusions: `drawTechCards` still fills `deck_tech_vis` with exactly `$count`, and the
  face-up half of invent draws nothing extra.
- The Advisors arm on `BE_TAPESTRY` still fires when the gaining player is a Psionics owner
  (composition test, see risks - Advisors re-queues through `benefitSingleEntry` directly, which
  bypasses `effect_onQueueBenefit`).

Edge:
- A finished or zombie Psionics owner: the income rows are dropped by `checkAliveForBenefit`, and no
  extra roll or draw is generated for a player who cannot answer the choice.
- Undo across a keepCard and across a die choice restores the pre-roll state including the new
  globals, which are inside the undo savepoint.
- `getAllDatas` carries the three new dice fields, zero without a Psionics.

## Client

- No new state and no new `onUpdateActionButtons_*`. Every choice is either `keepCard`, which
  already renders drawn cards for Gamblers and Illuminati, or a choose-one benefit row rendered by
  the benefit machinery, which also means the pending choices show up in `notif_benefitQueue` for
  free. That is the useful check that the server side was queued correctly.
- `notif_conquer_roll` and `notif_science_roll` learn the optional second face and must not guard on
  truthiness: face 0 is a real face, which is the bug the Illuminati stage 2 review found in
  `notif_conquer_roll` already.
- The die tooltip, built by `updateDieTooltip` since Illuminati stage 2, gains a line naming the
  alternate face while one is pending.
- The research state UI already highlights the EMPIRICISM track alongside the primary one; it gains
  a third highlight from `args.psionics`. Confirm the existing markup is not hardcoded to two.
- CSS: nothing for the mat (`civ_ff.webp` and `.civilization_47` are in place). At most one class
  for the alternate-face badge on a die, following the `.on_civ_mat::after` recipe, and remembering
  that `.die_wrapper` is `transform-style: preserve-3d`, so a badge needs `translateZ`.
- Tooltip on the mat comes from the `description` array, nothing special.

## Rulings

Proposed, to be recorded in FORMAL_RULES as clauses under 5.47 (CIV_PSIONICS).

- PSIONICS gives one extra option, never a doubled one. Where an effect already offers the player a
  choice among N randomly generated options, PSIONICS makes it N+1: EMPIRICISM plus PSIONICS is
  three science rolls and one choice, row 301 is three black rolls and one choice, row 302 is three
  science rolls and one choice, a draw-3-keep-1 row becomes draw 4 keep 1. From the card's own
  worked example. Where an effect rolls a die more than once and gains EACH result (rows 303 and
  304, and the conquer both-dice case), each of those is a separate option set and each gets its own
  extra roll and its own choice.
- PSIONICS applies to gaining a random card from a deck or supply. It does not apply to a face-up
  option (the visible technology market, the face-up half of invent), nor to revealing a card
  without gaining it, nor to a card moved from a discard, another player's hand, or a private civ
  deck by an effect that names the card.
- The unchosen card is discarded rather than shuffled back. The decks in this implementation
  reshuffle their discard when they run out (`dbPickCardsForLocation`), so the two are equivalent in
  outcome and discarding is what every existing draw-and-keep row already does.
- PSIONICS against ILLUMINATI: a PSIONICS roll of a die from the ILLUMINATI mat is ONE roll. The
  ILLUMINATI hook fires once, on the first of the two faces, and the owner gains the benefit of that
  first face regardless of which face the roller keeps. This composes 5.24's "you may gain only from
  the first roll immediately after they take the die" with the PSIONICS extra roll being a reroll
  from the owner's point of view, and it needs no ordering surgery: `dieRolled` keeps firing at the
  same point with the same argument. The die still leaves the mat on that first roll.
- PSIONICS against EMPIRICISM: three rolls, one choice, as above. The extra roll is added once per
  research decision, not once per physical roll, which is what makes it additive.
- PSIONICS against GAMBLERS: rows 311 and 319 draw one more card. The "play a WHEN PLAYED card if
  you can" constraint is unchanged and is checked over the enlarged set, so a draw of 4 with one
  WHEN PLAYED card still forces that card.
- PSIONICS against INFILTRATORS: row 172 draws 4 civilizations and keeps 1. PSIONICS against
  ILLUMINATI's setup draw: 4 tapestry cards, keep 1.
- PSIONICS against ADVISORS: ADVISORS intercepts an opponent's tapestry gain before PSIONICS sees
  it. The opponent still gains their tapestry, so PSIONICS still applies to that gain; the ADVISORS
  owner's copy is a separate gain and PSIONICS applies to it only if the ADVISORS owner is the
  PSIONICS owner.
- PSIONICS against ALCHEMISTS: unresolved, see open questions.
- WEREFOLK's coin flip is not a die roll, so PSIONICS does not double it. WEREFOLK's space tile IS a
  random gain and is doubled if the same player somehow holds both civs.
- A PSIONICS owner who is finished or zombie generates no extra roll and no extra draw; an effect
  that would have given them a choice resolves as it does without the civ.
- PSIONICS does not apply to the initial deal at setup, including the draw that hands out PSIONICS
  itself. Proposed on the grounds that setup is not a gain during play, and that the alternative is
  circular.

## Open questions

For Victoria.

1. ALCHEMISTS plus PSIONICS. ALCHEMISTS rolls up to three dice, keeps one, rerolls the rest, in a
   loop. Three readings, and I do not want to pick one blind: (a) every physical roll of every die is
   doubled, which is up to six rolls per pass and a per-die choice before the keep choice;
   (b) ALCHEMISTS' keep choice is itself the option set, so PSIONICS adds exactly one extra die roll
   to each pass and the owner picks one from the enlarged set; (c) PSIONICS does not apply, because
   ALCHEMISTS' rerolls are rerolls in the sense clause CIV.ILLUMINATI.1 already uses. Reading (b) is the one that
   fits the additive rule best and is the cheapest to build.
2. Which row is income turn 4's [TECHNOLOGY]? `BE_TECH_CARD` (26), which routes to the `invent`
   state and lets the player choose face-up or face-down, or `BE_INVENT` (20) with `FLAG_FACE_BOTH`,
   or row 126, invent from the top of the deck face down? The distinction matters precisely because
   PSIONICS applies to one branch and not the other, so this civ's own income turn 4 is the place a
   player will first notice the face-up exclusion.
3. Income turn 5's [RESEARCH]: `BE_RESEARCH` (18, gains the spot benefit and pays the bonus) or
   `BE_RESEARCH_NB` (19, neither)? The transcribed icon does not distinguish them.
4. `automa => false`? Nothing about the civ needs a live opponent, unlike Genies, Weefolk and
   Illuminati, so it could legally be in solo. It is excluded here only because the Automa would
   need a keep policy for six decks and three dice. If solo support matters, the cheapest policy is
   "always keep the first option", which makes PSIONICS a no-op for the bot.
5. The card's [LANDMARK CARD] bullet has no site: nothing in this implementation gains a landmark at
   random. Is that a base-game gap I should not worry about, an FF-only effect from another civ in
   the pack that has not been transcribed yet, or a sign that landmarks in the physical game are
   drawn rather than chosen?
6. On a conquer, is collapsing "pick a face per die, then pick a die" into one choice among four
   (die, face) pairs acceptable, given the outcomes are identical and it saves a whole state's worth
   of UI? The visible difference is only in the log and in how the dice render.
