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
     * Nothing on the mat is clicked, the owner only confirms the ability. It still goes through the
     * civ ability state, the one place a player holding two income civs picks the order.
     */
    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
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
     * The draw is with replacement: every token goes back in the pile first, which also clears the
     * one left on the ring by the previous wish. An opponent past income turn 5 cannot answer, so
     * their token is skipped; a zombie answers with a random circle (FORMAL_RULES 5.6).
     */
    function drawOpponent(int $player_id): bool {
        $game = $this->game;
        $this->returnTokensToPile();
        $tokens = array_values(
            array_filter($this->getAllCubesOnCiv(), fn($token) => $game->getCurrentEra((int) $token["card_location_arg"]) <= 5)
        );
        if (count($tokens) == 0) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} has nobody left to grant a wish, the ability is skipped'),
                [],
                $player_id
            );
            return true;
        }

        $opponent_id = (int) $tokens[$game->bgaRand(0, count($tokens) - 1)]["card_location_arg"];
        $game
            ->notif("message", $player_id)
            ->withPlayer2($opponent_id)
            ->notifyAll(clienttranslate('${player_name} draws the player token of ${player_name2}'));
        if ($game->isZombiePlayer($opponent_id)) {
            $this->grantRandomWish($opponent_id);
            // nobody else becomes active, so the rolls need their own savepoint or undo re-draws
            $game->prepareUndoSavepoint();
            return true;
        }
        $game->queueBenefitInterrupt(["or" => $this->getCircleBenefits()], $opponent_id, reason_civ($this->civ, self::WISH));
        return true;
    }

    /** The drawn opponent's pick comes back through the queue as a circled benefit tagged as the wish. */
    function interceptOpponentBenefit(int $ben, int $player_id, string $reason, int $count): bool {
        if ($this->game->getReasonArg($reason, 3) !== self::WISH) {
            return true;
        }
        $this->grantWish($player_id, $this->getSpotOfCircle($ben));
        return false;
    }

    function zombieBenefit(array $benefit): void {
        if ($this->game->getReasonArg($benefit["benefit_data"], 3) === self::WISH) {
            $this->grantRandomWish((int) $benefit["benefit_player_id"]);
        }
    }

    function grantRandomWish(int $opponent_id): void {
        $game = $this->game;
        $spots = $this->getCircleSpots();
        $game
            ->notif("message", $opponent_id)
            ->notifyAll(clienttranslate('${player_name} is zombie, a random circled benefit is chosen for them'));
        $this->grantWish($opponent_id, $spots[$game->bgaRand(0, count($spots) - 1)]);
    }

    /**
     * Both score the circle; the owner also gains one of its neighbouring squares, in either order.
     * A zombie opponent does not score, so the engine never has to make them active (FORMAL_RULES 5.6).
     */
    function grantWish(int $opponent_id, int $spot): void {
        $game = $this->game;
        $owner = (int) $game->getCivOwner($this->civ);
        $this->systemAssertTrue("ERR:Genies:17", $this->isCircle($spot));
        $this->systemAssertTrue("ERR:Genies:18", $owner > 0 && $opponent_id != $owner);
        $token = $this->getTokenOf($opponent_id);
        $this->systemAssertTrue("ERR:Genies:19", $token !== null);

        $game->dbSetStructureLocation(
            (int) $token["card_id"],
            $this->getCivSlot($spot),
            null,
            clienttranslate('${player_name} places their player token on the chosen circled benefit'),
            $opponent_id
        );
        $circle = $this->getSlotBenefit($spot);
        $game->interruptBenefit();
        if (!$game->isZombiePlayer($opponent_id)) {
            $game->queueBenefitNormal($circle, $opponent_id, reason_civ($this->civ));
        }
        $game->queueBenefitNormal(["choice" => [$circle, BE_GENIES_SQUARE]], $owner, reason_civ($this->civ));
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
