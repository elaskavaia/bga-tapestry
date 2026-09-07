<?php

declare(strict_types=1);

/**
 * Merfolk, the sixth Fantasies and Futures civilization: a tapestry card and a submerge on income
 * turns 2-4, a card, the return of the submerged ones and a cull on income turn 5, then extended
 * play where every turn spends tapestry cards until the hand is empty.
 */
class Merfolk extends AbsCivilization {
    /** Cards left in hand after a submerge, and after the income turn 5 cull. */
    const KEEP_SUBMERGED = 2;
    const KEEP_CULL = 5;
    const DISCARD_VP = 5;
    /** Reason arg telling the three prompts of this civ apart, all of them one civ row. */
    const PHASE_SUBMERGE = "submerge";
    const PHASE_KEEP = "keep";
    const PHASE_TURN = "turn";
    const CHOICE_SELECT = 1;
    const CHOICE_DISCARD = 1;
    const CHOICE_PLAY = 2;

    public function __construct(object $game) {
        parent::__construct(CIV_MERFOLK, $game);
    }

    function hasExtendedPlay(): bool {
        return true;
    }

    /**
     * The income ability is one deterministic row, not a civ state: it draws a card and only then
     * knows whether there is anything to choose between.
     */
    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        $game = $this->game;
        // the parent signature is untyped and getActivePlayerId returns a string, which the typed
        // helpers below would reject outright under strict_types
        $player_id = (int) $player_id;
        $incomeTurn = (int) ($incomeTurn ?: $game->getCurrentEra($player_id));
        $income_trigger = $this->getRules("income_trigger", []);
        if (!in_range($incomeTurn, array_get($income_trigger, "from", 0), array_get($income_trigger, "to", 0))) {
            parent::queueEraCivAbility($player_id, $incomeTurn);
            return;
        }
        $ben = $incomeTurn == 5 ? BE_MERFOLK_SURFACE : BE_MERFOLK_DIVE;
        $game->queueBenefitNormal($ben, $player_id, reason_civ($this->civ));
    }

    /** The cull is at the end of income turn 5, so it rides the same undo window as the rest of it. */
    function queueEndOfIncome(int $player_id, int $incomeTurn): void {
        if ($incomeTurn == 5) {
            $this->game->queueBenefitNormal(BE_MERFOLK_CULL, $player_id, reason_civ($this->civ));
        }
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Merfolk:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Merfolk:12", $game->hasCiv($player_id, $this->civ));

        switch ($ben) {
            case BE_MERFOLK_DIVE:
                return $this->dive($player_id, $reason);
            case BE_MERFOLK_SURFACE:
                return $this->surface($player_id, $reason);
            case BE_MERFOLK_CULL:
                return $this->cull($player_id);
        }
        $this->systemAssertTrue("ERR:Merfolk:13", false);
        return true;
    }

    /** The drawn card is a real gain, so ACADEMIA style triggers fire on it (FORMAL_RULES 5.19). */
    function dive(int $player_id, string $reason): bool {
        $game = $this->game;
        $game->awardCard($player_id, 1, CARD_TAPESTRY, false, $reason);
        if (count($this->getHand($player_id)) > self::KEEP_SUBMERGED) {
            $this->queuePhase($player_id, self::PHASE_SUBMERGE);
        }
        return true;
    }

    /** The submerged cards come back rather than being gained, so nothing triggers on them. */
    function surface(int $player_id, string $reason): bool {
        $game = $this->game;
        $game->awardCard($player_id, 1, CARD_TAPESTRY, false, $reason);
        $submerged = array_keys($game->getCardsSearch(CARD_TAPESTRY, null, "submerged", $player_id));
        $game->moveCardsHidden(
            $submerged,
            $player_id,
            "hand",
            clienttranslate('${player_name} returns ${count} submerged tapestry cards to their hand')
        );
        return true;
    }

    function cull(int $player_id): bool {
        if (count($this->getHand($player_id)) > self::KEEP_CULL) {
            $this->queuePhase($player_id, self::PHASE_KEEP);
        }
        return true;
    }

    /**
     * An empty hand is what ends the game, judged when the turn starts rather than when the last
     * card leaves (FORMAL_RULES 5.22).
     */
    function startExtendedTurn(int $player_id): bool {
        $game = $this->game;
        if (count($this->getHand($player_id)) == 0) {
            $game->endExtendedPlay($player_id, clienttranslate('${player_name} has expended their tapestry cards'));
            return true;
        }
        $this->queuePhase($player_id, self::PHASE_TURN);
        $game->gamestate->nextState("next");
        return true;
    }

    /** The prompt answers the row being resolved, so it has to jump ahead of what is already queued. */
    function queuePhase(int $player_id, string $phase): void {
        $this->game->interruptBenefit();
        $this->game->benefitCivEntry($this->civ, $player_id, reason_civ($this->civ, $phase));
    }

    function getPhase(array $benefit): string {
        return (string) $this->game->getReasonArg(array_get($benefit, "benefit_data", ""), 3);
    }

    function getHand(int $player_id): array {
        return $this->game->getCardsInHand($player_id, CARD_TAPESTRY);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $game = $this->game;
        $data = $benefit;
        $data["reason"] = $game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        $phase = $this->getPhase($benefit);
        $data["phase"] = $phase;
        switch ($phase) {
            case self::PHASE_SUBMERGE:
                $data["keep"] = self::KEEP_SUBMERGED;
                $data["title"] = clienttranslate("Select 2 tapestry cards to keep, place the rest under this mat");
                $data["slots_choice"] = [
                    self::CHOICE_SELECT => [
                        "title" => clienttranslate("Submerge the rest"),
                        "tooltip" => clienttranslate("The submerged cards cannot be played, spent or scored until income turn 5"),
                    ],
                ];
                return $data;
            case self::PHASE_KEEP:
                $data["keep"] = self::KEEP_CULL;
                $data["title"] = clienttranslate("Select 5 tapestry cards to keep, discard the rest");
                $data["slots_choice"] = [
                    self::CHOICE_SELECT => ["title" => clienttranslate("Keep the selected cards")],
                ];
                return $data;
            case self::PHASE_TURN:
                $data["title"] = clienttranslate("Discard tapestry cards for 5 VP each, or play one as a tapestry");
                $data["slots_choice"] = [
                    self::CHOICE_DISCARD => [
                        "title" => clienttranslate("Discard selected cards"),
                        "tooltip" => clienttranslate("Gain 5 VP per discarded tapestry card, at least one card must be selected"),
                    ],
                ];
                if ($game->getLatestTapestry($player_id, 4)) {
                    $data["slots_choice"][self::CHOICE_PLAY] = [
                        "play" => true, // the client sends the one picked card here, or none and the server asks
                        "title" => clienttranslate("Play a tapestry"),
                        "tooltip" => clienttranslate(
                            "Play a tapestry card over the era 4 stack for its WHEN PLAYED ability; select the card first, or leave the hand unselected to be asked"
                        ),
                    ];
                }
                return $data;
        }
        $this->systemAssertTrue("ERR:Merfolk:14", false);
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Merfolk:15", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Merfolk:16", $game->hasCiv($player_id, $this->civ));

        switch ($this->getPhase($civ_args)) {
            case self::PHASE_SUBMERGE:
                $this->systemAssertTrue("ERR:Merfolk:17", $spot == self::CHOICE_SELECT);
                $this->submerge($player_id, $this->getSelectedCards($player_id, $extra, self::KEEP_SUBMERGED));
                return;
            case self::PHASE_KEEP:
                $this->systemAssertTrue("ERR:Merfolk:18", $spot == self::CHOICE_SELECT);
                $this->discardRest($player_id, $this->getSelectedCards($player_id, $extra, self::KEEP_CULL));
                return;
            case self::PHASE_TURN:
                if ($spot == self::CHOICE_PLAY) {
                    // without an era 4 card to cover the overplay is void, and the turn would be spent
                    $offered = $this->argCivAbilitySingle($player_id, $civ_args)["slots_choice"];
                    $this->systemAssertTrue("ERR:Merfolk:21", isset($offered[self::CHOICE_PLAY]));
                    $selected = $this->getSelectedCards($player_id, $extra);
                    $game->userAssertTrue(clienttranslate("Select a single tapestry card to play"), count($selected) <= 1);
                    $this->playTapestry($player_id, (int) array_key_first($selected));
                    return;
                }
                $this->systemAssertTrue("ERR:Merfolk:19", $spot == self::CHOICE_DISCARD);
                $this->discardForVP($player_id, $this->getSelectedCards($player_id, $extra));
                return;
        }
        $this->systemAssertTrue("ERR:Merfolk:20", false);
    }

    /**
     * The ids the owner picked in their hand, MYSTICS style: a list reaches the action through
     * extra_js as an array. A count of 0 means the prompt takes any number of cards.
     */
    function getSelectedCards(int $player_id, $extra, int $count = 0): array {
        $game = $this->game;
        $hand = $this->getHand($player_id);
        $selected = [];
        foreach (is_array($extra) ? $extra : explode(",", (string) $extra) as $id) {
            if ($id === "") {
                continue;
            }
            $game->checkNumber($id);
            $card_id = (int) $id;
            $game->userAssertTrue(clienttranslate("Select tapestry cards from your hand"), isset($hand[$card_id]));
            $selected[$card_id] = $hand[$card_id];
        }
        if ($count) {
            $game->userAssertTrue(clienttranslate("Wrong number of tapestry cards selected"), count($selected) == $count);
        }
        return $selected;
    }

    function submerge(int $player_id, array $keep): void {
        $game = $this->game;
        $submerge = array_keys(array_diff_key($this->getHand($player_id), $keep));
        $game->moveCardsHidden(
            $submerge,
            $player_id,
            "submerged",
            clienttranslate('${player_name} places ${count} tapestry cards under their civilization mat')
        );
    }

    /** "Keep up to 5" is answered as exactly 5, every extra card is worth VP later (FORMAL_RULES 5.20). */
    function discardRest(int $player_id, array $keep): void {
        $game = $this->game;
        $discard = array_diff_key($this->getHand($player_id), $keep);
        $game->effect_discardCard($discard, $player_id, "discard", true);
    }

    /** An extended turn is mandatory, so at least one card goes (FORMAL_RULES 5.21). */
    function discardForVP(int $player_id, array $discard): void {
        $game = $this->game;
        $game->userAssertTrue(clienttranslate("You must select at least one tapestry card to discard"), count($discard) > 0);
        $game->effect_discardCard($discard, $player_id, "discard", true);
        $game->queueBenefitNormal(BE_VP, $player_id, reason_civ($this->civ), self::DISCARD_VP * count($discard));
    }

    /**
     * The overplay lands on era 4 through getTapestryEra and covers the old card as usual. A card
     * picked in the prompt rides along in the reason, so stTapestryCard plays it without asking again.
     */
    function playTapestry(int $player_id, int $card_id = 0): void {
        $this->game->queueBenefitNormal(64, $player_id, reason_civ($this->civ, $card_id));
    }
}
