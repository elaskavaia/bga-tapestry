## Victoria

Triage 2026-09-13. Tags: [blocker] breaks or strands a table, [rules] wrong game rule, [ux] confusing
or misleading client, [ui-temp] cosmetic, [debt] test gaps and cleanup, [feature] new content. Priority: P1 must fix before
the FF deploy, P2 before the deploy but narrower or cheaper, P3 after, P4 no action planned.

STUDIO BUILD WARNINGS (deploy 261101-2149, 12 warnings, all pre-existing framework deprecations)

- [ ] [debt/P3] PHP: `gamestate->table_globals` (PGameXBody.php:11496-11497), use
      `$this->bga->tableOptions->get()`; `gamestate->state()` (PGameXBody.php:9381, 9456, 13312,
      tapcommon.php:62), use `getCurrentMainState` / `getCurrentState`.
- [ ] [debt/P3] PHP: direct `player_score` / `player_score_aux` writes (tapcommon.php:130-139,
      PGameXBody.php:11898-11925), use `$this->bga->playerScore` / `playerScoreAux`. Careful: the
      `playerextra` mirror for bots is written alongside.
- [ ] [debt/P3] PHP: `_()` / `self::_()` in about 30 exception messages (PGameXBody.php, e.g. 1171,
      4374, 8318; civs/Renegades.php:93; civs/AbsCivilization.php:108; tapcommon.php:214), use
      `clienttranslate` with exception args.
- [ ] [debt/P3] JS: `ajaxcall` (tapestry.js:2240, 2413, 3275), use `this.bga.actions.performAction`;
      direct preference controls (tapestry.js:3824-3851), use `this.bga.userPreferences`;
      `scoreCtrl` (tapestry.js:6187-6188), use `this.bga.playerPanels.getScoreCounter`; direct player
      panel elements (tapestry.js:322-364, 6629), use `this.bga.playerPanels.getElement`.
- [ ] [debt/P4] Non-code: more than 20 image files (sprites); obsolete `tapestry.view.php` /
      `tapestry_tapestry.tpl`; `stats.inc.php` to `stats.jsonc`; `exception_on_warning` not set in
      gameinfos.

OPEN FROM 2026-10-31 (track token choice, review of e9cf09f, BGA #58486) - all pre-existing,
CONFIRMED by stub probes unless marked; probes in the session scratchpad, rewrite as real tests.

- [ ] [rules/P2] Adjacent tokens: `action_first_benefit` and `selectTrackSpot` store the
      DESTINATION spot as `cube_choice`, which is read as the token to move. Tokens at 0 and 1, button
      to 1 moves the token already on 1. Fix: store `$spot - change` for the picked benefit. Possible
      cause of BGA #62514 (Entertainers), untested.
- [ ] [rules/P3] `action_choose_benefit` subtracts `tracks_change` (the LAST option's `adv`), not the
      picked option's: `o,23,68` picking the advance stores destination+1 and the player is asked
      again. Fix: `$args["tracks"][$bid]["change"]`.
- [ ] [rules/P3] FUTURISTS jump clamped to 13 (`o,151..154,401`, token at 10): stored choice 9
      matches no token and `trackMovementInteractive` returns for `FLAG_JUMP`, the jump is silently
      lost.
- [ ] [rules/P3] An advance blocked by THEOCRACY / BROKER OF PEACE / DICTATORSHIP leaks
      `cube_choice` into the next advance of the turn. Half fixed by the no-token fix (BGA #58486):
      `trackMovementInteractive` now consumes it before any early return. Still open:
      `action_research_decision` stores `$spot - 1` then calls `trackMovementProper` directly, whose
      `triggerAdvanceCheck` early return skips the reset at its end.
- [ ] [rules/P4] Research option (84-91, 97-100) picked from a composite, then Decline in
      `action_research_decision`: the spot is never cleared and leaks. The review says reachable, the
      fix agent said those are never offered inside a choice - verify which first.
- [ ] [rules/P2] FUTURISTS (adj 4/8) "advance on up to two different tracks": the second
      `o,151..154,401` still offers the track jumped by the first, benefit 601 (meant to remove it,
      `Futurists::setupCiv`) is a no-op in `awardBenefits`. Pinned by `BenefitChoiceTest`
      `testFuturistsSecondChoiceStillOffersTheJumpedTrack`.

OPEN FROM 2026-10-26 (player token control, BGA #252643) - commits 0e96db9, 57718b8, not deployed,
not tried on Studio (Studio was down). Plan and rules: PLAN_PLAYER_TOKEN_CONTROL.md, FORMAL_RULES
MAP.2-MAP.5.

- [ ] [blocker/P1] Studio: the table stuck on BGA #252643 (933811349, adj=8) resumes on retry. The
      retried `effect_endOfConquer` should find one owner, the conqueror.
- [ ] [rules/P1] Studio: conquer a lone Treasure Hunters player token. The conqueror controls, the
      TH owner is offered the trap, no topple award count. Play the trap: the TH owner controls
      again and the conqueror's outpost is toppled.
- [ ] [ux/P2] Studio: the floating capital shows the gold glow (`.floating_capital`), at setup,
      after a move, after F5, and on a legacy table (unmarked token). The setup case depends on the
      civ mat being drawn before the token (`ownsCiv`). On a legacy table where the owner also has
      INFILTRATORS, those tokens glow too (accepted).
- [ ] [rules/P2] Behaviour change on running tables, intended by MAP.2: a base TRADERS token placed
      on an empty territory used to own nothing and now controls it. It counts as a controlled
      territory (scoring, ISOLATIONISTS landmass, conquer adjacency), blocks its owner from
      conquering it, and offers its owner the trap when an opponent conquers it. Variant 4 TRADERS
      "share control" is dropped. Watch for bug reports.
- [ ] [rules/P2] Behaviour change: the CELESTIALS move offers only the floating capital. Before, it
      offered any inert token of the owner (INFILTRATORS, ISOLATIONISTS). On a legacy table every
      unmarked token of the owner is still offered.
- [ ] [debt/P3] After 2026-12: remove the legacy fallback in `isFloatingCapital` (PGameXBody and
      tapestry.js, both marked TODO).
- [ ] [debt/P4] The server does not check that an ISOLATIONISTS token goes on an empty territory,
      only the client does (`effect_conquer` trusts `$isol`). Pre-existing.
- [ ] [debt/P4] `modules/civs/Infiltrators.php` fails `prettier --check` (pre-existing), reformat in
      its own commit.

OPEN FROM 2026-10-25 (income spot incident, BGA #252057) - review feedback on bd2f96d, 96f1439, c27b72e

- [ ] [blocker/P1] The level 7 case (covered by the cap above): on an unmigrated table a level 6
      track still held its leftover sixth building and v261024-2009 let it be claimed (claim,
      Collectors, Traders, ben 110/144), leaving level 7 and ERR:game:04 on every income read.
- [ ] [debt/P4] Damage from the broken window that no migration can undo: VPincomeStructure counted
      one building short per type (negative at level 1), Traders read spot 0 (PHP warning, opponent
      got nothing). Only tables that did those between v261024-2009 and v261025-0248.
- [ ] [ux/P2] Check the Lighthouse `ability_used` client change on Studio (not browser verified):
      after a use the cube is not highlighted or clickable, it comes back at the owner's next turn,
      and how the dashed marker looks on the slot.
- [ ] [rules/P3] Urban Planners: the activated ability used from playerTurn (state 13) jumps to state
      18; with an empty stack the turn seems to end without the advance/income choice. Unverified.
- [ ] [rules/P4] Merfolk in extended play using the Lighthouse from state 13 would re-run
      startExtendedTurn through the BE_RESUME re-entry. Pre-existing, unverified combination.
- [ ] [debt/P3] Remove the deploy-transition branch in the BE_RESUME (201) handler from 1e679bf: it
      starts the turn when `getPlayerTurn == 0`, only for a table caught resolving setup rows by
      the deploy that moved the turn start into stTransition / stFinishSetup. Drop it one or two
      releases after that deploy, once no table can still be in setup.

P1 - MUST FIX BEFORE THE FF DEPLOY

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

Designer answers of 2026-09 (misc/QUESTIONS.txt) overturned eight readings. FORMAL_RULES.txt and
the material rulings are already updated; each item below is the code change for one clause.

- [ ] [debt/P2] A player who quits in the middle of their own income turn 5 is never finally scored
      at all: effect_zombieBenefits deletes their pending 602 row so effect_endOfIncome never runs,
      canSkipConfirm is true for a zombie so the playerTurnEnd zombie branch never fires, and
      stTransition skips them from then on. Pre-existing, not FF specific. zombieTurn could finish an
      abandoned income turn (effect_endOfIncome when isIncomeTurn and the era is 5).

- [ ] [debt/P4] finalIslandersScoring() ignores the player it is scoring and reads
      getActivePlayerId(). Correct at both call sites today, but finalCivScoring made it
      load-bearing in one more place; it should take the player_id its siblings take.

- [ ] [debt/P4] Weefolk and Genies both walk getOpponentsStartingFromLeft filtering on
      isRealPlayer, which is now the same loop in both. A getRealOpponents helper on PGameXBody
      next to getOpponentsStartingFromLeft would hold it once.

P2 - BEFORE THE DEPLOY, NARROWER OR CHEAPER

- [ ] [rules/P2] Utilitarians: no city when they place a landmark. The landmark row is declinable
      and never returns: there is no income_trigger in material, so AbsCivilization.php 204 defaults
      decline=true; a decline cashes the row and the landmark sits on civilization_39 forever. Fix
      is a material line. The same block has a null-unsafe getObjectFromDB with no player filter
      (PGameXBody.php 5445).

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

- [ ] [ux/perf/P2] Era 5 cost: getTapestryEra runs hasExtendedPlayCiv on every isTapestryActive,
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

- [ ] [ux/P3] argPlayerTurn still marks a blocked advance as allowed, so the client gives it an
      active_slot and a button and the click earns a rejection toast. isTrackBlocked now exists to
      answer it: mark such a spot 0 in getPossibleAdvances(false) so it renders illegal_slot like an
      unaffordable one, which would also make the client agree with the extended play end test.

- [ ] [ui-temp/P3] The benefit stack tooltip prints a bonus row's raw quantity (tapestry.js around
      4785), so the Elder Ones trade reads "Bonus:-6 x Tapestry" and the older unlimited row reads
      "Bonus:-1 x". Render negatives as "up to 6" and "any number of" instead.

- [ ] [ui-temp/P3] Elder Ones: during extended play updateCurrentEra finds no era 5 slot and drops
      the income mat highlight, so the era 4 tapestry that is still in force looks inactive. Keeping
      era 4 lit needs the flag on the client side (argPlayerTurn already sends extended_play, the
      panel does not get it). Since getTapestryEra now answers 4 from the start of era 5, this
      covers income turn 5 as well, where the server plays an overplay into slot 4 while the client
      rings slot 5 - and argPlayerTurn's extended_play is still false there, so the flag has to ride
      on the income notification instead.

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
