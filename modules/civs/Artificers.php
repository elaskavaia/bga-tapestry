<?php

declare(strict_types=1);

/**
 * Artificers re-lay their own income mat, so the uncovered spots of a track stop being a prefix.
 * The engine seams that allow that are in PGameXBody (a building row carries the spot it covers);
 * everything here is the choice and the mutation.
 */
class Artificers extends AbsCivilization {
    const ACTION_SET_ASIDE = 1;
    const ACTION_SLIDE = 2;
    const ACTION_SLIDE_ALL = 3;
    const ACTION_SCORE = 4;
    const FINAL_INCOME_TURN = 5;

    public function __construct(object $game) {
        parent::__construct(CIV_ARTIFICERS, $game);
    }

    /**
     * Nothing on any track means no legal answer, so the prompt is skipped rather than shown
     * empty. Income turn 5 always has the scoring option, so only turns 2-4 can end up here.
     */
    function queueEraCivAbility($player_id, $incomeTurn = 0) {
        $game = $this->game;
        $player_id = (int) $player_id;
        $incomeTurn = (int) ($incomeTurn ?: $game->getCurrentEra($player_id));
        $income_trigger = $this->getRules("income_trigger", []);
        $in_range = in_range($incomeTurn, array_get($income_trigger, "from", 0), array_get($income_trigger, "to", 0));
        if ($in_range && !$this->getChoices($player_id, $incomeTurn)) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name}: ability of ${civ_name} is not applicable, no income building is left on their mat'),
                ["civ_name" => $game->getTokenName(CARD_CIVILIZATION, $this->civ)],
                $player_id
            );
            return;
        }
        parent::queueEraCivAbility($player_id, $incomeTurn);
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $data = $benefit;
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $this->civ), false);
        $data["slots"] = [];
        $this->populateSlotChoiceForArgs($data);
        $incomeTurn = (int) $this->game->getCurrentEra((int) $player_id);
        $data["title"] =
            $incomeTurn == self::FINAL_INCOME_TURN
                ? clienttranslate("You may score your income buildings, or pack one income track to the left")
                : clienttranslate("You may set aside or slide the leftmost building of one income track");
        $data["slots_choice"] = $this->getChoices((int) $player_id, $incomeTurn);
        return $data;
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $game = $this->game;
        $this->systemAssertTrue("ERR:Artificers:11", $game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Artificers:12", $game->hasCiv($player_id, $this->civ));
        $this->systemAssertTrue("ERR:Artificers:13 spot $spot", array_key_exists($spot, array_get($civ_args, "slots_choice", [])));

        $track = intdiv($spot, 10);
        switch ($spot % 10) {
            case self::ACTION_SET_ASIDE:
                $this->setAsideBuilding($player_id, $track);
                return;
            case self::ACTION_SLIDE:
                $this->slideBuildingLeft($player_id, $track);
                return;
            case self::ACTION_SLIDE_ALL:
                $this->slideTrackLeft($player_id, $track);
                return;
            case self::ACTION_SCORE:
                $this->scoreBuildings($player_id);
                return;
        }
        $this->systemAssertTrue("ERR:Artificers:14 spot $spot");
    }

    /** The prompt entry key: readable in the log and decoded back into a track and an action. */
    function getChoiceKey(int $track, int $action): int {
        return $track * 10 + $action;
    }

    function getChoices(int $player_id, int $incomeTurn): array {
        return $incomeTurn == self::FINAL_INCOME_TURN ? $this->getFinalChoices($player_id) : $this->getMutationChoices($player_id);
    }

    /** Income turns 2-4: set aside or slide the leftmost building of any track that still has one. */
    function getMutationChoices(int $player_id): array {
        $choices = [];
        for ($track = 1; $track <= 4; $track++) {
            $buildings = $this->game->getIncomeBuildingsOnTrack($player_id, $track);
            if (!$buildings) {
                continue;
            }
            $choices[$this->getChoiceKey($track, self::ACTION_SET_ASIDE)] = $this->getTrackChoice(
                $track,
                clienttranslate('Set aside: ${track_name}'),
                clienttranslate("Take the leftmost building off this track, it is not placed in your capital city")
            );
            // the leftmost building carries the smallest spot, so the space left of it is empty whenever it exists
            if ((int) reset($buildings)["card_location_arg2"] > 1) {
                $choices[$this->getChoiceKey($track, self::ACTION_SLIDE)] = $this->getTrackChoice(
                    $track,
                    clienttranslate('Slide left: ${track_name}'),
                    clienttranslate("Slide the leftmost building one space left, covering that space and revealing the one it came from")
                );
            }
        }
        return $choices;
    }

    /** Income turn 5: score, or pack a track that is not packed already. */
    function getFinalChoices(int $player_id): array {
        $choices = [
            self::ACTION_SCORE => [
                "title" => clienttranslate("Score your income buildings"),
                "tooltip" => clienttranslate("1 VP per income building that left your income mat, wherever it is now"),
            ],
        ];
        for ($track = 1; $track <= 4; $track++) {
            $spots = array_column($this->game->getIncomeBuildingsOnTrack($player_id, $track), "card_location_arg2");
            if (!$spots || array_map("intval", $spots) == range(1, count($spots))) {
                continue;
            }
            $choices[$this->getChoiceKey($track, self::ACTION_SLIDE_ALL)] = $this->getTrackChoice(
                $track,
                clienttranslate('Slide all left: ${track_name}'),
                clienttranslate("Slide every building of this track as far left as it goes, revealing the highest numbered spaces")
            );
        }
        return $choices;
    }

    function getTrackChoice(int $track, string $title, string $tooltip): array {
        return [
            "title" => $title,
            "tooltip" => $tooltip,
            "i18n" => ["track_name"],
            "track_name" => $this->game->income_tracks[$track]["name"],
        ];
    }

    function getLeftmostBuilding(int $player_id, int $track): array {
        $buildings = $this->game->getIncomeBuildingsOnTrack($player_id, $track);
        $this->systemAssertTrue("ERR:Artificers:15 track $track", count($buildings) > 0);
        return reset($buildings);
    }

    /** Not claimIncomeStructure: setting a building aside is not gaining one (CIV.ARTIFICERS.6). */
    function setAsideBuilding(int $player_id, int $track): void {
        $game = $this->game;
        $building = $this->getLeftmostBuilding($player_id, $track);
        $game->dbSetStructureLocation(
            (int) $building["card_id"],
            "hand",
            0,
            clienttranslate('${player_name} sets aside one ${structure_name}'),
            $player_id
        );
        $game->dbIncIncomeTrackLevel($player_id, $track);
    }

    /** One space left, never to the leftmost empty space (CIV.ARTIFICERS.1). The level does not move. */
    function slideBuildingLeft(int $player_id, int $track): void {
        $building = $this->getLeftmostBuilding($player_id, $track);
        $spot = (int) $building["card_location_arg2"];
        $this->systemAssertTrue("ERR:Artificers:16 track $track spot $spot", $spot > 1);
        $this->game->dbSetStructureLocation(
            (int) $building["card_id"],
            "income",
            $spot - 1,
            clienttranslate('${player_name} slides one ${structure_name} one space to the left'),
            $player_id
        );
    }

    /** Flush against the left end, so the spaces it uncovers are the highest numbered ones (CIV.ARTIFICERS.4). */
    function slideTrackLeft(int $player_id, int $track): void {
        $spot = 1;
        foreach ($this->game->getIncomeBuildingsOnTrack($player_id, $track) as $building) {
            if ((int) $building["card_location_arg2"] != $spot) {
                $this->game->dbSetStructureLocation(
                    (int) $building["card_id"],
                    "income",
                    $spot,
                    clienttranslate('${player_name} slides one ${structure_name} to the left'),
                    $player_id
                );
            }
            $spot++;
        }
    }

    /** 1 VP per building that left the mat, wherever it sits now, set aside ones included (BUILDING.1). */
    function scoreBuildings(int $player_id): void {
        $count = 0;
        for ($track = 1; $track <= 4; $track++) {
            $count += $this->game->countIncomeStructuresOffTrack($player_id, $track);
        }
        if ($count) {
            $this->game->awardVP($player_id, $count, reason_civ($this->civ));
        }
    }
}
