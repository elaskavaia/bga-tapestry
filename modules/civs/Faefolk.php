<?php

declare(strict_types=1);

class Faefolk extends AbsCivilization {
    const ELLIPSE_SPOTS = 7;
    const CHOICE_GAIN_TAPESTRY = 1;
    const CHOICE_FLICKER_ONLY = 2;

    public function __construct(object $game) {
        parent::__construct(CIV_FAEFOLK, $game);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        $data["title"] = clienttranslate("You may gain a tapestry card, then flicker");
        $data["slots_choice"] = [
            self::CHOICE_GAIN_TAPESTRY => [
                "benefit" => [BE_TAPESTRY],
            ],
            self::CHOICE_FLICKER_ONLY => [
                "title" => clienttranslate("Flicker only"),
                "tooltip" => clienttranslate("Move the token without gaining a tapestry card"),
            ],
        ];
        return $data;
    }

    /**
     * The flicker is queued behind the tapestry card rather than resolved here. Hand cards count,
     * so a gained card always adds a spot: the printed "gain first, count second" order decides
     * the result on every use of the ability, not just in corner cases.
     */
    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $civ = $this->civ;
        $game = $this->game;
        $this->systemAssertTrue("ERR:Faefolk:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Faefolk:12", $game->hasCiv($player_id, $civ));
        $this->systemAssertTrue("ERR:Faefolk:13", $spot == self::CHOICE_GAIN_TAPESTRY || $spot == self::CHOICE_FLICKER_ONLY);

        $queue = $spot == self::CHOICE_GAIN_TAPESTRY ? [BE_TAPESTRY, BE_FAEFOLK_FLICKER] : [BE_FAEFOLK_FLICKER];
        $game->queueBenefitNormal($queue, $player_id, reason_civ($civ));
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $civ = $this->civ;
        $game = $this->game;
        $this->systemAssertTrue("ERR:Faefolk:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Faefolk:12", $game->hasCiv($player_id, $civ));
        $this->systemAssertTrue("ERR:Faefolk:15", $ben == BE_FAEFOLK_FLICKER);

        $new_spot = $this->flicker($player_id, $this->countVisibleTapestry($player_id));
        $slots = $this->getRules("slots");
        $this->systemAssertTrue("ERR:Faefolk:14", array_get($slots, $new_spot) !== null);

        $times = $game->getCurrentEra($player_id) == 5 ? 2 : 1;
        $game->interruptBenefit();
        for ($i = 0; $i < $times; $i++) {
            $game->queueBenefitNormal($slots[$new_spot]["benefit"], $player_id, $reason);
        }
        return true; // stBenefitManager cashes row 343 on true, the interrupt already put the spot ahead of it
    }

    /**
     * Visible tapestry cards are the ones in hand plus the ones played on the income mat
     * (FORMAL_RULES CIV.FAEFOLK.2). A card on era_6 is covered by the one played over it. The HERALDS clone
     * on civilization_6 and the ESPIONAGE clones on tapestry_NN are copies whose originals are
     * already counted in era%, and neither location is searched here.
     */
    function countVisibleTapestry(int $player_id): int {
        $game = $this->game;
        $count = count($game->getCardsSearch(CARD_TAPESTRY, null, "hand", $player_id));
        foreach ($game->getCardsSearch(CARD_TAPESTRY, null, "era%", $player_id) as $card) {
            if ($card["card_location"] !== "era_6") {
                $count++;
            }
        }
        return $count;
    }

    function flicker(int $player_id, int $steps): int {
        $game = $this->game;
        $tokens = $this->getAllCubesOnCiv();
        $this->systemAssertTrue("ERR:Faefolk:20", count($tokens) == 1);
        $token = reset($tokens);
        $spot = (int) getPart($token["card_location"], 2);
        $this->systemAssertTrue("ERR:Faefolk:21", $spot >= 1 && $spot <= self::ELLIPSE_SPOTS);

        $new_spot = (($spot - 1 + $steps) % self::ELLIPSE_SPOTS) + 1;
        $game->notifyWithName(
            "message",
            clienttranslate('${player_name} has ${count} visible tapestry cards and flickers to spot ${spot}'),
            [
                "count" => $steps,
                "spot" => $new_spot,
            ],
            $player_id
        );
        $game->dbSetStructureLocation(
            (int) $token["card_id"],
            $this->getCivSlot($new_spot),
            $game->getCurrentEra($player_id),
            "",
            $player_id
        );
        return $new_spot;
    }
}
