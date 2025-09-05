<?php

declare(strict_types=1);

class Mystics extends AbsCivilization {
    public function __construct(object $game) {
        parent::__construct(CIV_MYSTICS, $game);
    }

    function setupCiv(int $player_id, string $start) {
        $civ = $this->civ;
        $game = $this->game;
        if ($game->isAdjustments8()) {
        }
        if (!$start) {
            $midgame_setup = $this->getRules("midgame_setup", false);
            if ($midgame_setup) {
                $this->game->benefitCivEntry($civ, $player_id, "midgame");
            }
        }

        return ["tokens" => [], "outposts" => []];
    }

    function finalScoring($player_id) {
        $game = $this->game;
        if ($game->isAdjustments8()) {
            return;
        }
        for ($i = 1; $i <= 4; $i++) {
            $data = $this->getMysticPrediction($i, $player_id);
            if ($data == null) {
                continue;
            } // ?
            $prediction = $data["value"];
            if ($prediction == -1) {
                continue;
            } // no prediction
            $real = $data["actual"];
            $category_name = $data["category_name"];
            $game->notifyAllPlayers(
                "message",
                clienttranslate('Final MYSTICS prediction for ${category_name}: predicted ${predicted}, actual ${actual}'),
                [
                    "i18n" => ["category_name"],
                    "category_name" => $category_name,
                    "predicted" => $prediction,
                    "actual" => $real,
                ]
            );
            if ($real == $prediction) {
                $reason = reason_civ(CIV_MYSTICS);
                $count = 10;
                if ($game->isAdjustments4()) {
                    $count = 20;
                }
                $game->awardVP($player_id, $count, $reason);
            }
        }
    }

    function moveCivCube(int $player_id, int $spot, $ids, array $civ_args) {
        $game = $this->game;

        // VALIDITY CHECKS
        $this->systemAssertTrue("user does not have mystics", $this->hasCiv($player_id, CIV_MYSTICS));
        $era = $game->getCurrentEra($player_id);
        if ($era <= 1) {
            $game->userAssertTrue(totranslate("You must choose a value from each row"), count($ids) == 4);
        } elseif ($game->isAdjustments4()) {
            if ($era >= 5) {
                return;
            }
            $game->userAssertTrue(totranslate("Make a number of predictions equal to number of remaining eras"), count($ids) + $era == 5);
        } else {
            $this->systemAssertTrue("ERR:Mystics:500");
        }
        $rows = [];

        foreach ($ids as $id) {
            $index = floor(($id - 1) / 9);
            if ($index > 3 || array_key_exists((int) $index, $rows)) {
                $game->userAssertTrue(totranslate("You must choose a value from each row"));
            }
            $pos = (($id - 1) % 9) + 1;
            $rows[$index] = $pos;
            $this->placeCivCube($player_id, $id + 0, 0, 0);
            $this->checkMysticPrediction($index + 1, $player_id);
        }
    }

    function checkMysticPrediction($category, $player_id = null) {
        $civ = $this->civ;
        $game = $this->game;
        if ($game->isAdjustments8()) {
            return;
        }
        // Completed track - check for mystic bonus

        if (!$game->hasCiv($player_id, CIV_MYSTICS)) {
            return;
        }
        //What did they predict?
        $mystic_prediction = $this->getMysticPrediction($category, $player_id);
        if ($mystic_prediction == null) {
            return;
        }
        $prediction = $mystic_prediction["value"];
        $awarded = $mystic_prediction["awarded"];
        $actual = $mystic_prediction["actual"];
        // Award
        if ($prediction == $actual && !$awarded) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} achieves their MYSTICS prediction: ${category_name}'),
                [
                    "category_name" => $mystic_prediction["category_name"],
                ],
                $player_id
            );
            $civ_location = $mystic_prediction["location"];
            $game->DbQuery("UPDATE structure SET card_location_arg2=1 WHERE card_location='$civ_location'");
            $game->queueBenefitNormal(BE_ANYRES, $player_id, reason_civ(CIV_MYSTICS));
        }
    }

    function getMysticPrediction($category, $player_id) {
        $game = $this->game;
        if (!$this->hasCiv($player_id, CIV_MYSTICS)) {
            return null;
        }
        $civ_tokens = $this->getAllCubesOnCiv();
        if (count($civ_tokens) == 0) {
            return null;
        }
        $prediction = 0;
        $awarded = false;
        $category_name = "unknown";
        $actual = 0;
        $prediction = -1;
        $civ_location = null;
        $cube_id = null;
        switch ($category) {
            case 1:
                $actual = count($game->getCardsSearch(CARD_TECHNOLOGY, null, "hand", $player_id));
                $category_name = clienttranslate("Tech cards");
                break;
            case 2:
                $actual = $game->getDistrictCount($player_id);
                $category_name = clienttranslate("Complete districts");
                break;
            case 3:
                $actual = $game->getNumberOfControlledTerritories($player_id);
                $category_name = clienttranslate("Controlled territories");
                break;
            case 4:
                $actual = $game->getFinishedTracks($player_id);
                $category_name = clienttranslate("Completed tracks");
                break;
            default:
                return null;
        }
        foreach ($civ_tokens as $ct) {
            $holder = explode("_", $ct["card_location"])[2];
            $awarded = $ct["card_location_arg2"] ? true : false;
            $civ_location = $ct["card_location"];
            $cube_id = $ct["card_id"];
            if ($category == 1 && $holder < 10) {
                // TECH CARDS
                $prediction = $holder;
                break;
            }
            if ($category == 2 && $holder > 9 && $holder < 19) {
                // DISTRICTS
                $prediction = $holder - 9;
                break;
            }
            if ($category == 3 && $holder > 18 && $holder < 28) {
                // CONTROLLED OR CONQUERED TERRITORY?
                $prediction = $holder - 18;
                break;
            }
            if ($category == 4 && $holder > 27) {
                // TECH TRACKS
                $prediction = $holder - 27;
                break;
            }
        }
        return [
            "awarded" => $awarded,
            "value" => $prediction,
            "cube_id" => $cube_id,
            "location" => $civ_location,
            "actual" => $actual,
            "category_name" => $category_name,
        ];
    }

    function argCivAbilitySingle($player_id, $benefit) {
        $civ = $this->civ;
        $data = $benefit;
        $condition = array_get($benefit, "benefit_data");
        $data["reason"] = $this->game->getReasonFullRec(reason_civ($civ), false);
        $data["slots"] = [];
        $slots = $this->getRules("slots");
        $slot_choice = $this->getRules("slot_choice");
        $is_midgame = $condition == "midgame";
        return $data;
    }

    function awardBenefits(int $player_id, int $ben, int $count = 1, string $reason = "") {
        $civ = $this->civ;
        $game = $this->game;
        $reason = reason_civ($civ);
        $this->systemAssertTrue("ERR:Mystic:11", $this->game->isRealPlayer($player_id));
        $this->systemAssertTrue("ERR:Mystic:12", $this->game->hasCiv($player_id, $civ));
        switch ($ben) {
            case 333: // BE_MYSTIC_TAP
                /* gain the top card of the public deck and discard your entire hand of tapestry cards to your private discard pile. 
                Based on the quantity of cards discarded in this way, gain the following, then gain 2 cards from your pricate deck:    
                2 cards --> choose one: 
                4 cards --> choose two different:   [CONQUER]/[RESOURCE]/[INVENT]/[REMOVE INCOME BUILDING]     
                6 cards --> choose three different:   [CONQUER-BOTH DICE]/[RESOURCE]/[INVENT]+[UPGRADE]/[INCOME BUILDING]  
                */
                $game->interruptBenefit();
                $game->awardCard($player_id, 1, CARD_TAPESTRY, false, $reason, $game->card_types[CARD_TAPESTRY]["deck"], "discard");
                $this->misDiscard($player_id);
                $game->queueBenefitNormal(BE_TAPESTRY, $player_id, $reason, 2); // gain 2 tapestry
                return true;
            case 334:
                $game->interruptBenefit();
                $era = $game->getCurrentEra($player_id);
                $deck = $game->card_types[CARD_TAPESTRY]["deck"];
                $discard = "discard";

                $game->dbPickCardsForLocation(8, CARD_TAPESTRY, "deck_13", $player_id, $deck, $discard);
                $game->notifyWithName(
                    "message",
                    clienttranslate('${player_name} creates private tapestry deck ${reason}'),
                    [
                        "reason" => $game->getReasonFullRec($reason),
                    ],
                    $player_id
                );

                $game->notifyDeckCounters($deck);
                $game->notifyDeckCounters("deck_13");

                if ($era > 1) {
                    $game->awardCard($player_id, 1, CARD_TAPESTRY, false, $reason);
                    $this->misDiscard($player_id);
                    $game->queueBenefitNormal(BE_TAPESTRY, $player_id, $reason, 2); // gain 2 tapestry
                } else {
                    $game->queueBenefitNormal(BE_TAPESTRY, $player_id, $reason, 2); // gain 2 tapestry
                }
                return true;
            default:
                $this->systemAssertTrue("ERR:Mystic:10");
                return true;
        }
        return true;
    }

    function misDiscard(int $player_id) {
        $civ = $this->civ;
        $game = $this->game;
        $reason = reason_civ($civ);
        $cards = $game->getCardsInHand($player_id, CARD_TAPESTRY);
        $count = count($cards);
        $game->effect_discardCard($cards, $player_id, "discard_13", true);
        if ($count <= 1) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} gains no reward for discarding 0-1 tapestry cards'),
                [],
                $player_id
            );
        } elseif ($count >= 6) {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} gains reward for discarding ${count} tapestry cards'),
                ["count" => $count],
                $player_id
            );
            $rewards = ["or" => [74 /* conq both dice */, BE_ANYRES, 127 /* invent and upgrade */, 110 /* income building */]];
            $game->queueBenefitNormal($rewards, $player_id, $reason, 3);
        } else {
            $game->notifyWithName(
                "message",
                clienttranslate('${player_name} gains reward for discarding ${count} tapestry cards'),
                ["count" => $count],
                $player_id
            );
            $rewards = ["or" => [BE_CONQUER, BE_ANYRES, BE_INVENT, 144 /* income building outside of city */]];
            $game->queueBenefitNormal($rewards, $player_id, $reason, $count >= 4 ? 2 : 1);
        }
    }
}
