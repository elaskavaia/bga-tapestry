## Victoria

NEW CIVILIZATIONS

- [ ] Faefolk: verify the 7 ring slot positions in the studio (measured off civ_ff.webp, not yet seen
      rendered on a real mat).

- [ ] Faefolk: no test goes through the game object, so the two case CIV_FAEFOLK lines in
      saction_civTokenAdvance and argCivAbilitySingle are unverified - delete them and the suite still
      passes, while a real game falls through to the generic path and places a second cube.

- [ ] Faefolk: FaefolkUT::resolveFlicker scans the row list instead of popping the head of the stack, so
      testCardPlayedWhileGainingIsCounted passes whether the flicker is queued before or after the
      tapestry gain. It is the regression guard for the gain-then-count ordering and currently guards
      nothing.

- [ ] BE_TECH_CARD (26) is broken as a queued benefit: awardBenefits case 26 sends it to the invent
      state, but stInvent asserts the benefit's r rule is "i" and row 26 has "g". Nothing used it until
      Faefolk did, which is how it surfaced. Faefolk now uses BE_INVENT instead; row 26 is still a trap
      for the next caller.

- [ ] Werefolk: verify in the studio that the space tile explored onto the mat renders where the art
      expects it. The generic ".civilization .space_tile" rule positions it, and no Werefolk specific
      CSS was added.

- [ ] Werefolk: onSpaceTileClick has no checkActiveSlot guard, unlike onTerritoryTileClick and the track
      handlers, so a space tile the new spaceExploration filter dimmed is still clickable and fires
      explore_space just to earn a server rejection toast. Add "if (!this.checkActiveSlot(id)) return;"
      to the default branch.

- [x] Werefolk, ANSWERED: if no space tile can be gained (deck and discard both empty) there is nothing
      to flip, so the whole ability is skipped - ruled by Victoria, recorded in FORMAL_RULES 5.3. flip()
      now early-outs with a "cannot gain a space tile" message when drawTile gains nothing. Test:
      testEmptyDeckAndDiscardSkipsTheFlip.

- [ ] Werefolk cleanup: queueEraCivAbility reads income_trigger, computes from/to and calls in_range, then
      on a miss hands off to the parent which re-reads the same income_trigger and re-runs the same check
      just to reach its else branch and post the "not applicable in era" chat line. Extract the range test
      into an AbsCivilization helper both can call, instead of nine duplicated lines per civ.

- [ ] Coal baron reset when spies was using it
- [ ] Utilitarients - no city when they place landmark

- [ ] Several civ mat queries build their LIKE pattern as `civ_$cid\_%` (PGameXBody.php 4849, 4951, 4955,
      4996) or `civ_6_%` / `civ_9_%` / `civ_12_%` (3849, 7468, 11273) instead of escaping the underscore
      the way getStructuresOnCiv does (`civ\_$cid\_%`). An unescaped underscore is a single character
      wildcard, so those helpers do not search quite the same thing. Harmless with today's location names.

- [ ] soft_block is registered as game state label id 99 (PGameXBody.php initGameStateLabels), but the
      BGA docs only allow ids 10-89 for globals (1-9 framework, 100-199 gameoptions; 90-99 undocumented).
      It works today and is debug-only, but it relies on unspecified framework behavior. Consider moving
      it to a free id under 90; do not add more ids in 90-99.

RULES

VISUAL EFFECTS

- [ ] Show color of player who owns Nomads buildings?s TODO

TOOLTIPS

- [ ] No tooltips on on-board achievement
- [ ] Tooltips on slots of Civ cards especiall mystic

LOGS

- [ ] Nice to show building icons

TRANSLATION

-

JAMEY
https://boardgamearena.com/table?table=183993490
Tapestry cards
“Tyranny” had a series of glitches—it wouldn’t let the player play a card on top of it, and it prevented the game from continuing due to reasons we didn’t completely understand (something related to the income turn after an era in which Tyranny was active.
“Olympic Host” should given the person who plays it the first choice, but it didn’t FIXED

“Vaccine” should give you the option to advance or not advance, and it shouldn’t provide a benefit/bonus. However, in the game it didn’t give a choice, and it provided the benefit and bonus.
UI
I like that the board can rotate, but it frequently rotated without me asking it to. I couldn’t pinpoint why or when this was happening—it seemed to be random.
Because the names on the advancement and income tracks are sometimes important to read, I would suggest adding place on each track where you can hover your mouse in such a way that the entire track will appear zoomed into the foreground.
Even though the landmark track is available on the screen, I think it would help if players could hover over a landmark icon on an advancement track or tech card and see the landmark size (the number of plots it will cover). Also, in Plans & Ploys we add little tokens that are placed on the board during setup to show landmarks that have or haven’t been gained yet. It might be worth adding those tokens to the core game on BGA.
There were some glitches around placing buildings in capital cities that refreshing seemed to help. I’d also recommend adding an indicator for landmark placement that shows clearly that the highlighted plots are the eligible upper left plots for the landmarks (it’s a clever solution—it just needs to be communicated to the player).
It would be helpful to see in other players’ tableaus (near their income mats) if they have tapestry cards in hand and how many. They would be face down. This information is in the upper right of the screen, but pretty much everything else is tracked in their tableaus, so it would be helpful for the tapestry cards to appear there too.
Little things
In our 4-player game (username “jameystegmaier” in case you need to look it up), even though my neighbor passed into a new era first, I still received the first-to-pass bonus. We thought maybe this was due to the missing fifth player between us on the map, but that player should be ignored whenever looking at neighbors. FIXED
In the scrolling sidebar, the word “adjustments” is misspelled in reference to civ adjustments. FIXED
In certain orientations of the board, the starting outposts appeared to stack on top of each other instead of next to each other. FIXED
In the text explaining science die benefits, I think “benefit” was misspelled. FIXED

Questions for Jamey (orig dev): 1. If you gain a new Civ during 1st step of income turn (e.g. Inventors upgrade radio to square) can you use that civ ability straight away (e.g. if one of the ‘income turn’ abilities…?). Was answered here (Inventors + Radio technology during the first step of the income | BoardGameGeek) as ‘B’ which I think means you are okay to use it’s ability…? For now, not coded. 2. Nomadic structures.
a. Can you conquer adjacent to these? Assumed yes.
b. Can you play a trap card if a player tries to conquer these? Assumed yes.
c. If you are conquering a nomadic structure, does this count as a topple? Assumed yes. 3. Age of Discovery vs Broker of Peace, Theocracy, Dictatorship? 4. Discarding tech cards bonus: can you discard a tech card if there is an inventor token on it? 5. Socialism: if you already match an opponent on a track can you move the token? Assumed no. 6. Dark Ages: Is the intention here to advance one token 3 places and the other three regress if possible? (I ask as the AI Singularity could have moved a token so one track is doubled up which then makes the wording of this card very difficult to interpret as not all 4 ‘tracks’ will be in play). Assuming for now it’s essentially the tokens.
id=86694803

Bug: cap mats
