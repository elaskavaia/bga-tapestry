# Tapestry - Automa and Shadow Empire rules

Solo (and 2-player Shadow Empire) rules, converted from `Automa_TapRules_r8.pdf` (Stonemaier Games,
2019) with `pdftotext`. Designed by Morten Monrad Pedersen with Lieve Teugels and Nick Shaw.

Companion to [RULES.md](RULES.md) - any rule not explicitly overridden here is still in effect,
including you following the multiplayer rules. This is the source of truth for bot behaviour
(`playerextra` rows, the decision deck, the hex tiebreaker), the same way RULES.md is for the base
game.

Icons do not survive text extraction; where the original printed an icon inline it is written here
as `[icon]`, so a sentence reading oddly is a missing picture, not a missing rule. The decision
cards, tiebreaker cards and difficulty chart are largely iconographic, so those sections below are
the thinnest - the physical cards are authoritative there.

Source PDF lives outside the repo in `~/Develop/bga/bga-assets/Tapestry/`.

## Introduction

This rulebook introduces a system for adding two artificial players, the Automa and the Shadow
Empire. They take the place of human players and are collectively known as bots.

You can use the Automa and the Shadow Empire together to play by yourself or you can use just the
Shadow Empire with 2 human players. For the latter, read the separate rule sheet.

To reduce your effort, the Automa and Shadow Empire play by their own simpler rules. Any rule not
explicitly overridden here is still in effect, which includes you following the multiplayer rules.

## Components

- 2 Automa civilization cards
- 1 double-sided Automa income mat
- 2 Automa player aid cards
- 2 Automa income cards
- 22 decision cards

## The Shadow Empire

The Shadow Empire increases the competition for landmarks and the "complete any advancement track"
achievement.

- It follows a small subset of the Automa's rules.
- It functions as a neighbor and opponent for both you and the Automa.
- Note that it never places its outposts; those are instead placed as part of the Automa's conquer
  actions.

## Components to remove

Remove these tapestries from the game: Age of Sail*, Alliance, Coal Baron*, Dictatorship, Diplomacy*,
Espionage*, Marriage of State, Oil Magnate, Olympic Host, Steam Tycoon*, Trade Economy*, and 2 traps.

Remove these civilizations from the game: Futurists*, Heralds*, Inventors*, and Traders.

*These components may be used, but they will either unfairly benefit or hurt you.

## Setup

Set up as you would for a 3-player game except that you always start on the territory labeled "2/4"
no matter which capital city mat you're using.

Set up for yourself following the normal rules, then for the bots:

1. Choose a color for the Automa. Give it the following components and nothing else:
   a. The Automa income mat with the normal side up.
   b. All outposts of its color. Place 2 of them on the territory labeled "3/5".
   c. All player tokens of its color: Place 1 on 0 VP and 1 on the starting space of each
      advancement track.
   d. Roll the science die and place the Automa civilization card side with the corresponding icon
      face-up on the Automa's income mat.
   e. Place 1 of the Automa's outposts next to the track indicated by the heart icon on its
      civilization card. This is the Automa's favorite track.

2. Choose a color for the Shadow Empire. Give it the following components and nothing else:
   a. All outposts of its color.
   b. Five player tokens. Place 1 on the starting space of each advancement track. Set 1 aside.
   c. Roll the science die until you get a track different than the Automa's favorite track. Place 1
      of its outposts by this track. This is the Shadow Empire's favorite track.

3. Shuffle decision cards 8-22 (they have rectangles in the bottom right corner - the color is
   irrelevant) to form the face-down progress deck.

4. Shuffle the topmost card from the progress deck and the 7 remaining decision cards (1-7, with a
   circle in the bottom right corner) to form the face-down decision deck.

## What the bots never gain

The bots only gain what's explicitly mentioned in this rulebook (e.g., they never gain income
buildings or resources, and the Shadow Empire also never gains VP).

## A bot turn

The bot pair take their turn together either before or after you depending on whether you're first
player or not.

If you need to draw a decision card and the decision deck is empty, the bots take an income turn
(see Income turns). Otherwise follow this procedure:

1. Discard the decision card pair from last turn (if any).
2. Draw the topmost 2 cards of the decision deck and place them face up randomly as a decision card
   pair.
3. If the decision deck is now empty and the track card has an income icon, the bots take their
   income turn and you skip the last step of this procedure.
4. Otherwise:
   a. Advance on a track for the Automa.
   b. Advance on a track for the Shadow Empire.

You can look through the discard pile at any time.

## Anatomy of a decision card

Decision cards consist of several elements: an Automa track indicator, a Shadow Empire track
indicator (the same icons in grey and with an "S" on them), track tiebreakers, a hex tiebreaker, an
income indicator, a toppled outpost indicator, a card type, and a card ID.

The Card ID number has no gameplay function.

### A decision card pair

Decision cards are placed as pairs on the table to determine what the bots do. The left card is
called the **track card** and the right card is called the **tiebreaker card**. Only the active
section (highlighted in purple) is used during the turn.

## Advance on a track

The blue track indicator icon on the track card defines one or more valid tracks for the Automa. The
Shadow Empire uses the same icons in grey and with an "S" on them.

The valid tracks are:

- All tracks where the bot hasn't reached the end.
- The track(s) with the shortest distance from the bot's token to either an unclaimed landmark or
  the end of the track. Ignore tracks where it has reached the end.
- The track(s) with the shortest distance from the bot's token to the end of the track. Ignore
  tracks where it has reached the end.
- The bot's favorite track.

If more than one track is valid, the bot advances on the track that's first in the track tiebreaker
section on the tiebreaker card - top to bottom for the Automa and bottom to top for the Shadow
Empire.

Example: If the military and technology tracks are valid for an Automa advance, the tiebreakers
would make it pick the technology track.

(The bullets above each correspond to a distinct icon on the track card; the icons themselves did
not survive extraction, so which bullet a given printed card means must be read off the card.)

## Benefits

- When advancing to a new space the Automa only gains benefits from icons that are listed in this
  chart. Benefits and text not shown here are ignored.
- If a benefit is granted multiple times on a space, the Automa only gains it once.
- The Shadow Empire never gains benefits.
- Neither bot gains bonuses.

The chart maps a normal benefit to the Automa's version of it:

- Research / advance icons: roll and advance on the indicated track (with or without the benefit).
- Roll until it selects one of the indicated tracks, then carry out the corresponding
  advancement/regression.
- Discard all 3 face-up tech cards and replace them: as printed.
- Conquer: see Conquer.
- Explore: see Explore.
- Gain a tapestry card: give the Automa a tapestry card face down next to its mat.

Designer's note: The Automa implements these icons (and not others) because they relate to
interactions between players.

## Landmarks

If either bot gains a landmark, place it in the `[icon]` box on the Automa's income mat. All
landmarks there belong to the Automa.

## The hex tiebreaker

All procedures for placing tiles and outposts on the board use the hex tiebreaker to choose 1 hex
among a set of valid hexes.

Using the hex tiebreaker icon on the tiebreaker card, start with the hex that the black arrow points
to. Look at the hexes in order along the row in the direction indicated by that arrow.

If you don't reach a valid hex, go on to the row pointed at by the gray arrow, then the row beyond
that, etc. Continue until you reach a valid hex. The Automa chooses this hex.

## Distance

Some of the Automa actions refer to a hex closest to a specific hex. This is the hex that is the
fewest hexes away, not "as the crow flies", but via the shortest path of hexes with 0 Automa
outposts and 0-1 of your tokens (i.e., a path along which the Automa could do a series of
conquests). The path can include empty hexes.

## Conquer

The Automa's conquer actions are divided into 2 different procedures:

1. If the Automa can legally conquer a territory you control, carry out the Conquer Opponent
   procedure.
2. Otherwise carry out the Conquer Neutral procedure.

### Conquer opponent

Valid territories: All territories you control, which the Automa can legally conquer, are valid.

Tiebreakers:

1. If the Automa can still gain the "middle island" achievement, only the valid territories closest
   to the middle island remain valid.
2. Use the hex tiebreaker to pick one territory among the valid ones.

Action: Place an outpost from the Automa's supply on the chosen territory and topple your outpost,
or the Automa's outpost if you play a trap.

### Conquer neutral

Valid hexes:

1. All hexes that can legally be conquered or explored by the Automa are valid.
2. Hexes adjacent to territories you control are only valid if a `[icon]` is on the tiebreaker card.

If there are no valid hexes, skip the action.

Tiebreakers:

1. If the Automa can still gain the "middle island" achievement, only the valid hexes closest to the
   middle island remain valid.
2. If you control any territories that have a single token on them, only valid hexes closest to such
   territories remain valid.
3. If you don't control a territory with a single token on it, only valid hexes closest to any
   territory you control remain valid.
4. Use the hex tiebreaker to pick one hex among the valid ones.

Actions:

1. If the Automa is conquering an empty hex: Draw a territory tile and place it face-up with a random
   orientation on the chosen hex.
2. Place an outpost from the Automa's supply (of its own color) on the conquered territory.
3. If the `[icon]` is on the tiebreaker card and the conquered terrain isn't the middle island, place
   one of the Shadow Empire's outposts toppled on the territory.

## Explore

Valid hexes: All hexes that the Automa can legally explore are valid. If there are none, skip this
action.

Tiebreakers:

1. If the Automa doesn't have military as its favorite track, then only the valid hexes furthest
   from territories you control remain valid.
2. Use the hex tiebreaker to pick one hex among the valid ones.

Action: Draw a territory tile and place it face up with a random orientation on the chosen hex.

## Traps

If you conquer a territory controlled by the Automa and it has any tapestry cards next to its mat:

1. Discard one of its tapestry cards at random.
2. If that card was a trap, the Automa retains control of the territory and your outpost enters play
   toppled.
3. Repeat this procedure until the Automa either discards a trap or runs out of tapestry cards.

## Achievements

- The Automa earns achievements and the VP from them in the same way as you, except that only your
  outposts count towards the "topple 2 opponent outposts" achievement.
- The Shadow Empire can only earn the "complete any advancement track" achievement but gains no VP
  from doing so.

## Income turns

The Automa gains what's listed on the income chart on its mat, top to bottom, left column first.

If the Automa takes an action during an income turn that requires tiebreaking, draw and use the top
card of the progress deck in turn 1 (reshuffle afterwards) and the latest tiebreaker card in income
turns 2-5.

The income chart steps are:

- If a bot's token has reached the end of its favorite track or if there's a further advanced token
  there, then its favorite track becomes the track that would be chosen by `[icon]` or `[icon]`.
  Move its "favorite" outpost there (it could be the same track or the favorite of the other bot).
- Advance track tokens of both bots using the most recent decision card pair. The Automa gains the
  benefits (if any). This means that the Automa will do an advance and gain income during a single
  turn. It also means that the most recent card pair is sometimes used twice.
- The Automa gains the income turn bonus (if any) from its civilization card.
- The Automa gains VP for each landmark on its mat and for each controlled territory.
- The Automa gains VP for each space advanced on each of the indicated tracks.
- Add the 2 topmost cards from the progress deck to the decision deck discard pile.
- Place a card from the tapestry deck (not from the Automa's tapestry cards) face down on the
  leftmost empty tapestry space on the Automa's income mat.
- Shuffle all the decision deck cards from the most recent era (including any gained this income
  turn) to form a new face-down decision deck.

For each of the VP icons, multiply the number of advancements/landmarks/territories by the leftmost
multiplier not covered by a tapestry card.

Note that contrary to human players, the Automa gains VP before playing a tapestry card and it
almost exclusively gains VP during income turns.

If the Automa is the first to start a new era, it gains the VP shown.

Example: During income turn 3, the multipliers are x2, x1, and x1, respectively. This means that the
Automa gains 2 VP per territory it controls, 2 VP per landmark on its mat, 1 VP for each advance on
the military and science tracks, and 1 VP for each advance on the exploration and technology tracks.
If it was the first to start a new era, it also gains 3 VP.

## Game end

Determine the winner following the instructions in the normal rulebook. The Shadow Empire doesn't
participate in determining the winner at the end of the game. Only you or the Automa can win.

## Difficulty levels

You can choose one of the difficulty levels below to get the level of challenge you want. The setup
instructions use "Automa the Average". For levels 1, 3, and 4 you must cover the income chart of the
Automa's mat with the corresponding income card. For levels 5 and 6, flip the Automa's mat to the
Hard side.

- Track icons indicate track advances to be performed in the usual way, as the first event in income
  turn 1.
- On level 6, the Automa starts with an extra civilization (your choice). That civilization card's
  favorite track has no effect. During income turns, apply the effects of the first civilization
  before the second.

The levels, with the income mat side each uses:

1. Automa the Underachiever - Normal
2. Automa the Average - Normal
3. Automa the Slightly Intimidating - Normal
4. Automa the Somewhat Awesome - Normal
5. Automa the Definitely Awesome - Hard
6. Automa the Crusher of Dreams - Hard

The chart also has "Income turn 1: you" and "Income turn 1: bots" columns of track-advance icons per
level, of which only level 5's "Advance on favorite track" survived text extraction. Read the
per-level advances off the printed chart, not from here.

## Component card text (partial, extraction-limited)

The two Automa civilization cards are named **Conquerors** and **Engineers**. Their income-turn text
extracted as two bullets that could not be reliably attributed to one card or the other:

- If the Automa controls fewer territories than the income turn number, it does a conquer action.
- If more than 2 territories controlled by the Automa have only 1 token, place a toppled Shadow
  Empire outpost on one of those (use hex tiebreaker).

Do not cite the pairing above as a rule. The physical cards (and `Tap_CivIncAid_r6`) are
authoritative for which civilization has which ability.

The Automa income cards read: "INCOME TURNS 2-5: The Automa gains 1 extra `[icon]` for each territory
it controls" and "INCOME TURNS 2-5: The Automa gains 1 extra `[icon]` for each landmark it has."

(c) 2019 Stonemaier LLC. Tapestry is a trademark of Stonemaier LLC. All Rights Reserved.
