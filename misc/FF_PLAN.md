# Fantasies & Futures - implementation plan and cost estimate

Scope: the 10 civilizations transcribed in [FF_CIVILIZATIONS.md](FF_CIVILIZATIONS.md).

Rate assumption: 35 EUR/hour. AI assistance assumed, but not at a flat multiplier (see below).

## Estimate

Recommended quote: 6,300 to 7,400 EUR.

- Baseline, skilled human without AI: 457 h, range 380 to 520 h.
- With AI, multiplier applied per band rather than flat: about 175 h, an effective 2.6x.
- At 35 EUR/hour: 6,125 EUR for the working estimate.
- Best case floor if a flat 5x actually held: 92 h, 3,220 EUR. Do not commit to this.
- Worst case if the post income 5 turn loop proves harder than scoped: 260 h, 9,100 EUR.

Where the hours go: 12 h one time infrastructure, 65 h fixed per civ tax across ten civs, 260 h
civilization logic, 60 h shared engine work for the post income 5 turn loop, 60 h integration and
playtesting.

Two of the ten (Faefolk, Werefolk) are ordinary civilizations. Two more (Genies, Weefolk) need new
cross player states. The remaining six need engine changes, and those six carry roughly 70 percent
of the cost.

Excludes the maintenance tail. Details and reasoning follow.

## How this was estimated

1. Calibrated against the 17 civilization classes already in `modules/civs/`, built May to
   August 2024, most in 1 to 6 commits each. That is the unit of "a civ that fits the existing
   engine".
2. Scored each new civ against the override points in `AbsCivilization.php`: `awardBenefits`,
   `moveCivCube`, `argCivAbilitySingle`, `setupCiv`, `finalScoring`, `triggerPreGainBenefit`,
   `queueEraCivAbility`, `hasActivatedAbilities`. A civ that fits entirely inside those costs one
   unit. A civ needing a hook that does not exist becomes a separate engine line item.
3. Added the fixed per-civ tax that is independent of cleverness.
4. Estimated the engine items separately and looked for sharing.

## Classification

Fits existing hooks, roughly one unit each:

- Faefolk - token walking a 7 spot ring, structurally Alchemists with different topology.
- Werefolk - random bit (the coin flip), then regress or advance.

New cross-player interactive states, precedent in Advisors and Historians:

- Genies - random opponent picks a circled benefit, you mirror it plus an adjacent squared one.
- Weefolk - your token occupies a plot in an opponent's capital and counts as filled, then row and
  column scoring at income 5.

Engine surgery, where the real cost lives:

- Elder Ones and Merfolk - both keep taking turns after income turn 5 while everyone else is
  finished. This is a change to the end of game state machine, not a civilization. Estimated once
  and shared between the two. Merfolk additionally needs a hidden "submerged" card zone.
- Psionics - hooks every random draw in the game (die, tech, tapestry, landmark, territory, space
  tile, civilization) with draw two keep one, and must add rather than multiply with Empiricism.
- Illuminati - hooks every die roll globally with "whose mat did this die come from", plus the
  rule that an opponent's roll leaves the die off the mat until your next income turn.
- Artificers - mutates the income track building layout mid game (set aside, slide left). Touches
  income resolution and the income mat UI.
- Celestials - a player token replaces an outpost, moves between territories, blocks conquest
  without taking control, plus "landmark hangs off the side of the capital" scoring.

## Baseline hours (skilled human, no AI)

One time infrastructure, 12 h: FF pack game option, `doAdjustMaterial` variant, `CIV_*` constants,
art asset wiring.

Per civ fixed tax, 6.5 h each, 65 h for ten: material entry with descriptions and translations 2 h,
`slots` coordinates against the art 1 h, client wiring 1.5 h, tests 2 h.

Civilization logic, 260 h total:

- Faefolk 8, Werefolk 8
- Genies 20, Weefolk 24
- Illuminati 32, Celestials 32, Artificers 40, Psionics 48
- Elder Ones 24, Merfolk 24

Shared engine work, 60 h: the post income 5 alternate turn loop used by Elder Ones and Merfolk.

Integration, cross civ playtesting and rules arbitration against `FORMAL_RULES.txt`, 60 h.

Baseline total: 457 h, expressed as a range of 380 to 520 h.

## Why a flat 5x AI multiplier is wrong here

5x is achievable on the mechanical portion: CSV rows, material entries, slot coordinates, test
scaffolding, and civ classes modelled closely on existing ones. That is about 17 percent of the
work.

It does not hold for the engine items. There the bottleneck is deciding what is correct, arbitrating
rules questions, and debugging. The real BGA framework is not available locally (only the stubs in
`bga-sharedcode`), so much of the verification happens in the studio. Human review of AI written
engine changes does not compress either.

Multipliers applied per band: infrastructure and fixed tax 5x, green and yellow civs 3.5x, engine
work 2.5x, playtesting 1.5x. Result: about 175 h, an effective 2.6x overall.

## Cost at 35 EUR/hour

- Flat 5x assumption: 92 h, 3,220 EUR. Treat as a best case floor, do not commit to it.
- Banded multiplier, the working estimate: 175 h, 6,125 EUR.
- If the post income 5 turn loop proves harder than scoped: 260 h, 9,100 EUR.

Recommended quote: 6,300 to 7,400 EUR.

Not included: a maintenance tail. Git history shows existing civilizations receiving bug fixes one
to two years after they shipped (Infiltrators was last touched in February 2026). Budget separately.

## Risks

- The post income 5 turn loop is the single largest unknown. It affects the end of game state
  machine, which every other civilization also depends on. Regression risk is high.
- Psionics and Illuminati are cross cutting. Every future random source and every future die roll
  must remember they exist. This is an ongoing tax on all later work, not a one time cost.
- Artificers changes the income mat, which is shared UI, not civ local.
- Cross civ interactions are combinatorial. Ten new civs against the existing set is where the
  playtesting budget goes.

## Recommended sequencing

1. Faefolk end to end. Cheapest, fully specified, and exercises the entire per civ tax at once.
   Completing it converts the "one unit" figure from an estimate into a measurement, which makes
   every other number here arithmetic instead of judgement. Re-baseline this document afterwards.
2. Werefolk. Confirms the unit and finishes the cheap band.
3. The post income 5 turn loop as a standalone engine change, verified against existing
   civilizations before any new civ depends on it. Then Elder Ones, then Merfolk.
4. Psionics and Illuminati together, since both are hooks into random generation.
5. Genies and Weefolk.
6. Celestials and Artificers last, as they touch map and income mat respectively.

## Resolved blockers

- The Faefolk ellipse and the Genies ring are now transcribed. Every icon maps to an existing
  benefit type in `benefit_types.csv`. The only new row appears to be Faefolk's "score any
  building", which is a choose one over the existing farm, armory, house and market VP benefits.
- Art is available, which unblocks `slots` coordinate tuning. This does not reduce the total; it is
  about an hour per civ and is already inside the fixed tax.
