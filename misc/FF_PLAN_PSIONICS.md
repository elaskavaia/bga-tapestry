# Psionics - implementation plan

Part of the Fantasies & Futures pack, see [FF_PLAN.md](FF_PLAN.md) for the pack wide plan,
classification and sequencing. Rules arbitration is against [FORMAL_RULES.txt](FORMAL_RULES.txt).
The die half shares the engine seam that [Illuminati](FF_PLAN_ILLUMINATI.md) built, so this plan
reuses its vocabulary: `rollDieFace`, `dieRolled`, "interrupt before the roll, queue Normal after".

Status: stage 1 (the engine seam) landed, stage 2 (the civilization) not started.

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
- `automa => true`: nothing about the civ needs a live opponent, so it is legal in solo. The Automa
  never chooses, so both seams add an option only for a real player (`isRealPlayer`): a bot holding
  the mat gets exactly today's roll or draw, which is the "keep the first option" policy for free and
  leaves `queueBenefitAutomaSingle` untouched.
- New `benefit_types.csv` rows, all copies of the existing draw/keep shape that
  `case 172 / 175 / BE_ILLUMINATI_DRAW` in `awardBenefits` already resolves
  (`'icon' => 'no','tt'=>'card','ct'=>CARD_X,'draw'=>2,'keep'=>1`):
  - `BE_PSIONICS_TERRITORY` (`ct => CARD_TERRITORY`)
  - `BE_PSIONICS_TAPESTRY` (`ct => CARD_TAPESTRY`)
  - `BE_PSIONICS_TECH` (`ct => CARD_TECHNOLOGY`)
  - `BE_PSIONICS_SPACE` (`ct => CARD_SPACE`)
  - `BE_PSIONICS_CIV` (`ct => CARD_CIVILIZATION`) - a kept civilization card stays in `draw` rather
    than moving to hand. `effect_keepCard` decides that with an explicit list, 172 and this row. It
    cannot key on `ct`: row 174 (Keep Civilization) is a civilization row too and is the one that
    moves the card to hand, so keyed on `ct` it re-queued itself forever (found in review).
    No income row is needed: `queueEraCivAbility` maps the turn straight onto `BE_TERRITORY` (6),
    `BE_TAPESTRY` (7), `BE_INVENT` (20, `FLAG_FACE_BOTH`, not `BE_TECH_CARD` 26 or row 126) and
    `BE_RESEARCH` (18, spot benefit and bonus, not `BE_RESEARCH_NB` 19), and those rows then get
    intercepted like any other random gain.
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
  args carry the third track; `argConquerRoll` carries both second faces. No new state: every choice
  Psionics creates is an existing state (`keepCard`, `conquer_die`, `research`) or a plain choose-one
  benefit row rendered by the benefit machinery.

## Stage 1 - engine seam and timing

Everything here lands with tests and no civ class, and must provably change nothing at a table with
no Psionics. Two independent seams, because dice and cards go through different machinery.

### Seam A, the die

`rollDieFace(string $die): int` in [PGameXBody.php](../modules/PGameXBody.php) already is the
single place every die value in the game comes from. It gains a sibling:

- `rollDieFaces(string $die, int $player_id, bool $extra = true): array` - returns `[$face]` today,
  and `[$face1, $face2]` when `$extra` and the roller is a real player with `CIV_PSIONICS`. One
  `bgaRand` per element, so seeded tests stay readable. Every current caller of `rollDieFace` moves
  to it. `$extra = false` is how a row that already rolls twice and picks (301, 302) asks for plain
  faces and adds its own single extra roll: the additive rule needs the caller to own the decision,
  the seam cannot know that two calls belong to one choice.

The four roll functions pass `$extra` through, keep their current return value (the first face) and
additionally record the second face where the flow can reach it:

- `rollConquerDice($player_id)` - writes `conquer_die_red_2` and `conquer_die_black_2`, or
  zeroes them. Its single two-die notification gains two optional face args.
- `rollRedConquerDie($player_id, $undosave)` and `rollBlackConquerDie` - same, for
  their die only. The black one already builds a territory-benefit name for face 1; with two faces
  it has to name both.
- `rollScienceDie($data, $dievar, $player_id, $undosave)` - writes only `$dievar`, as today,
  and returns the array. The callers decide where a second face goes, because the science die has
  four different consumers.

`dieRolled($die, $face, $roller_id)` keeps firing exactly once per call site, with the FIRST
face. That is the Illuminati composition, and it is a ruling, not an accident: see rulings.

Then, per consuming flow. These are the flows I verified exist; each needs its own work because the
seam only produces faces, it does not know what they mean:

- `research()` and `rollScienceDie2($reason)`. `rollScienceDie2` rolls once, then once
  more for EMPIRICISM when `isTapestryActive($player_id, TAP_EMPIRICISM)`. Both of those become
  plain rolls (`$extra = false`) and it gains a third arm: once more for Psionics, into
  `science_die_psionics`. This is where additivity is implemented and where it is most visible -
  one extra roll per decision, not one per physical roll, which is exactly the card's "a total of
  3 times (not 4)" example. `argResearch()` and
  `action_research_decision($track, $spot)` each read the two globals today and both learn
  the third; the error message they build for an illegal track has to list up to three tracks.
- Row 302, "roll the research die twice and gain one benefit of your choice". Rolls twice and
  queues `["or" => [21 + $b1, 21 + $b2]]`. Additively that becomes three plain rolls
  (`$extra = false`) and an `or` of three. Local change in the case.
- Rows 325 and 332. 325 interrupts, rolls, and queues 332, which reads `science_die` back. With a
  second face, 325 queues `["or" => [21 + $f1, 21 + $f2]]` directly instead of 332. Note the
  `$count > 1` branch: it is a reroll loop, `["or" => [332, BE_REROLL]]` then the 603 cleanup row
  then 325 again with `$count - 1`, so the `or` there is `[21 + $f1, 21 + $f2, BE_REROLL]`, and
  every roll of the loop gets its own extra face: a reroll replaces the previous result rather than
  adding to an option set, so there is nothing to add to.
- Rows 324 and 330. Same recipe on the black die, same `$count > 1` reroll loop: 324 queues an `or`
  of the benefits of both faces (plus BE_REROLL while rerolls remain) instead of 330.
- Row 301, "roll the black conquer die twice and gain one benefit of your choice". Already
  an extra-option effect, so additively three rolls and an `or` of up to three; the existing
  "this die roll results in no benefit" message per empty face stays.
- Rows 303 and 304, "gain both benefits". These are two independent rolls, each of
  which is gained, so each becomes roll-twice-keep-one and two `or` rows are queued. This is the
  case where Psionics genuinely doubles the number of rolls, because there is no shared option set
  to add to.
- `conquer()` and `effect_endOfConquer($player_id)`, reached by row 141. The roller picks one die's
  benefit unless `conquer_bonus` or PILLAGE AND PLUNDER makes it both. The `conquer_die` state STAYS:
  `action_choose_die` does more than pick a benefit, it also pays the unclaimed die to a TRADERS
  owner with a trader on the hex and applies PIRATE RULE, so collapsing the pick into one `or` over
  the four (die, face) pairs would silently drop both. Instead `action_choose_die($die, $face = 0)`
  takes the kept face, validates it against the two stored faces, writes it into `conquer_die_$color`,
  writes the unclaimed die's second face into its own global and zeroes both `_2` globals.
  Everything downstream (`getConquerDieBenefit`, TRADERS, PIRATE RULE, ILLUMINATI's `queueDieGain`)
  keeps reading one global and does not change. The unclaimed die goes to TRADERS on its second
  face (ruling, see below). The both-dice path has no pick state: it queues one `or` over the two
  faces' benefits per die (`getConquerDieBenefitOptions(string $die): array`),
  a face with no benefit contributing nothing, and a die with no benefit on either face printing the
  existing message.
- `Alchemists::alchemistRoll` and `Alchemists::rollAllDice` ([Alchemists.php](../modules/civs/Alchemists.php)).
  Ordinary rolls under CIV.PSIONICS.1: every die a pass rolls gets one extra face, and a die rolled
  again in a later pass gets a fresh one, the same as the 324 / 325 reroll loop. Variants 1, 2 and 4
  roll the science die once per pass, so the owner picks a face before the bust check: the roll
  stores both faces (`science_die`, `science_die_psionics`) and re-enters the ability with the two
  faces as `slots_choice`, and the pick runs today's bust-or-token code on the kept face. Variants 8
  and 9 roll up to three different dice per pass and keep a die, so `slots_choice` lists both faces
  of each remaining die and keeping one is the `action_choose_die` recipe: the face goes into the
  die's primary global, its `_2` is zeroed, and the gain at the end keeps reading one global per
  die. No TRADERS or PIRATE RULE here, so unlike conquer the collapse into one choice is safe.
- `queueBenefitAutomaSingle` case `"r"` - the last roll-then-advance-inline site, Automa only.
  Nothing to do: `rollDieFaces` adds a face only for a real player, so the bot never has a choice.

### Seam B, the card

`awardBenefits` is the seam, at the case level, and the precedent is already there: `case 65
BE_GAIN_CIV` for an Infiltrators owner queues row 172 (draw 3 keep 1) with `queueBenefitInterrupt`
instead of calling `awardCard`. Psionics is that same substitution, generalised into one helper:

- `awardRandomCard(int $player_id, int $count, int $card_type, string $reason): void` - for a real
  player with `CIV_PSIONICS` queues the matching `BE_PSIONICS_*` row `$count` times with
  `queueBenefitInterrupt` (each card is its own draw 2 keep 1); otherwise calls `awardCard`. Cases
  `BE_TERRITORY`, `BE_TAPESTRY`, 51, 65 (after the Infiltrators branch), 126, 173 and 199 move to it.
  So does every site in the inventory below whose `awardCard` return value is unused: the swap is one
  line per site and the helper's name is the seam's documentation.
- Why not `effect_onQueueBenefit`: it fires only from `queueBenefitStandardOne`, so anything that
  calls `awardBenefits` directly bypasses it, and a material predicate (`tt == "card"` plus `ct`)
  is wrong both ways - rows 51, 65 and 126 carry neither key, while 26 (face-up-or-down invent),
  137, 138, 181, 191-193, 320 and 321 (give, discard or copy a named card) carry both and are not
  random gains. The case level needs no predicate at all.
- Why not `awardCard`: it returns the drawn cards and a dozen callers use the return value
  immediately; a keep-one choice is interactive and cannot return a card. Those callers are listed
  below and each is a decision, not a swap.
- Rows that already carry `draw` and `keep` (172, 175, `BE_GAMBLES_PICK` 311, `BE_GAMBLES_PICK_2`
  319, `BE_ILLUMINATI_DRAW` 354) are NOT re-queued. Psionics adds one to the `draw` count where the
  row is resolved, so draw 3 keep 1 becomes draw 4 keep 1. That is the additive rule again, and it
  is why the count is read through `getDrawCount(int $ben, int $player_id): int` rather than straight
  off `getRulesBenefit`.
- The draw-and-keep case resolves with `dbPickCardsForLocation($count * $draw, $card_type, "draw",
$player_id)` and no deck argument, so a MYSTICS owner (adjustment 8) draws Psionics tapestry
  cards from the shared deck while `awardCard` would use `deck_13`. Pre-existing for GAMBLERS; fix
  it on the way by lifting `awardCard`'s deck resolution into `getDeckFor(int $player_id, int
$card_type): array` and calling it from both.
- `effect_keepCard` hands `reason("be", $ben)` to `effect_cardComesInPlay`, which loses the reason
  the keep row was queued with (the civ's income reason, an opponent's ADVISORS copy). It passes the
  row's own `benefit_data` instead; the four existing keep rows already carry a civ reason there, so
  their log lines only get more specific. Verify that with the existing Gamblers and Illuminati tests.

### Every random source the card names

For each: what produces it today, and whether a seam covers it. The `effect_automa*` draws are the
bot's own and never a real player's gain: nothing to do there.

- Die, black and red. Produced by `rollDieFace` via `rollConquerDice`, `rollRedConquerDie`,
  `rollBlackConquerDie`. Seam A produces the extra face; each of the eight consuming flows listed
  above needs its own work.
- Die, science. Produced by `rollDieFace` via `rollScienceDie`. Same: seam A plus per-flow work in
  `rollScienceDie2`, rows 302, 325 and the Alchemists loop.
- Technology, from the deck. Three producers, none of them free:
  - Row 126 ("invent from top of the deck", `FLAG_FACE_DOWN`) calls `awardCard` from
    `awardBenefits`: seam B.
  - `action_invent` is a state action, not a row. Its face-down branch calls
    `dbPickCardsForLocation` inline and then `effect_cardComesInPlay` (or `upgradeTechCard` under
    `FLAG_UPGRADE`) in the same handler, and `BE_INVENT` (20, `FLAG_FACE_BOTH`) is what income turn
    4 queues, so this is the path the civ's own income hits. For a Psionics owner
    the face-down branch calls `awardRandomCard` and returns; `effect_keepCard` already moves the
    kept card to hand and fires `effect_cardComesInPlay`. The `FLAG_UPGRADE` variant has to upgrade
    the kept card too: the keep row's `benefit_data` carries the original row and `effect_keepCard`
    reads its flags. The face-up branch is excluded by the card and does not change. RECYCLERS'
    pick from the seen `draw` pile is a named card (CIV.PSIONICS.2), not a random one: unchanged.
  - `drawTechCards($count, $refresh)` fills `deck_tech_vis`, the face-up market: excluded by the
    card text, nothing to do, and worth a regression test that says so.
  - Row 199, "opponents gain a tech card": `awardRandomCard` per opponent, each subject only to
    their own civ (CIV.PSIONICS.8).
- Tapestry, from the deck. `BE_TAPESTRY` (7): seam B. Swaps: `democracy` (draws 3, return unused).
  Decisions, because the return value is used or the draw is face down: `effect_drawCardsUntil`
  (draws one at a time until a condition, so the keep has to happen before the condition is
  checked), `stTapestryCard` (a face-down consolation draw when nothing can be played),
  [Merfolk.php](../modules/civs/Merfolk.php) (the income and era-5 draws feed the submerge step),
  [Mystics.php](../modules/civs/Mystics.php) (draws from the private deck, see `getDeckFor` in
  seam B).
- Landmark card. Not in this game: the bullet belongs to an expansion that is not implemented.
  Nothing to build.
- Territory. `BE_TERRITORY` (6): seam B. Swaps: `ageOfSailCheck` (draws 3),
  `effect_allOpponentsGainTerritoryTile` (row 340, per opponent), the neighbour draw in
  `coalBaron`, and [Isolationists.php](../modules/civs/Isolationists.php) if its return value is
  unused. Decision: `coalBaron`'s own draw, whose tile is then explored.
- Space tile. `case 51`: seam B. [Werefolk.php](../modules/civs/Werefolk.php) draws the tile it
  then flips and CIV.PSIONICS.7 says that IS a random gain: the flip has to wait for the keep, so it
  is a decision for that civ, not a swap.
- Civilization. `case 65 BE_GAIN_CIV`: seam B, and the Infiltrators branch two lines above is the
  precedent for how (and the additive case: 3 + 1 = 4). Row 173, "discard a civilization, gain
  another": seam B. The initial deal in `setupNewGameTables` is a pick into `choice` and a player
  keeps one civ from it, so a Psionics owner cannot hold a second civ at setup: CIV.PSIONICS.3 holds
  with no flag, and `Illuminati::setupCiv`'s draw is subject to Psionics exactly when `$start` is
  empty (gained mid game), for free.
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
- `awardRandomCard` calls `awardCard` once with the same arguments for every card gain when nobody
  has Psionics, and the Advisors and Infiltrators arms still fire.
- `action_choose_die` with no face argument behaves exactly as today, TRADERS and PIRATE RULE
  included.
- An Illuminati table with no Psionics still sees `dieRolled` exactly once per roll with the same
  face, which is the guard that seam A did not disturb clause CIV.ILLUMINATI.1.

### Stage 1 status

Done, `npm run predeploy` green at 459 tests (409 before). What landed, and where it differs from
the plan above:

- `hasExtraOption($player_id)` in [PGameXBody.php](../modules/PGameXBody.php) is the one predicate
  both seams ask (`isRealPlayer` plus `hasCiv(CIV_PSIONICS)`), rather than the check inline at
  fifteen sites. It is not the `hasExtraOption` hook on `AbsCivilization` the plan warns against
  lifting early: it is a private question the engine asks itself.
- Seam A: `rollDieFaces`, `setConquerDieFaces` / `getConquerDieFaces`, `notifyExtraDieFace`, and
  `rollConquerDice` / `rollRedConquerDie` / `rollBlackConquerDie` / `rollScienceDie` on top of them.
  The two conquer roll functions gained an `$extra` parameter so a caller that owns the decision can
  ask for a plain face; `rollScienceDie` returns the array of faces, and its five callers were
  updated.
- **The second face globals hold face plus one.** The plan's "zero means no second face" does not
  work: 0 is a real conquer die face. `conquer_die_red_2` and `conquer_die_black_2` store the face
  plus one so the framework default 0 is still an honest sentinel, and `getConquerDieFaces` decodes
  it. `science_die_psionics` needs no offset, science faces being 1-4, and matches
  `science_die_empiricism` exactly. The notification and `argConquerRoll` args carry the encoded
  value, which also kills the truthiness bug the client review found on `notif_conquer_roll`.
- The sampled face gets its own log line (`notifyExtraDieFace`, and the second `science_roll` notif)
  rather than being folded into the roll message. A table with no PSIONICS therefore sees the
  message it has always seen, byte for byte, which is what the regression tests pin.
- Per flow: `rollScienceDie2` third arm, `argResearch` third track, `action_research_decision`
  validating up to three (the two existing messages are untouched and a third variant covers the
  sampled case), rows 301, 302, 303, 304, 324 and 325, `queueConquerDieGain` for the both dice path
  and `keepConquerDieFace` plus `action_choose_die($die, $face)` for the pick. `face` is a new
  optional `AT_int` argument in [tapestry.action.php](../tapestry.action.php), defaulting to -1
  because 0 is a real face.
- `queueUnclaimedDieBenefit` pays a TRADERS owner the **second** face, per the corrected
  CIV.PSIONICS.9. `conquerDieBenefit` is unchanged and still reads the one global, which is what the
  roller's kept die and the UTILITARIENS Barracks gain use.
- `action_celestialMove` was not in the plan's flow inventory but rolls both conquer dice and gains
  both, so it went through `queueConquerDieGain` too; leaving it would have silently dropped the
  sample for a CELESTIALS owner who also holds PSIONICS.
- Seam B: `getDeckFor`, `awardRandomCard` and `getDrawCount`. `getDeckFor` is called from
  `dbPickCardsForLocation` as well as `awardCard`, which is the MYSTICS private deck fix the plan
  asked for on the way. Swapped to `awardRandomCard`: `BE_TERRITORY`, `BE_TAPESTRY`, 51, 65, 126,
  173, 199, 340, `democracy`, `ageOfSailCheck`, the `coalBaron` neighbour draw and
  `Isolationists::setupCiv`. `effect_keepCard` keys the "stays in draw" branch on
  `ct == CARD_CIVILIZATION` and passes the row's own `benefit_data` to `effect_cardComesInPlay`.
- **The six `BE_PSIONICS_*` CSV rows landed in stage 1, not stage 2**, because `awardRandomCard` is
  the seam and cannot be written or tested without them. They are inert with no civilization: no
  other row references them. They carry a new `'sampled' => 1` material key that `getDrawCount`
  reads, so a row that is already the sample is not enlarged a second time - without it every
  PSIONICS gain would draw 3, not 2.
- `BE_PSIONICS_TECH_UPGRADE` (363) is a sixth row the plan did not have. Row 127 "invent and
  instantly upgrade" is the only `FLAG_UPGRADE` invent, and the plan's idea of reading the original
  row's flags out of `benefit_data` does not work, `benefit_data` being the reason string. The keep
  row carries `FLAG_UPGRADE` itself instead and `effect_keepCard` upgrades the kept card, using
  `effect_cardComesInPlayTriggerResolve` the way the `action_invent` tail does.
- `tests/Stubs/PsionicsUT.php` and [tests/PsionicsSeamTest.php](../tests/PsionicsSeamTest.php), 50
  cases. The harness stubs one material field (`civilizations[CIV_PSIONICS]["name"]`, for the log
  line and the `getCivilizationInstance` walk, which falls back to `BasicCivilization`) and hands
  out a real civ card, so the cases exercise the production predicate rather than an override. Every
  case comes in a pair, sampled and not. Three of them were stashed and confirmed to fail without
  their fix: the face-zero sentinel, the `sampled` guard and the TRADERS second face.

Answered:

- **ALCHEMISTS is deferred to stage 2.** `rollAllDice` and `alchemistRoll` pass `$extra` false and
  say so in a comment. The plan's recipe needs the mat's `slots_choice` UI to carry two faces per
  die and a spot encoding to name which face is kept, which is civ and client work with nothing to
  verify it against while the civilization does not exist. Rolling the extra face and not offering
  it would be a silent rules hole, so the dice stay plain until then. A: sequencing only, no rules
  question in it. Psionics on the Alchemists dice follows from "whenever you roll a die, roll it
  twice": one extra face per die, each with its own keep (CIV.PSIONICS.1). Stays in stage 2 with the
  mat UI.
- UTILITARIENS Barracks gains "the result of the red die" at roll time, before the pick, and still
  reads the first face. Is that the result, or is the kept face? Not ruled; unchanged for now. A:
  the kept face. The mat has the roller "choose 1 of the 2 values that you rolled", so the chosen
  value is the result of that die for everything that reads it; only the die the roller does not
  claim shows its second face (CIV.PSIONICS.9). Pinned as CIV.PSIONICS.10. Reading the first face
  is a bug, not a ruling to ask for; fix it in stage 2 with the civ.
- A face carrying several benefits was offered by its first when it went into an `or`, the old row
  301 `XXX there could be 2 tiles` limitation, and a sampled black face 1 reached it on every
  choose-one path. Fixed in review: a choice offers each face as the row printed on it, the black
  territory face as `BE_TERRITORY_BE_BLACKDIE`, which pays the whole tile when picked
  (`getConquerDieChoiceRows`, `queueConquerDieChoice`). Two equal faces collapse to one option.
- The plan's "decisions, not swaps" list is untouched and belongs with the civilization:
  `effect_drawCardsUntil`, `stTapestryCard`'s face-down consolation draw, `coalBaron`'s own draw,
  MERFOLK, MYSTICS and WEREFOLK. A: stage 2 backlog, no open rules in it. Each is a draw the deck
  decides, so each gets the extra option (CIV.PSIONICS.2); the draw-until condition is answered
  over the enlarged set (CIV.PSIONICS.5); the WEREFOLK tile is random, its flip is not
  (CIV.PSIONICS.7). One exception: the face-down consolation card is a placeholder, nothing is
  chosen, so no extra option (CIV.PSIONICS.11).

## Stage 2 - the civilization

- [modules/civs/Psionics.php](../modules/civs/Psionics.php), `class Psionics extends AbsCivilization`,
  `declare(strict_types=1)`. It is unusually thin, because stage 1 put the behaviour in the engine
  and the civ only answers "is it on":
  - `queueEraCivAbility($player_id, $incomeTurn = 0)` - the Illuminati shape: in range, queue the
    turn's row with `reason_civ(CIV_PSIONICS)`; out of range, fall through to the parent so the
    "not applicable in era" message still prints.
  - No predicate methods: the two seams ask `isRealPlayer($player_id) && hasCiv($player_id,
CIV_PSIONICS)` inline, one indexed query, the same shape `case 65` uses for INFILTRATORS. A
    second civ with the same trick is the moment to lift that into a `hasExtraOption` hook, not before.
  - No `setupCiv`, no `awardBenefits` (there is no `civ =>` row), no `finalScoring`.
- Material entry as described in Shape, with each rules sentence its own `clienttranslate`, matching
  the Illuminati entry's structure.
- The five `BE_PSIONICS_*` CSV rows through `npm run genmat` plus the prettier pass. Never hand
  edit the generated block.
- `case BE_PSIONICS_*` joins the existing `case 172 / 175 / BE_ILLUMINATI_DRAW` group in
  `awardBenefits`; `BE_PSIONICS_CIV` joins 172 in the `effect_keepCard` stay-in-draw list.
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

- The material entry: `exp` FF, `automa` true, `income_trigger` 2-5 with `decline` false, no `slots`.
- Income turns 2, 3, 4 and 5 each queue their one row with the civ reason; income turn 1 queues
  nothing and prints the not-applicable message.
- Each income row is then itself intercepted, so income turn 2 ends in a keepCard over two territory
  tiles rather than a territory in hand.

Dice, all seeded:

- A plain conquer by a Psionics roller consumes four rand values, stores both faces per die, and
  `action_choose_die($die, $face)` queues that face's benefit, writes it into the primary global and
  zeroes both `_2` globals; a face that was not rolled is refused. The same conquer by a
  non-Psionics roller consumes two and is byte-identical to today.
- The unclaimed die still pays a TRADERS owner with a trader on the hex, on its second face, and
  PIRATE RULE still queues the tile benefit.
- A both-dice conquer (`conquer_bonus` 2, and again via PILLAGE AND PLUNDER) queues one `or` row per
  die over that die's two faces.
- A black face 0 offers no benefit for that face but does not suppress the other; both black faces
  at 0 gives the existing no-benefit message.
- Black face 1 offers the territory benefit from `getTileBenefit`.
- The Automa rolling any die consumes one rand value per die and takes no choice.
- Research with Psionics and no EMPIRICISM offers two tracks; with EMPIRICISM, three, and never
  four; the roller may still decline; picking a track clears all three globals.
- Row 301 with Psionics rolls three times and offers three; row 302 the same on science.
- Rows 303 and 304 with Psionics roll four times and queue two independent `or` rows.
- Rows 324 and 325 with Psionics offer both faces instead of the fixed 330 / 332 row; with `$count`
  2 the `or` also offers BE_REROLL, and the reroll gets its own second face.
- ALCHEMISTS variant 8 with Psionics: the first pass consumes six rand values and offers both faces
  of each die, keeping a face zeroes that die's `_2` global, and the next pass gives the rerolled
  dice fresh second faces; variant 1 offers two science faces and the bust check runs on the kept
  one.
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
  face-up branch of `action_invent` draws nothing extra.
- `action_invent` face-down for a Psionics owner ends in keepCard over two technology cards and the
  kept one comes into play once; with `FLAG_UPGRADE` the kept card is upgraded; the RECYCLERS pick
  from the seen pile is unchanged.
- Row 173 offers two civilizations after the discard; row 199 gives each opponent a plain card
  unless that opponent owns Psionics.
- A MYSTICS owner (adjustment 8) with Psionics draws both tapestry candidates from `deck_13`.
- The kept card's `effect_cardComesInPlay` receives the keep row's own reason, and the existing
  Gamblers and Illuminati keep tests still pass with theirs.
- ADVISORS composition (CIV.PSIONICS.8): the gaining player's row and the ADVISORS owner's copy are
  each intercepted only by their own owner's civ; `interceptTapestryGain` re-queues through
  `benefitSingleEntry`, which lands in `awardBenefits` like any row, so nothing special is needed.
- WEREFOLK with Psionics draws two space tiles, keeps one, and flips only the kept one.

Edge:

- A finished or zombie Psionics owner: the income rows are dropped by `checkAliveForBenefit`, and no
  extra roll or draw is generated for a player who cannot answer the choice.
- Undo across a keepCard and across a die choice returns to the savepoint the roll or draw itself
  placed (`rollConquerDice` and `dbPickCardsForLocation` save right after the random event, on
  purpose): both faces or both cards are still there and the choice can be made again, never
  rerolled.
- `getAllDatas` carries the three new dice fields, zero without a Psionics.

## Client

- No new state and no new `onUpdateActionButtons_*`. Every choice is either `keepCard`, which
  already renders drawn cards for Gamblers and Illuminati, or a choose-one benefit row rendered by
  the benefit machinery, which also means the pending choices show up in `notif_benefitQueue` for
  free. That is the useful check that the server side was queued correctly.
- `notif_conquer_roll` and `notif_science_roll` learn the optional second face and must not guard on
  truthiness: face 0 is a real face, which is the bug the Illuminati stage 2 review found in
  `notif_conquer_roll` already.
- The second `science_roll` line carries `die => faces[1]`, so after a live sampled roll the die on
  the board shows the second face (the last physical roll) while a reload paints `dice.science`, the
  first. Decide whether setup should paint `dice.psionics` when it is set, so both agree.
- The die tooltip, built by `updateDieTooltip` since Illuminati stage 2, gains a line naming the
  alternate face while one is pending.
- The research state UI already highlights the EMPIRICISM track alongside the primary one; it gains
  a third highlight from `args.psionics`. Confirm the existing markup is not hardcoded to two.
- CSS: nothing for the mat (`civ_ff.webp` and `.civilization_47` are in place). At most one class
  for the alternate-face badge on a die, following the `.on_civ_mat::after` recipe, and remembering
  that `.die_wrapper` is `transform-style: preserve-3d`, so a badge needs `translateZ`.
- Tooltip on the mat comes from the `description` array, nothing special.

## Rulings

Recorded in FORMAL_RULES as CIV.PSIONICS.1-9.

Implementation notes those clauses deliberately leave out:

- CIV.PSIONICS.1 sites: rows 301 and 302 become three rolls and one choice, rows 303 and 304 get an
  extra face and a choice per roll, rows 324 and 325 get an extra face on every roll of their reroll
  loop, ALCHEMISTS gets an extra face on every die of every pass, row 172 draws 4 civilizations and
  keeps 1, GAMBLERS rows 311 and 319 draw one more, ILLUMINATI's mid-game setup draw becomes 4
  tapestry cards keep 1.
- CIV.PSIONICS.1 excludes the face-up technology market and the face-up branch of `action_invent`,
  which is why income turn 4 being `BE_INVENT` matters: the owner's own income is the first place
  the exclusion shows.
- CIV.PSIONICS.2 makes RECYCLERS' pick from the seen pile a named card, so `action_invent` with
  `$card_type == -1` is unchanged.
- CIV.PSIONICS.3 needs no code: a player keeps one civ from the setup deal, so no setup row can
  belong to a Psionics owner.
- CIV.PSIONICS.9 On a conquer the roller keeps one face per die. The die they do not claim goes to a
  TRADERS owner on its second face: the roller rerolled the same die, so that is the face left
  showing.
- The unchosen card is discarded rather than shuffled back. The decks reshuffle their discard when
  they run out (`dbPickCardsForLocation`), so the two are the same in outcome and discarding is what
  every existing draw-and-keep row already does.
- CIV.PSIONICS.6 needs no ordering surgery: `dieRolled` keeps firing at the same point with the same
  argument, the extra face being a reroll from the ILLUMINATI owner's point of view.
- PSIONICS against ALCHEMISTS is CIV.PSIONICS.1 applied per die and per pass, see the Alchemists
  entry in seam A; it needs no clause of its own.
