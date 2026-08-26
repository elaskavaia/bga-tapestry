# Tapestry - Rules

Base game rulebook, converted from `TapestryRules_r13.pdf` (Stonemaier Games, 2019) with
`pdftotext`. The reference guide section below comes from `Tap_ReferenceGuide_r11.pdf`. Both PDFs
live in `~/Develop/bga/bga-assets/Tapestry/` and are not part of this repo.

This is the rulebook, i.e. the source of truth for what the game *should* do. It is a plain
transcription - wording is verbatim, only the layout is reflowed. Icons do not survive text
extraction; where the original printed an icon inline it is written here as `[icon]`, so a sentence
reading oddly is a missing picture, not a missing rule.

Victoria's own rules interpretation (effect ordering, void/mandatory/optional semantics, open
questions to the designer) is a separate document: [FORMAL_RULES.txt](FORMAL_RULES.txt). Where this
file and that one disagree, this file is the printed rule and that one is the ruling actually
implemented.

## Overview

A Civilization Game. 1-5 players; 90-120 minutes; ages 12+; competitive.
Designed by Jamey Stegmaier. Art by Andrew Bosley. Sculpts by Rom Brown.

Create the civilization with the most storied history, starting at the beginning of humankind and
reaching into the future. The paths you choose will vary greatly from real-world history - your
civilization is unique!

In Tapestry, you will advance on 4 advancement tracks (science, technology, exploration, and
military) to earn progressively better benefits. Along the way, you will also improve your income,
build your capital city, leverage your unique abilities, earn victory points, and gain tapestry
cards that will tell the story of your civilization.

## Global setup

1. BOARD: Place the board on the table (big map for 4-5 players; small map for 1-3).
2. TECH CARDS (x33): Shuffle the tech cards. Place the deck face down next to the board and reveal
   3 cards.
3. TAPESTRY CARDS (x50): Shuffle the tapestry cards and place the deck face down next to the board.
4. TERRITORY (x48) and SPACE TILES (x15): Shuffle the territory tiles and place them in face-down
   stacks next to the board. Do the same with the space tiles.
5. LANDMARKS (x18): Place landmark miniatures on top of the matching slots on the landmark board.
   The extra landmarks go near the tech card deck.
6. DICE (one 12-sided and two 6-sided): Place the science die (green) near the science track and the
   conquer dice (red and black) near the military track.

## Player setup

1. CAPITAL CITY MAT (x6): Claim a random capital city mat* and sit near it. Place 2 outpost tokens
   (hexagonal tokens in your player color) on the territory on the map matching the number of your
   capital city. Your other 8 outpost tokens are kept off to the side of your income mat.

   * In a 1-3 player game, pair up the capital city mats based on the numbers on the board. Gain a
   random pair. Choose 1 and discard the other.

2. INCOME MAT (x5): Seed each resource track on your income mat with the corresponding building
   (5 brown farms, 5 grey houses, 5 yellow markets, and 5 red armories). The far left space of each
   row is exposed. Place 1 token for each resource on the 0 space of the resource tracker at the
   bottom of your mat.
3. CIVILIZATION MAT (x16): Gain 2 random civilization mats. Choose 1 and discard the other. Shuffle
   all unused and discarded civilization mats into a deck.
4. PLAYER TOKENS (x13): Place 1 player token on each of the 4 advancement track starting spaces
   (far-left circles on the board) and 1 token on 0 VP. Keep the extras off to the side.

Randomly select the 1st player and begin. Each player's first turn is an income turn (see Income).

## Gameplay

On your turn, you may either collect INCOME to begin a new era or ADVANCE your player token once on
an advancement track (the 4 tracks along the sides of the board) by paying the cost and gaining the
resulting benefit. Then play proceeds clockwise.

## Income

When you use your turn to collect income, you are beginning a new era for your civilization. Other
than the first income turn to begin the game, players will end up taking income turns at different
times.

Follow these steps in order (the chart on your income mat shows which of these apply to income
turns 1-5):

1. Activate civilization abilities (if applicable).

2. Play a tapestry card onto the leftmost blank space on your income mat (if applicable to the
   current era). You must play a tapestry card from hand.*

   IMPORTANT: If you are the first of your neighbors to start a new era, gain resources as shown
   underneath the newly covered space.

   *In the rare case that you do not have a tapestry card in hand, place the top card of the
   tapestry deck face down on your income mat.

3. Upgrade 1 tech card (optional; see Technology) and gain victory points from all exposed VP icons
   on your income mat tracks.

   - Gain 1 VP for each tech card next to your capital city mat.
   - Gain 1 VP for each completed row and column in your capital city (see Buildings and Capital
     City).
   - Gain VP equal to the number shown.
   - Gain 1 VP for each territory on the map you currently control.

4. Gain income from all exposed icons for resources, territory tiles, and tapestry cards on your
   resource tracks. You can have at most 8 of each resource (coins, workers, food, and culture are
   resources).

### Playing the tapestry card

If the tapestry card has a "when played" ability, use it now. If it has a "this era" ability, it
applies from now until the beginning of your next income turn.

If you are the first of your neighbors to take a second income turn, before you play a tapestry card
on top of this icon, gain any 1 resource.

## Advance

Most turns in Tapestry will be used to advance on an advancement track. Follow these steps in order:

1. Pay the cost (the resources indicated under the tier of the track into which you're advancing).
2. Move your player token 1 space forward on the track, then gain the benefit.
3. If available, you may pay to gain the bonus once (e.g., `[icon]:[icon]` means "pay any 1 resource
   to gain 1 tapestry card").

Each advancement track is divided into tiers. If you are the first player to advance into a new tier
(II-IV) by any means, gain the corresponding landmark and place it in your capital city (see
Buildings and Capital City).

The core benefits associated with each track are explained on page 3, and all benefits are explained
in detail on the reference guide. We recommend teaching new players the core benefits before
starting the game, but not the other specific benefits until they're reached.

## Exploration - EXPLORE

First, select 1 territory tile from your supply and place it on an unexplored hex adjacent to a
territory you control, oriented in any direction.

Second, gain 1 VP for each side of the explored territory with at least 1 aligned terrain (water,
mountains, desert, etc; max 6 VP). Ignore the "rivers" between land terrain and the tile edge -
that's just an aesthetic touch.

Third, gain the benefit on the territory tile (e.g., 1 culture).

When you reach Tier IV of the exploration track, you will venture beyond the Earth. Space tiles
offer more powerful benefits than territory tiles. When you explore a space tile, simply place it
next to your income mat and gain the benefits on the tile (it doesn't align with other space tiles).
There are a limited number of space tiles, so it's possible to run out.

## Science - RESEARCH

First, roll the science die, which will result in an icon that represents an advancement track.

Second, you may advance your player token on the corresponding advancement track for free (you may
choose not to advance after seeing the results of the die roll). If you are the first player to
advance into a new tier (II-IV), gain the landmark.

If researching would push your player token off the end of a track (beyond the 12th space), nothing
happens.

Third, if the research icon has an X on it, do not gain the benefit or the bonus (if any). If the
research icon doesn't have an X on it, gain the benefit and you may pay to gain the bonus (if any).

Similar to research, there are benefits that allow you to advance on a specific track: when you gain
one of these benefits, advance for free on the corresponding track and then - if there is no X -
gain the benefit and you may pay to gain the bonus (if any).

## Technology - INVENT / UPGRADE

First, gain a tech card, selecting from the face-up cards or the top of the deck. Replenish a
face-up card immediately after you gain it. If the deck is empty, reshuffle discarded cards to form
a new deck.

Second, place the card to the right of your capital city mat in the bottom row. There is no limit to
the number of tech cards in each row, and there is no immediate benefit from the tech card.

Tech cards provide benefits when they're upgraded. When upgrading, select a tech card in the bottom
or middle row and shift it upwards to the next row. Cards in the top row can't be upgraded.

The benefit gained from upgrading a tech card to the middle row is shown in the circle (e.g.,
advance on the exploration track without gaining the benefit or the bonus).

The benefit gained from upgrading a tech card to the top row is shown in the square. To upgrade to
the top row, you or one of your neighbors must meet the prerequisite noted on the card (e.g., must
currently be in or beyond Tier II on the exploration track).

## Military - CONQUER

First, place an outpost from your supply onto a territory that has no more than 1 token on it and is
adjacent to a territory you control.

Second, roll the 2 conquer dice and gain the benefit shown on 1 of them. The red die includes an
icon that means "1 VP for each territory you control," and the black die includes an icon that means
"the benefit on the territory tile (if any)."

Control refers to a territory on which your outpost is the only upright outpost. You can't conquer
territories you already control.

If you conquer an opponent's territory, "topple" their outpost token (tip it over on its side).
Because there are now 2 tokens on the territory, it cannot be conquered again.

If you attempt to conquer a territory controlled by an opponent, beware of trap cards! Trap cards are
disguised as tapestry cards, but if the opponent discards one from their hand, they will retain
control of the territory (you still gain a benefit from the conquer dice).

You may make and break deals with opponents ("I won't conquer you if you won't conquer me"), but you
can't exchange anything tangible. Once all of your outposts are on the map, you may not conquer any
further.

## Buildings and capital cities

Buildings are permanently placed in your capital city to help you (1) complete districts to gain
instant resources and (2) complete rows and columns to score victory points. You can place buildings
on any open plot in your capital city. Certain plots of land are impassable - you cannot build
there, but they contribute toward the completion of districts, rows, and columns.

There are 2 categories of buildings:

- INCOME BUILDINGS: When you gain a farm, house, market, or armory, pick up the leftmost building of
  that type from your income mat (revealing improvements to your income) and place it in your
  capital city.
- LANDMARK BUILDINGS: Landmarks show which civilization is the first to advance to a new tier on an
  advancement track or the first to invent something (i.e., certain tech cards). Each landmark is a
  specific building miniature with a unique shape that you place in your capital city, aligned with
  the grid. There is exactly 1 of each landmark, so even if one of you finds a way to gain the same
  landmark again, you cannot.

Other notes about buildings:

- When you complete a district by filling all plots in one of the nine indicated 3x3 areas,
  immediately gain any 1 resource.
- When scoring your capital city, gain 1 VP for every completed row and column.
- You may gain and place buildings even if they extend outside of your city grid, as they may not
  always fit in an increasingly crowded city.

## Other important notes

- CIVILIZATIONS: It is possible to gain additional civilizations (end of military track, tech cards,
  etc). If you do, add them to the left of your current civilization mat. If you run out of player
  tokens, use spare cubes.
- OVERLAPPING TURNS: If your turn isn't impacted by decisions being made by the previous player, you
  may proceed to take your turn. This is particularly important during a player's income turn after
  they've played their tapestry card.
- OPTIONAL VS MANDATORY REWARDS: All bonuses in the game are optional. Benefits and landmarks are
  mandatory rewards.
- NEIGHBORS: A few elements of the game have you consider your neighbors. This refers to the players
  sitting to your immediate left and right.
- ACHIEVEMENTS: There are 3 achievements on the board. When you earn each of them for the first
  time, place a player token on the highest available VP space under that achievement. You cannot
  lose achievements or earn the same achievement twice.

  You cannot earn the same achievement twice. For the middle achievement, the 2 outposts you topple
  (from conquering or trapping) may be those of the same or different opponents. For the rightmost
  achievement, if an opponent plays a trap card as you attempt to conquer the middle island, you do
  not gain this achievement.
- VICTORY POINT TRACK: If you exceed 100 VP, place your VP token on the 100 space, and place a
  second player token at 0. If you exceed 200 VP, shift the token over (and so on for 300 and 400).
- VARIABLE LENGTH: While each player will take the same number of income turns (5), the number of
  advance turns will vary.
- AI SINGULARITY: This technology track benefit may result in you having multiple player tokens on
  the same track. Either is eligible for advance turns. When considering the relative position on a
  track, only look at your most advanced token.

## End game

The game ends at different times for each player. Your game ends when you finish your final (5th)
income turn. Gain benefits from your civilization, 1 upgrade, and victory points as shown on your
income mat, but you cannot play a tapestry card or gain income. If other players still have turns
after your game has ended, you may still gain victory points from passive civilization abilities,
but you cannot gain anything else.

When all players have taken their final income turns, the winner is the player with the most victory
points.

In case of a tie, the player with the most total resources remaining is the winner. Otherwise,
players share the victory. A great final score is 300 VP.

(c) 2019 Stonemaier LLC. Tapestry is a trademark of Stonemaier LLC. All Rights Reserved.

# Reference guide

From `Tap_ReferenceGuide_r11.pdf`. The guide prints one row per advancement track, one entry per
board space, left to right. Entries are numbered 1-12 here to match the board spaces after the
starting circle; that alignment was verified against the board data, the text is the guide's.

Where an entry has a second sentence starting "You may then...", that is the *bonus* (optional, paid
for), not the benefit.

## Exploration track

1. Gain 2 territory tiles (always keep territory tiles face up in your supply).
2. Explore: Place 1 territory tile from your supply on the map, gain 1 VP per aligning side, and
   gain the benefit on the tile. You may then pay any 1 resource to gain 1 tapestry card.
3. Explore OR gain 1 farm.
4. Gain 1 territory tile, then explore.
5. Gain 1 VP for each territory you control. You may then pay any 1 resource to gain 1 farm.
6. Gain 1 territory tile and 1 farm. You may then pay any 1 resource to explore.
7. Gain 2 territory tiles, then explore.
8. Gain 1 farm, then gain 1 VP for each farm in your capital city. You may then discard 2 territory
   tiles to gain 5 VP.
9. Gain 2 territory tiles, then explore anywhere on the map. You may then pay any 1 resource to gain
   1 tapestry card.
10. Gain 1 VP per technology track space you've advanced. You may then discard 3 territory tiles to
    gain 10 VP.
11. Gain 3 space tiles, then explore 1 of them (place explored space tiles next to your income mat).
12. Explore a space tile from your supply (place it next to your income mat). You may then pay any 1
    resource to explore another space tile.

## Science track

1. Research: Roll the science die to advance for free (don't gain benefit and bonus).
2. Gain 1 tapestry card. You may then pay any 1 resource to gain 1 house.
3. Research (don't gain benefit and bonus) OR gain 1 house.
4. Gain 1 VP for each tech card in your supply; also gain 1 tapestry card.
5. Research to gain the benefit and pay to gain the bonus (if any). You may then discard 2 tapestry
   cards from hand to gain 5 VP.
6. Research to gain the benefit and pay to gain the bonus (if any) OR gain 1 house.
7. Gain the benefit and pay to gain the bonus (if any) of your current position on any advancement
   track.
8. Gain 1 house, then gain 1 VP for each house in your capital city.
9. Advance on 1 of these tracks, then gain the benefit and pay to gain the bonus (if any).
10. Regress on 1 of these tracks, then gain the benefit and pay to gain the bonus (if any).
11. Advance on 1 of these tracks, then gain the benefit and pay to gain the bonus (if any). Then do
    it again (same or different track).
12. Roll 4 science dice to advance (don't gain the benefits and bonuses). Gain 5 VP per die that
    would push you off a track.

## Technology track

1. Invent: Gain 1 tech card and place it to the right of your capital city mat in the bottom row. If
   you gained a face-up card, replenish it immediately.
2. Gain 1 tapestry card. You may then pay any 1 resource to gain 1 market.
3. Invent 1 tech card OR gain 1 market.
4. You may discard all 3 face-up tech cards and replace them. Invent 1 tech card.
5. Gain either a farm, house, or armory. You may then pay any 1 resource to upgrade 1 tech card.
6. Gain 1 VP for each armory in your capital city and gain 1 market. You may then pay any 1 resource
   to invent 1 tech card.
7. You may discard all 3 face-up tech cards and replace them. Invent 2 tech cards (one at a time).
8. Gain 1 market, then gain 1 VP for each market in your capital city. You may also pay any 1
   resource to upgrade 1 tech card.
9. In any order, upgrade 1 tech card and gain the circle benefit of 1 tech card in your middle row.
10. Gain 1 VP per military and science track space you've advanced.
11. In any order, upgrade 1 tech card and gain the square benefit of 1 tech card in your top row.
    You may then discard 3 tech cards to gain 10 VP.
12. Remove your player token from the technology track and place it on the starting space of any
    track. Gain 1 of each resource. This track still counts as complete.

## Military track

1. Conquer: Place an outpost on a territory adjacent to a territory you control. Roll the 2 conquer
   dice and pick 1 of the benefits rolled.
2. Gain 1 tapestry card. You may then pay any 1 resource to gain 1 armory.
3. Conquer 1 territory OR gain 1 armory.
4. Gain 1 worker and gain 1 VP per territory tile in your supply.
5. Conquer 1 territory and gain 1 armory.
6. Conquer 1 territory and gain 1 tapestry card. You may then pay any 1 resource to gain 1 armory.
7. Conquer 1 territory. If that territory was controlled by an opponent, gain the benefits of both
   conquer dice.
8. Conquer 1 territory anywhere on the map. You may then pay any 1 resource to gain 1 tapestry card.
9. Gain 1 armory and gain 1 VP per tapestry card (in hand and on your income mat).
10. Gain 1 VP per exploration track space you've advanced. Also play a tapestry on top of your
    current tapestry. Only the new card is active.
11. Score your capital city. You may then discard 3 tapestry cards from hand to gain 10 VP.
12. Conquer 1 territory (gain the benefits of both conquer dice). Also gain a random additional
    civilization.

## Tech card benefits

Page 2 of the guide lists all 33 tech cards as pairs: the first line is the **circle** benefit
(gained on upgrading to the middle row), the second is the **square** benefit (top row). The guide
prints them without card names - the names are on the cards themselves - so entries below are in the
guide's own grid order (row by row, left to right) and are matched by their text, not by an id.

1. Gain 1 house. / Gain 1 worker.
2. Gain 1 VP for each farm in your capital city. / Gain 1 farm.
3. Gain 1 VP for each armory in your capital city. / Gain 1 armory.
4. Gain the circle benefit of 1 tech card in your middle row. / Gain the square benefit of 1 tech
   card in your top row.
5. Place the Bakery in your capital city. / Gain 4 VP.
6. Place the Barn in your capital city. / Gain 3 VP.
7. Gain the benefit and pay to gain the bonus of your current position on any track. Use at most
   1x/turn. / Gain 4 VP.
8. Gain 7 VP. / Gain 1 culture.
9. Gain 7 VP. / Gain 1 food.
10. Gain 1 VP per technology track space you've advanced. / Advance on the technology track (no
    benefit/bonus).
11. Explore (place 1 territory tile). / Gain 2 territory tiles.
12. Place the Com Tower in your capital city. / Gain 5 VP.
13. Gain 1 VP for each market in your capital city. / Gain 1 market.
14. Gain 1 VP for each territory you control. / Conquer 1 territory.
15. Gain 1 VP for each of your tech cards. / Invent 1 tech card.
16. Gain 1 farm. / Gain 1 food.
17. Place the Library in your capital city. / Gain 3 VP.
18. Gain 1 market. / Gain 1 coin.
19. Gain 1 VP for each house in your capital city. / Gain 1 house.
20. Gain 5 VP. / Research (no benefit/bonus).
21. Play a tapestry card on top of your current tapestry. / Gain 1 tapestry card.
22. Gain 1 market, house, or farm. / Gain 1 tapestry card.
23. Gain a random additional civilization. / Gain 3 VP.
24. Gain 1 armory. / Gain 1 culture.
25. Gain 1 VP per military track space you've advanced. / Advance on the military track (no
    benefit/bonus).
26. Place the Stock Market in your capital city. / Gain 5 VP.
27. Gain 1 VP per territory tile in your supply. / Gain 1 culture and 1 territory tile.
28. Regress on 1 of these tracks (no benefit/bonus). / Gain 1 coin.
29. Gain 1 culture and 4 VP. / Gain 1 coin.
30. Place the Treasury in your capital city. / Gain 4 VP.
31. Gain 1 VP per science track space you've advanced. / Advance on the science track (no
    benefit/bonus).
32. Conquer 1 territory. / Gain 1 worker.
33. Gain 1 VP per exploration track space you've advanced. / Advance on the exploration track (no
    benefit/bonus).
