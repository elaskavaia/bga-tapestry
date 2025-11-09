<?php

declare(strict_types=1);

class Advisors extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_ADVISORS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        if (!$start) {
            $midgame_setup = $this->getRules("midgame_setup", false);
            if ($midgame_setup) {
                if ($this->game->isAdjustments8()) {
                    //immediately place up to 2 player tokens on spaces that do not have your player tokens on opponents' income mats.
                    $this->game->benefitCivEntry($civ, $player_id);
                    $this->game->benefitCivEntry($civ, $player_id);
                } else {
                    $this->game->benefitCivEntry($civ, $player_id, "midgame");
                }
            }
        }
        $tokens = $this->game->effect_setupCivTokens($civ, $player_id);
        return ["tokens" => $tokens, "outposts" => []];
    }

    function interceptTapestryGain($ben, $player_id = null, $reason = "", $count = 1) {
        $civ_owner = $this->game->getCivOwner(CIV_ADVISORS);
        $game = $this->game;
        if ($civ_owner && $civ_owner != $player_id && !$game->isIncomeTurn() && $game->getAdjustmentVariant() < 8) {
            $nei = $game->getPlayerNeighbours($civ_owner, false);
            if (!in_array($player_id, $nei)) {
                return true;
            }
            if ($game->getCardCountInHand($civ_owner, CARD_TAPESTRY) == 0) {
                return true;
            }

            for ($i = 0; $i < $count; $i++) {
                $game->queueBenefitNormal(
                    ["p" => 138, "g" => [BE_TAPESTRY, "h" => 603]],
                    $civ_owner,
                    reason(CARD_CIVILIZATION, CIV_ADVISORS, $player_id)
                );
                $game->benefitSingleEntry("standard", $ben, $player_id, 1, $reason);
            }
            return false;
        }
        return true;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $civ = $this->civ;
        $game = $this->game;
        $this->systemAssertTrue("ERR:Advisors:11", $this->game->isRealPlayer($player_id));
        if ($ben == BE_ADVISORS_OVERTAKE_ADVISE_SELECTED) {
            $game->gamestate->nextState("tapestryChoice");
            return false;
        }

        $this->systemAssertTrue("ERR:Advisors:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case BE_ADVISORS_OVERTAKE:
                $opponent_id = $game->getReasonArg($reason, 3);
                $card_id = $game->getReasonArg($reason, 4);
                $card = $game->getCardInfoById($card_id);
                $tap_type = $card["card_type_arg"];
                $tapvar = $game->getRulesCard(CARD_TAPESTRY, $tap_type, "type");
                $game->clearCurrentBenefit();
                if ($tapvar == "now") {
                    // WHEN PLAYED
                    $game->queueBenefitInterrupt(
                        ["or" => [BE_ADVISORS_OVERTAKE_APPROVE, BE_ADVISORS_OVERTAKE_ADVISE]],
                        $player_id,
                        reason_civ($civ, "$opponent_id:$card_id")
                    );
                } else {
                    $game->queueBenefitInterrupt(BE_ADVISORS_OVERTAKE_ADVISE, $player_id, reason_civ($civ, "$opponent_id:$card_id"));
                }
                $game->nextStateBenefitManager();
                return false;
            case BE_ADVISORS_OVERTAKE_APPROVE:
                $opponent_id = $game->getReasonArg($reason, 3);
                $card_id = $game->getReasonArg($reason, 4);
                $game->clearCurrentBenefit();
                $game->switchPlayer($opponent_id, false);
                $game->playTapestryCard($card_id, $opponent_id, false);
                $card = $game->getCardInfoById($card_id);
                $tap_type = $card["card_type_arg"];
                $card_effect = $game->getRulesCard(CARD_TAPESTRY, $tap_type);
                if ($card_effect) {
                    $game->queueBenefitNormal($card_effect, $player_id, reason_tapestry($tap_type));
                }
                $game->nextStateBenefitManager();
                return false;
            case BE_ADVISORS_OVERTAKE_ADVISE:
                $card_id = $game->getReasonArg($reason, 4);
                $opponent_id = $game->getReasonArg($reason, 3);

                $res = $this->getWhenPlayedInHand($player_id);
                if (count($res) == 0) {
                    $game->clearCurrentBenefit();

                    $game->playTapestryCard($card_id, $opponent_id, false);
                    $game->notifyWithName("message", clienttranslate('${player_name} ADVISORS get decline bonus'), [], $player_id);
                    $game->queueBenefitNormal(BE_ADVISORS_FALLBACK, $player_id, reason_civ($civ));
                    $game->nextStateBenefitManager();
                } else {
                    $game->gamestate->nextState("tapestryChoice");
                }

                return false;

            default:
                $this->systemAssertTrue("ERR:Advisors:10");
                return true;
        }
        return true;
    }

    function playAdvise(int $player_id, int $card_id) {
        $game = $this->game;
        $bene = $game->getCurrentBenefit(BE_ADVISORS_OVERTAKE_ADVISE);
        $reason = $bene["benefit_data"];
        $opponent_id = $game->getReasonArg($reason, 3);
        //$card_id = $game->getReasonArg($reason, 4);

        $args = $game->notifArgsAddCardInfo($card_id);
        $game->notifyWithName("message", clienttranslate('${player_name} proposes to play ${card_name} instead'), $args, $player_id);
        $game->effect_moveCard($card_id, $player_id, "hand", $opponent_id); // move card to opponent hand
        $game->queueBenefitInterrupt(BE_ADVISORS_OVERTAKE_ADVISE_SELECTED, $opponent_id, reason_civ(CIV_ADVISORS, "$player_id:$card_id"));
        return;
    }

    function playAdviseResponse(int $player_id, int $card_id) {
        $civ = $this->civ;
        $game = $this->game;
        $bene = $game->getCurrentBenefit(BE_ADVISORS_OVERTAKE_ADVISE);
        $reasonA = $bene["benefit_data"];
        $original_card_id = $game->getReasonArg($reasonA, 4);
        $beneS = $game->getCurrentBenefit(BE_ADVISORS_OVERTAKE_ADVISE_SELECTED);
        $reason = $beneS["benefit_data"];
        $advisor_id = $game->getReasonArg($reason, 3);
        $advised_card_id = $game->getReasonArg($reason, 4);
        $this->systemAssertTrue("ERR:Advisors:13", $advisor_id == $game->getCivOwner($civ));
        $this->systemAssertTrue("ERR:Advisors:14", $advisor_id != $player_id);
        $game->clearCurrentBenefit($bene);
        $game->clearCurrentBenefit($beneS);
        if ($advised_card_id != $card_id) {
            // declined
            //$game->effect_moveCard($card_id, $player_id, "hand", $player_id, null, ""); // move card back  otherwise cannot play
            $game->effect_moveCard($advised_card_id, $player_id, "hand", $advisor_id); // move card back to advisor
            $this->systemAssertTrue("ERR:Advisors:15", $card_id == $original_card_id);
            $game->playTapestryCard($card_id, $player_id, false);
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} declines to play advisors card, ADVISORS get decline bonus'),
                [],
                $player_id
            );
            $game->queueBenefitNormal(BE_ADVISORS_FALLBACK, $advisor_id, reason_civ($civ));
        } else {
            // accepted
            $game->effect_moveCard($original_card_id, $player_id, "hand", $player_id); // move card back to opponent hand
            $game->playTapestryCard($card_id, $player_id, false);
            $card = $game->getCardInfoById($card_id);
            $tap_type = $card["card_type_arg"];
            $card_effect = $game->getRulesCard(CARD_TAPESTRY, $tap_type);
            $game->queueBenefitNormal($card_effect, $advisor_id, reason_tapestry($tap_type));
        }
    }

    function getWhenPlayedInHand($player_id) {
        $game = $this->game;
        $res = [];
        $cards = $game->getCardsInHand($player_id, CARD_TAPESTRY);
        foreach ($cards as $card) {
            $tap_type = $card["card_type_arg"];
            $tapvar = $game->getRulesCard(CARD_TAPESTRY, $tap_type, "type");
            if ($tapvar == "now") {
                // WHEN PLAYED
                $res[] = $card;
            }
        }
        return $res;
    }

    function moveCivCube(int $player_id, int $slot, $extra, array $civ_args) {
        $civ = $this->civ;
        $game = $this->game;
        $condition = array_get($civ_args, "benefit_data");
        $is_midgame = $condition == "midgame";

        if ($game->isAdjustments8()) {
            $spot = $extra;
            // TODO: validate $spot with $civ_args['targets']

            if (array_search($spot, $civ_args["targets"]) === false) {
                $this->systemAssertTrue("Invalid location $extra");
            }

            // find free cube
            $cube_id = (int) $this->game->addCube($player_id, "hand");
            $message = clienttranslate('${player_name} places player token on opponent\'s mat');
            // UPDATE cube
            $this->game->dbSetStructureLocation($cube_id, $spot, 0, $message, $player_id);
        } else {
            $slots_choice_arr = array_get($civ_args, "slots_choice", []);
            $slots_choice = array_get($slots_choice_arr, $slot, []);
            $this->systemAssertTrue("ERR:Advisors:01", count($slots_choice) > 0);
            $benefit = array_get($slots_choice, "benefit", null);
            $this->systemAssertTrue("ERR:Advisors:02", $benefit !== null);
            $game->queueBenefitNormal($benefit, $player_id, reason_civ($civ));
        }
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $game = $this->game;
        $data = $benefit;
        $condition = array_get($benefit, "benefit_data");
        $data["reason"] = $this->game->getReasonFullRec(reason_civ($civ), false);
        $data["slots"] = [];
        $slots = $this->getRules("slots");
        $slot_choice = $this->getRules("slot_choice");
        $is_midgame = $condition == "midgame";

        if ($game->isAdjustments8()) {
            //At the beginning of your income turns (1-4), place a player token on a space on an opponent's income mat that does not already have one of your player tokens.

            $data["title"] = clienttranslate(
                'Place a player token on a space on an opponent\'s income mat that does not already have one of your player tokens'
            );
            $opponents = $this->game->getOpponentsStartingFromLeft($player_id);
            //$opponents[] = $player_id;
            $data["targets"] = [];
            foreach ($opponents as $opponent_id) {
                if ($opponent_id == PLAYER_SHADOW) {
                    continue;
                }
                // do the query to figure out right slots

                $full = [];
                // $tapestryies = $game->getCardsSearch(CARD_TAPESTRY, null, "era%", $opponent_id, null);
                // foreach ($tapestryies as $card) {
                //     $era = substr($card['card_location'], 3);
                //     $full[$era] = 1;
                // }
                $cubes = $game->getStructuresSearch(BUILDING_CUBE, null, "tapestry_slot_{$opponent_id}_%", $player_id, null);
                //$this->systemAssertTrue("cube at ".toJson($cubes));
                foreach ($cubes as $card) {
                    $era = getPart($card["card_location"], 3);

                    $full[$era] = 1;
                }
                for ($i = 1; $i <= 4; $i++) {
                    if (array_get($full, $i, 0) == 0) {
                        $data["targets"][] = "tapestry_slot_{$opponent_id}_$i";
                    }
                }
            }
        } else {
            if ($is_midgame) {
                $choices = [];
                $choices[0]["benefit"] = [];
                for ($i = 1; $i <= 3; $i++) {
                    $choices[0]["benefit"][] = ["p" => 137, "g" => 505];
                }
                $data["slots_choice"] = $choices;
                $data["decline"] = true;
            } else {
                $cards = $game->getCardsInHand($player_id, CARD_TAPESTRY);
                if (count($cards) > 4) {
                    $count = count($cards) - 4;
                    $data["slots_choice"][1]["benefit"] = [136, array_fill(0, $count, 181), BE_ANYRES];
                    $data["title"] = clienttranslate("Discard down to 4 tapestry cards and gain any resource");
                } else {
                    $data["title"] = clienttranslate("You have 4 or less tapestry cards in hand");
                    $data["slots_choice"][1]["benefit"] = [136]; // 1 vp per tapestry in hand
                }
            }
        }

        return $data;
    }

    public function isAdvisorsOvertake($card_id, $opponent_id, $ben, $era) {
        $civ = $this->civ;
        $game = $this->game;

        if (!($ben == 64 || $ben == BE_PLAY_TAPESTY_INCOME)) {
            return false;
        }
        $player_id = $game->getCivOwner($civ);
        if ($player_id == $opponent_id || $player_id == null) {
            return false;
        }
        $cube = $game->getStructureInfoSearch(BUILDING_CUBE, null, "tapestry_slot_{$opponent_id}_{$era}", $player_id, null);

        if ($cube == null) {
            return false;
        }
        $this->game->dbSetStructureLocation($cube["card_id"], "hand", 0, "", $player_id);
        $game->interruptBenefit();
        if ($game->isRealPlayer($opponent_id)) {
            $this->game->getCurrentEra($opponent_id);
            $this->game->DbQuery("UPDATE card SET card_location='era$era',card_location_arg='$opponent_id' WHERE card_id='$card_id'");

            $this->game
                ->notif("moveCard", $opponent_id)
                ->withCard($card_id)
                ->notifyAll(clienttranslate('${player_name} attempts to play ${card_name}'));
            $game->queueBenefitNormal(BE_ADVISORS_OVERTAKE, $player_id, reason_civ($civ, "$opponent_id:$card_id"));
            return true;
        }
        $game->queueBenefitNormal(BE_ADVISORS_FALLBACK, $player_id, reason_civ($civ));
        return false;
    }
}
