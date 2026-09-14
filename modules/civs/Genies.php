<?php

declare(strict_types=1);

class Genies extends AbsCivilization {
    const RING_SPOTS = 8;
    /** Slot 0 of the mat, where the opponents' player tokens wait between draws. */
    const PILE = 0;
    const CHOICE_USE = 1;
    /** Reason arg of the drawn opponent's choice, telling their pick apart from the rows it produces. */
    const WISH = "wish";

    public function __construct(object $game) {
        parent::__construct(CIV_GENIES, $game);
    }

    /** One player token per opponent, owned by that opponent, collected on the mat. */
    function setupCiv(int $player_id, string $start) {
        $game = $this->game;
        $tokens = [];
        foreach ($game->getOpponentsStartingFromLeft($player_id) as $opponent_id) {
            if ($game->isRealPlayer($opponent_id)) {
                $tokens[] = $game->addCube($opponent_id, $this->getCivSlot(self::PILE), CUBE_CIV, reason_civ($this->civ));
            }
        }
        return ["tokens" => $tokens, "outposts" => []];
    }

    /**
     * The owner normally only confirms the ability, nothing on the mat is clicked. It still goes
     * through the civ ability state, the one place a player holding two income civs picks the
     * order, and the state a delegated wish comes back in with the ring's circles to click.
     */
    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        if ($this->getDrawnOpponent($benefit)) {
            $data["title"] = clienttranslate("Choose the circled benefit for the drawn player, who cannot choose");
            $data["slots_choice"] = [];
            foreach ($this->getCircleSpots() as $spot) {
                $data["slots_choice"][$spot] = ["benefit" => [$this->getSlotBenefit($spot)]];
            }
            return $data;
        }
        if ($this->game->getCurrentEra($player_id) >= 5) {
            $data["title"] = clienttranslate("Score 2 different circled benefits of your choice");
            $data["slots_choice"] = [
                self::CHOICE_USE => [
                    "title" => clienttranslate("Score 2 circled benefits"),
                    "tooltip" => clienttranslate("Choose 2 different circled benefits of the ring, one after the other"),
                ],
            ];
            return $data;
        }
        $data["title"] = clienttranslate("A random opponent will choose the circled benefit you both score");
        $data["slots_choice"] = [
            self::CHOICE_USE => [
                "title" => clienttranslate("Grant a wish"),
                "tooltip" => clienttranslate(
                    "Draw a random opponent who chooses and scores a circled benefit; you score the same one and gain an adjacent squared benefit"
                ),
            ],
        ];
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Genies:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Genies:12", $game->hasCiv($player_id, $this->civ));

        $drawn_id = $this->getDrawnOpponent($civ_args);
        if ($drawn_id) {
            $this->systemAssertTrue("ERR:Genies:23", $this->isCircle($spot));
            $this->grantWish($drawn_id, $spot);
            return;
        }
        $this->systemAssertTrue("ERR:Genies:13", $spot == self::CHOICE_USE);

        if ($game->getCurrentEra($player_id) >= 5) {
            $game->queueBenefitNormal(["or" => $this->getCircleBenefits()], $player_id, reason_civ($this->civ), 2);
            return;
        }
        $game->queueBenefitNormal(BE_GENIES_WISH, $player_id, reason_civ($this->civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Genies:14", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Genies:15", $game->hasCiv($player_id, $this->civ));

        switch ($ben) {
            case BE_GENIES_WISH:
                return $this->drawOpponent($player_id);
            case BE_GENIES_SQUARE:
                return $this->gainAdjacentSquare($player_id, $reason);
        }
        $this->systemAssertTrue("ERR:Genies:16", false);
        return true;
    }

    /**
     * The draw is with replacement: every token goes back in the pile first, which also clears a
     * token left on the ring by a wish that was undone. Nobody leaves the bag as they finish, and a
     * drawn opponent who cannot answer has the owner choose in their place (FORMAL_RULES CIV.GENIES.1).
     *
     * The civ is never dealt in a solo game, so there is always at least one opponent token.
     */
    function drawOpponent(int $player_id): bool {
        $game = $this->game;
        $this->returnTokensToPile();
        $tokens = array_values($this->getAllCubesOnCiv());
        $this->systemAssertTrue("ERR:Genies:22", count($tokens) > 0);

        $opponent_id = (int) $tokens[$game->bgaRand(0, count($tokens) - 1)]["card_location_arg"];
        $game
            ->notif("message", $player_id)
            ->withPlayer2($opponent_id)
            ->notifyAll(clienttranslate('${player_name} draws the player token of ${player_name2}'));
        if ($game->isPlayerAlive($opponent_id)) {
            $game->queueBenefitInterrupt(["or" => $this->getCircleBenefits()], $opponent_id, reason_civ($this->civ, self::WISH));
            return true;
        }
        $this->queueWishForOwner($player_id, $opponent_id);
        // nobody else becomes active, so the draw needs its own savepoint or an undo re-draws it
        $game->prepareUndoSavepoint();
        return true;
    }

    /**
     * The wish the drawn opponent cannot answer, handed to the owner as their own civ ability row.
     * The drawn opponent rides in the reason arg: it is the whole difference between this row and
     * the ordinary one, and it is what the pick then places the token of.
     */
    function queueWishForOwner(int $owner_id, int $opponent_id): void {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Genies:24", $owner_id > 0);
        $game
            ->notif("message", $opponent_id)
            ->withPlayer2($owner_id)
            ->notifyAll(clienttranslate('${player_name} is out of the game, ${player_name2} chooses the circled benefit in their place'));
        $game->interruptBenefit();
        $game->benefitCivEntry($this->civ, $owner_id, $game->withReasonDataArg(reason_civ($this->civ), $opponent_id));
    }

    /** The opponent a delegated wish is answered for, 0 on an ordinary civ ability row. */
    function getDrawnOpponent(array $benefit): int {
        return (int) $this->game->getReasonArg(array_get($benefit, "benefit_data", ""), 3);
    }

    /** The drawn opponent's pick comes back through the queue as a circled benefit tagged as the wish. */
    function interceptOpponentBenefit(int $ben, int $player_id, string $reason, int $count): bool {
        if ($this->game->getReasonArg($reason, 3) !== self::WISH) {
            return true;
        }
        $this->grantWish($player_id, $this->getSpotOfCircle($ben));
        return false;
    }

    /** A quitter at the prompt is a drawn opponent who cannot answer, like a finished one. */
    function zombieBenefit(array $benefit): void {
        if ($this->game->getReasonArg($benefit["benefit_data"], 3) === self::WISH) {
            $this->queueWishForOwner((int) $this->game->getCivOwner($this->civ), (int) $benefit["benefit_player_id"]);
        }
    }

    /**
     * Both score the circle; the owner also gains one of its neighbouring squares, in either order.
     * An opponent who is finished or zombie does not score, so the engine never has to make them
     * active (FORMAL_RULES CIV.GENIES.1).
     */
    function grantWish(int $opponent_id, int $spot): void {
        $game = $this->game;
        $owner = (int) $game->getCivOwner($this->civ);
        $this->systemAssertTrue("ERR:Genies:17", $this->isCircle($spot));
        $this->systemAssertTrue("ERR:Genies:18", $owner > 0 && $opponent_id != $owner);
        $token = $this->getTokenOf($opponent_id);
        $this->systemAssertTrue("ERR:Genies:19", $token !== null);

        $delegated = !$game->isPlayerAlive($opponent_id);
        $game->dbSetStructureLocation(
            (int) $token["card_id"],
            $this->getCivSlot($spot),
            null,
            $delegated
                ? clienttranslate('${player_name} places the drawn player token on the chosen circled benefit')
                : clienttranslate('${player_name} places their player token on the chosen circled benefit'),
            $delegated ? $owner : $opponent_id
        );
        $circle = $this->getSlotBenefit($spot);
        $game->interruptBenefit();
        if (!$delegated) {
            $game->queueBenefitNormal($circle, $opponent_id, reason_civ($this->civ));
        }
        $game->queueBenefitNormal(["choice" => [$circle, BE_GENIES_SQUARE]], $owner, reason_civ($this->civ));
        $game->queueBenefitNormal(BE_CIV_END, $owner, reason_civ($this->civ));
    }

    /** The wish is fully paid out, the drawn token goes back in the pile. */
    function endCivAbility(int $player_id): void {
        $this->returnTokensToPile();
    }

    function gainAdjacentSquare(int $player_id, string $reason): bool {
        $spot = $this->getWishedSpot();
        $this->systemAssertTrue("ERR:Genies:20", $this->isCircle($spot));

        $before = (($spot + self::RING_SPOTS - 2) % self::RING_SPOTS) + 1;
        $after = ($spot % self::RING_SPOTS) + 1;
        $this->game->queueBenefitInterrupt(["or" => [$this->getSlotBenefit($before), $this->getSlotBenefit($after)]], $player_id, $reason);
        return true;
    }

    function returnTokensToPile(): void {
        foreach ($this->getAllCubesOnCiv() as $token) {
            if ((int) getPart($token["card_location"], 2) != self::PILE) {
                $this->game->dbSetStructureLocation((int) $token["card_id"], $this->getCivSlot(self::PILE));
            }
        }
    }

    /** The circle the drawn token sits on, 0 while no wish is in progress. */
    function getWishedSpot(): int {
        foreach ($this->getAllCubesOnCiv() as $token) {
            $spot = (int) getPart($token["card_location"], 2);
            if ($spot != self::PILE) {
                return $spot;
            }
        }
        return 0;
    }

    function getTokenOf(int $player_id): ?array {
        foreach ($this->getAllCubesOnCiv() as $token) {
            if ((int) $token["card_location_arg"] == $player_id) {
                return $token;
            }
        }
        return null;
    }

    /** Even spots are the circled (score) benefits, odd ones the squared (gain) benefits. */
    function isCircle(int $spot): bool {
        return $spot >= 1 && $spot <= self::RING_SPOTS && $spot % 2 == 0;
    }

    function getCircleSpots(): array {
        return range(2, self::RING_SPOTS, 2);
    }

    function getCircleBenefits(): array {
        return array_map(fn($spot) => $this->getSlotBenefit($spot), $this->getCircleSpots());
    }

    function getSpotOfCircle(int $ben): int {
        foreach ($this->getCircleSpots() as $spot) {
            if ($this->getSlotBenefit($spot) == $ben) {
                return $spot;
            }
        }
        return 0;
    }

    function getSlotBenefit(int $spot): int {
        $slots = $this->getRules("slots");
        $slot = array_get($slots, $spot);
        $this->systemAssertTrue("ERR:Genies:21", $slot !== null);
        return (int) reset($slot["benefit"]);
    }
}
