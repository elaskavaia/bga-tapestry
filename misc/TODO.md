## Victoria

REGRESSION REVIEW

Review of every non-civ change since 1fad461 (2026-08-26, the base of the Fantasies and Futures
work): PGameXBody, tapcommon, taputils, AbsCivilization, the touched Advisors, Historians and
Infiltrators lines, states, action, material and CSV, client JS and CSS, gameoptions. No hard
regression found. Behavior changes and risks first, worst first:

- [ ] stBonus drops any bonus row the player holds zero of the payment for, not only a positive
      quantity they cannot cover (the "$actual == 0 ||" added for the Elder Ones trade). The one
      pre-existing producer that notices is DEMOCRACY's unlimited row (democracy(), quantity -1):
      a player whose 3 card draw came up empty now gets "cannot pay for bonus" in the log instead
      of a Decline-only prompt. Same outcome, different log. Pinned by tests/BonusTest.php.
      Recommendation: keep as is. A row the player can only decline is better dropped, and the log
      line says why it vanished. No action.

- [ ] Global 38 (selected_space_tile) is written at every turn start and end of every table, old
      tables included, and nothing inserts the row: the project never calls
      setGameStateInitialValue and upgradeTableDb is empty. Reads are gated behind
      BE_WEREFOLK_EXPLORE, so an old table only ever UPDATEs a missing row. starting_player (37)
      was added the same way in June 2024. Low.
      Recommendation: optional insurance, one line in upgradeTableDb behind a $from_version check:
      INSERT IGNORE INTO global (global_id, global_value) VALUES (38, 0). Without it, grep the
      error log for selected_space_tile after the deploy.

- [ ] Era 5 cost: getTapestryEra runs isExtendedPlay on every isTapestryActive, stTapestryCard and
      playTapestryCard call for a player in era 5, and that runs getAllCivs plus a
      getCivilizationInstance per civ in hand. 42 isTapestryActive call sites, none inside a hex
      loop. Correctness is unchanged without an extended play civ; this is the hasExtendedPlayCiv
      memoization item under CLEANUP.
      Recommendation: do the memoization before the FF deploy. This is the one item that touches
      every table, not only FF ones, since every player passes through era 5. A per-request cache
      array on hasExtendedPlayCiv keyed by player id is about five lines.

- [ ] effect_onQueueBenefit runs getCivOwner plus a non-strict getCivilizationInstance for every row
      queued for a real player under a civ reason. Every caller passes a real civ constant, so the
      ERR:game:02 assert inside cannot fire today; a reason_civ with a bogus id would now be a
      system error instead of a log line. Pass-through pinned by tests/BenefitQueueTest.php.
      Recommendation: no action. It only bites a future bug, and a loud system error is the right
      response to a bogus civ id.

CODE BUGS

- [ ] BE_TECH_CARD (26) is broken as a queued benefit: awardBenefits case 26 sends it to the invent
      state, but stInvent asserts the benefit's r rule is "i" and row 26 has "g". Nothing used it until
      Faefolk did, which is how it surfaced. Faefolk now uses BE_INVENT instead; row 26 is still a trap
      for the next caller.

- [ ] Werefolk: onSpaceTileClick has no checkActiveSlot guard, unlike onTerritoryTileClick and the track
      handlers, so a space tile the new spaceExploration filter dimmed is still clickable and fires
      explore_space just to earn a server rejection toast. Add "if (!this.checkActiveSlot(id)) return;"
      to the default branch.

- [ ] Genies and Faefolk: clicking any holder on the mat during civAbility sends civTokenAdvance with
      that spot (the checkActiveSlot guard in onCubeHolderClick, tapestry.js around 4842, is commented
      out), so a legal-looking click on the ring or the token pile earns an ERR:Genies:13 (ERR:Faefolk:13)
      system error toast; clicking spot 1 silently fires the ability like the button. Fix: either
      "case this.CON.CIV_GENIES: return;" beside "case 15: return;" in that handler, or restore the
      guard for every civ.
      Holder-click bug: reproduced. Clicking a ring holder during the ability state produced the "Internal Error ... [ERR:Genies:13]" toast in the log. The state survived and the button still worked afterwards. The fix in the TODO (early return for CIV_GENIES in onCubeHolderClick, or restoring the guard) is the right shape.

- [ ] place_structure never validates the requested x_y against the options argPlaceStructure computed,
      so the only thing stopping an illegal cell is the client "possible" class plus whatever
      effect_placeOnCapitalMat happens to assert. Pre-existing, and it applies to income buildings and
      landmarks as much as to the Weefolk token; the token's own "only in a full city" rule is now
      enforced server-side, the general case is not.
      The other half of it, seen in the studio 2026-09-08 placing a 2 by 2 landmark: the client
      offers Confirm for a cell the server then refuses, and the refusal is invisible. The
      "Invalid structure placement" userAssert (effect_placeOnCapitalMat, PGameXBody.php around
      8740) reaches the console but no toast and no title change, so the state just sits there and
      looks like the button did nothing. Anything that reports the rejection would do.

- [ ] Coal baron reset when spies was using it

- [ ] Utilitarients - no city when they place landmark

- [ ] Several civ mat queries build their LIKE pattern as `civ_$cid\_%` (PGameXBody.php 4849, 4951, 4955, 4996) or `civ_6_%` / `civ_9_%` / `civ_12_%` (3849, 7468, 11273) instead of escaping the underscore
      the way getStructuresOnCiv does (`civ\_$cid\_%`). An unescaped underscore is a single character
      wildcard, so those helpers do not search quite the same thing. Harmless with today's location names.

- [ ] soft_block is registered as game state label id 99 (PGameXBody.php initGameStateLabels), but the
      BGA docs only allow ids 10-89 for globals (1-9 framework, 100-199 gameoptions; 90-99 undocumented).
      It works today and is debug-only, but it relies on unspecified framework behavior. Consider moving
      it to a free id under 90; do not add more ids in 90-99.

- [ ] Celestials: onCelestialTokenClick (tapestry.js around 851) opens with disconnectAllTemp,
      which strips the handler and the active_slot class off every cube, so once a token is picked
      there is no way to pick a different one short of Undo. Only bites with two or more inert
      tokens on the map, which is Celestials plus an Infiltrators or Isolationists one. The target
      hexes still work, they are connected permanently in setupLand, not as temp handlers. Fix:
      disconnect only the target hexes' highlight, or re-connect the cubes after showing targets.

- [ ] "generated notifications are larger than 128k" (seen at 331844 bytes) on a solo table while
      the Automa and Shadow Empire ran their end of game turns, studio 2026-09-08. The move was
      lost and the table sat in benefitManager with no active player; a page reload showed the game
      had in fact ended normally, so nothing was corrupted. Not Celestials, the row was an Automa
      research turn. Worth finding which notification is that big before the FF deploy, since a
      real table cannot always be rescued by a reload. Seen again at 339182 bytes on the Artificers
      solo table 962350 (2026-09-09), this time on "Automa conquers an empty territory" during
      Automa turn 9, right after the human's income turn 5 confirm: the log lost the final scoring
      lines and only the reload showed the end screen. Two sightings at roughly the same size on
      different Automa rows points at the request that runs every remaining Automa turn in one go
      once the human is finished, not at one fat notification.

- [ ] Elder Ones: stPlayerTurn returns early when a civ has activated abilities or the player owns a
      playable lighthouse (PGameXBody.php around 11290), before the "no affordable advance" test, so an
      extended play player in that position is never auto-finished and has to press "End my game"
      themselves. That mirrors what happens to everyone else (no auto income either), but for an Elder
      Ones player the only other button ends their game for good. Confirm this is the wanted reading of
      FORMAL_RULES CIV.ELDER_ONES.2, or move the extended play test above the two early returns.

TEST GAPS

- [ ] stBonus now drops any bonus row whose payment resource count is 0, not just one whose count is
      below a positive quantity (PGameXBody.php around 10356, the "$actual == 0 ||" added for the Elder
      Ones trade). That also changes the pre-existing unlimited row queued at PGameXBody.php:2690
      (discard any tapestry for 2 VP each): a player with an empty hand now has it dropped silently
      instead of being shown a Decline button. ElderOnesTest covers the -6 case only; nothing pins the
      -1 case.

- [ ] The pre-expansion civs have no test file at all (Architects, Renegades, Craftsmen, Gamblers,
      Collectors, Traders, Alchemists, Mystics, Advisors), so their case lines in
      saction_civTokenAdvance and both argCivAbilitySingle switches can still be deleted with the suite
      green. GameUT::civTokenAdvance is the harness the FF civs now use, so a test file per civ is all
      it takes.

- [x] Weefolk: interaction with Celestials. Ruled in FORMAL_RULES CIV.CELESTIALS.7 and pinned by
      testLandmarkHangingOffTheMatStillCountsInTheLinesItTouches: a landmark hanging off the side
      still counts once per line it touches, and the lines outside the mat are never a token's row
      or column.

STUDIO CHECKS

- [x] Artificers: played a solo studio table end to end (table 962350, 2026-09-09). Income turn 1
      says "not applicable in era 1"; turns 2-4 show all eight track-named buttons ("Set aside:
      Farms", "Slide left: Markets") with their tooltips plus Decline; set aside moved the farm to
      the extras area beside the capital and that same turn paid Farming (spot 2); slide left put
      the market on Barter and the same turn paid Currency instead of Barter, and the Markets slide
      entry disappeared once its building sat on spot 1; a second farm slide left a 1,2,3,5 layout
      that paid Fertilization and skipped Preservation. Turn 5 offered Score plus one "Slide all
      left" per unpacked track: Score paid 3 VP (one set aside, two in the city), the pack put both
      farms on spots 1 and 2 and the VP income paid Breeding, Fertilization and Food Printing;
      whole-turn Undo restored the mat and let both options be tried. Rows 10, 110 and 144 all
      took the leftmost building of the mutated track (spots 3, 4 and then 4 again after the undo),
      and Score counted the row 144 out-of-bounds farm for 4 VP. Still unseen: the OLYMPIC HOST
      building gain (needs an opponent with buildings, not a solo table), the slide animation
      itself (only the DOM after it was read) and the "no building left on any track" skip.

- [x] Celestials: played a solo studio table end to end (table 956979, 2026-09-08). Setup logs both
      lines and leaves outpost plus cube on the start hex; era 1 says "not applicable"; income turns
      2 and 3 highlight the explored neighbours, the hex click sends the move and both conquer dice
      are rolled and awarded (including a move onto a territory that already held an outpost);
      income turn 4 declines with "does not move their floating capital"; income turn 5 scored
      "gains 3 VP" for one landmark on the mat and one hanging off. Still unseen: the dice
      animation itself (only the log lines were read) and a negative income turn 5 total.

- [ ] Celestials: moveStructure no longer gates a cube on a land slot behind Infiltrators ownership,
      so any cube on a territory now sits on the hex itself rather than in an outpost slot. Check
      the three other cubes that reach a territory: an Infiltrators token, an Isolationists token
      (now placed inert, so its topple visual changed too) and a MILITANTS cube under variant 4.
      The overlap this caused is dealt with: the token used to be 28px at right 50% / top 20px,
      which is wider than an outpost and covered slot 1 whole (measured 100% of its width and 59%
      of its height, roof included). The hex is 91 by 79 and the two slots span 20% to 80% of it,
      so no 28px position clears them; the token is now 20px at right 60% / top 46%, which sits on
      the outpost's lower body and leaves the roof read, and the second token moved to right 38% to
      match. Both were only checked against the hex geometry, never seen rendered - eyeball them.

- [ ] Elder Ones: the whole client half is unverified, there are no JS tests. Four things to see on a
      real table: the two generic slots_choice buttons on income turns 2-4, the bonus state on income
      turn 5 with a hand of tapestry cards (multi select plus the new "up to 6" prompt), the red "End my
      game" button with its confirmation dialog on an extended play turn, and the player panel greying
      out only when the player actually stops rather than at income turn 5.

- [ ] Genies: verify the 8 ring slot positions and the token pile (slot 0, 4 cubes wide) in the studio
      (measured off civ_ff.webp, not yet seen rendered on a real mat).

- [ ] Weefolk: verify in the studio that the plot token renders inside the opponent's capital cell and
      in the capital_helper preview. The two CSS rules at the end of tapestry.css were written blind
      against the 26.5px cell and the helper box, never seen on a real mat.

- [ ] Weefolk: there are no JS tests, so the client half is unverified. Three pieces to check in the
      studio: the cube div is found as "cube*<id>" in placeStructure (the branch keys off
      args.structure_type == BUILDING_CUBE, and BUILDING*\* constants were only just added to
      addConstants; plantToken now notifies moveStructure so the div exists before the state opens);
      the build prompt's territory tile selection reaches onCivSpotHandler as clientStateArgs.extra;
      and the opponent's capital cells accept the click in the replacement case, where the cell is
      occupied but marked possible.

- [ ] Research state title names the advance row, not "Research". research() now queues the
      optional advance row of the rolled track (84-91, 97-100) instead of transitioning with the
      research row on top, so argResearch's bid is that row and tapestry.js "research" shows
      "Advance (optional, benefits) - Science: You must confirm"; the benefit queue panel shows the
      advance row too. The reason subtitle and the roll are unchanged. If the old wording is wanted:
      special-case the three families in the client research case, or carry the research row's
      name in the queued row's reason (game-review-diff, 2026-09-07, deferred).

CLEANUP

- [ ] BEFORE THE FF DEPLOY: the income mat migration guard in upgradeTableDb is a placeholder
      timestamp (`$from_version <= 2609091200`, written 2026-09-09) and must become the real deploy
      version. Too low and in-flight tables never get spots on their income building rows, so their
      next income turn dies on the ERR:game:04 invariant. Too high is worse than it looks:
      dbAssignIncomeSpots overwrites arg2 on every income row it finds, not only the ones still at
      0, so a re-run over an already migrated table flattens the mat back to a prefix layout - which
      is a no-op everywhere except an Artificers table, where it silently undoes every slide the
      owner paid an income turn for. Also finish that function's comment, it stops mid-sentence at
      "and goes".

- [ ] Elder Ones engine methods added to PGameXBody (hasExtendedPlayCiv, isExtendedPlay, getTapestryEra,
      finishPlayer, endExtendedPlay, dbSetPlayerIncomeTurns, dbSetTapestryEraSlot, queueTrapResponse,
      action_endMyGame) declare no parameter or return types, unlike everything in modules/civs/. Adding
      them means touching the GameUT/ElderOnesUT overrides of getCurrentEra, dbSetPlayerIncomeTurns,
      getPossibleAdvances and finalGameScoring in the same pass, which is why it was left out here.

- [ ] hasExtendedPlayCiv runs a getAllCivs query and instantiates every civ class of the player on each
      call. isTapestryActive reaches it through getTapestryEra on every era 5 lookup, which for an Elder
      Ones player is the whole of extended play. getTapestryEra already short circuits eras 1-4 on the
      era value alone; memoizing the civ answer per player per request would close the rest.

- [ ] Weefolk cleanup: queueEraCivAbility duplicates the income_trigger range test the parent does, the
      same duplication the Werefolk item below calls out. Both should use the AbsCivilization helper
      once it exists.

- [ ] Werefolk cleanup: queueEraCivAbility reads income_trigger, computes from/to and calls in_range, then
      on a miss hands off to the parent which re-reads the same income_trigger and re-runs the same check
      just to reach its else branch and post the "not applicable in era" chat line. Extract the range test
      into an AbsCivilization helper both can call, instead of nine duplicated lines per civ.

- [ ] Alchemists cleanup: removeCubes() is called at the four exits of the roll sequence, right after
      the final benefits are queued, so the kept-die cubes vanish from the mat before the player resolves
      them. Queue BE_CIV_END once instead and clear the cubes in endCivAbility, the way Genies and
      Werefolk do. Check first whether BE_ALCHEMISTS_DIE in the level 9 branch relies on
      getRemainingDice() seeing an empty mat.

DONE

- [x] Map, DONE: getMap deduped map_owners and map_occupants once after the structure loop, so only
      whichever hex the last structure row happened to be on was cleaned. Two outposts of one player
      on a hex made that player two owners of it, which effect_endOfConquer asserts against. Both
      lines moved inside the loop, pinned by testTwoOutpostsOfOnePlayerAreOneOwnerOnEveryHex in the
      new tests/MapTest.php.

- [x] Map, DONE: action_standup toggled card_type_arg on every structure of the territory, so a cube
      sharing it (an INFILTRATORS token, a toppled MILITANTS cube) silently became a controlling
      structure and the client toggled its topple visual too. The toggle now selects outposts only.
      Pinned by testStandingUpFlipsTheOutpostsAndLeavesATokenAlone.

- [x] Map, DONE: effect_endOfConquer read "somebody was toppled" off count(map_occupants) == 2, which
      is wrong on any territory with a third occupant or with a single one. It now reads the
      toppled_player global effect_conquer writes on both branches a few lines earlier. This drives
      the topple achievement and the both-dice conquer_bonus paths.

- [x] Map, DONE: getMap, getNeighbourHexes and the two conquer flows no longer reach the map and
      structure tables with raw SQL, so tests can drive the map at all. getStructuresOnMapDb,
      getMapCoordsDb, isControllingStructure and dbSetStructureToppled are the new seams, and
      tests/Stubs/MapUT.php needs no method override. DiceUT and InfiltratorsUT dropped the
      getMapHexData, getOutpostsInHand and effect_placeOnMap fakes they used instead.

- [x] Infiltrators, DONE: the sixth argument of the getStructuresSearch call in argCivAbilitySingle was
      a copy of the ownership flag of effect_placeOnMap, which that method never had - dropped. The two
      filters it does pass are now pinned by testTokensOnOneCapitalDoNotCountTowardAnother and
      testCubesOfOtherPlayersOnTheCapitalDoNotCount in the new tests/InfiltratorsTest.php.

- [x] Infiltrators, ANSWERED: the third token counts every cube of the player on that capital
      territory, whatever civilization put it there - player tokens are indistinguishable in the
      physical game, so it is threaded in the player's favor. Ruled by Victoria, recorded in
      FORMAL_RULES CIV.INFILTRATORS.1. Test: testATokenLeftByAnotherCivilizationCountsTowardTheThird.

- [x] Infiltrators, DONE: tests/InfiltratorsTest.php covers both slots through action_civTokenAdvance,
      the third token civilization bonus, midgame entry, the adjustment pack 8 setup tokens and the
      draw 3 keep 1 rerouting of BE_GAIN_CIV.

- [x] Faefolk, Genies, Weefolk and Werefolk, DONE: the four UT classes now press the button through
      action_civTokenAdvance (GameUT::civTokenAdvance) and read their prompts through the game object's
      argCivAbilitySingle, so all nine case lines of those civs are covered - deleting any one of them
      now fails the suite.

- [x] Faefolk, DONE: GameUT::resolveBenefit pops the head of the stack instead of searching the row
      list, so a test naming a row that is queued behind something else fails. Flipping the queue order
      in Faefolk::moveCivCube now breaks testCardPlayedOntoTheMatWhileGainingIsCountedOnce and three
      other tests.

- [x] Weefolk, DONE: undo across the cross-player placement is covered. WeefolkUT::offerPlot runs the
      real stBenefitManager, so switchPlayer takes the savepoint, and GameUT records every savepoint.
      Tests: testMakingTheOpponentActiveTakesAnUndoSavepoint, testPlantingForAZombieTakesItsOwnUndoSavepoint,
      testTradePromptTakesNoSavepoint.

- [x] Weefolk, DONE: the Infiltrators interaction is covered in both directions - a token planted in a
      capital does not count toward the Infiltrators third-cube bonus, and cubes on the start hex do not
      score as planted tokens. Tests: testPlantedTokenDoesNotCountTowardTheInfiltratorsBonus,
      testInfiltratorsCubesOnTheStartHexDoNotScore.

- [x] Werefolk, ANSWERED: if no space tile can be gained (deck and discard both empty) there is nothing
      to flip, so the whole ability is skipped - ruled by Victoria, recorded in FORMAL_RULES CIV.WEREFOLK.1. flip()
      now early-outs with a "cannot gain a space tile" message when drawTile gains nothing. Test:
      testEmptyDeckAndDiscardSkipsTheFlip.

- [x] Genies, RESOLVED by the redesign: the drawn opponent now answers a plain choose-one row in the
      benefitOption state, so no civAbility description names them as the ability's owner.

- [x] Genies, DONE: a drawn opponent who quits at the wish prompt no longer swallows the ability.
      zombieTurn now goes through effect_zombieBenefits, which hands every row the quitter holds for
      another player's civ to that civ (AbsCivilization::zombieBenefit) before the delete; Genies
      answers with a random circle (FORMAL_RULES CIV.GENIES.1). Test: testOpponentQuittingAtThePromptGetsARandomCircle.

RULES

VISUAL EFFECTS

- [ ] Show color of player who owns Nomads buildings?s TODO

- [ ] The benefit stack tooltip prints a bonus row's raw quantity (tapestry.js around 4785), so the
      Elder Ones trade reads "Bonus:-6 x Tapestry" and the older unlimited row reads "Bonus:-1 x".
      Render negatives as "up to 6" and "any number of" instead.

- [ ] Elder Ones: during extended play updateCurrentEra finds no era 5 slot and drops the income mat
      highlight, so the era 4 tapestry that is still in force looks inactive. Keeping era 4 lit while
      isExtendedPlay holds needs the flag on the client side (argPlayerTurn already sends
      extended_play, the panel does not get it).

TOOLTIPS

- [ ] Merfolk review leftovers (blind review of the Merfolk diff, 2026-09-06), all judged not worth
      code changes now: `Merfolk::queuePhase` calls `interruptBenefit()` on the extended turn path
      where the benefit table is guaranteed empty, so the bump is dead there (harmless, keeps the
      one helper); `testTrapDiscardCanEmptyTheHandAndEndTheGameNextTurn` discards through
      `effect_discardCard` rather than actually playing a trap, so it only covers half its name;
      `testEmptyHandFinishesThePlayerExactlyOnce` re-enters `playerTurn` at era 6, which exercises
      `takeIncomeAuto` rather than the `stTransition` skip the name implies.

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

ILLUMINATI:
- Black face 1 outside a conquer (rows 324/330) reads whatever hex `getSelectedMapHex` still holds.
  Before any conquer that is coords 0_0, `getMapHexData` answers null and `getTileBenefit` raises a
  PHP warning before falling through to "no benefit". The roller already hits this today through
  row 330; ILLUMINATI makes it reachable for the owner too, including when the roller declines row
  330 in favour of 202. Guard `getTileBenefit` against a missing hex.
