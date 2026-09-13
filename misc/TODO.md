## Victoria

Triage 2026-09-13. Tags: [blocker] breaks or strands a table, [rules] wrong game rule, [ux] confusing
or misleading client, [ui-temp] cosmetic, [debt] test gaps and cleanup, [feature] new content. Priority: P1 must fix before
the FF deploy, P2 before the deploy but narrower or cheaper, P3 after, P4 no action planned.

P1 - MUST FIX BEFORE THE FF DEPLOY

- [x] [blocker/P1] FIXED. TYRANNY with 2 or more tapestry cards gained at once (Jamey's "it wouldn't
      let the player play a card on top of it" and "prevented the game from continuing", JAMEY
      below). A gain still queues one benefit 64 row per card, but argTapestryCard now offers the
      whole gain at once (tyranny_cards, the cards of every pending TYRANNY row) instead of only the
      card whose row popped first, and stTapestryCard voids a TYRANNY row that has nothing left to
      offer, which is every other row of the gain once one card has covered TYRANNY. A TYRANNY row
      is told apart from a HERALDS or MERFOLK benefit 64 by its data being a bare card id.
      tests/TyrannyTest.php. The 5 VP on every overplay is a separate item under P2.
      NOTE: the client half (tapestry.js, one button per gained card and only those cards clickable)
      is not browser-verified, please confirm on Studio.

- [ ] [blocker/P1] "generated notifications are larger than 128k" (seen at 331844 bytes) on a solo
      table while the Automa and Shadow Empire ran their end of game turns, studio 2026-09-08. The
      move was lost and the table sat in benefitManager with no active player; a page reload showed
      the game had in fact ended normally, so nothing was corrupted. Not Celestials, the row was an
      Automa research turn. Worth finding which notification is that big before the FF deploy,
      since a real table cannot always be rescued by a reload. Seen again at 339182 bytes on the
      Artificers solo table 962350 (2026-09-09), this time on "Automa conquers an empty territory"
      during Automa turn 9, right after the human's income turn 5 confirm: the log lost the final
      scoring lines and only the reload showed the end screen. Two sightings at roughly the same
      size on different Automa rows points at the request that runs every remaining Automa turn in
      one go once the human is finished, not at one fat notification.

- [-] [rules/P1] keepCard leaks hidden tapestry information to the whole table. arg_keepCard
      (PGameXBody around 10222) is a plain public state arg, so every opponent gets the full
      candidate list and the client renders the cards face up; then effect_keepCard moves the kept
      card with effect_moveCard, which logs '${player_name} gains ${card_name}' (PGameXBody 4958),
      and drops the rejects through effect_discardCard, which logs '${player_name} discards
      ${card_type_name} ${card_name}' (4904). Seen live on table 965881: as laskava1 the two PSIONICS
      candidates arrived in gamestate.args and the log read "laskava0 gains PLEA FOR AID" /
      "laskava0 discards Tapestry TRAP". The normal draw path does not do this - awardCard sends
      CARD_TAPESTRY with \_private and send_cards null. Hits ILLUMINATI's setup draw 3 keep 1, every
      PSIONICS tapestry sample and GAMBLERS. Leaking a discarded TRAP is the worst case: the opponent
      now knows the conquer is safe.
      Fix needs both halves: private state args for the tapestry case, and a private (or name-less)
      notification for the keep and the discard when card_type is CARD_TAPESTRY.

P2 - BEFORE THE DEPLOY, NARROWER OR CHEAPER

- [ ] [rules/P2] Utilitarians: no city when they place a landmark. The landmark row is declinable
      and never returns: there is no income_trigger in material, so AbsCivilization.php 204 defaults
      decline=true; a decline cashes the row and the landmark sits on civilization_39 forever. Fix
      is a material line. The same block has a null-unsafe getObjectFromDB with no player filter
      (PGameXBody.php 5445).

- [ ] [blocker/ux/P2] place_structure never validates the requested x_y against the options
      argPlaceStructure computed, so the only thing stopping an illegal cell is the client "possible"
      class plus whatever effect_placeOnCapitalMat happens to assert. Pre-existing, and it applies to
      income buildings and landmarks as much as to the Weefolk token; the token's own "only in a full
      city" rule is now enforced server-side, the general case is not.
      The other half of it, seen in the studio 2026-09-08 placing a 2 by 2 landmark: the client
      offers Confirm for a cell the server then refuses, and the refusal is invisible. The
      "Invalid structure placement" userAssert (effect_placeOnCapitalMat, PGameXBody.php around 8740)
      reaches the console but no toast and no title change, so the state just sits there and looks
      like the button did nothing. Anything that reports the rejection would do.

- [ ] [rules/P2] TYRANNY awards 5 VP on every overplay (PGameXBody.php 3710); the card says "first
      and only time". Separate from the LIFO blocker under P1.

- [ ] [rules/P2] getDeckFor inside dbPickCardsForLocation (PGameXBody around 2489) silently changed
      MYSTICS under adjustment 8: their draw-and-keep tapestry rows (BE_ILLUMINATI_DRAW, GAMBLERS 311
      and 319, anything through effect_drawFromBenefit) now draw from the private deck_13 instead of
      the public deck. It is coherent with effect_discardCard already sending their rejects to
      discard_13 (the old code leaked public cards into the private discard), but it is an untested
      rules change: confirm it against the MYSTICS mat text and add a MysticsTest case for a
      draw-and-keep row either way. Needs a ruling plus a test either way.

- [ ] [rules/P2] VACCINE (Jamey: "should give you the option to advance or not advance, and it
      shouldn't provide a benefit/bonus. However, in the game it didn't give a choice, and it
      provided the benefit and bonus"). The "gave benefit and bonus" half is not reproducible (row 77
      has flags 0). The "no choice" half is real but conflicts with FORMAL_RULES.txt 211 MANDATORY.7
      (Mike Young). Needs Victoria's ruling, not a code change yet.

- [ ] [blocker/P2] Global 38 (selected_space_tile) is written at every turn start and end of every
      table, old tables included, and nothing inserts the row: the project never calls
      setGameStateInitialValue and upgradeTableDb did not touch it. Reads are gated behind
      BE_WEREFOLK_EXPLORE, so an old table only ever UPDATEs a missing row, but a read on a missing
      row throws. starting_player (37) was added the same way in June 2024. One line in
      upgradeTableDb behind a $from_version check is cheap insurance:
      INSERT IGNORE INTO global (global_id, global_value) VALUES (38, 0). Without it, grep the
      error log for selected_space_tile after the deploy.

- [ ] [blocker/P2] ILLUMINATI: black face 1 outside a conquer (rows 324/330) reads whatever hex
      `getSelectedMapHex` still holds. Before any conquer that is coords 0_0, `getMapHexData`
      answers null and `getTileBenefit` raises a PHP warning before falling through to "no
      benefit". The roller already hits this today through row 330; ILLUMINATI makes it reachable
      for the owner too, including when the roller declines row 330 in favour of 202. Guard
      `getTileBenefit` against a missing hex.

- [ ] [ux/P2] Board rotation (Jamey: "it frequently rotated without me asking it to ... it seemed to
      be random"). tapestry.js 519-522 binds the four tech_age_N_0 corners to boardRotate, so
      clicking a tech deck to look at it rotates the board. Not random, just undiscoverable.

- [ ] [ux/P2] Genies and Faefolk: clicking any holder on the mat during civAbility sends
      civTokenAdvance with that spot (the checkActiveSlot guard in onCubeHolderClick, tapestry.js
      around 4842, is commented out), so a legal-looking click on the ring or the token pile earns
      an ERR:Genies:13 (ERR:Faefolk:13) system error toast; clicking spot 1 silently fires the
      ability like the button. Fix: either "case this.CON.CIV_GENIES: return;" beside
      "case 15: return;" in that handler, or restore the guard for every civ.
      Reproduced: clicking a ring holder during the ability state produced the "Internal Error ...
      [ERR:Genies:13]" toast in the log. The state survived and the button still worked afterwards.
      One-line fix; system errors get bug-reported even when harmless.

- [ ] [ux/perf/P2] Era 5 cost: getTapestryEra runs isExtendedPlay on every isTapestryActive,
      stTapestryCard and playTapestryCard call for a player in era 5, and that runs getAllCivs plus a
      getCivilizationInstance per civ in hand. 42 isTapestryActive call sites, none inside a hex
      loop. Correctness is unchanged without an extended play civ. getTapestryEra already short
      circuits eras 1-4 on the era value alone; memoizing the civ answer per player per request
      would close the rest. This is the one item that touches every table, not only FF ones, since
      every player passes through era 5. A per-request cache array on hasExtendedPlayCiv keyed by
      player id is about five lines.

- [ ] [ux/P2] Elder Ones: the whole client half is unverified, there are no JS tests. Four things to
      see on a real table: the two generic slots_choice buttons on income turns 2-4, the bonus state
      on income turn 5 with a hand of tapestry cards (multi select plus the new "up to 6" prompt),
      the red "End my game" button with its confirmation dialog on an extended play turn, and the
      player panel greying out only when the player actually stops rather than at income turn 5.

- [ ] [ux/P2] Weefolk: there are no JS tests, so the client half is unverified. Three pieces to
      check in the studio: the cube div is found as "cube_<id>" in placeStructure (the branch keys
      off args.structure_type == BUILDING_CUBE, and BUILDING_* constants were only just added to
      addConstants; plantToken now notifies moveStructure so the div exists before the state opens)
      - a miss there is a hard JS error, not a toast; the build prompt's territory tile selection
      reaches onCivSpotHandler as clientStateArgs.extra; and the opponent's capital cells accept
      the click in the replacement case, where the cell is occupied but marked possible.

- [ ] [debt/P2] Deploy checklist: the income mat migration guard in upgradeTableDb
      (`$from_version <= 2609091200`, tapestry.game.php 96) only has to sit above the last prod
      deploy version so in-flight tables migrate; the exact number does not matter and a re-run
      cannot happen since BGA bumps the table version after the upgrade. Too low and in-flight
      tables never get spots on their income rows and die on ERR:game:04 at the next income turn.
      Also finish that function's comment, it stops mid-sentence at "and goes".

P3

- [ ] [rules/P3] Coal Baron + Spies (was "Coal baron reset when spies was using it"): argExplore
      (PGameXBody.php 10034) gates the marker on isTapestryActive, which is false for the Spies
      player, so they explore with any tile instead of the drawn one. Silent, in their favour.
      There is a commented-out SPIES block at 3474-3478. Also a speculative stuck state if the
      global coal_baron is left set.

- [ ] [rules/P3] Merfolk + Elder Ones on one player: FORMAL_RULES CIV.MERFOLK.6 (Mike Young, BGG
      3052380) lets the player pick a Merfolk turn or an Elder Ones advance turn each turn.
      getExtendedPlayCiv (PGameXBody around 3386) takes the first civ found and warns ERR:game:03,
      so the second civ's turn type is never offered and the end condition (neither kind of turn
      possible) is not checked. Needs the per-turn choice, the combined end test and a test that
      gains the second civ via military 12 or tech card 23 before income turn 5.

- [ ] [rules/P3] conquerDieBenefit (PGameXBody around 8066) refuses a die whose face pays nothing
      at all - "This die has net effect of zero. Want to try another one?" - and it is a hard server
      assert, not a warning. Harmless for a plain player, but PSIONICS makes it a real choice: the
      die the roller does not claim stays showing its second roll (CIV.PSIONICS.9) and that face is
      what other effects read (CIV.PSIONICS.10, UTILITARIANS Barracks, a TRADERS owner). Claiming
      the worthless face to control what the other die shows is currently impossible. Decide
      whether to allow it when the player has more than two faces on offer.

- [ ] [rules?/P3] Alchemists: removeCubes() is called at the four exits of the roll sequence, right
      after the final benefits are queued, so the kept-die cubes vanish from the mat before the
      player resolves them. Queue BE_CIV_END once instead and clear the cubes in endCivAbility, the
      way Genies and Werefolk do. Check first whether BE_ALCHEMISTS_DIE in the level 9 branch relies
      on getRemainingDice() seeing an empty mat.

- [ ] [rules?/P3] Elder Ones: stPlayerTurn returns early when a civ has activated abilities or the
      player owns a playable lighthouse (PGameXBody.php around 11290), before the "no affordable
      advance" test, so an extended play player in that position is never auto-finished and has to
      press "End my game" themselves. That mirrors what happens to everyone else (no auto income
      either), but for an Elder Ones player the only other button ends their game for good. Confirm
      this is the wanted reading of FORMAL_RULES CIV.ELDER_ONES.2, or move the extended play test
      above the two early returns.

- [ ] [rules?/P3] The open "Questions for Jamey (orig dev)" block under JAMEY below. Blocked on the
      designer.

- [-] [blocker-latent/P3] BE_TECH_CARD (26) is broken as a queued benefit: awardBenefits case 26
      sends it to the invent state, but stInvent asserts the benefit's r rule is "i" and row 26 has
      "g". Nothing used it until Faefolk did, which is how it surfaced. Faefolk now uses BE_INVENT
      instead; row 26 is still a trap for the next caller.

- [ ] [ux/P3] Celestials: onCelestialTokenClick (tapestry.js around 851) opens with
      disconnectAllTemp, which strips the handler and the active_slot class off every cube, so once
      a token is picked there is no way to pick a different one short of Undo. Only bites with two
      or more inert tokens on the map, which is Celestials plus an Infiltrators or Isolationists
      one. The target hexes still work, they are connected permanently in setupLand, not as temp
      handlers. Fix: disconnect only the target hexes' highlight, or re-connect the cubes after
      showing targets.

- [ ] [ux/P3] Werefolk: onSpaceTileClick has no checkActiveSlot guard, unlike onTerritoryTileClick
      and the track handlers, so a space tile the new spaceExploration filter dimmed is still
      clickable and fires explore_space just to earn a server rejection toast. Add
      "if (!this.checkActiveSlot(id)) return;" to the default branch.

- [ ] [ux/P3] A territory tile gained through effect_moveCard logs without its type: "laskava0
      gains 27", while the discard of the same tile reads "laskava0 discards Territory 30". The gain
      message (PGameXBody 4958) uses ${card_name} alone where the discard (4904) uses
      ${card_type_name} ${card_name}. Territory tiles have no name, only a number, so the gain line
      is a bare integer. Same message serves every card type, so adding the type word fixes all of
      them at once.

- [ ] [ux/P3] No tooltips on on-board achievement
- [ ] [ux/P3] Tooltips on slots of Civ cards especially mystic
- [ ] [ux/P3] Jamey: a hover spot on each advancement and income track that zooms the whole track
      into the foreground, since the track names are sometimes important to read.
- [ ] [ux/P3] Jamey: show in each opponent's tableau (near the income mat) whether they hold
      tapestry cards and how many, face down. It is in the upper right today, everything else is
      tracked in the tableau.

- [ ] [ui-temp/P3] The benefit stack tooltip prints a bonus row's raw quantity (tapestry.js around
      4785), so the Elder Ones trade reads "Bonus:-6 x Tapestry" and the older unlimited row reads
      "Bonus:-1 x". Render negatives as "up to 6" and "any number of" instead.

- [ ] [ui-temp/P3] Elder Ones: during extended play updateCurrentEra finds no era 5 slot and drops
      the income mat highlight, so the era 4 tapestry that is still in force looks inactive. Keeping
      era 4 lit while isExtendedPlay holds needs the flag on the client side (argPlayerTurn already
      sends extended_play, the panel does not get it).

- [ ] [ui-temp/P3] Celestials: moveStructure no longer gates a cube on a land slot behind
      Infiltrators ownership, so any cube on a territory now sits on the hex itself rather than in
      an outpost slot. Check the three other cubes that reach a territory: an Infiltrators token, an
      Isolationists token (now placed inert, so its topple visual changed too) and a MILITANTS cube
      under variant 4. The overlap this caused is dealt with: the token used to be 28px at right
      50% / top 20px, which is wider than an outpost and covered slot 1 whole (measured 100% of its
      width and 59% of its height, roof included). The hex is 91 by 79 and the two slots span 20% to
      80% of it, so no 28px position clears them; the token is now 20px at right 60% / top 46%,
      which sits on the outpost's lower body and leaves the roof read, and the second token moved
      to right 38% to match. Both were only checked against the hex geometry, never seen rendered -
      eyeball them.

- [ ] [ui-temp/P3] Genies: verify the 8 ring slot positions and the token pile (slot 0, 4 cubes
      wide) in the studio (measured off civ_ff.webp, not yet seen rendered on a real mat).

- [ ] [ui-temp/P3] Weefolk: verify in the studio that the plot token renders inside the opponent's
      capital cell and in the capital_helper preview. The two CSS rules at the end of tapestry.css
      were written blind against the 26.5px cell and the helper box, never seen on a real mat.

- [ ] [feature/P3] Cap mats: add the 2 adjusted capital mats (was "Bug: cap mats").

- [ ] [debt/P3] The pre-expansion civs have no test file at all (Architects, Renegades, Craftsmen,
      Gamblers, Collectors, Traders, Alchemists, Mystics, Advisors), so their case lines in
      saction_civTokenAdvance and both argCivAbilitySingle switches can still be deleted with the
      suite green. GameUT::civTokenAdvance is the harness the FF civs now use, so a test file per
      civ is all it takes.

P4 / NO ACTION

Regression review of every non-civ change since 1fad461 (2026-08-26, the base of the Fantasies and
Futures work): PGameXBody, tapcommon, taputils, AbsCivilization, the touched Advisors, Historians
and Infiltrators lines, states, action, material and CSV, client JS and CSS, gameoptions. No hard
regression found. Its two actionable items are global 38 and the era 5 cost under P2; the two
below were closed with no action.

- [ ] [debt/P4] stBonus drops any bonus row the player holds zero of the payment for, not only a
      positive quantity they cannot cover (the "$actual == 0 ||" added for the Elder Ones trade,
      PGameXBody.php around 10356). The one pre-existing producer that notices is DEMOCRACY's
      unlimited row (democracy(), quantity -1, queued at PGameXBody.php:2690, discard any tapestry
      for 2 VP each): a player whose hand is empty now gets "cannot pay for bonus" in the log
      instead of a Decline-only prompt. Same outcome, different log. Pinned by tests/BonusTest.php
      for the drop; ElderOnesTest covers the -6 case only, nothing pins the -1 case.
      Recommendation: keep as is. A row the player can only decline is better dropped, and the log
      line says why it vanished. No action beyond the missing -1 test.

- [ ] [debt/P4] effect_onQueueBenefit runs getCivOwner plus a non-strict getCivilizationInstance for
      every row queued for a real player under a civ reason. Every caller passes a real civ
      constant, so the ERR:game:02 assert inside cannot fire today; a reason_civ with a bogus id
      would now be a system error instead of a log line. Pass-through pinned by
      tests/BenefitQueueTest.php. No action: it only bites a future bug, and a loud system error is
      the right response to a bogus civ id.

- [ ] [debt/P4] Several civ mat queries build their LIKE pattern as `civ_$cid\_%` (PGameXBody.php
      4849, 4951, 4955, 4996) or `civ_6_%` / `civ_9_%` / `civ_12_%` (3849, 7468, 11273) instead of
      escaping the underscore the way getStructuresOnCiv does (`civ\_$cid\_%`). An unescaped
      underscore is a single character wildcard, so those helpers do not search quite the same
      thing. Harmless with today's location names.

- [ ] [debt/P4] soft_block is registered as game state label id 99 (PGameXBody.php
      initGameStateLabels), but the BGA docs only allow ids 10-89 for globals (1-9 framework,
      100-199 gameoptions; 90-99 undocumented). It works today and is debug-only, but it relies on
      unspecified framework behavior. Consider moving it to a free id under 90; do not add more ids
      in 90-99.

- [ ] [debt/P4] Elder Ones engine methods added to PGameXBody (hasExtendedPlayCiv, isExtendedPlay,
      getTapestryEra, finishPlayer, endExtendedPlay, dbSetPlayerIncomeTurns, dbSetTapestryEraSlot,
      queueTrapResponse, action_endMyGame) declare no parameter or return types, unlike everything
      in modules/civs/. Adding them means touching the GameUT/ElderOnesUT overrides of
      getCurrentEra, dbSetPlayerIncomeTurns, getPossibleAdvances and finalGameScoring in the same
      pass, which is why it was left out here.

- [ ] [debt/P4] Weefolk cleanup: queueEraCivAbility duplicates the income_trigger range test the
      parent does, the same duplication the Werefolk item below calls out. Both should use the
      AbsCivilization helper once it exists.

- [ ] [debt/P4] Werefolk cleanup: queueEraCivAbility reads income_trigger, computes from/to and
      calls in_range, then on a miss hands off to the parent which re-reads the same income_trigger
      and re-runs the same check just to reach its else branch and post the "not applicable in era"
      chat line. Extract the range test into an AbsCivilization helper both can call, instead of
      nine duplicated lines per civ.

- [ ] [ui-temp/P4] Research state title names the advance row, not "Research". research() now
      queues the optional advance row of the rolled track (84-91, 97-100) instead of transitioning
      with the research row on top, so argResearch's bid is that row and tapestry.js "research"
      shows "Advance (optional, benefits) - Science: You must confirm"; the benefit queue panel
      shows the advance row too. The reason subtitle and the roll are unchanged. If the old wording
      is wanted: special-case the three families in the client research case, or carry the
      research row's name in the queued row's reason (game-review-diff, 2026-09-07, deferred).

- [ ] [ui-temp/P4] Show color of player who owns Nomads buildings?
- [ ] [ui-temp/P4] Log: nice to show building icons
- [ ] [ux/P4] Jamey: hover on a landmark icon (advancement track or tech card) showing the landmark
      size in plots; and the Plans & Ploys setup tokens that mark which landmarks have been gained.
- [ ] [ux/P4] Jamey: an indicator that the highlighted plots for landmark placement are the
      eligible upper left plots (untriaged, sits with the landmark items).

- [ ] [ui-temp/P4] DARK AGES description (material.inc.php 3722) reads "Regress once on 3
      difference advancement tracks" - should be "different". (The earlier note about an unclosed
      "</b" is stale, the tag is closed.)

- [ ] [debt/P4] Merfolk review leftovers (blind review of the Merfolk diff, 2026-09-06), all judged
      not worth code changes now: `Merfolk::queuePhase` calls `interruptBenefit()` on the extended
      turn path where the benefit table is guaranteed empty, so the bump is dead there (harmless,
      keeps the one helper); `testTrapDiscardCanEmptyTheHandAndEndTheGameNextTurn` discards through
      `effect_discardCard` rather than actually playing a trap, so it only covers half its name;
      `testEmptyHandFinishesThePlayerExactlyOnce` re-enters `playerTurn` at era 6, which exercises
      `takeIncomeAuto` rather than the `stTransition` skip the name implies.

DONE

- [x] Map, DONE: getMap deduped map_owners and map_occupants once after the structure loop, so only
      whichever hex the last structure row happened to be on was cleaned. Two outposts of one player
      on a hex made that player two owners of it, which effect_endOfConquer asserts against. Both
      lines moved inside the loop, pinned by testTwoOutpostsOfOnePlayerAreOneOwnerOnEveryHex in the
      new tests/MapTest.php.

- [x] Weefolk: interaction with Celestials. Ruled in FORMAL_RULES CIV.CELESTIALS.7 and pinned by
      testLandmarkHangingOffTheMatStillCountsInTheLinesItTouches: a landmark hanging off the side
      still counts once per line it touches, and the lines outside the mat are never a token's row
      or column.

- [x] Jamey: OLYMPIC HOST first choice, first-to-pass bonus with a missing neighbour, "adjustments"
      spelling, stacked starting outposts, "benefit" spelling in the science die text. All FIXED.

JAMEY

Raw feedback, kept verbatim as the source. Every item is tracked above: TYRANNY (P1 + P2), VACCINE
(P2), board rotation (P2), track zoom and tapestry hand count (P3), landmark hover and setup
tokens and the placement indicator (P4), the rest DONE. The capital city glitch itself is
not tracked; it was never reproduced.

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
