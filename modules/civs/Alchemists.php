<?php

declare(strict_types=1);

class Alchemists extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_ALCHEMISTS, $game);
    }

    function moveCivCube(int $player_id, int $spot, $extra, array $civ_args) {
        $condition = array_get($civ_args, "benefit_data");
        $is_midgame = $condition == "midgame";
        $civ = $this->civ;
        $game = $this->game;
        if ($is_midgame) {
            $this->systemAssertTrue("ERR:Alchemists:20");
            return;
        }

        if ($game->isAdjustments9()) {
            $this->alchemistRoll9($player_id, $spot);
        } elseif ($game->getAdjustmentVariant() == 8) {
            $this->alchemistRoll8($player_id, $spot);
        } else {
            // 1 roll, 0 - stop
            if ($spot == 1) {
                $this->alchemistRoll($player_id);
            } else {
                $this->alchemistClaim($player_id);
            }
        }
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $benefit;
        $condition = array_get($benefit, "benefit_data");
        $data["reason"] = $this->game->getReasonFullRec(reason(CARD_CIVILIZATION, $civ), false);
        $data["slots"] = [];
        //$slots = $this->getRules('slots');

        if ($game->getAdjustmentVariant() >= 8) {
            $data["slots_choice"] = [];
            $remaining = $this->getRemainingDice();

            if ($remaining[0]) {
                $data["title"] = clienttranslate("Roll all dice");
                $data["slots_choice"][0] = [
                    "title" => "Roll",
                ];
            } else {
                $data["title"] = clienttranslate("Chose one die to keep");
                if (!$game->isAdjustments9() && $remaining[4]) {
                    $data["title"] = clienttranslate("Chose to re-roll all dice or select one die to keep");
                    $data["slots_choice"][4] = [
                        "title" => "Re-roll",
                    ];
                }
                $this->addDieChoices($data, $remaining);
            }
        }
        return $data;
    }

    function addDieChoices(array &$data, array $remaining) {
        if ($remaining[1]) {
            $data["slots_choice"][1] = [
                "benefit" => BE_CONQ_DIE_BLACK,
            ];
        }
        if ($remaining[2]) {
            $data["slots_choice"][2] = [
                "benefit" => BE_CONQ_DIE_RED,
            ];
        }
        if ($remaining[3]) {
            $data["slots_choice"][3] = [
                "benefit" => BE_RESEARCH_DIE,
            ];
        }
    }

    function alchemistRoll8(int $player_id, int $spot) {
        $civ = $this->civ;
        $game = $this->game;

        $remaining = $this->getRemainingDice();
        $remaining_count = $this->getRemainingDiceCount($remaining);

        $this->systemAssertTrue("ERR:Alchemists:Roll:$spot", array_get($remaining, $spot));

        if ($spot == 0 || $spot == 4) {
            $this->systemAssertTrue("ERR:Alchemists:Roll:5", $remaining_count > 1);
            $this->placeCivCube($player_id, $spot, 0, $spot, clienttranslate('${player_name} rolls dice'));
            $this->rollAllDice($player_id, $remaining);
            // re-schedule benefit again
            $game->benefitCivEntry($civ, $player_id, "");
            return;
        }

        $this->keepDie($player_id, $spot);
        $remaining[$spot] = false;
        $remaining_count = $this->getRemainingDiceCount($remaining);
        if ($remaining_count > 1) {
            $this->rollAllDice($player_id, $remaining);
            // re-schedule benefit again
            $game->benefitCivEntry($civ, $player_id, "");
        } else {
            // gain

            $bene = [];

            if ($remaining[1] == false) {
                $slots = $this->getRules("slots");
                $roll = $game->getGameStateValue("conquer_die_black");
                $ben = $slots[$roll]["benefit"];
                $bene = array_merge($bene, $ben);
            }
            if ($remaining[2] == false) {
                $ben = $game->getConquerDieBenefit("red");
                $bene = array_merge($bene, $ben);
                $bene = array_merge($bene, $ben);
            }
            if ($remaining[3] == false) {
                $research = $game->getGameStateValue("science_die");
                $bene[] = 21 + $research;
            }
            $game->queueBenefitNormal(["choice" => $bene], $player_id, reason_civ($civ));
            $this->removeCubes();
        }
    }

    function keepDie(int $player_id, int $spot) {
        $blanks = $this->game->getStructuresOnCiv($this->civ, BUILDING_CUBE, 4);
        $cube = array_shift($blanks);
        $cube_id = 0;
        if ($cube) {
            $cube_id = $cube["card_id"] + 0;
        }

        $this->placeCivCube($player_id, $spot, $cube_id, $spot, clienttranslate('${player_name} keeps die'));
    }

    /**
     * Alchemists 2026 (a9): no re-rolls, all three dice end up on the mat, one die at a time.
     */
    function alchemistRoll9(int $player_id, int $spot) {
        $civ = $this->civ;
        $game = $this->game;

        $remaining = $this->getRemainingDice();
        $this->systemAssertTrue("ERR:Alchemists:Roll9:$spot", array_get($remaining, $spot));

        if ($spot == 0) {
            $this->placeCivCube($player_id, $spot, 0, $spot, clienttranslate('${player_name} rolls dice'));
            $this->rollAllDice($player_id, $remaining);
            $game->benefitCivEntry($civ, $player_id, "");
            return;
        }

        $this->systemAssertTrue("ERR:Alchemists:Roll9:5", $spot >= 1 && $spot <= 3);
        $this->keepDie($player_id, $spot);
        $remaining[$spot] = false;
        $this->rollAllDice($player_id, $remaining);

        if ($this->getRemainingDiceCount($remaining) > 1) {
            $game->benefitCivEntry($civ, $player_id, "");
            return;
        }

        // the last die is placed without a choice
        foreach ([1, 2, 3] as $last) {
            if ($remaining[$last]) {
                $this->keepDie($player_id, $last);
                break;
            }
        }
        $this->alchemistGain9($player_id);
    }

    function alchemistGain9(int $player_id) {
        $game = $this->game;
        $reason = reason_civ($this->civ);
        $research = $game->getGameStateValue("science_die");

        $game->queueBenefitNormal($game->getConquerDieBenefit("red"), $player_id, $reason);
        $game->queueBenefitNormal(["or" => [21 + $research, BE_ALCHEMISTS_DIE]], $player_id, $reason);
        $this->removeCubes();
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $civ = $this->civ;
        $game = $this->game;
        $game->systemAssertTrue("ERR:Alchemists:31", $game->isRealPlayer($player_id));
        $game->systemAssertTrue("ERR:Alchemists:32", $game->hasCiv($player_id, $civ));
        $this->systemAssertTrue("ERR:Alchemists:30", $ben == BE_ALCHEMISTS_DIE);
        $slots = $this->getRules("slots");
        $roll = $game->getGameStateValue("conquer_die_black");
        $game->queueBenefitInterrupt($slots[$roll]["benefit"], $player_id, $reason);
        return true; // stBenefitManager cashes row 341 on true, the interrupt already put the pair ahead of it
    }

    function getRemainingDice() {
        $token_data = $this->getAllCubesOnCiv();
        $remaining = [];
        $remaining[0] = true;
        $remaining[1] = true;
        $remaining[2] = true;
        $remaining[3] = true;
        $remaining[4] = true;

        foreach ($token_data as $token) {
            $state = (int) $token["card_location_arg2"];
            $remaining[$state] = false;
        }

        return $remaining;
    }

    function getRemainingDiceCount(array $remaining) {
        $count = 0;
        foreach ($remaining as $state => $value) {
            if ($value == true && $state >= 1 && $state <= 3) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Plain rolls, permanently: ALCHEMISTS and PSIONICS are incompatible (FORMAL_RULES
     * CIV.PSIONICS.12), so no owner of this mat ever samples a second face. The dropped dice
     * integration is why the sample is never offered here.
     */
    function rollAllDice(int $player_id, array $remaining) {
        $civ = $this->civ;
        $game = $this->game;
        if (array_get($remaining, 1)) {
            $game->rollBlackConquerDie($player_id, false, false);
        }
        if (array_get($remaining, 2)) {
            $game->rollRedConquerDie($player_id, false, false);
        }
        if (array_get($remaining, 3)) {
            $game->rollScienceDie(reason_civ($civ), "science_die", $player_id, false, false);
        }

        $game->prepareUndoSavepoint();
    }

    function alchemistRoll(int $player_id) {
        $civ = $this->civ;
        $game = $this->game;

        // interrupt before the roll, so a civilization reacting to it is ahead of the bust benefit
        $game->interruptBenefit();
        // plain roll, permanently: ALCHEMISTS and PSIONICS are incompatible, see rollAllDice
        $die_roll = $game->rollScienceDie(reason_civ($civ), "science_die", -1, true, false)[0];
        $token_data = $this->getAllCubesOnCiv();
        $bust = false;
        foreach ($token_data as $tid => $token) {
            $slot = explode("_", $token["card_location"])[2];
            if ($slot == $die_roll) {
                $bust = true;
                break;
            }
        }
        $token_id = -1;
        if ($bust) {
            $game->notifyWithName("message_error", clienttranslate('${player_name} busts'));

            if ($game->isAdjustments4()) {
                $track = $die_roll;
                $game->queueBenefitNormal(["or" => [BE_REGRESS_E - 1 + $track, 401]], $player_id, reason_civ(CIV_ALCHEMISTS)); // Regress with BB
            } else {
                $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ(CIV_ALCHEMISTS));
            }
            $this->removeCubes();
        }
        if (!$bust) {
            $token_id = $game->addCivToken($player_id, $die_roll, CIV_ALCHEMISTS);
        }

        $game->notifyWithName(
            "alchemistRoll",
            "",
            [
                "token_id" => $token_id,
                "tokens" => $token_data,
                "die" => $die_roll,
            ],
            $player_id
        );

        if ($bust) {
            return true;
        }
        // re-schedule benefit again
        $game->benefitCivEntry($civ, $player_id, "");
        return false;
    }

    function alchemistClaim(int $player_id) {
        $civ = $this->civ;
        $game = $this->game;
        $token_data = $this->getAllCubesOnCiv();
        $game->userAssertTrue(totranslate("You must Roll at least once"), count($token_data) > 0);

        $tracks = [];
        foreach ($token_data as $tid => $token) {
            $track = getPart($token["card_location"], 2);
            $tracks[] = $track;
        }
        $reason = reason_civ(CIV_ALCHEMISTS);
        if ($game->isAdjustments4()) {
            $withben = [];
            foreach ($tracks as $track) {
                $withben[] = $game->matFindBenefit([
                    "t" => $track,
                    "adv" => 1,
                    "flags" => FLAG_GAIN_BENEFIT | FLAG_PAY_BONUS | FLAG_MAXOUT_BONUS,
                ]);
            }
            $withben[] = 401;
            $game->queueBenefitNormal(["or" => $withben], $player_id, $reason);
        } else {
            foreach ($tracks as $track) {
                $game->queueBenefitNormal(96 + $track, $player_id, $reason); // advance no ben, buf +5 for going over
            }
        }
        $this->removeCubes();
        $game->notifyWithName("alchemistRoll", "", ["token_id" => -1, "tokens" => $token_data, "die" => 1], $player_id);
    }

    function removeCubes() {
        $token_data = $this->getAllCubesOnCiv();

        foreach ($token_data as $id => $token) {
            $this->game->dbSetStructureLocation($id, "hand", 0, "");
        }
    }
}
